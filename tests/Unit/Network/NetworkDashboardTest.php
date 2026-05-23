<?php

declare(strict_types=1);

namespace MYVH\Tests\Unit\Network;

use Brain\Monkey\Functions;
use Mockery\MockInterface;
use MYVH\Network\NetworkDashboard;
use MYVH\Subscriptions\Entities\Plan;
use MYVH\Subscriptions\Entities\Subscription;
use MYVH\Subscriptions\Repositories\AccountRepository;
use MYVH\Subscriptions\Repositories\PlanRepository;
use MYVH\Subscriptions\Repositories\SubscriptionRepository;
use MYVH\Tests\Unit\UnitTestCase;

class NetworkDashboardTest extends UnitTestCase {
    /** @var AccountRepository&MockInterface */
    private $account_repository;

    /** @var SubscriptionRepository&MockInterface */
    private $subscription_repository;

    /** @var PlanRepository&MockInterface */
    private $plan_repository;
    private NetworkDashboard $dashboard;

    protected function setUp(): void {
        parent::setUp();

        Functions\stubs([
            'current_user_can' => true,
            '__' => static fn(string $text): string => $text,
            '_n' => static fn(string $single, string $plural, int $number): string => $number === 1 ? $single : $plural,
            'sanitize_key' => static fn(string $value): string => strtolower(trim($value)),
            'esc_html' => static fn($value): string => (string) $value,
            'esc_attr' => static fn($value): string => (string) $value,
            'esc_url' => static fn($value): string => (string) $value,
            'get_admin_url' => static fn(int $blog_id, string $path = ''): string => 'https://site-' . $blog_id . '.example.test/wp-admin/' . ltrim($path, '/'),
            'wp_die' => static function (string $message): void {
                throw new \RuntimeException($message);
            },
        ]);

        Functions\when('get_site_option')->alias(static fn(string $key, $default = []): array => [
            'template_site_id' => 3,
        ]);

        Functions\when('get_main_site_id')->alias(static fn(): int => 1);
        Functions\when('get_sites')->alias(static fn(array $args = []): array => [
            (object) ['blog_id' => 1],
            (object) ['blog_id' => 2],
            (object) ['blog_id' => 3],
            (object) ['blog_id' => 4],
        ]);

        $blog_names = [
            1 => 'Master Hall',
            2 => 'Client Hall',
            3 => 'Template Hall',
            4 => 'Neighbour Hall',
        ];

        Functions\when('get_blog_option')->alias(static function (int $blog_id, string $option, $default = '') use ($blog_names) {
            return $blog_names[$blog_id] ?? $default;
        });
        Functions\when('get_site_url')->alias(static fn(int $blog_id): string => 'https://site-' . $blog_id . '.example.test');

        $this->account_repository = $this->mock(AccountRepository::class);
        $this->subscription_repository = $this->mock(SubscriptionRepository::class);
        $this->plan_repository = $this->mock(PlanRepository::class);

        $this->dashboard = new NetworkDashboard(
            null,
            $this->account_repository,
            $this->subscription_repository,
            $this->plan_repository
        );
    }

    /** @test */
    public function render_subscription_status_page_excludes_template_and_master_sites_and_shows_trial_days(): void {
        $this->account_repository->shouldReceive('get_by_blog_id')->once()->with(2)->andReturnUsing(static fn(): array => ['id' => 22]);
        $this->account_repository->shouldReceive('get_by_blog_id')->once()->with(4)->andReturnUsing(static fn(): array => ['id' => 44]);
        $this->account_repository->shouldReceive('get_by_external_reference')->never();

        $this->subscription_repository->shouldReceive('get_latest_by_account_id')
            ->once()
            ->with(22)
            ->andReturn(Subscription::fromArray([
                'status' => 'trialing',
                'plan_code' => 'trial',
                'trial_ends_at' => '2099-01-01 00:00:00',
            ]));

        $this->subscription_repository->shouldReceive('get_latest_by_account_id')
            ->once()
            ->with(44)
            ->andReturn(Subscription::fromArray([
                'status' => 'past_due',
                'plan_code' => 'standard',
                'metadata' => wp_json_encode(['manual_invoice_id' => 99]),
            ]));

        $this->plan_repository->shouldReceive('getByCode')
            ->once()
            ->with('trial')
            ->andReturn(Plan::fromArray([
                'plan_key' => 'trial',
                'name' => 'Trial Plan',
            ]));

        $this->plan_repository->shouldReceive('getByCode')
            ->once()
            ->with('standard')
            ->andReturn(Plan::fromArray([
                'plan_key' => 'standard',
                'name' => 'Standard Plan',
            ]));

        ob_start();
        $this->dashboard->render_subscription_status_page();
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('Client Hall', $output);
        $this->assertStringContainsString('Neighbour Hall', $output);
        $this->assertStringContainsString('Trial Plan', $output);
        $this->assertStringContainsString('Standard Plan', $output);
        $this->assertStringContainsString('Invoice #99 pending', $output);
        $this->assertStringContainsString('days', $output);
        $this->assertStringContainsString('wp-admin/admin.php?page=myvh-subscription-dashboard&blog_id=2', $output);
        $this->assertStringNotContainsString('Master Hall', $output);
        $this->assertStringNotContainsString('Template Hall', $output);
    }
}
