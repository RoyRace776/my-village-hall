<?php

declare(strict_types=1);

namespace MYVH\Subscriptions\Http;

use MYVH\Subscriptions\Entities\SubscriptionStatus;
use MYVH\Subscriptions\Repositories\ProcessedStripeEventRepository;
use MYVH\Subscriptions\Repositories\PlanRepository;
use MYVH\Subscriptions\Repositories\SettingsRepository;
use MYVH\Subscriptions\Repositories\SubscriptionRepository;
use MYVH\Subscriptions\Services\BillingNotificationService;
use MYVH\Subscriptions\Services\SubscriptionEventLogger;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if (!defined('ABSPATH')) {
    exit;
}

class StripeWebhookHandler {
    private const REST_NAMESPACE = 'myvh/v1';
    private const REST_ROUTE = '/stripe/webhook';

    public function __construct(
        private SettingsRepository $settings_repository,
        private SubscriptionRepository $subscription_repository,
        private ProcessedStripeEventRepository $processed_event_repository,
        private BillingNotificationService $billing_notification_service,
        private SubscriptionEventLogger $event_logger,
        private LoggerInterface $logger = new NullLogger(),
        private ?PlanRepository $plan_repository = null
    ) {
    }

    public function register(): void {
        add_action('rest_api_init', [$this, 'register_route']);
    }

