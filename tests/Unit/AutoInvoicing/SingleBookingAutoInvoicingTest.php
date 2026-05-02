<?php

namespace MYVH\Tests\Unit\AutoInvoicing;

use Brain\Monkey\Functions;
use Mockery;
use MYVH\AutoInvoicing\SingleBookingAutoInvoicing;
use MYVH\Bookings\BookingService;
use MYVH\Invoices\InvoiceGeneratorService;
use MYVH\Tests\Unit\UnitTestCase;

class SingleBookingAutoInvoicingTest extends UnitTestCase
{
    /** @var InvoiceGeneratorService&\Mockery\MockInterface */
    private $invoice_generator;
    /** @var BookingService&\Mockery\MockInterface */
    private $booking_service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->invoice_generator = Mockery::mock(InvoiceGeneratorService::class);
        $this->booking_service   = Mockery::mock(BookingService::class);
    }

    private function make_sut(): SingleBookingAutoInvoicing
    {
        return new SingleBookingAutoInvoicing($this->invoice_generator, $this->booking_service);
    }

    // ── process ──────────────────────────────────────────────────────────

    /** @test */
    public function process_returns_zero_when_single_invoicing_disabled(): void
    {
        Functions\when('myvh_setting')->justReturn(false);

        $sut = $this->make_sut();

        $this->assertSame(0, $sut->process());
    }

    /** @test */
    public function process_returns_zero_when_no_bookings_to_invoice(): void
    {
        Functions\when('myvh_setting')->justReturn(true);
        $this->booking_service->shouldReceive('get_uninvoiced_single_bookings')->andReturn([]);

        $sut = $this->make_sut();

        $this->assertSame(0, $sut->process());
    }

    /** @test */
    public function process_returns_zero_when_no_bookings_meet_trigger_condition(): void
    {
        Functions\when('myvh_setting')
            ->alias(function (string $key) {
                if ($key === 'invoicing.single_enabled') return true;
                if ($key === 'invoicing.single_trigger_timing') return 'confirmation';
                return null;
            });

        $this->booking_service->shouldReceive('get_uninvoiced_single_bookings')
            ->andReturn([['Id' => 1, 'Status' => 'pending', 'StartDate' => '2026-06-01']]);

        $sut = $this->make_sut();

        $this->assertSame(0, $sut->process());
    }

    /** @test */
    public function process_generates_invoice_when_booking_is_confirmed(): void
    {
        Functions\when('myvh_setting')
            ->alias(function (string $key) {
                if ($key === 'invoicing.single_enabled') return true;
                if ($key === 'invoicing.single_trigger_timing') return 'confirmation';
                if ($key === 'invoicing.single_group_by') return 'customer';
                return null;
            });

        $this->booking_service->shouldReceive('get_uninvoiced_single_bookings')
            ->andReturn([['Id' => 10, 'Status' => 'confirmed', 'StartDate' => '2026-06-01']]);

        $this->invoice_generator->shouldReceive('generate_invoices_from_bookings')
            ->once()
            ->with([10], Mockery::type('array'));

        $sut = $this->make_sut();

        $this->assertSame(1, $sut->process());
    }

    // ── is_trigger_condition_met – booking_date ───────────────────────────

    /** @test */
    public function trigger_condition_booking_date_is_met_when_date_is_in_past(): void
    {
        Functions\when('myvh_setting')
            ->alias(function (string $key) {
                if ($key === 'invoicing.single_enabled') return true;
                if ($key === 'invoicing.single_trigger_timing') return 'booking_date';
                if ($key === 'invoicing.single_group_by') return 'customer';
                return null;
            });

        $past_date = (new \DateTime('-1 day'))->format('Y-m-d');

        $this->booking_service->shouldReceive('get_uninvoiced_single_bookings')
            ->andReturn([['Id' => 1, 'Status' => 'confirmed', 'StartDate' => $past_date]]);

        $this->invoice_generator->shouldReceive('generate_invoices_from_bookings')->once();

        $sut = $this->make_sut();

        $this->assertSame(1, $sut->process());
    }

    /** @test */
    public function trigger_condition_booking_date_not_met_for_future_date(): void
    {
        Functions\when('myvh_setting')
            ->alias(function (string $key) {
                if ($key === 'invoicing.single_enabled') return true;
                if ($key === 'invoicing.single_trigger_timing') return 'booking_date';
                return null;
            });

        $future_date = (new \DateTime('+30 days'))->format('Y-m-d');

        $this->booking_service->shouldReceive('get_uninvoiced_single_bookings')
            ->andReturn([['Id' => 1, 'Status' => 'confirmed', 'StartDate' => $future_date]]);

        $sut = $this->make_sut();

        $this->assertSame(0, $sut->process());
    }

    // ── is_trigger_condition_met – days_after_booking_date ───────────────

    /** @test */
    public function trigger_condition_days_after_met_when_past_offset_days(): void
    {
        Functions\when('myvh_setting')
            ->alias(function (string $key) {
                if ($key === 'invoicing.single_enabled') return true;
                if ($key === 'invoicing.single_trigger_timing') return 'days_after_booking_date';
                if ($key === 'invoicing.single_trigger_offset_days') return 3;
                if ($key === 'invoicing.single_group_by') return 'customer';
                return null;
            });

        $past_date = (new \DateTime('-5 days'))->format('Y-m-d');

        $this->booking_service->shouldReceive('get_uninvoiced_single_bookings')
            ->andReturn([['Id' => 1, 'Status' => 'confirmed', 'StartDate' => $past_date]]);

        $this->invoice_generator->shouldReceive('generate_invoices_from_bookings')->once();

        $sut = $this->make_sut();

        $this->assertSame(1, $sut->process());
    }
}
