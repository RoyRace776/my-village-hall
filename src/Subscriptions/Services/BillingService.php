<?php

declare(strict_types=1);

namespace MYVH\Subscriptions\Services;

use MYVH\Subscriptions\Entities\Plan;
use MYVH\Subscriptions\Entities\Subscription;
use MYVH\Subscriptions\Http\StripeWebhookHandler;
use MYVH\Subscriptions\Repositories\AccountRepository;
use MYVH\Subscriptions\Repositories\PlanRepository;
use MYVH\Subscriptions\Repositories\SubscriptionRepository;

if (!defined('ABSPATH')) {
    exit;
}

class BillingService {
    public function __construct(
        private StripeService $stripe_service,
        private StripeWebhookHandler $stripe_webhook_handler,
        private ManualInvoiceService $manual_invoice_service,
        private AccountRepository $account_repository,
        private PlanRepository $plan_repository,
        private SubscriptionRepository $subscription_repository
    ) {
    }

    public function createCheckoutSession(int $account_id, string $plan_code, string $payment_method = 'stripe'): ?array {
        $payment_method = sanitize_key($payment_method);
        $plan_code = sanitize_key($plan_code);

        if ($account_id <= 0 || $plan_code === '') {
            return null;
        }

        if ($payment_method === 'invoice') {
            $result = $this->manual_invoice_service->handlePaymentMethod($account_id, 'invoice');

            return is_int($result)
                ? [
                    'success' => true,
                    'invoice_id' => $result,
                ]
                : null;
        }

        if ($payment_method !== 'stripe') {
            return null;
        }

        try {
            $account = $this->account_repository->get_by_id($account_id);
            if (!is_array($account)) {
                return null;
            }

            $email = sanitize_email((string) ($account['contact_email'] ?? ''));
            if ($email === '') {
                return null;
            }

            $name = sanitize_text_field((string) ($account['account_name'] ?? ''));
            $customer_id = $this->resolveStripeCustomerId($account_id, $email, $name, $account);
            if ($customer_id === '') {
                return null;
            }

            $price_id = $this->resolvePriceId($plan_code);
            if ($price_id === '') {
                return null;
            }

            $subscription_id = $this->resolveStripeSubscriptionId($customer_id, $price_id);
            if ($subscription_id === '') {
                return null;
            }

            return [
                'success' => true,
                'subscription_id' => $subscription_id,
                'customer_id' => $customer_id,
            ];
        } catch (\Throwable $exception) {
            return null;
        }
    }

    public function requestPlanChange(int $account_id, string $plan_code, string $payment_method): ?array {
        $plan_code = sanitize_key($plan_code);
        $payment_method = sanitize_key($payment_method);

        if ($account_id <= 0 || $plan_code === '' || $payment_method === '') {
            return null;
        }

        $target_plan = $this->plan_repository->getByCode($plan_code);
        if (!$target_plan instanceof Plan) {
            return null;
        }

        $subscription = $this->subscription_repository->get_active_by_account_id($account_id);
        if ($subscription instanceof Subscription && $this->isDowngrade($subscription, $target_plan)) {
            if ($this->isForbiddenTrialDowngrade($subscription, $target_plan)) {
                return null;
            }

            return $this->scheduleDowngrade($subscription, $target_plan);
        }

        $plan_saved = $this->subscription_repository->update_latest_plan_for_account(
            $account_id,
            $target_plan->getCode(),
            $target_plan->getId() > 0 ? $target_plan->getId() : null
        );

        if (!$plan_saved) {
            return null;
        }

        return $this->createCheckoutSession($account_id, $target_plan->getCode(), $payment_method);
    }

    public function createStripeCheckoutUrl(int $account_id, string $plan_code, string $success_url, string $cancel_url): ?array {
        $plan_code = sanitize_key($plan_code);
        $success_url = trim(esc_url_raw($success_url));
        $cancel_url = trim(esc_url_raw($cancel_url));

        if ($account_id <= 0 || $plan_code === '') {
            return null;
        }

        $account = $this->account_repository->get_by_id($account_id);
        if (!is_array($account)) {
            return null;
        }

        $email = sanitize_email((string) ($account['contact_email'] ?? ''));
        if ($email === '') {
            return null;
        }

        $name = sanitize_text_field((string) ($account['account_name'] ?? ''));
        $customer_id = $this->resolveStripeCustomerId($account_id, $email, $name, $account);
        if ($customer_id === '') {
            return null;
        }

        $price_id = $this->resolvePriceId($plan_code);
        if ($price_id === '') {
            return null;
        }

        if ($success_url === '') {
            $success_url = add_query_arg(
                ['page' => 'myvh-subscription-dashboard', 'checkout' => 'success'],
                admin_url('admin.php')
            );
        }

        if ($cancel_url === '') {
            $cancel_url = add_query_arg(
                ['page' => 'myvh-subscription-dashboard', 'checkout' => 'cancel'],
                admin_url('admin.php')
            );
        }

        $result = $this->stripe_service->createCheckoutSession(
            $customer_id,
            $price_id,
            $success_url,
            $cancel_url,
            ['plan_code' => $plan_code, 'myvh_account_id' => (string) $account_id]
        );

        $checkout_url = trim((string) ($result['url'] ?? ''));
        if ($checkout_url === '') {
            return null;
        }

        return [
            'success' => true,
            'checkout_url' => $checkout_url,
            'session_id' => (string) ($result['id'] ?? ''),
        ];
    }

