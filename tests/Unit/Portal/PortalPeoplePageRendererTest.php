<?php

namespace MYVH\Tests\Unit\Portal;

use Brain\Monkey\Functions;
use MYVH\AutoInvoicing\RecurringBookingAutoInvoiceRuleRepository;
use MYVH\AutoInvoicing\SingleBookingAutoInvoiceRuleRepository;
use MYVH\Customers\CustomerService;
use MYVH\Portal\Ajax\PortalPeoplePageRenderer;
use MYVH\Portal\ClientAdminService;
use MYVH\Subscriptions\Entities\Plan;
use MYVH\Subscriptions\Entities\Subscription;
use MYVH\Subscriptions\Repositories\AccountRepository;
use MYVH\Subscriptions\Repositories\PlanRepository;
use MYVH\Subscriptions\Repositories\SubscriptionRepository;
use MYVH\Subscriptions\Services\AccountService;
use MYVH\Subscriptions\Services\PlanChangePolicyService;
use MYVH\Subscriptions\Services\TrialService;
use MYVH\Tests\Unit\UnitTestCase;

class PortalPeoplePageRendererTest extends UnitTestCase {
    private ClientAdminService $client_admin_service;
    private $customer_service;
    private $rule_repository;
    private $recurring_rule_repository;
    private $account_repository;
    private $account_service;
    private $subscription_repository;
    private $plan_repository;
    private $plan_change_policy_service;
    private $trial_service;
    private PortalPeoplePageRenderer $renderer;

    protected function setUp(): void {
        parent::setUp();

        Functions\stubs([
            'esc_html' => static fn($value) => (string) $value,
            'esc_attr' => static fn($value) => (string) $value,
            'esc_url' => static fn($value) => (string) $value,
            '__' => static fn($value) => (string) $value,
            'esc_html__' => static fn($value) => (string) $value,
            'esc_html_e' => static function ($value): void {
                echo (string) $value;
            },
            'sanitize_key' => static fn($value) => strtolower((string) $value),
            'sanitize_text_field' => static fn($value) => (string) $value,
            'get_current_blog_id' => static fn() => 2,
            'get_current_user_id' => static fn() => 19,
            'get_bloginfo' => static fn($show = '') => $show === 'name' ? 'Neighbour Hall' : '',
            'get_option' => static fn($name, $default = '') => $default,
            'current_time' => static fn($type = 'mysql') => $type === 'timestamp' ? strtotime('2026-05-25 12:00:00') : '2026-05-25 12:00:00',
            'is_multisite' => static fn() => true,
            'is_super_admin' => static fn($user_id = null) => true,
        ]);

        $this->client_admin_service = new ClientAdminService();
        $this->customer_service = $this->mock(CustomerService::class);
        $this->rule_repository = $this->mock(SingleBookingAutoInvoiceRuleRepository::class);
        $this->recurring_rule_repository = $this->mock(RecurringBookingAutoInvoiceRuleRepository::class);
        $this->account_repository = $this->mock(AccountRepository::class);
        $this->account_service = $this->mock(AccountService::class);
        $this->subscription_repository = $this->mock(SubscriptionRepository::class);
        $this->plan_repository = $this->mock(PlanRepository::class);
        $this->plan_change_policy_service = $this->mock(PlanChangePolicyService::class);
        $this->trial_service = $this->mock(TrialService::class);

        $this->renderer = new PortalPeoplePageRenderer(
            $this->client_admin_service,
            $this->customer_service,
            $this->rule_repository,
            $this->recurring_rule_repository,
            $this->account_repository,
            $this->account_service,
            $this->subscription_repository,
            $this->plan_repository,
            $this->plan_change_policy_service,
            $this->trial_service
        );
    }

    /** @test */
    public function render_subscription_upgrade_shows_trial_reset_form_for_expired_trials_to_global_admins(): void {
        $this->account_repository->shouldReceive('get_by_blog_id')
            ->once()
            ->with(2)
            ->andReturnUsing(static fn() => ['id' => 44]);

        $this->subscription_repository->shouldReceive('get_active_by_account_id')
            ->once()
            ->with(44)
            ->andReturn(Subscription::fromArray([
                'id' => 91,
                'account_id' => 44,
                'status' => 'expired',
                'current_period_start' => '2026-05-01 00:00:00',
                'current_period_end' => '2026-05-15 00:00:00',
                'trial_ends_at' => '2026-05-15 00:00:00',
                'plan_code' => 'trial',
            ]));

        $this->plan_repository->shouldReceive('getByCode')
            ->once()
            ->with('trial')
            ->andReturn(Plan::fromArray([
                'plan_key' => 'trial',
                'name' => 'Trial',
            ]));

        $this->plan_change_policy_service->shouldReceive('getPlanOptionsForAccount')
            ->once()
            ->with(44)
            ->andReturn([]);

        ob_start();
        $this->renderer->render_subscription_upgrade(true);
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('Update Trial Start Date', $html);
        $this->assertStringContainsString('Trial start date (Super User)', $html);
    }
}