<?php

declare(strict_types=1);

namespace MYVH\Subscriptions\Services;

use MYVH\Subscriptions\Entities\Subscription;
use MYVH\Subscriptions\Repositories\AccountRepository;
use MYVH\Subscriptions\Repositories\SubscriptionRepository;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

if (!defined('ABSPATH')) {
    exit;
}

class BillingNotificationService {
    public function __construct(
        private SubscriptionRepository $subscription_repository,
        private AccountRepository $account_repository,
        private LoggerInterface $logger = new NullLogger()
    ) {
    }

    public function notifyTrialEndingSoon(array $account, Subscription $subscription, int $days_left): bool {
        try {
            $subscription_id = $subscription->getId();
            $account_id = (int) ($account['id'] ?? 0);

            if ($subscription_id <= 0 || $account_id <= 0) {
                return false;
            }

            $email = sanitize_email((string) ($account['contact_email'] ?? ''));
            if ($email === '' || !is_email($email)) {
                $this->logger->warning('Skipping trial ending notification because account email is missing', [
                    'account_id' => $account_id,
                    'subscription_id' => $subscription_id,
                ]);
                return false;
            }

            $metadata = $this->decode_metadata($subscription->getMetadataRaw());
            $notification_key = 'trial_ending_notified_' . gmdate('Ymd');

            if (!empty($metadata[$notification_key])) {
                return true;
            }

            $subject = sprintf(
                __('Trial ending in %d day(s) - %s', 'my-village-hall'),
                max(0, $days_left),
                get_bloginfo('name')
            );

            $body = sprintf(
                __('Your trial for %1$s ends in %2$d day(s). Please update your billing method to avoid service interruption.', 'my-village-hall'),
                get_bloginfo('name'),
                max(0, $days_left)
            );

            $sent = wp_mail($email, $subject, $body);

            $this->logger->info('Trial ending notification attempted', [
                'account_id' => $account_id,
                'subscription_id' => $subscription_id,
                'days_left' => $days_left,
                'sent' => $sent,
            ]);

            if (!$sent) {
                return false;
            }

            $metadata[$notification_key] = 1;

            $this->subscription_repository->update_by_id($subscription_id, [
                'metadata' => wp_json_encode($metadata),
            ]);

            return true;
        } catch (\Throwable $exception) {
            $this->logger->error('Trial ending notification failed unexpectedly', [
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    public function notifyPaymentFailedByStripeSubscriptionId(string $stripe_subscription_id, ?string $event_id = null): bool {
        try {
            $subscription = $this->subscription_repository->get_latest_by_stripe_subscription_id($stripe_subscription_id);
            if (!$subscription instanceof Subscription) {
                $this->logger->warning('Payment failed notification skipped because subscription was not found', [
                    'stripe_subscription_id' => $stripe_subscription_id,
                ]);
                return false;
            }

            $account_id = $subscription->getAccountId();
            if ($account_id <= 0) {
                return false;
            }

            $account = $this->account_repository->get_by_id($account_id);
            if (!is_array($account)) {
                return false;
            }

            $email = sanitize_email((string) ($account['contact_email'] ?? ''));
            if ($email === '' || !is_email($email)) {
                return false;
            }

            $subscription_id = $subscription->getId();
            $metadata = $this->decode_metadata($subscription->getMetadataRaw());

            if ($event_id !== null && $event_id !== '') {
                $notification_key = 'payment_failed_event_' . sanitize_key($event_id);
                if (!empty($metadata[$notification_key])) {
                    return true;
                }
                $metadata[$notification_key] = 1;
            }

            $subject = sprintf(__('Payment failed - %s', 'my-village-hall'), get_bloginfo('name'));
            $body = __('A subscription payment has failed. Please update your payment method to keep your subscription active.', 'my-village-hall');

            $sent = wp_mail($email, $subject, $body);

            $this->logger->info('Payment failed notification attempted', [
                'account_id' => $account_id,
                'subscription_id' => $subscription_id,
                'stripe_subscription_id' => $stripe_subscription_id,
                'event_id' => $event_id,
                'sent' => $sent,
            ]);

            if ($sent && !empty($metadata)) {
                $this->subscription_repository->update_by_id($subscription_id, [
                    'metadata' => wp_json_encode($metadata),
                ]);
            }

            return $sent;
        } catch (\Throwable $exception) {
            $this->logger->error('Payment failed notification failed unexpectedly', [
                'stripe_subscription_id' => $stripe_subscription_id,
                'event_id' => $event_id,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function decode_metadata(string $metadata_raw): array {
        if ($metadata_raw === '') {
            return [];
        }

        $decoded = json_decode($metadata_raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
