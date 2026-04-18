<?php

namespace MYVH\Tests\Unit\Services;

use MYVH\Bookings\Booking;
use MYVH\Bookings\BookingStatus;
use MYVH\Tests\Unit\UnitTestCase;

class BookingQueryServiceTest extends UnitTestCase {
    private $booking_repo;
    private $customer_repo;
    private $grouping_service;
    private $service;

    protected function setUp(): void {
        parent::setUp();

        $this->booking_repo = $this->mock(\MYVH\Bookings\BookingRepository::class);
        $this->customer_repo = $this->mock(\MYVH\Customers\CustomerRepository::class);
        $this->grouping_service = new \MYVH\Bookings\Services\BookingListGroupingService();

        $this->service = new \MYVH\Bookings\Services\BookingQueryService(
            $this->booking_repo,
            $this->customer_repo,
            $this->grouping_service
        );
    }

    /** @test */
    public function get_by_id_with_details_returns_null_for_invalid_id(): void {
        $this->assertNull($this->service->get_by_id_with_details(0));
    }

    /** @test */
    public function get_by_id_with_details_returns_first_result_when_found(): void {
        $this->booking_repo->shouldReceive('get_all_with_details')
            ->with(['booking_id' => 10])
            ->once()
            ->andReturn([
                ['Id' => 10, 'Status' => BookingStatus::PENDING->value],
                ['Id' => 11, 'Status' => BookingStatus::CONFIRMED->value],
            ]);

        $result = $this->service->get_by_id_with_details(10);

        $this->assertSame(10, $result['Id']);
    }

    /** @test */
    public function get_by_id_with_details_for_customer_returns_null_when_customer_id_invalid(): void {
        $this->assertNull($this->service->get_by_id_with_details_for_customer(10, 0));
    }

    /** @test */
    public function get_between_delegates_to_repository(): void {
        $booking = Booking::fromDatabaseRow([
            'Id' => 1,
            'CustomerId' => 2,
            'RoomId' => 5,
            'OrganisationId' => 0,
            'Status' => BookingStatus::CONFIRMED,
            'Start' => new \DateTimeImmutable('2026-05-01 09:00:00'),
            'End' => new \DateTimeImmutable('2026-05-01 10:00:00'),
            'AdminEmail' => null,
        ]);

        $this->booking_repo->shouldReceive('get_between')
            ->with('2026-05-01', '2026-05-31', 'public', ['room_id' => 5])
            ->once()
            ->andReturn([$booking]);

        $result = $this->service->get_between('2026-05-01', '2026-05-31', 'public', ['room_id' => 5]);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(Booking::class, $result[0]);
        $this->assertSame(1, $result[0]->id());
    }

    /** @test */
    public function get_upcoming_bookings_resolves_customer_and_returns_repo_data(): void {
        $wp_user = (object) ['ID' => 88];

        $this->customer_repo->shouldReceive('get_customer_id')
            ->with($wp_user)
            ->once()
            ->andReturn(501);

        $this->booking_repo->shouldReceive('get_upcoming_bookings')
            ->with(501)
            ->once()
            ->andReturn([['Id' => 12]]);

        $result = $this->service->get_upcoming_bookings($wp_user);

        $this->assertCount(1, $result);
        $this->assertSame(12, $result[0]['Id']);
    }

    /** @test */
    public function get_booking_list_groups_recurring_and_standalone_bookings(): void {
        $this->booking_repo->shouldReceive('get_all_with_details')
            ->once()
            ->andReturn([
                [
                    'Id' => 11,
                    'RecurringPatternId' => 200,
                    'RecurrenceType' => 'weekly',
                    'RecurrenceInterval' => 1,
                    'RecurrenceDay' => 'monday',
                    'RecurrenceWeek' => '',
                    'PatternStartDate' => '2026-05-01',
                    'PatternEndDate' => '2026-07-01',
                    'PatternIsActive' => 1,
                    'StartDate' => '2026-05-05',
                    'Status' => BookingStatus::PENDING->value,
                ],
                [
                    'Id' => 12,
                    'RecurringPatternId' => 200,
                    'RecurrenceType' => 'weekly',
                    'RecurrenceInterval' => 1,
                    'RecurrenceDay' => 'monday',
                    'RecurrenceWeek' => '',
                    'PatternStartDate' => '2026-05-01',
                    'PatternEndDate' => '2026-07-01',
                    'PatternIsActive' => 1,
                    'StartDate' => '2026-05-12',
                    'Status' => BookingStatus::CONFIRMED->value,
                ],
                [
                    'Id' => 99,
                    'RecurringPatternId' => null,
                    'StartDate' => '2026-05-06',
                    'Status' => BookingStatus::PENDING->value,
                ],
            ]);

        $result = $this->service->get_booking_list([]);

        $this->assertSame(3, $result['total']);
        $this->assertSame(1, $result['recurring_groups']);
        $this->assertArrayHasKey('r_200', $result['groups']);
        $this->assertArrayHasKey('b_99', $result['groups']);
        $this->assertSame(BookingStatus::CONFIRMED->value, $result['groups']['r_200']['status']);
    }
}
