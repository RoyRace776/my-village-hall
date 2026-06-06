<?php

namespace MYVH\Portal;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use MYVH\Application\Services\LoginRenderer;
use MYVH\Application\Services\SubscriptionPlanTableRenderer;
use MYVH\Core\Shortcode\ShortcodeInterface;
use MYVH\Subscriptions\Repositories\AccountRepository;
use MYVH\Subscriptions\Repositories\SubscriptionRepository;
use MYVH\Subscriptions\Services\AccountService;
use MYVH\Subscriptions\Services\PlanChangePolicyService;
use MYVH\Subscriptions\Services\SettingsService;
use MYVH\Subscriptions\Services\TrialService;

class SubscriptionPlanTableShortcode implements ShortcodeInterface {
    public function __construct(
        private ClientAdminService $client_admin_service,
        private ?AccountRepository $account_repository = null,
        private ?SubscriptionRepository $subscription_repository = null,
        private ?PlanChangePolicyService $plan_change_policy_service = null,
        private ?AccountService $account_service = null,
        private ?TrialService $trial_service = null,
        private ?SettingsService $settings_service = null
    ) {
    }

    public function tag(): string {
        return 'myvh_subscription_plan_table';
    }

    public function render( mixed $atts = [], mixed $content = null ): string {
        return ( new SubscriptionPlanTableRenderer(
            $this->client_admin_service,
            $this->account_repository,
            $this->subscription_repository,
            $this->plan_change_policy_service,
            $this->account_service,
            $this->trial_service,
            $this->settings_service,
            new LoginRenderer()
        ) )->render( (array) $atts );
    }
}