    public function register_route(): void {
        register_rest_route(self::REST_NAMESPACE, self::REST_ROUTE, [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'handle'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function handle(WP_REST_Request $request): WP_REST_Response {
        $payload = (string) $request->get_body();
        $signature = (string) $request->get_header('stripe-signature');

        $event = $this->verify_event($payload, $signature);
        if (!$event instanceof Event) {
            $this->logger->warning('Stripe webhook rejected because signature verification failed');
            return new WP_REST_Response(['received' => false], 400);
        }

        $this->logger->info('Stripe webhook received', [
            'event_id' => (string) ($event->id ?? ''),
            'event_type' => (string) ($event->type ?? ''),
        ]);

        $event_id = trim((string) ($event->id ?? ''));
        $event_type = trim((string) ($event->type ?? ''));
        if ($event_id !== '' && $this->processed_event_repository->hasBeenProcessed($event_id)) {
            $this->logger->info('Stripe webhook ignored because event has already been processed', [
                'event_id' => $event_id,
                'event_type' => $event_type,
            ]);

            return new WP_REST_Response(['received' => true, 'duplicate' => true], 200);
        }

        try {
            $this->dispatch_event($event);

            if ($event_id !== '' && $event_type !== '') {
                $this->processed_event_repository->markProcessed($event_id, $event_type);
            }
        } catch (\Throwable $exception) {
            $this->logger->error('Stripe webhook dispatch failed', [
                'event_id' => (string) ($event->id ?? ''),
                'event_type' => (string) ($event->type ?? ''),
                'error' => $exception->getMessage(),
            ]);

            return new WP_REST_Response(['received' => false], 500);
        }

        return new WP_REST_Response(['received' => true], 200);
    }

    private function verify_event(string $payload, string $signature): ?Event {
        $endpoint_secret = trim((string) $this->settings_repository->get_global_setting('stripe_webhook_secret', ''));
        if ($endpoint_secret === '' || $signature === '' || $payload === '') {
            return null;
        }

        try {
            return Webhook::constructEvent($payload, $signature, $endpoint_secret);
        } catch (UnexpectedValueException|SignatureVerificationException $exception) {
            return null;
        }
    }

    private function dispatch_event(Event $event): void {
        $event_type = (string) ($event->type ?? '');
        $object = $event->data->object ?? null;
        if (!is_object($object)) {
            return;
        }

        if ($event_type === 'invoice.paid') {
            $subscription_id = (string) ($object->subscription ?? '');
            if ($subscription_id !== '') {
                $subscription = $this->subscription_repository->get_latest_by_stripe_subscription_id($subscription_id);
                $previous_status = $subscription ? $subscription->getStatus() : null;
                $this->subscription_repository->update_status_by_stripe_subscription_id($subscription_id, SubscriptionStatus::ACTIVE);
                if ($subscription) {
                    $this->event_logger->log(
                        $subscription->getAccountId(),
                        'stripe_invoice_paid',
                        $previous_status,
                        SubscriptionStatus::ACTIVE,
                        'stripe_webhook',
                        ['stripe_subscription_id' => $subscription_id]
                    );
                }
                $this->logger->info('Subscription set active from invoice.paid', [
                    'stripe_subscription_id' => $subscription_id,
                ]);
            }
            return;
        }

        if ($event_type === 'invoice.payment_failed') {
            $subscription_id = (string) ($object->subscription ?? '');
            if ($subscription_id !== '') {
                $subscription = $this->subscription_repository->get_latest_by_stripe_subscription_id($subscription_id);
                $previous_status = $subscription ? $subscription->getStatus() : null;
                $this->subscription_repository->update_status_by_stripe_subscription_id($subscription_id, SubscriptionStatus::PAST_DUE);
                $this->billing_notification_service->notifyPaymentFailedByStripeSubscriptionId(
                    $subscription_id,
                    (string) ($event->id ?? '')
                );

                if ($subscription) {
                    $this->event_logger->log(
                        $subscription->getAccountId(),
                        'stripe_invoice_payment_failed',
                        $previous_status,
                        SubscriptionStatus::PAST_DUE,
                        'stripe_webhook',
                        ['stripe_subscription_id' => $subscription_id]
                    );
                }

                $this->logger->warning('Subscription marked past_due from invoice.payment_failed', [
                    'stripe_subscription_id' => $subscription_id,
                ]);
            }
            return;
        }

        if ($event_type === 'customer.subscription.deleted') {
            $subscription_id = (string) ($object->id ?? '');
            if ($subscription_id !== '') {
                $subscription = $this->subscription_repository->get_latest_by_stripe_subscription_id($subscription_id);
                $previous_status = $subscription ? $subscription->getStatus() : null;
                $this->subscription_repository->update_status_by_stripe_subscription_id($subscription_id, SubscriptionStatus::CANCELLED);
                if ($subscription) {
                    $this->event_logger->log(
                        $subscription->getAccountId(),
                        'stripe_subscription_deleted',
                        $previous_status,
                        SubscriptionStatus::CANCELLED,
                        'stripe_webhook',
                        ['stripe_subscription_id' => $subscription_id]
                    );
                }
                $this->logger->warning('Subscription cancelled from customer.subscription.deleted', [
                    'stripe_subscription_id' => $subscription_id,
                ]);
            }
            return;
        }

        if ($event_type === 'customer.subscription.updated') {
            $subscription_id = (string) ($object->id ?? '');
            if ($subscription_id === '') {
                return;
            }

            $period_end = isset($object->current_period_end) ? (int) $object->current_period_end : null;
            $period_start = isset($object->current_period_start) ? (int) $object->current_period_start : null;

            $this->subscription_repository->update_period_by_stripe_subscription_id($subscription_id, $period_end, $period_start);
            $this->logger->info('Subscription period updated from customer.subscription.updated', [
                'stripe_subscription_id' => $subscription_id,
                'period_end' => $period_end,
                'period_start' => $period_start,
            ]);

            // Update plan_code if the plan has changed (upgrade/downgrade)
            if ($this->plan_repository !== null) {
                $items = $object->items->data ?? [];
                $new_price_id = '';
                if (is_array($items) && count($items) > 0) {
                    $new_price_id = trim((string) ($items[0]->price->id ?? ''));
                }
                if ($new_price_id !== '') {
                    $plan = $this->plan_repository->getByStripePriceId($new_price_id);
                    if ($plan !== null) {
                        $plan_code = $plan->getCode();
                        if ($plan_code !== '') {
                            $this->subscription_repository->update_plan_code_by_stripe_subscription_id($subscription_id, $plan_code);
                            $this->logger->info('Subscription plan_code updated from customer.subscription.updated', [
                                'stripe_subscription_id' => $subscription_id,
                                'plan_code' => $plan_code,
                            ]);
                        }
                    }
                }
            }
            return;
        }

        if ($event_type === 'checkout.session.completed') {
            $stripe_subscription_id = trim((string) ($object->subscription ?? ''));
            if ($stripe_subscription_id === '') {
                return;
            }

            $metadata = $object->metadata ?? null;
            $plan_code_from_meta = '';
            if (is_object($metadata)) {
                $plan_code_from_meta = sanitize_key(trim((string) ($metadata->plan_code ?? '')));
            }

            $subscription = $this->subscription_repository->get_latest_by_stripe_subscription_id($stripe_subscription_id);
            $previous_status = $subscription ? $subscription->getStatus() : null;

            $this->subscription_repository->update_status_by_stripe_subscription_id($stripe_subscription_id, SubscriptionStatus::ACTIVE);

            if ($plan_code_from_meta !== '') {
                $this->subscription_repository->update_plan_code_by_stripe_subscription_id($stripe_subscription_id, $plan_code_from_meta);
                $this->logger->info('Subscription plan_code set from checkout.session.completed metadata', [
                    'stripe_subscription_id' => $stripe_subscription_id,
                    'plan_code' => $plan_code_from_meta,
                ]);
            }

            if ($subscription) {
                $this->event_logger->log(
                    $subscription->getAccountId(),
                    'stripe_checkout_completed',
                    $previous_status,
                    SubscriptionStatus::ACTIVE,
                    'stripe_webhook',
                    ['stripe_subscription_id' => $stripe_subscription_id, 'plan_code' => $plan_code_from_meta]
                );
            }

            $this->logger->info('Subscription activated from checkout.session.completed', [
                'stripe_subscription_id' => $stripe_subscription_id,
            ]);
        }
    }
}
