<?php

namespace MYVH\Tests\Unit\AutoInvoicing;

use Brain\Monkey\Functions;
use Mockery;
use MYVH\AutoInvoicing\RecurringBookingAutoInvoiceRuleRepository;
use MYVH\AutoInvoicing\RecurringBookingAutoInvoicing;
use MYVH\Bookings\BookingService;
use MYVH\Invoices\InvoiceGeneratorService;
use MYVH\Tests\Unit\UnitTestCase;

class RecurringBookingAutoInvoicingTest extends UnitTestCase
{
    /** @var InvoiceGeneratorService&\Mockery\MockInterface */
    private $invoice_generator;
    /** @var BookingService&\Mockery\MockInterface */
    private $booking_service;
    /** @var RecurringBookingAutoInvoiceRuleRepository&\Mockery\MockInterface */
    private $rule_repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->invoice_generator = Mockery::mock(InvoiceGeneratorService::class);
        $this->booking_service   = Mockery::mock(BookingService::class);
        $this->rule_repository   = Mockery::mock(RecurringBookingAutoInvoiceRuleRepository::class);
    }

    private function make_sut(): RecurringBookingAutoInvoicing
    {
        return new class(
            $this->invoice_generator,
            $this->booking_service,
            $this->rule_repository
        ) extends RecurringBookingAutoInvoicing {
            protected function create_recurring_invoices_for_rule(array $bookings, array $rule): array {
                $rule_id = \intval($rule['id'] ?? 0);

                return $rule_id > 0 ? [$rule_id] : [];
            }
        };
    }

    /** @test */
    public function process_with_result_only_considers_confirmed_recurring_bookings(): void
    {
        Functions\when('myvh_setting')->alias(function (string $key) {
            if ($key === 'invoicing.recurring_default_rule_id') return 12;
            return null;
        });

        $this->rule_repository->shouldReceive('get_active_rules')->andReturn([
            ['Id' => 12, 'IsActive' => 1, 'TriggerTiming' => 'start_of_month', 'TriggerOffsetDays' => 0, 'GroupBy' => 'per_booking', 'DueDateOffsetDays' => 30],
        ]);

        $this->booking_service->shouldReceive('get_uninvoiced_recurring_bookings')->andReturn([
            ['Id' => 1, 'Status' => 'confirmed', 'StartDate' => '2026-06-01'],
            ['Id' => 2, 'Status' => 'completed', 'StartDate' => '2026-06-01'],
        ]);

        $sut = $this->make_sut();
        $result = $sut->process_with_result();

        $this->assertSame([12], $result['created_invoice_ids']);
    }

    /** @test */
    public function process_with_result_uses_organisation_before_customer_before_default(): void
    {
        Functions\when('myvh_setting')->alias(function (string $key) {
            if ($key === 'invoicing.recurring_default_rule_id') return 9;
            return null;
        });

        $this->rule_repository->shouldReceive('get_active_rules')->andReturn([
            ['Id' => 9, 'IsActive' => 1, 'TriggerTiming' => 'start_of_month', 'TriggerOffsetDays' => 0, 'GroupBy' => 'per_booking', 'DueDateOffsetDays' => 30],
            ['Id' => 10, 'IsActive' => 1, 'TriggerTiming' => 'start_of_month', 'TriggerOffsetDays' => 0, 'GroupBy' => 'per_booking', 'DueDateOffsetDays' => 30],
            ['Id' => 11, 'IsActive' => 1, 'TriggerTiming' => 'start_of_month', 'TriggerOffsetDays' => 0, 'GroupBy' => 'per_booking', 'DueDateOffsetDays' => 30],
        ]);

        $this->booking_service->shouldReceive('get_uninvoiced_recurring_bookings')->andReturn([
            [
                'Id' => 100,
                'Status' => 'confirmed',
                'StartDate' => '2026-06-01',
                'OrganisationRecurringBookingAutoInvoiceRuleId' => 11,
                'CustomerRecurringBookingAutoInvoiceRuleId' => 10,
            ],
            [
                'Id' => 101,
                'Status' => 'confirmed',
                'StartDate' => '2026-06-01',
                'OrganisationRecurringBookingAutoInvoiceRuleId' => 0,
                'CustomerRecurringBookingAutoInvoiceRuleId' => 10,
            ],
            [
                'Id' => 102,
                'Status' => 'confirmed',
                'StartDate' => '2026-06-01',
                'OrganisationRecurringBookingAutoInvoiceRuleId' => 0,
                'CustomerRecurringBookingAutoInvoiceRuleId' => 0,
            ],
        ]);

        $sut = $this->make_sut();
        $result = $sut->process_with_result();

        sort($result['created_invoice_ids']);

        $this->assertSame([9, 10, 11], $result['created_invoice_ids']);
    }

    /** @test */
    public function process_with_result_collects_treat_as_single_bookings_from_all_partitions(): void
    {
        Functions\when('myvh_setting')->alias(function (string $key) {
            if ($key === 'invoicing.recurring_default_rule_id') return 9;
            return null;
        });

        $this->rule_repository->shouldReceive('get_active_rules')->andReturn([
            ['Id' => 9, 'IsActive' => 1, 'TriggerTiming' => 'treat_as_single_bookings', 'TriggerOffsetDays' => 0, 'GroupBy' => 'per_booking', 'DueDateOffsetDays' => 30],
            ['Id' => 10, 'IsActive' => 1, 'TriggerTiming' => 'treat_as_single_bookings', 'TriggerOffsetDays' => 0, 'GroupBy' => 'per_booking', 'DueDateOffsetDays' => 30],
            ['Id' => 11, 'IsActive' => 1, 'TriggerTiming' => 'treat_as_single_bookings', 'TriggerOffsetDays' => 0, 'GroupBy' => 'per_booking', 'DueDateOffsetDays' => 30],
        ]);

        $this->booking_service->shouldReceive('get_uninvoiced_recurring_bookings')->andReturn([
            [
                'Id' => 200,
                'Status' => 'confirmed',
                'StartDate' => '2026-06-01',
                'OrganisationRecurringBookingAutoInvoiceRuleId' => 11,
                'CustomerRecurringBookingAutoInvoiceRuleId' => 10,
            ],
            [
                'Id' => 201,
                'Status' => 'confirmed',
                'StartDate' => '2026-06-01',
                'OrganisationRecurringBookingAutoInvoiceRuleId' => 0,
                'CustomerRecurringBookingAutoInvoiceRuleId' => 10,
            ],
            [
                'Id' => 202,
                'Status' => 'confirmed',
                'StartDate' => '2026-06-01',
                'OrganisationRecurringBookingAutoInvoiceRuleId' => 0,
                'CustomerRecurringBookingAutoInvoiceRuleId' => 0,
            ],
        ]);

        $sut = $this->make_sut();
        $result = $sut->process_with_result();

        $this->assertSame([], $result['created_invoice_ids']);
        $this->assertCount(3, $result['treat_as_single_bookings']);
    }

    /** @test */
    public function booking_with_default_rule_id_is_processed_in_default_partition_not_override_partitions(): void
    {
        Functions\when('myvh_setting')->alias(function (string $key) {
            if ($key === 'invoicing.recurring_default_rule_id') return 9;
            return null;
        });

        $this->rule_repository->shouldReceive('get_active_rules')->andReturn([
            ['Id' => 9, 'IsActive' => 1, 'TriggerTiming' => 'start_of_month', 'TriggerOffsetDays' => 0, 'GroupBy' => 'per_booking', 'DueDateOffsetDays' => 30],
            ['Id' => 10, 'IsActive' => 1, 'TriggerTiming' => 'treat_as_single_bookings', 'TriggerOffsetDays' => 0, 'GroupBy' => 'per_booking', 'DueDateOffsetDays' => 30],
        ]);

        $this->booking_service->shouldReceive('get_uninvoiced_recurring_bookings')->andReturn([
            [
                'Id' => 300,
                'Status' => 'confirmed',
                'StartDate' => '2026-06-01',
                'OrganisationRecurringBookingAutoInvoiceRuleId' => 9,
                'CustomerRecurringBookingAutoInvoiceRuleId' => 9,
            ],
        ]);

        $sut = $this->make_sut();
        $result = $sut->process_with_result();

        $this->assertSame([9], $result['created_invoice_ids']);
        $this->assertSame([], $result['treat_as_single_bookings']);
    }

    /** @test */
    public function create_recurring_invoices_for_rule_passes_recurring_scope_and_filters_existing_invoices(): void
    {
        $this->booking_service->shouldReceive('booking_has_invoices')->with(401)->andReturn(false);
        $this->booking_service->shouldReceive('booking_has_invoices')->with(402)->andReturn(true);

        $this->invoice_generator->shouldReceive('generate_invoices_from_bookings')
            ->once()
            ->with([401], [
                'group_by' => 'by_customer',
                'rule_scope' => 'recurring',
                'rule_id' => 55,
                'due_date_offset_days' => 14,
            ])
            ->andReturn([7001]);

        $sut = new RecurringBookingAutoInvoicing(
            $this->invoice_generator,
            $this->booking_service,
            $this->rule_repository
        );

        $method = new \ReflectionMethod(RecurringBookingAutoInvoicing::class, 'create_recurring_invoices_for_rule');
        $method->setAccessible(true);
        /** @var array<int> $result */
        $result = $method->invoke(
            $sut,
            [
                ['Id' => 401],
                ['Id' => 402],
            ],
            [
                'group_by' => 'by_customer',
                'id' => 55,
                'due_date_offset_days' => 14,
            ]
        );

        $this->assertSame([7001], $result);
    }

    /** @test */
    public function create_recurring_invoices_for_rule_returns_empty_when_generator_fails(): void
    {
        $this->booking_service->shouldReceive('booking_has_invoices')->with(501)->andReturn(false);

        $this->invoice_generator->shouldReceive('generate_invoices_from_bookings')
            ->once()
            ->andReturn(new \WP_Error('failed', 'Generation failed'));

        $sut = new RecurringBookingAutoInvoicing(
            $this->invoice_generator,
            $this->booking_service,
            $this->rule_repository
        );

        $method = new \ReflectionMethod(RecurringBookingAutoInvoicing::class, 'create_recurring_invoices_for_rule');
        $method->setAccessible(true);
        /** @var array<int> $result */
        $result = $method->invoke(
            $sut,
            [
                ['Id' => 501],
            ],
            [
                'group_by' => 'per_booking',
                'id' => 12,
            ]
        );

        $this->assertSame([], $result);
    }
}
