<?php

namespace MYVH\Tests\Unit\Portal\Actions;

use Brain\Monkey\Functions;
use MYVH\Customers\CustomerService;
use MYVH\Deposits\DepositService;
use MYVH\Portal\Actions\QuoteBookingAction;
use MYVH\Portal\ClientAdminService;
use MYVH\Pricing\PricingService;
use MYVH\Tests\Unit\UnitTestCase;

class QuoteBookingActionTest extends UnitTestCase {
    private $customer_service;
    private $client_admin_service;
    private $pricing_service;
    private $deposit_service;
    private QuoteBookingAction $action;

    protected function setUp(): void {
        parent::setUp();

        $this->customer_service = $this->mock(CustomerService::class);
        $this->client_admin_service = $this->mock(ClientAdminService::class);
        $this->pricing_service = $this->mock(PricingService::class);
        $this->deposit_service = $this->mock(DepositService::class);

        $this->action = new QuoteBookingAction(
            $this->customer_service,
            $this->client_admin_service,
            $this->pricing_service,
            $this->deposit_service
        );

        Functions\stubs([
            'get_current_user_id' => 21,
            'get_current_blog_id' => 7,
            'is_wp_error' => static fn($value): bool => $value instanceof \WP_Error,
        ]);
    }

    /** @test */
    public function execute_returns_error_when_non_admin_has_no_customer_profile(): void {
        $this->client_admin_service->shouldReceive('can_administer_blog')
            ->once()
            ->with(21, 7)
            ->andReturn(false);

        $this->customer_service->shouldReceive('get_by_user_id')
            ->once()
            ->with(21)
            ->andReturn([]);

        $result = $this->action->execute([
            'start' => '2026-05-10 09:00:00',
            'end' => '2026-05-10 10:00:00',
            'room_id' => 4,
            'organisation_id' => 12,
        ]);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('No customer profile found', $result->get_error_message());
    }

    /** @test */
    public function execute_rewrites_customer_and_organisation_for_non_admins(): void {
        $this->client_admin_service->shouldReceive('can_administer_blog')
            ->once()
            ->with(21, 7)
            ->andReturn(false);

        $this->customer_service->shouldReceive('get_by_user_id')
            ->once()
            ->with(21)
            ->andReturnUsing(static fn(): array => ['Id' => 9]);

        $this->customer_service->shouldReceive('get_organisations_for_user_id')
            ->once()
            ->with(21)
            ->andReturn([
                ['Id' => 12],
            ]);

        $this->customer_service->shouldReceive('get_organisations_for_customer')
            ->once()
            ->with(9)
            ->andReturn([
                ['Id' => 13],
            ]);

        $this->pricing_service->shouldReceive('get_charge_snapshot_for_data')
            ->once()
            ->with(\Mockery::on(static function (array $payload): bool {
                return intval($payload['room_id'] ?? 0) === 4
                    && intval($payload['customer_id'] ?? 0) === 9
                    && intval($payload['organisation_id'] ?? 0) === 12
                    && ($payload['start_date'] ?? '') === '2026-05-10'
                    && ($payload['start_time'] ?? '') === '09:00:00'
                    && ($payload['end_date'] ?? '') === '2026-05-10'
                    && ($payload['end_time'] ?? '') === '10:00:00';
            }))
            ->andReturnUsing(static fn(): array => [
                'TotalAmount' => 25.00,
            ]);

        $this->deposit_service->shouldReceive('evaluate')
            ->once()
            ->andReturnUsing(static fn(): array => [
                'amount' => 10.00,
                'action' => 'auto_add',
            ]);

        $result = $this->action->execute([
            'start' => '2026-05-10 09:00:00',
            'end' => '2026-05-10 10:00:00',
            'room_id' => 4,
            'customer_id' => 99,
            'organisation_id' => 999,
        ]);

        $this->assertSame(25.0, $result['room_charge']);
        $this->assertSame(0.0, $result['addons_total']);
        $this->assertSame(10.0, $result['deposit_amount']);
        $this->assertSame(35.0, $result['booking_total']);
    }

