<?php

namespace MYVH\Tests\Unit\Subscriptions;

use Brain\Monkey\Functions;
use Mockery\MockInterface;
use MYVH\Subscriptions\Entities\Subscription;
use MYVH\Subscriptions\Admin\SubscriptionsAdmin;
use MYVH\Subscriptions\Repositories\PlanRepository;
use MYVH\Subscriptions\Repositories\SubscriptionRepository;
use MYVH\Subscriptions\Services\AccountService;
use MYVH\Subscriptions\Services\BillingNotificationService;
use MYVH\Subscriptions\Services\BillingService;
use MYVH\Subscriptions\Services\ManualInvoiceService;
use MYVH\Subscriptions\Services\PlanChangePolicyService;
use MYVH\Subscriptions\Services\SettingsService;
use MYVH\Subscriptions\Services\TrialService;
use MYVH\Subscriptions\Services\UsageService;
use MYVH\Tests\Unit\UnitTestCase;

class SubscriptionsAdminTest extends UnitTestCase {
    /** @var AccountService&MockInterface */
    private $account_service;

    /** @var SubscriptionRepository&MockInterface */
    private $subscription_repository;

    /** @var SettingsService&MockInterface */
    private $settings_service;

    /** @var UsageService&MockInterface */
    private $usage_service;

    /** @var PlanRepository&MockInterface */
    private $plan_repository;

    /** @var ManualInvoiceService&MockInterface */
    private $manual_invoice_service;

    /** @var BillingService&MockInterface */
    private $billing_service;

    /** @var PlanChangePolicyService&MockInterface */
    private $plan_change_policy_service;

    /** @var BillingNotificationService&MockInterface */
    private $billing_notification_service;

    /** @var TrialService&MockInterface */
    private $trial_service;

    private SubscriptionsAdmin $admin;

    protected function setUp(): void {
        parent::setUp();

        Functions\stubs([
            '__' => fn($value) => (string) $value,
            'esc_html__' => fn($value) => (string) $value,
            'is_admin' => true,
            'current_user_can' => true,
            'get_current_blog_id' => 2,
            'get_current_screen' => (object) ['id' => 'myvh-dashboard'],
            'current_time' => fn($type = 'mysql') => $type === 'timestamp' ? strtotime('2026-05-21 10:00:00') : '2026-05-21 10:00:00',
            'esc_html' => fn($value) => (string) $value,
            'esc_attr' => fn($value) => (string) $value,
            'esc_url' => fn($value) => (string) $value,
            'sanitize_key' => fn($value) => strtolower((string) $value),
            'selected' => fn($a, $b, $echo = false) => $a === $b ? ' selected="selected"' : '',
            'submit_button' => static function ($label = 'Save'): void {
                echo '<button>' . $label . '</button>';
            },
            'wp_nonce_field' => static function (): void {
            },
            'check_admin_referer' => true,
            'admin_url' => fn($path = '') => 'http://example.test/wp-admin/' . ltrim((string) $path, '/'),
            'add_query_arg' => function (array $args, string $url): string {
                return $url . '?' . http_build_query($args);
            },
            'wp_safe_redirect' => true,
            'date_i18n' => fn($format, $timestamp) => gmdate('Y-m-d H:i:s', (int) $timestamp),
            'get_option' => fn($name, $default = '') => $default,
        ]);

        /** @var AccountService&MockInterface $account_service */
        $account_service = $this->mock(AccountService::class);
        $this->account_service = $account_service;

        /** @var SubscriptionRepository&MockInterface $subscription_repository */
        $subscription_repository = $this->mock(SubscriptionRepository::class);
        $this->subscription_repository = $subscription_repository;

        /** @var SettingsService&MockInterface $settings_service */
        $settings_service = $this->mock(SettingsService::class);
        $this->settings_service = $settings_service;

        /** @var UsageService&MockInterface $usage_service */
        $usage_service = $this->mock(UsageService::class);
        $this->usage_service = $usage_service;

        /** @var PlanRepository&MockInterface $plan_repository */
        $plan_repository = $this->mock(PlanRepository::class);
        $this->plan_repository = $plan_repository;

        /** @var ManualInvoiceService&MockInterface $manual_invoice_service */
        $manual_invoice_service = $this->mock(ManualInvoiceService::class);
        $this->manual_invoice_service = $manual_invoice_service;

        /** @var BillingNotificationService&MockInterface $billing_notification_service */
        $billing_notification_service = $this->mock(BillingNotificationService::class);
        $this->billing_notification_service = $billing_notification_service;

        /** @var BillingService&MockInterface $billing_service */
        $billing_service = $this->mock(BillingService::class);
        $this->billing_service = $billing_service;

        /** @var PlanChangePolicyService&MockInterface $plan_change_policy_service */
        $plan_change_policy_service = $this->mock(PlanChangePolicyService::class);
        $this->plan_change_policy_service = $plan_change_policy_service;

        /** @var TrialService&MockInterface $trial_service */
        $trial_service = $this->mock(TrialService::class);
        $this->trial_service = $trial_service;

        $this->admin = new SubscriptionsAdmin(
            $this->account_service,
            $this->subscription_repository,
            $this->settings_service,
            $this->usage_service,
            $this->plan_repository,
            $this->billing_service,
            $this->plan_change_policy_service,
            $this->manual_invoice_service,
            $this->billing_notification_service,
            $this->trial_service
        );
    }

