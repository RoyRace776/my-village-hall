<?php

namespace MYVH\Tests\Unit\Services;

use MYVH\Tests\Unit\UnitTestCase;
use WP_Error;

class BookingChargeServiceTest extends UnitTestCase {
    private $pricing;
    private $booking_charge_repo;
    private $service;

    protected function setUp(): void {
        parent::setUp();

        $this->pricing = $this->mock(\MYVH\Pricing\PricingService::class);
        $this->booking_charge_repo = $this->mock(\MYVH\Bookings\BookingChargeRepository::class);

        $this->service = new \MYVH\Bookings\Services\BookingChargeService(
            $this->pricing,
            $this->booking_charge_repo
        );
    }

    /** @test */
    public function recalculate_returns_wp_error_for_invalid_id(): void {
        $result = $this->service->recalculate(0);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('validation', $result->get_error_code());
    }

    /** @test */
    public function recalculate_propagates_pricing_wp_error(): void {
        $this->pricing->shouldReceive('get_charge_snapshot')
            ->with(7)
            ->once()
            ->andReturn(new WP_Error('pricing', 'No rates found'));

        $result = $this->service->recalculate(7);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('pricing', $result->get_error_code());
    }

    /** @test */
    public function recalculate_returns_wp_error_when_delete_fails(): void {
        $this->pricing->shouldReceive('get_charge_snapshot')->with(7)->once()->andReturnUsing(static fn () => []);

        $this->booking_charge_repo->shouldReceive('delete_by_booking_id')
            ->with(7)
            ->once()
            ->andReturn(false);

        $result = $this->service->recalculate(7);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('database', $result->get_error_code());
    }

    /** @test */
    public function recalculate_returns_wp_error_when_create_fails(): void {
        $this->pricing->shouldReceive('get_charge_snapshot')->with(7)->once()->andReturnUsing(static fn () => []);
        $this->booking_charge_repo->shouldReceive('delete_by_booking_id')->with(7)->once()->andReturn(1);
        $this->booking_charge_repo->shouldReceive('create')->once()->andReturn(false);

        $result = $this->service->recalculate(7);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('database', $result->get_error_code());
    }

    /** @test */
    public function recalculate_returns_true_on_success(): void {
        $this->pricing->shouldReceive('get_charge_snapshot')->with(7)->once()->andReturnUsing(static fn () => []);
        $this->booking_charge_repo->shouldReceive('delete_by_booking_id')->with(7)->once()->andReturn(1);
        $this->booking_charge_repo->shouldReceive('create')->once()->andReturn(1);

        $result = $this->service->recalculate(7);

        $this->assertTrue($result);
    }
}
