<?php

declare(strict_types=1);

namespace MYVH\Subscriptions\Services;

use MYVH\Customers\CustomerRepository;
use MYVH\Invoices\InvoiceService;
use MYVH\Subscriptions\Entities\Plan;
use MYVH\Subscriptions\Entities\Subscription;
use MYVH\Subscriptions\Entities\SubscriptionStatus;
use MYVH\Subscriptions\Repositories\AccountRepository;
use MYVH\Subscriptions\Repositories\PlanRepository;
use MYVH\Subscriptions\Repositories\SettingsRepository;
use MYVH\Subscriptions\Repositories\SubscriptionRepository;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class ManualInvoiceService {
    public function __construct(
        private SubscriptionRepository $subscription_repository,
        private PlanRepository $plan_repository,
        private AccountRepository $account_repository,
        private SettingsRepository $settings_repository,
        private InvoiceService $invoice_service,
        private CustomerRepository $customer_repository,
        private LoggerInterface $logger = new NullLogger()
    ) {
    }

    public function handlePaymentMethod(int $account_id, string $payment_method): int|WP_Error {
        $method = sanitize_key($payment_method);

        $this->logger->info('Manual payment method requested', [
            'account_id' => $account_id,
            'payment_method' => $method,
        ]);

        if ($method !== 'invoice') {
            $this->logger->warning('Unsupported manual payment method requested', [
                'account_id' => $account_id,
                'payment_method' => $method,
            ]);

            return new WP_Error('subscription_payment_method_unsupported', __('Unsupported manual payment method', 'my-village-hall'));
        }

        return $this->createPendingInvoiceForAccount($account_id);
    }

    public function createPendingInvoiceForAccount(int $account_id): int|WP_Error {
        if ($account_id <= 0) {
            return new WP_Error('subscription_account_missing', __('Account is required', 'my-village-hall'));
        }

        $account = $this->account_repository->get_by_id($account_id);
        if (!is_array($account)) {
            return new WP_Error('subscription_account_missing', __('Account not found', 'my-village-hall'));
        }

        $subscription = $this->subscription_repository->get_active_by_account_id($account_id);
        if (!$subscription instanceof Subscription) {
            return new WP_Error('subscription_missing', __('No subscription found for this account', 'my-village-hall'));
        }

        $plan = $this->resolve_plan($subscription);
        if (!$plan instanceof Plan) {
            return new WP_Error('subscription_plan_missing', __('Plan not found for this subscription', 'my-village-hall'));
        }

        $customer_id = $this->resolve_customer_id($account);
        if ($customer_id <= 0) {
            return new WP_Error('subscription_customer_missing', __('No billing customer found for this account', 'my-village-hall'));
        }

        $invoice_total = $plan->getPrice();
        if ($invoice_total <= 0) {
            return new WP_Error('subscription_plan_price_invalid', __('Plan price must be greater than zero', 'my-village-hall'));
        }

        $due_offset_days = max(1, (int) $this->settings_repository->get_global_setting('grace_period_days', 7));
        $invoice_date = gmdate('Y-m-d');
        $due_date = gmdate('Y-m-d', strtotime($invoice_date . ' +' . $due_offset_days . ' days'));

        $invoice_id = $this->invoice_service->save([
            'customer_id' => $customer_id,
            'billing_name' => (string) ($account['account_name'] ?? ''),
            'billing_email' => (string) ($account['contact_email'] ?? ''),
            'billing_reference' => 'SUB-' . $subscription->getId(),
            'invoice_date' => $invoice_date,
            'due_date' => $due_date,
            'sub_total' => $invoice_total,
            'tax_amount' => 0,
            'total_amount' => $invoice_total,
            'amount_paid' => 0,
            'status' => 'sent',
            'notes' => __('Subscription invoice (manual payment)', 'my-village-hall'),
        ]);

        if (is_wp_error($invoice_id)) {
            return $invoice_id;
        }

        $pdf_result = $this->invoice_service->generate_pdf((int) $invoice_id);
        if (is_wp_error($pdf_result)) {
            return $pdf_result;
        }

        $metadata = $this->decode_metadata($subscription->getMetadataRaw());
        $metadata['manual_payment_method'] = 'invoice';
        $metadata['manual_invoice_id'] = (int) $invoice_id;

        $period_start = (string) current_time('mysql');

        $updated = $this->subscription_repository->update_by_id($subscription->getId(), [
            'status' => SubscriptionStatus::PAST_DUE,
            'provider' => 'manual_invoice',
            'current_period_start' => $period_start,
            'current_period_end' => $due_date . ' 23:59:59',
            'metadata' => wp_json_encode($metadata),
        ]);

        if (!$updated) {
            $this->logger->error('Failed to update subscription to past_due after invoice generation', [
                'account_id' => $account_id,
                'invoice_id' => (int) $invoice_id,
            ]);

            return new WP_Error('subscription_update_failed', __('Failed to set subscription to pending payment', 'my-village-hall'));
        }

        $this->logger->info('Manual invoice generated and subscription set to past_due', [
            'account_id' => $account_id,
            'invoice_id' => (int) $invoice_id,
        ]);

        return (int) $invoice_id;
    }

    public function markInvoicePaid(int $account_id, int $invoice_id): bool|WP_Error {
        if ($account_id <= 0 || $invoice_id <= 0) {
            return new WP_Error('subscription_mark_paid_invalid', __('Account and invoice are required', 'my-village-hall'));
        }

        $subscription = $this->subscription_repository->get_active_by_account_id($account_id);
        if (!$subscription instanceof Subscription) {
            return new WP_Error('subscription_missing', __('No subscription found for this account', 'my-village-hall'));
        }

        $metadata = $this->decode_metadata($subscription->getMetadataRaw());
        $expected_invoice_id = (int) ($metadata['manual_invoice_id'] ?? 0);
        if ($expected_invoice_id > 0 && $expected_invoice_id !== $invoice_id) {
            return new WP_Error('subscription_invoice_mismatch', __('Invoice does not match the pending subscription invoice', 'my-village-hall'));
        }

        $invoice = $this->invoice_service->get($invoice_id);
        if (!is_array($invoice)) {
            return new WP_Error('invoice_not_found', __('Invoice not found', 'my-village-hall'));
        }

        $amount_due = round((float) ($invoice['AmountDue'] ?? 0), 2);
        if ($amount_due > 0) {
            $payment = $this->invoice_service->record_payment(
                $invoice_id,
                $amount_due,
                'transfer',
                gmdate('Y-m-d'),
                'manual-subscription-payment',
                __('Marked as paid from subscription admin', 'my-village-hall')
            );

            if (is_wp_error($payment)) {
                return $payment;
            }
        }

        $period_start = (string) current_time('mysql');
        $period_end = $this->calculate_period_end($subscription, $period_start);

        $metadata['manual_invoice_paid_id'] = $invoice_id;
        unset($metadata['manual_invoice_id']);

        $updated = $this->subscription_repository->update_by_id($subscription->getId(), [
            'status' => SubscriptionStatus::ACTIVE,
            'provider' => 'manual_invoice',
            'current_period_start' => $period_start,
            'current_period_end' => $period_end,
            'metadata' => wp_json_encode($metadata),
        ]);

        $this->logger->info('Manual invoice marked paid and subscription set active', [
            'account_id' => $account_id,
            'invoice_id' => $invoice_id,
            'subscription_id' => $subscription->getId(),
            'updated' => $updated,
        ]);

        return $updated;
    }

    private function resolve_plan(Subscription $subscription): ?Plan {
        $plan_code = $subscription->getPlanCode();
        if ($plan_code !== '') {
            return $this->plan_repository->getByCode($plan_code);
        }

        $plan_id = $subscription->getPlanId();
        if ($plan_id > 0) {
            $plan = $this->plan_repository->get_by_id($plan_id);

            return $plan instanceof Plan ? $plan : null;
        }

        return null;
    }

    private function resolve_customer_id(array $account): int {
        $owner_user_id = (int) ($account['owner_user_id'] ?? 0);
        if ($owner_user_id > 0) {
            $customer = $this->customer_repository->get_by_user_id($owner_user_id);
            if (is_array($customer) && !empty($customer['Id'])) {
                return (int) $customer['Id'];
            }
        }

        $email = sanitize_email((string) ($account['contact_email'] ?? ''));
        if ($email !== '') {
            $customer = $this->customer_repository->get_by_email($email);
            if (is_array($customer) && !empty($customer['Id'])) {
                return (int) $customer['Id'];
            }
        }

        return 0;
    }

    private function calculate_period_end(Subscription $subscription, string $period_start): string {
        $interval = 'monthly';
        $plan = $this->resolve_plan($subscription);
        if ($plan instanceof Plan && $plan->getBillingInterval() !== '') {
            $interval = $plan->getBillingInterval();
        }

        return match ($interval) {
            'year', 'yearly', 'annual' => gmdate('Y-m-d H:i:s', strtotime($period_start . ' +1 year')),
            'week', 'weekly' => gmdate('Y-m-d H:i:s', strtotime($period_start . ' +1 week')),
            'quarter', 'quarterly' => gmdate('Y-m-d H:i:s', strtotime($period_start . ' +3 months')),
            default => gmdate('Y-m-d H:i:s', strtotime($period_start . ' +1 month')),
        };
    }

    private function decode_metadata(string $metadata_raw): array {
        if ($metadata_raw === '') {
            return [];
        }

        $decoded = json_decode($metadata_raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
