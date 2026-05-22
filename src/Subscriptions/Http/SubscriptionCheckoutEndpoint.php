<?php

declare(strict_types=1);

namespace MYVH\Subscriptions\Http;

use MYVH\Subscriptions\Repositories\AccountRepository;
use MYVH\Subscriptions\Repositories\PlanRepository;
use MYVH\Subscriptions\Services\BillingService;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if (!defined('ABSPATH')) {
    exit;
}

class SubscriptionCheckoutEndpoint {
    private const REST_NAMESPACE = 'myvh/v1';
    private const REST_ROUTE = '/subscription/checkout';

    public function __construct(
        private AccountRepository $account_repository,
        private PlanRepository $plan_repository,
        private BillingService $billing_service,
        private LoggerInterface $logger = new NullLogger()
    ) {
    }

    public function register(): void {
        add_action('rest_api_init', [$this, 'register_route']);
    }

    public function register_route(): void {
        register_rest_route(self::REST_NAMESPACE, self::REST_ROUTE, [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'handle'],
            'permission_callback' => [$this, 'can_access'],
        ]);
    }

    public function can_access(): bool {
        return is_user_logged_in() && current_user_can('manage_options');
    }

    public function handle(WP_REST_Request $request): WP_REST_Response {
        $payload = $request->get_json_params();
        if (!is_array($payload)) {
            $payload = [];
        }

        $account_id = (int) ($payload['account_id'] ?? 0);
        $plan_code = sanitize_key((string) ($payload['plan_code'] ?? ''));
        $success_url = esc_url_raw(trim((string) ($payload['success_url'] ?? '')));
        $cancel_url = esc_url_raw(trim((string) ($payload['cancel_url'] ?? '')));

        $this->logger->info('Subscription checkout requested', [
            'account_id' => $account_id,
            'plan_code' => $plan_code,
        ]);

        if ($account_id <= 0 || $plan_code === '') {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('account_id and plan_code are required', 'my-village-hall'),
            ], 400);
        }

        $account = $this->account_repository->get_by_id($account_id);
        if (!is_array($account)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Account not found', 'my-village-hall'),
            ], 404);
        }

        $plan = $this->plan_repository->getByCode($plan_code);
        if ($plan === null) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Plan not found', 'my-village-hall'),
            ], 404);
        }

        try {
            $result = $this->billing_service->createStripeCheckoutUrl(
                $account_id,
                $plan_code,
                $success_url,
                $cancel_url
            );
        } catch (\Throwable $e) {
            $this->logger->error('Stripe checkout session creation failed', [
                'account_id' => $account_id,
                'plan_code' => $plan_code,
                'error' => $e->getMessage(),
            ]);

            return new WP_REST_Response([
                'success' => false,
                'message' => __('Failed to create checkout session. Please try again.', 'my-village-hall'),
            ], 500);
        }

        if (!is_array($result) || empty($result['checkout_url'])) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Could not generate a checkout URL. Please check Stripe configuration.', 'my-village-hall'),
            ], 500);
        }

        return new WP_REST_Response([
            'success' => true,
            'checkout_url' => $result['checkout_url'],
            'session_id' => $result['session_id'] ?? '',
        ], 200);
    }
}