    protected function resolveStripeCustomerId(int $account_id, string $email, string $name, array $account): string {
        $existing_subscription = $this->subscription_repository->get_active_by_account_id($account_id);
        if ($existing_subscription instanceof Subscription) {
            $existing_customer_id = trim($existing_subscription->getStripeCustomerId());
            if ($existing_customer_id !== '') {
                return $existing_customer_id;
            }
        }

        $customer = $this->stripe_service->createCustomer(
            $email,
            $name,
            [
                'myvh_account_id' => (string) $account_id,
                'myvh_blog_id' => (string) ((int) ($account['blog_id'] ?? 0)),
            ]
        );

        $customer_id = trim((string) ($customer['id'] ?? ''));
        if ($customer_id === '') {
            return '';
        }

        $this->subscription_repository->update_latest_stripe_customer_id_for_account($account_id, $customer_id);

        return $customer_id;
    }

    private function resolveStripeSubscriptionId(string $customer_id, string $price_id): string {
        $existing_subscription = $this->subscription_repository->get_active_by_stripe_customer_id($customer_id);
        if ($existing_subscription instanceof Subscription) {
            $existing_subscription_id = trim($existing_subscription->getStripeSubscriptionId());
            if ($existing_subscription_id !== '') {
                return $existing_subscription_id;
            }
        }

        $result = $this->stripe_service->createSubscription($customer_id, $price_id);
        $subscription_id = trim((string) ($result['id'] ?? ''));
        if ($subscription_id === '') {
            return '';
        }

        $this->subscription_repository->update_latest_stripe_subscription_id_for_customer($customer_id, $subscription_id);

        return $subscription_id;
    }

    protected function resolvePriceId(string $price_id_or_plan_code): string {
        $candidate = trim($price_id_or_plan_code);
        if ($candidate === '') {
            return '';
        }

        if (str_starts_with($candidate, 'price_')) {
            return $candidate;
        }

        return (string) ($this->plan_repository->getStripePriceIdMonthlyByCode($candidate) ?? '');
    }

    public function cancelProviderSubscription(string $provider_subscription_id, string $provider = 'stripe'): bool {
        $provider = sanitize_key($provider);
        $provider_subscription_id = trim($provider_subscription_id);

        if ($provider_subscription_id === '' || $provider !== 'stripe') {
            return false;
        }

        if (!$this->stripe_service->cancelSubscription($provider_subscription_id)) {
            return false;
        }

        return $this->subscription_repository->cancel_by_stripe_subscription_id($provider_subscription_id);
    }

    public function registerWebhooks(): void {
        $this->stripe_webhook_handler->register();
    }

    private function isDowngrade(Subscription $subscription, Plan $target_plan): bool {
        $current_plan = $this->resolvePlan($subscription);
        if (!$current_plan instanceof Plan) {
            return false;
        }

        if ($target_plan->getPrice() < $current_plan->getPrice()) {
            return true;
        }

        $current_limit = $current_plan->getBookingLimit();
        $target_limit = $target_plan->getBookingLimit();

        if ($current_limit === null) {
            return $target_limit !== null;
        }

        return $target_limit !== null && $target_limit < $current_limit;
    }

    private function resolvePlan(Subscription $subscription): ?Plan {
        $plan_id = $subscription->getPlanId();
        if ($plan_id > 0) {
            $plan = $this->plan_repository->get_by_id($plan_id);
            if ($plan instanceof Plan) {
                return $plan;
            }
        }

        $plan_code = $subscription->getPlanCode();
        if ($plan_code === '') {
            return null;
        }

        return $this->plan_repository->getByCode($plan_code);
    }

    private function scheduleDowngrade(Subscription $subscription, Plan $target_plan): ?array {
        $subscription_id = $subscription->getId();
        if ($subscription_id <= 0) {
            return null;
        }

        $metadata = json_decode($subscription->getMetadataRaw(), true);
        if (!is_array($metadata)) {
            $metadata = [];
        }

        $effective_at = $subscription->getCurrentPeriodEnd();
        if ($effective_at === '') {
            $effective_at = gmdate('Y-m-d H:i:s', (int) current_time('timestamp') + (30 * 86400));
        }

        $metadata['scheduled_plan_change'] = [
            'plan_code' => $target_plan->getCode(),
            'plan_id' => $target_plan->getId(),
            'effective_at' => $effective_at,
            'scheduled_at' => current_time('mysql'),
        ];

        $updated = $this->subscription_repository->update_by_id($subscription_id, [
            'metadata' => wp_json_encode($metadata),
        ]);

        if (!$updated) {
            return null;
        }

        return [
            'success' => true,
            'scheduled' => true,
            'effective_at' => $effective_at,
            'plan_code' => $target_plan->getCode(),
            'plan_id' => $target_plan->getId(),
        ];
    }

    private function isForbiddenTrialDowngrade(Subscription $subscription, Plan $target_plan): bool {
        $current_plan = $this->resolvePlan($subscription);
        if (!$current_plan instanceof Plan) {
            return false;
        }

        return $target_plan->getCode() === 'trial' && $current_plan->getCode() !== 'trial';
    }
}