    /** @test */
    public function execute_keeps_requested_customer_and_organisation_for_client_admins(): void {
        $this->client_admin_service->shouldReceive('can_administer_blog')
            ->once()
            ->with(21, 7)
            ->andReturn(true);

        $this->customer_service->shouldReceive('get_by_user_id')->never();

        $this->pricing_service->shouldReceive('get_charge_snapshot_for_data')
            ->once()
            ->with(\Mockery::on(static function (array $payload): bool {
                return intval($payload['customer_id'] ?? 0) === 77
                    && intval($payload['organisation_id'] ?? 0) === 88;
            }))
            ->andReturnUsing(static fn(): array => [
                'TotalAmount' => 40.00,
            ]);

        $this->deposit_service->shouldReceive('evaluate')
            ->once()
            ->andThrow(new \RuntimeException('Deposit lookup failed'));

        $result = $this->action->execute([
            'start' => '2026-05-10 09:00:00',
            'end' => '2026-05-10 10:00:00',
            'room_id' => 4,
            'customer_id' => 77,
            'organisation_id' => 88,
        ]);

        $this->assertSame(40.0, $result['room_charge']);
        $this->assertSame(0.0, $result['deposit_amount']);
        $this->assertSame(40.0, $result['booking_total']);
    }

    /** @test */
    public function execute_returns_validation_error_when_end_is_not_later_than_start(): void {
        $this->client_admin_service->shouldReceive('can_administer_blog')
            ->once()
            ->with(21, 7)
            ->andReturn(true);

        $this->pricing_service->shouldReceive('get_charge_snapshot_for_data')->never();

        $result = $this->action->execute([
            'start' => '2026-05-10 10:00:00',
            'end' => '2026-05-10 09:00:00',
            'room_id' => 4,
            'customer_id' => 77,
            'organisation_id' => 88,
        ]);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('End time must be later than the start time', $result->get_error_message());
    }

    /** @test */
    public function execute_returns_pricing_error_directly(): void {
        $this->client_admin_service->shouldReceive('can_administer_blog')
            ->once()
            ->with(21, 7)
            ->andReturn(true);

        $pricing_error = new \WP_Error('pricing', 'No rates found');

        $this->pricing_service->shouldReceive('get_charge_snapshot_for_data')
            ->once()
            ->andReturn($pricing_error);

        $result = $this->action->execute([
            'start' => '2026-05-10 09:00:00',
            'end' => '2026-05-10 10:00:00',
            'room_id' => 4,
            'customer_id' => 77,
            'organisation_id' => 88,
        ]);

        $this->assertSame($pricing_error, $result);
    }

    /** @test */
    public function execute_normalizes_addons_and_includes_them_in_total(): void {
        $this->client_admin_service->shouldReceive('can_administer_blog')
            ->once()
            ->with(21, 7)
            ->andReturn(true);

        $this->pricing_service->shouldReceive('get_charge_snapshot_for_data')
            ->once()
            ->andReturnUsing(static fn(): array => [
                'TotalAmount' => 20.00,
            ]);

        $this->deposit_service->shouldReceive('evaluate')
            ->once()
            ->andReturn(null);

        $result = $this->action->execute([
            'start' => '2026-05-10 09:00:00',
            'end' => '2026-05-10 10:00:00',
            'room_id' => 4,
            'customer_id' => 77,
            'organisation_id' => 88,
            'addons' => [
                ['addon_id' => 1, 'quantity' => 2, 'unit_price' => 2.50, 'enabled' => 'true'],
                ['addon_id' => 2, 'quantity' => 1, 'unit_price' => 3.00, 'enabled' => '0'],
                ['addon_id' => 3, 'quantity' => 3, 'unit_price' => 1.00],
                ['addon_id' => 0, 'quantity' => 2, 'unit_price' => 9.00, 'enabled' => 'true'],
            ],
        ]);

        $this->assertSame(8.0, $result['addons_total']);
        $this->assertSame(28.0, $result['booking_total']);
    }
}
