<?php

namespace MYVH\Tests\Unit\AutoInvoicing;

use Brain\Monkey\Functions;
use Mockery;
use MYVH\AutoInvoicing\RecurringBookingAutoInvoiceRuleRepository;
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
    /** @var RecurringBookingAutoInvoiceRuleRepository&\Mockery\MockInterface */
    private $recurring_rule_repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->invoice_generator = Mockery::mock(InvoiceGeneratorService::class);
        $this->booking_service   = Mockery::mock(BookingService::class);
        $this->rule_repository   = Mockery::mock(SingleBookingAutoInvoiceRuleRepository::class);
        $this->recurring_rule_repository = Mockery::mock(RecurringBookingAutoInvoiceRuleRepository::class);
    }

    private function make_sut(): SingleBookingAutoInvoicing
    {
        return new class(
            $this->invoice_generator,
            $this->booking_service,
            $this->rule_repository,
            $this->recurring_rule_repository
        ) extends SingleBookingAutoInvoicing {
            /** @var array<int> */
            public array $applied_rule_ids = [];

            protected function create_single_invoices_for_rule(array $bookings, array $rule): array {
                $rule_id = \intval($rule['id'] ?? 0);
                $this->applied_rule_ids[] = $rule_id;

                return $rule_id > 0 ? [$rule_id] : [];
            }
        };
    }

    /** @test */
    public function process_with_result_returns_empty_result_when_there_are_no_bookings(): void
    {
        Functions\when('myvh_setting')
            ->alias(function (string $key) {
                if ($key === 'invoicing.single_default_rule_id') return 12;
                return null;
            });
        $this->rule_repository->shouldReceive('get_active_rules')->andReturn([
            ['Id' => 12, 'IsActive' => 1, 'TriggerTiming' => 'confirmation', 'TriggerOffsetDays' => 0, 'GroupBy' => 'per_booking', 'DueDateOffsetDays' => 30],
        ]);
        $this->booking_service->shouldReceive('get_uninvoiced_single_bookings')->andReturn([]);

        $sut = $this->make_sut();
        $result = $sut->process_with_result();

        $this->assertSame(['created_invoice_ids' => []], $result);
    }

    /** @test */
    public function process_with_result_merges_and_deduplicates_delegated_recurring_bookings(): void
    {
        Functions\when('myvh_setting')->alias(function (string $key) {
            if ($key === 'invoicing.single_default_rule_id') return 12;
            return null;
        });
        $this->rule_repository->shouldReceive('get_active_rules')->andReturn([
            ['Id' => 12, 'IsActive' => 1, 'TriggerTiming' => 'confirmation', 'TriggerOffsetDays' => 0, 'GroupBy' => 'per_booking', 'DueDateOffsetDays' => 30],
        ]);
        $this->booking_service->shouldReceive('get_uninvoiced_single_bookings')->andReturn([
            ['Id' => 1, 'Status' => 'confirmed', 'StartDate' => '2026-06-01'],
        ]);

        $sut = $this->make_sut();
        $result = $sut->process_with_result([
            ['Id' => 1, 'Status' => 'confirmed', 'StartDate' => '2026-06-01'],
            ['Id' => 2, 'Status' => 'confirmed', 'StartDate' => '2026-06-01'],
        ]);

        $this->assertSame(['created_invoice_ids' => [12]], $result);
    }

    /** @test */
    public function process_uses_organisation_rule_before_customer_and_default(): void
    {
        Functions\when('myvh_setting')
            ->alias(function (string $key) {
                if ($key === 'invoicing.single_default_rule_id') return 9;
                return null;
            });
        $this->rule_repository->shouldReceive('get_active_rules')->andReturn([
            ['Id' => 9, 'IsActive' => 1, 'TriggerTiming' => 'confirmation', 'TriggerOffsetDays' => 0, 'GroupBy' => 'per_booking', 'DueDateOffsetDays' => 30],
            ['Id' => 10, 'IsActive' => 1, 'TriggerTiming' => 'confirmation', 'TriggerOffsetDays' => 0, 'GroupBy' => 'by_customer', 'DueDateOffsetDays' => 7],
            ['Id' => 11, 'IsActive' => 1, 'TriggerTiming' => 'confirmation', 'TriggerOffsetDays' => 0, 'GroupBy' => 'by_organisation', 'DueDateOffsetDays' => 14],
        ]);

        $this->booking_service->shouldReceive('get_uninvoiced_single_bookings')
            ->andReturn([[
                'Id' => 10,
                'Status' => 'confirmed',
                'StartDate' => '2026-06-01',
                'CustomerSingleBookingAutoInvoiceRuleId' => 10,
                'OrganisationSingleBookingAutoInvoiceRuleId' => 11,
            ]]);

        $sut = $this->make_sut();
        $result = $sut->process_with_result();

        $this->assertSame(['created_invoice_ids' => [11]], $result);
    }

    /** @test */
    public function process_with_result_respects_single_trigger_checks(): void
    {
        Functions\when('myvh_setting')
            ->alias(function (string $key) {
                if ($key === 'invoicing.single_default_rule_id') return 12;
                return null;
            });
        $this->rule_repository->shouldReceive('get_active_rules')->andReturn([
            ['Id' => 12, 'IsActive' => 1, 'TriggerTiming' => 'booking_date', 'TriggerOffsetDays' => 0, 'GroupBy' => 'per_booking', 'DueDateOffsetDays' => 30],
        ]);

        $this->booking_service->shouldReceive('get_uninvoiced_single_bookings')
            ->andReturn([['Id' => 10, 'Status' => 'confirmed', 'StartDate' => '2099-06-01']]);

        $sut = $this->make_sut();
        $result = $sut->process_with_result();

        $this->assertSame(['created_invoice_ids' => []], $result);
    }

    /** @test */
    public function create_single_invoices_for_rule_includes_all_non_invoiced_bookings(): void
    {
        $this->booking_service->shouldReceive('booking_has_invoices')->with(101)->andReturn(false);
        $this->booking_service->shouldReceive('booking_has_invoices')->with(102)->andReturn(true);
        $this->booking_service->shouldReceive('booking_has_invoices')->with(103)->andReturn(false);

        $this->invoice_generator->shouldReceive('generate_invoices_from_bookings')
            ->once()
            ->with([101, 103], [
                'group_by' => 'by_customer',
                'rule_scope' => 'single',
                'rule_id' => 77,
                'due_date_offset_days' => 30,
            ])
            ->andReturn([9001, 9002]);

        $sut = new SingleBookingAutoInvoicing(
            $this->invoice_generator,
            $this->booking_service,
            $this->rule_repository,
            $this->recurring_rule_repository
        );

        $method = new \ReflectionMethod(SingleBookingAutoInvoicing::class, 'create_single_invoices_for_rule');
        $method->setAccessible(true);
        /** @var array<int> $result */
        $result = $method->invoke(
            $sut,
            [
                ['Id' => 101],
                ['Id' => 102],
                ['Id' => 103],
            ],
            [
                'group_by' => 'by_customer',
                'id' => 77,
            ]
        );

        $this->assertSame([9001, 9002], $result);
    }
}
