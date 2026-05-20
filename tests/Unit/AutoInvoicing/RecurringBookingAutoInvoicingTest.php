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
              ['Id' => 1, 'Status' => 'confirmed', 'StartDate' => '2026-05-01', 'RecurringPatternId' => 901, 'OrganisationId' => 0, 'OrganisationInvoiceOrganisationBookings' => 0],
              ['Id' => 2, 'Status' => 'completed', 'StartDate' => '2026-05-01', 'RecurringPatternId' => 901, 'OrganisationId' => 0, 'OrganisationInvoiceOrganisationBookings' => 0],
        ]);

        $sut = $this->make_sut();
        $result = $sut->process_with_result();

        $this->assertSame([12], $result['created_invoice_ids']);
    }

    /** @test */
    public function process_with_result_batches_multiple_patterns_under_same_rule_for_grouping(): void
    {
        Functions\when('myvh_setting')->alias(function (string $key) {
            if ($key === 'invoicing.recurring_default_rule_id') return 12;
            return null;
        });

        $this->rule_repository->shouldReceive('get_active_rules')->andReturn([
            ['Id' => 12, 'IsActive' => 1, 'TriggerTiming' => 'start_of_month', 'TriggerOffsetDays' => 0, 'GroupBy' => 'per_booking', 'DueDateOffsetDays' => 30],
        ]);

        $this->booking_service->shouldReceive('get_uninvoiced_recurring_bookings')->andReturn([
            ['Id' => 11, 'Status' => 'confirmed', 'StartDate' => '2026-05-01', 'RecurringPatternId' => 901, 'OrganisationId' => 0, 'OrganisationInvoiceOrganisationBookings' => 0],
            ['Id' => 22, 'Status' => 'confirmed', 'StartDate' => '2026-05-02', 'RecurringPatternId' => 902, 'OrganisationId' => 0, 'OrganisationInvoiceOrganisationBookings' => 0],
        ]);

        $sut = new class(
            $this->invoice_generator,
            $this->booking_service,
            $this->rule_repository
        ) extends RecurringBookingAutoInvoicing {
            public int $create_calls = 0;
            /** @var array<int, int> */
            public array $captured_booking_ids = [];

            protected function create_recurring_invoices_for_rule(array $bookings, array $rule): array {
                $this->create_calls++;
                $this->captured_booking_ids = array_values(array_unique(array_merge(
                    $this->captured_booking_ids,
                    array_values(array_map(static fn(array $booking): int => \intval($booking['Id'] ?? 0), $bookings))
                )));

                return [1200 + $this->create_calls];
            }
        };

        $result = $sut->process_with_result();

        sort($sut->captured_booking_ids);
            $this->assertSame(2, $sut->create_calls);
            $this->assertSame([11, 22], $sut->captured_booking_ids);
            $this->assertSame([1201, 1202], $result['created_invoice_ids']);
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
                'StartDate' => '2026-05-01',
                'RecurringPatternId' => 910,
                    'OrganisationId' => 5,
                    'OrganisationInvoiceOrganisationBookings' => 1,
                'OrganisationRecurringBookingAutoInvoiceRuleId' => 11,
                'CustomerRecurringBookingAutoInvoiceRuleId' => 10,
            ],
            [
                'Id' => 101,
                'Status' => 'confirmed',
                'StartDate' => '2026-05-01',
                'RecurringPatternId' => 911,
                'OrganisationId' => 0,
                    'OrganisationInvoiceOrganisationBookings' => 0,
                'OrganisationRecurringBookingAutoInvoiceRuleId' => 0,
                'CustomerRecurringBookingAutoInvoiceRuleId' => 10,
            ],
            [
                'Id' => 102,
                'Status' => 'confirmed',
                'StartDate' => '2026-05-03',
                'RecurringPatternId' => 912,
                'OrganisationId' => 0,
                    'OrganisationInvoiceOrganisationBookings' => 0,
                'OrganisationRecurringBookingAutoInvoiceRuleId' => 0,
                'CustomerRecurringBookingAutoInvoiceRuleId' => 0,
            ],
        ]);

        $sut = $this->make_sut();
        $result = $sut->process_with_result();

        sort($result['created_invoice_ids']);

        $this->assertSame([10, 11], $result['created_invoice_ids']);
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
                'RecurringPatternId' => 920,
                'OrganisationRecurringBookingAutoInvoiceRuleId' => 11,
                'CustomerRecurringBookingAutoInvoiceRuleId' => 10,
            ],
            [
                'Id' => 201,
                'Status' => 'confirmed',
                'StartDate' => '2026-06-01',
                'RecurringPatternId' => 921,
                'OrganisationRecurringBookingAutoInvoiceRuleId' => 0,
                'CustomerRecurringBookingAutoInvoiceRuleId' => 10,
            ],
            [
                'Id' => 202,
                'Status' => 'confirmed',
                'StartDate' => '2026-06-01',
                'RecurringPatternId' => 922,
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
                'StartDate' => '2026-05-01',
                'RecurringPatternId' => 930,
                'OrganisationId' => 0,
                'OrganisationInvoiceOrganisationBookings' => 0,
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
            ->andReturnUsing(function (): array {
                return [7001];
            });

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
            ->with([501], [
                'group_by' => 'by_customer',
                'rule_scope' => 'recurring',
                'rule_id' => 12,
                'due_date_offset_days' => 30,
            ])
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

    /** @test */
    public function create_recurring_invoices_for_rule_uses_by_organisation_when_organisation_billing_is_enabled(): void
    {
        $this->booking_service->shouldReceive('booking_has_invoices')->with(601)->andReturn(false);

        $this->invoice_generator->shouldReceive('generate_invoices_from_bookings')
            ->once()
            ->with([601], [
                'group_by' => 'by_organisation',
                'rule_scope' => 'recurring',
                'rule_id' => 88,
                'due_date_offset_days' => 21,
            ])
            ->andReturnUsing(function (): array {
                return [9001];
            });

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
                [
                    'Id' => 601,
                    'OrganisationId' => 77,
                    'OrganisationInvoiceOrganisationBookings' => 1,
                ],
            ],
            [
                'id' => 88,
                'due_date_offset_days' => 21,
            ]
        );

        $this->assertSame([9001], $result);
    }

    // ── Period Range Calculation Tests ─────────────────────────────────────

    /** @test */
    public function calculate_invoicing_period_range_returns_current_month_for_start_of_month_zero_offset(): void
    {
        // May 18, 2026 should return May 1 - May 31
        $sut = new RecurringBookingAutoInvoicing(
            $this->invoice_generator,
            $this->booking_service,
            $this->rule_repository
        );

        $rule = [
            'trigger_timing' => 'start_of_month',
            'trigger_direction' => 'in_advance',
            'trigger_period_count' => 0,
        ];

        $method = new \ReflectionMethod(RecurringBookingAutoInvoicing::class, 'calculate_invoicing_period_range');
        $method->setAccessible(true);
        $result = $method->invoke($sut, $rule);

        $this->assertSame('2026-05-01', $result['start_date']);
        $this->assertSame('2026-05-31', $result['end_date']);
    }

    /** @test */
    public function calculate_invoicing_period_range_returns_current_month_for_in_advance_one_month(): void
    {
        // May 18, 2026 with 1 month in advance should return current month
        $sut = new RecurringBookingAutoInvoicing(
            $this->invoice_generator,
            $this->booking_service,
            $this->rule_repository
        );

        $rule = [
            'trigger_timing' => 'start_of_month',
            'trigger_direction' => 'in_advance',
            'trigger_period_count' => 1,
        ];

        $method = new \ReflectionMethod(RecurringBookingAutoInvoicing::class, 'calculate_invoicing_period_range');
        $method->setAccessible(true);
        $result = $method->invoke($sut, $rule);

        $this->assertSame('2026-05-01', $result['start_date']);
        $this->assertSame('2026-05-31', $result['end_date']);
    }

    /** @test */
    public function calculate_invoicing_period_range_returns_next_month_for_in_arrears_one_month(): void
    {
        // May 18, 2026 with 1 month in arrears should return June 1 - June 30
        $sut = new RecurringBookingAutoInvoicing(
            $this->invoice_generator,
            $this->booking_service,
            $this->rule_repository
        );

        $rule = [
            'trigger_timing' => 'start_of_month',
            'trigger_direction' => 'in_arrears',
            'trigger_period_count' => 1,
        ];

        $method = new \ReflectionMethod(RecurringBookingAutoInvoicing::class, 'calculate_invoicing_period_range');
        $method->setAccessible(true);
        $result = $method->invoke($sut, $rule);

        $this->assertSame('2026-06-01', $result['start_date']);
        $this->assertSame('2026-06-30', $result['end_date']);
    }

    /** @test */
    public function calculate_invoicing_period_range_returns_current_week_for_start_of_week_zero_offset(): void
    {
        // May 18, 2026 (Sunday) should return Monday May 18 - Sunday May 24
        $sut = new RecurringBookingAutoInvoicing(
            $this->invoice_generator,
            $this->booking_service,
            $this->rule_repository
        );

        $rule = [
            'trigger_timing' => 'start_of_week',
            'trigger_direction' => 'in_advance',
            'trigger_period_count' => 0,
        ];

        $method = new \ReflectionMethod(RecurringBookingAutoInvoicing::class, 'calculate_invoicing_period_range');
        $method->setAccessible(true);
        $result = $method->invoke($sut, $rule);

        // May 18, 2026 is a Sunday, so Monday this week is May 18
        $this->assertSame('2026-05-18', $result['start_date']);
        $this->assertSame('2026-05-24', $result['end_date']);
    }

    /** @test */
    public function calculate_invoicing_period_range_returns_current_week_for_in_advance_one_week(): void
    {
        // May 18, 2026 with 1 week in advance should return current week
        $sut = new RecurringBookingAutoInvoicing(
            $this->invoice_generator,
            $this->booking_service,
            $this->rule_repository
        );

        $rule = [
            'trigger_timing' => 'start_of_week',
            'trigger_direction' => 'in_advance',
            'trigger_period_count' => 1,
        ];

        $method = new \ReflectionMethod(RecurringBookingAutoInvoicing::class, 'calculate_invoicing_period_range');
        $method->setAccessible(true);
        $result = $method->invoke($sut, $rule);

        $this->assertSame('2026-05-18', $result['start_date']);
        $this->assertSame('2026-05-24', $result['end_date']);
    }

    /** @test */
    public function calculate_invoicing_period_range_returns_next_week_for_in_arrears_one_week(): void
    {
        // May 18, 2026 with 1 week in arrears should return next week
        $sut = new RecurringBookingAutoInvoicing(
            $this->invoice_generator,
            $this->booking_service,
            $this->rule_repository
        );

        $rule = [
            'trigger_timing' => 'start_of_week',
            'trigger_direction' => 'in_arrears',
            'trigger_period_count' => 1,
        ];

        $method = new \ReflectionMethod(RecurringBookingAutoInvoicing::class, 'calculate_invoicing_period_range');
        $method->setAccessible(true);
        $result = $method->invoke($sut, $rule);

        $this->assertSame('2026-05-25', $result['start_date']);
        $this->assertSame('2026-05-31', $result['end_date']);
    }

    /** @test */
    public function calculate_invoicing_period_range_returns_current_quarter_for_start_of_quarter_zero_offset(): void
    {
        // May 18, 2026 is in Q2 (April-June), should return April 1 - June 30
        $sut = new RecurringBookingAutoInvoicing(
            $this->invoice_generator,
            $this->booking_service,
            $this->rule_repository
        );

        $rule = [
            'trigger_timing' => 'start_of_quarter',
            'trigger_direction' => 'in_advance',
            'trigger_period_count' => 0,
        ];

        $method = new \ReflectionMethod(RecurringBookingAutoInvoicing::class, 'calculate_invoicing_period_range');
        $method->setAccessible(true);
        $result = $method->invoke($sut, $rule);

        $this->assertSame('2026-04-01', $result['start_date']);
        $this->assertSame('2026-06-30', $result['end_date']);
    }

    /** @test */
    public function calculate_invoicing_period_range_returns_current_quarter_for_in_advance_one_quarter(): void
    {
        // May 18, 2026 (Q2) with 1 quarter in advance should return current quarter
        $sut = new RecurringBookingAutoInvoicing(
            $this->invoice_generator,
            $this->booking_service,
            $this->rule_repository
        );

        $rule = [
            'trigger_timing' => 'start_of_quarter',
            'trigger_direction' => 'in_advance',
            'trigger_period_count' => 1,
        ];

        $method = new \ReflectionMethod(RecurringBookingAutoInvoicing::class, 'calculate_invoicing_period_range');
        $method->setAccessible(true);
        $result = $method->invoke($sut, $rule);

        $this->assertSame('2026-04-01', $result['start_date']);
        $this->assertSame('2026-06-30', $result['end_date']);
    }

    /** @test */
    public function calculate_invoicing_period_range_returns_next_quarter_for_in_arrears_one_quarter(): void
    {
        // May 18, 2026 (Q2) with 1 quarter in arrears should return Q3 (Jul-Sep)
        $sut = new RecurringBookingAutoInvoicing(
            $this->invoice_generator,
            $this->booking_service,
            $this->rule_repository
        );

        $rule = [
            'trigger_timing' => 'start_of_quarter',
            'trigger_direction' => 'in_arrears',
            'trigger_period_count' => 1,
        ];

        $method = new \ReflectionMethod(RecurringBookingAutoInvoicing::class, 'calculate_invoicing_period_range');
        $method->setAccessible(true);
        $result = $method->invoke($sut, $rule);

        $this->assertSame('2026-07-01', $result['start_date']);
        $this->assertSame('2026-09-30', $result['end_date']);
    }

    /** @test */
    public function calculate_invoicing_period_range_returns_previous_quarter_for_in_advance_two_quarters(): void
    {
        // May 18, 2026 (Q2) with 2 quarters in advance should return previous quarter
        $sut = new RecurringBookingAutoInvoicing(
            $this->invoice_generator,
            $this->booking_service,
            $this->rule_repository
        );

        $rule = [
            'trigger_timing' => 'start_of_quarter',
            'trigger_direction' => 'in_advance',
            'trigger_period_count' => 2,
        ];

        $method = new \ReflectionMethod(RecurringBookingAutoInvoicing::class, 'calculate_invoicing_period_range');
        $method->setAccessible(true);
        $result = $method->invoke($sut, $rule);

        $this->assertSame('2026-01-01', $result['start_date']);
        $this->assertSame('2026-03-31', $result['end_date']);
    }
}
