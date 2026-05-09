<?php

namespace MYVH\Tests\Unit\AutoInvoicing;

use Brain\Monkey\Functions;
use Mockery;
use MYVH\AutoInvoicing\SingleBookingAutoInvoiceRuleRepository;
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
    /** @var SingleBookingAutoInvoiceRuleRepository&\Mockery\MockInterface */
    private $rule_repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->invoice_generator = Mockery::mock(InvoiceGeneratorService::class);
        $this->booking_service   = Mockery::mock(BookingService::class);
        $this->rule_repository   = Mockery::mock(SingleBookingAutoInvoiceRuleRepository::class);
    }

    private function make_sut(): SingleBookingAutoInvoicing
    {
        return new SingleBookingAutoInvoicing($this->invoice_generator, $this->booking_service, $this->rule_repository);
    }

    // ── process ──────────────────────────────────────────────────────────

    /** @test */
    public function process_returns_zero_when_no_rules_or_legacy_rule_is_disabled(): void
    {
        Functions\when('myvh_setting')
            ->alias(function (string $key) {
                if ($key === 'invoicing.single_default_rule_id') return 0;
                if ($key === 'invoicing.single_enabled') return false;
                if ($key === 'invoicing.single_trigger_timing') return 'confirmation';
                if ($key === 'invoicing.single_trigger_offset_days') return 0;
                if ($key === 'invoicing.single_group_by') return 'per_booking';
                if ($key === 'invoicing.single_due_date_offset_days') return 30;
                return null;
            });
        $this->rule_repository->shouldReceive('get_active_rules')->andReturn([]);
        $this->booking_service->shouldReceive('get_uninvoiced_single_bookings')->andReturn([
            ['Id' => 1, 'Status' => 'confirmed', 'StartDate' => '2026-06-01'],
        ]);

        $sut = $this->make_sut();

        $this->assertSame(0, $sut->process());
    }

    /** @test */
    public function process_returns_zero_when_no_bookings_to_invoice(): void
    {
        Functions\when('myvh_setting')->alias(function (string $key) {
            if ($key === 'invoicing.single_default_rule_id') return 12;
            return null;
        });
        $this->rule_repository->shouldReceive('get_active_rules')->andReturn([
            ['Id' => 12, 'IsActive' => 1, 'TriggerTiming' => 'confirmation', 'TriggerOffsetDays' => 0, 'GroupBy' => 'per_booking', 'DueDateOffsetDays' => 30],
        ]);
        $this->booking_service->shouldReceive('get_uninvoiced_single_bookings')->andReturn([]);

        $sut = $this->make_sut();

        $this->assertSame(0, $sut->process());
    }

    /** @test */
    public function process_returns_zero_when_no_bookings_meet_trigger_condition(): void
    {
        Functions\when('myvh_setting')
            ->alias(function (string $key) {
                if ($key === 'invoicing.single_default_rule_id') return 12;
                return null;
            });
        $this->rule_repository->shouldReceive('get_active_rules')->andReturn([
            ['Id' => 12, 'IsActive' => 1, 'TriggerTiming' => 'confirmation', 'TriggerOffsetDays' => 0, 'GroupBy' => 'per_booking', 'DueDateOffsetDays' => 30],
        ]);

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
                if ($key === 'invoicing.single_default_rule_id') return 12;
                return null;
            });
        $this->rule_repository->shouldReceive('get_active_rules')->andReturn([
            ['Id' => 12, 'IsActive' => 1, 'TriggerTiming' => 'confirmation', 'TriggerOffsetDays' => 0, 'GroupBy' => 'by_customer', 'DueDateOffsetDays' => 14],
        ]);

        $this->booking_service->shouldReceive('get_uninvoiced_single_bookings')
            ->andReturn([['Id' => 10, 'Status' => 'confirmed', 'StartDate' => '2026-06-01']]);

        $this->invoice_generator->shouldReceive('generate_invoices_from_bookings')
            ->once()
            ->with([10], Mockery::on(function (array $options): bool {
                return $options['rule_id'] === 12 && $options['group_by'] === 'by_customer';
            }))
            ->andReturn([50]);

        $sut = $this->make_sut();

        $this->assertSame(1, $sut->process());
    }

    // ── is_trigger_condition_met – booking_date ───────────────────────────

    /** @test */
    public function trigger_condition_booking_date_is_met_when_date_is_in_past(): void
    {
        Functions\when('myvh_setting')
            ->alias(function (string $key) {
                if ($key === 'invoicing.single_default_rule_id') return 12;
                return null;
            });
        $this->rule_repository->shouldReceive('get_active_rules')->andReturn([
            ['Id' => 12, 'IsActive' => 1, 'TriggerTiming' => 'booking_date', 'TriggerOffsetDays' => 0, 'GroupBy' => 'by_customer', 'DueDateOffsetDays' => 30],
        ]);

        $past_date = (new \DateTime('-1 day'))->format('Y-m-d');

        $this->booking_service->shouldReceive('get_uninvoiced_single_bookings')
            ->andReturn([['Id' => 1, 'Status' => 'confirmed', 'StartDate' => $past_date]]);

        $this->invoice_generator->shouldReceive('generate_invoices_from_bookings')->once()->andReturn([99]);

        $sut = $this->make_sut();

        $this->assertSame(1, $sut->process());
    }

    /** @test */
    public function trigger_condition_booking_date_not_met_for_future_date(): void
    {
        Functions\when('myvh_setting')
            ->alias(function (string $key) {
                if ($key === 'invoicing.single_default_rule_id') return 12;
                return null;
            });
        $this->rule_repository->shouldReceive('get_active_rules')->andReturn([
            ['Id' => 12, 'IsActive' => 1, 'TriggerTiming' => 'booking_date', 'TriggerOffsetDays' => 0, 'GroupBy' => 'per_booking', 'DueDateOffsetDays' => 30],
        ]);

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
                if ($key === 'invoicing.single_default_rule_id') return 12;
                return null;
            });
        $this->rule_repository->shouldReceive('get_active_rules')->andReturn([
            ['Id' => 12, 'IsActive' => 1, 'TriggerTiming' => 'days_after_booking_date', 'TriggerOffsetDays' => 3, 'GroupBy' => 'by_customer', 'DueDateOffsetDays' => 30],
        ]);

        $past_date = (new \DateTime('-5 days'))->format('Y-m-d');

        $this->booking_service->shouldReceive('get_uninvoiced_single_bookings')
            ->andReturn([['Id' => 1, 'Status' => 'confirmed', 'StartDate' => $past_date]]);

        $this->invoice_generator->shouldReceive('generate_invoices_from_bookings')->once()->andReturn([101]);

        $sut = $this->make_sut();

        $this->assertSame(1, $sut->process());
    }

    /** @test */
    public function process_uses_customer_rule_before_organisation_and_default(): void
    {
        Functions\when('myvh_setting')
            ->alias(function (string $key) {
                if ($key === 'invoicing.single_default_rule_id') return 9;
                return null;
            });
        $this->rule_repository->shouldReceive('get_active_rules')->andReturn([
            ['Id' => 9, 'IsActive' => 1, 'TriggerTiming' => 'booking_date', 'TriggerOffsetDays' => 0, 'GroupBy' => 'per_booking', 'DueDateOffsetDays' => 30],
            ['Id' => 10, 'IsActive' => 1, 'TriggerTiming' => 'confirmation', 'TriggerOffsetDays' => 0, 'GroupBy' => 'by_customer', 'DueDateOffsetDays' => 7],
        ]);

        $this->booking_service->shouldReceive('get_uninvoiced_single_bookings')
            ->andReturn([[
                'Id' => 10,
                'Status' => 'confirmed',
                'StartDate' => '2026-06-01',
                'CustomerSingleBookingAutoInvoiceRuleId' => 10,
                'OrganisationSingleBookingAutoInvoiceRuleId' => 9,
            ]]);

        $this->invoice_generator->shouldReceive('generate_invoices_from_bookings')
            ->once()
            ->with([10], Mockery::on(function (array $options): bool {
                return $options['rule_id'] === 10 && $options['group_by'] === 'by_customer';
            }))
            ->andReturn([888]);

        $sut = $this->make_sut();

        $this->assertSame(1, $sut->process());
    }
}