    /** @test */
    public function save_billing_settings_persists_expected_keys_and_redirects(): void {
        $_POST['trial_days'] = '21';
        $_POST['grace_period_days'] = '5';
        $_POST['default_plan'] = 'STANDARD';

        $redirect_url = '';
        Functions\when('wp_safe_redirect')->alias(function (string $url) use (&$redirect_url): bool {
            $redirect_url = $url;
            return true;
        });

        $this->settings_service->shouldReceive('set')->once()->with('trial_days', 21)->andReturn(true);
        $this->settings_service->shouldReceive('set')->once()->with('grace_period_days', 5)->andReturn(true);
        $this->settings_service->shouldReceive('set')->once()->with('default_plan', 'standard')->andReturn(true);

        $this->admin->save_billing_settings();

        $this->assertStringContainsString('page=myvh-subscription-billing-settings', $redirect_url);
        $this->assertStringContainsString('updated=1', $redirect_url);
    }

    /** @test */
    public function render_subscription_notices_outputs_expired_notice(): void {
        $this->billing_notification_service->shouldReceive('notifyTrialEndingSoon')->never();

        $this->account_service->shouldReceive('resolveAccountFromBlogId')
            ->once()
            ->with(2)
            ->andReturnUsing(static fn(): array => ['id' => 88]);

        $this->trial_service->shouldReceive('checkAndExpireTrial')
            ->once()
            ->with(88)
            ->andReturnUsing(static fn(): Subscription => Subscription::fromArray(['status' => 'expired']));

        ob_start();
        $this->admin->render_subscription_notices();
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('Subscription is expired', $output);
    }

    /** @test */
    public function render_subscription_notices_outputs_trial_ending_soon_notice(): void {
        $this->billing_notification_service->shouldReceive('notifyTrialEndingSoon')->once()->andReturn(true);

        $this->account_service->shouldReceive('resolveAccountFromBlogId')
            ->once()
            ->with(2)
            ->andReturnUsing(static fn(): array => ['id' => 89, 'contact_email' => 'billing@example.test']);

        $this->trial_service->shouldReceive('checkAndExpireTrial')
            ->once()
            ->with(89)
            ->andReturnUsing(static fn(): Subscription => Subscription::fromArray([
                'status' => 'trial',
                'trial_ends_at' => '2026-05-24 10:00:00',
            ]));

        ob_start();
        $this->admin->render_subscription_notices();
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('Trial ends in', $output);
    }
}
