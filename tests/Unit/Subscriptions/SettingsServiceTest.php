<?php

namespace MYVH\Tests\Unit\Subscriptions;

use Mockery\MockInterface;
use MYVH\Subscriptions\Repositories\SettingsRepository;
use MYVH\Subscriptions\Services\SettingsService;
use MYVH\Tests\Unit\UnitTestCase;

class SettingsServiceTest extends UnitTestCase {
    /** @var SettingsRepository&MockInterface */
    private $settings_repository;
    private SettingsService $service;

    protected function setUp(): void {
        parent::setUp();

        /** @var SettingsRepository&MockInterface $repository */
        $repository = $this->mock(SettingsRepository::class);
        $this->settings_repository = $repository;

        $this->service = new SettingsService($this->settings_repository);
    }

    /** @test */
    public function get_returns_default_when_setting_is_missing(): void {
        $this->settings_repository->shouldReceive('get_setting_value')
            ->once()
            ->with('trial_days', null)
            ->andReturn(null);

        $this->assertSame('14', $this->service->get('trial_days', '14'));
    }

    /** @test */
    public function get_returns_raw_scalar_value_when_not_json(): void {
        $this->settings_repository->shouldReceive('get_setting_value')
            ->once()
            ->with('default_plan', null)
            ->andReturn('basic');

        $this->assertSame('basic', $this->service->get('default_plan', 'pro'));
    }

    /** @test */
    public function get_decodes_json_arrays_for_feature_payloads(): void {
        $json = '{"features":{"bookings":150,"deposits":true,"invoicing":true,"reporting":true}}';

        $this->settings_repository->shouldReceive('get_setting_value')
            ->once()
            ->with('plan_features', null)
            ->andReturn($json);

        $result = $this->service->get('plan_features', []);

        $this->assertIsArray($result);
        $this->assertSame(150, $result['features']['bookings']);
        $this->assertTrue($result['features']['deposits']);
    }

    /** @test */
    public function set_persists_setting_in_global_scope(): void {
        $this->settings_repository->shouldReceive('set_setting_value')
            ->once()
            ->with('default_plan', 'standard')
            ->andReturn(true);

        $this->assertTrue($this->service->set('default_plan', 'standard'));
    }

    /** @test */
    public function set_returns_false_when_repository_write_fails(): void {
        $this->settings_repository->shouldReceive('set_setting_value')
            ->once()
            ->with('trial_days', 21)
            ->andReturn(false);

        $this->assertFalse($this->service->set('trial_days', 21));
    }
}
