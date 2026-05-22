<?php

declare(strict_types=1);

namespace MYVH\Subscriptions\Http;

use MYVH\Subscriptions\Entities\Plan;
use MYVH\Subscriptions\Repositories\AccountRepository;
use MYVH\Subscriptions\Repositories\PlanRepository;
use MYVH\Subscriptions\Repositories\SubscriptionRepository;
use MYVH\Subscriptions\Services\BillingService;
use MYVH\Subscriptions\Services\FeatureService;
use MYVH\Subscriptions\Services\PlanChangePolicyService;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if (!defined('ABSPATH')) {
    exit;
}

class SubscriptionUpgradeEndpoint {
    private const REST_NAMESPACE = 'myvh/v1';
    private const REST_ROUTE = '/upgrade';

    public function __construct(
        private AccountRepository $account_repository,
        private ?SubscriptionRepository $subscription_repository,
        private PlanRepository $plan_repository,
        private BillingService $billing_service,
        private ?FeatureService $feature_service = null,
        private ?PlanChangePolicyService $plan_change_policy_service = null,
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
            'permission_callback' => [$this, 'can_upgrade'],
        ]);
    }

    public function can_upgrade(): bool {
        return is_user_logged_in() && current_user_can('manage_options');
    }

    public function handle(WP_REST_Request $request): WP_REST_Response {
        $payload = $request->get_json_params();
        if (!is_array($payload)) {
            $payload = [];
        }

        $account_id = (int) ($payload['account_id'] ?? 0);
        $plan_code = sanitize_key((string) ($payload['plan_code'] ?? ''));
        $payment_method = sanitize_key((string) ($payload['payment_method'] ?? ''));

        $this->logger->info('Subscription upgrade requested', [
            'account_id' => $account_id,
            'plan_code' => $plan_code,
            'payment_method' => $payment_method,
        ]);

        if ($account_id <= 0 || $plan_code === '' || $payment_method === '') {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('account_id, plan_code and payment_method are required', 'my-village-hall'),
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
        if (!$plan instanceof Plan) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Plan not found', 'my-village-hall'),
            ], 404);
        }

        if ($this->feature_service !== null && !$this->feature_service->allowsForAccount($account_id, 'subscription_upgrades')) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Your current subscription plan does not allow subscription upgrades', 'my-village-hall'),
            ], 403);
        }

        if ($this->plan_change_policy_service !== null) {
            $decision = $this->plan_change_policy_service->canChangeToPlan($account_id, $plan);
            if (empty($decision['allowed'])) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => (string) ($decision['message'] ?? __('Plan change is not allowed.', 'my-village-hall')),
                ], 422);
            }
        }

        if ($payment_method === 'stripe' || $payment_method === 'invoice') {
            return $this->upgrade_with_billing_service($account_id, $plan_code, $payment_method);
        }

        return new WP_REST_Response([
            'success' => false,
            'message' => __('Unsupported payment method', 'my-village-hall'),
        ], 400);
    }

    private function upgrade_with_billing_service(int $account_id, string $plan_code, string $payment_method): WP_REST_Response {
        try {
            $result = $this->billing_service->requestPlanChange($account_id, $plan_code, $payment_method);
            if (!is_array($result) || empty($result['success'])) {
                $this->logger->error('Billing upgrade failed', [
                    'account_id' => $account_id,
                    'plan_code' => $plan_code,
                    'payment_method' => $payment_method,
                ]);

                return new WP_REST_Response([
                    'success' => false,
                    'message' => __('Unable to start subscription upgrade billing flow', 'my-village-hall'),
                ], 422);
            }

            $this->logger->info('Billing upgrade succeeded', [
                'account_id' => $account_id,
                'plan_code' => $plan_code,
                'payment_method' => $payment_method,
                'result' => $result,
            ]);

            return new WP_REST_Response([
                'success' => true,
                'payment_method' => $payment_method,
                'billing' => $result,
            ], 200);
        } catch (\Throwable $exception) {
            $this->logger->error('Billing upgrade exception', [
                'account_id' => $account_id,
                'plan_code' => $plan_code,
                'payment_method' => $payment_method,
                'error' => $exception->getMessage(),
            ]);

            return new WP_REST_Response([
                'success' => false,
                'message' => (string) $exception->getMessage(),
            ], 500);
        }
    }

}
