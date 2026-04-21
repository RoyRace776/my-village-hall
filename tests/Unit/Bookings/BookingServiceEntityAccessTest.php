<?php

namespace MYVH\Tests\Unit\Bookings;

use MYVH\Bookings\Booking;
use MYVH\Bookings\BookingService;
use MYVH\Tests\Unit\UnitTestCase;
use Tests\Support\Factories\BookingFactory;

class BookingServiceEntityAccessTest extends UnitTestCase
{
    private BookingService $service;
    private $booking_repo;
    private $booking_query_service;

    protected function setUp(): void
    {
        parent::setUp();

        $room_service = $this->mock(\MYVH\Rooms\RoomService::class);
        $this->booking_repo = $this->mock(\MYVH\Bookings\BookingRepository::class);
        $booking_addon_repo = $this->mock(\MYVH\Bookings\BookingAddonRepository::class);
        $addon_service = $this->mock(\MYVH\Addons\AddonService::class);
        $validator = $this->mock(\MYVH\Bookings\BookingValidator::class);
        $booking_addon_sync_service = $this->mock(\MYVH\Bookings\Services\BookingAddonSyncService::class);
        $booking_charge_service = $this->mock(\MYVH\Bookings\Services\BookingChargeService::class);
        $booking_chargeable_hours_calculator = $this->mock(\MYVH\Bookings\Services\BookingChargeableHoursCalculator::class);
        $booking_creation_event_dispatcher = $this->mock(\MYVH\Bookings\Services\BookingCreationEventDispatcher::class);
        $booking_deletion_service = $this->mock(\MYVH\Bookings\Services\BookingDeletionService::class);
        $booking_lifecycle_event_dispatcher = $this->mock(\MYVH\Bookings\Services\BookingLifecycleEventDispatcher::class);
        $booking_access_control = $this->mock(\MYVH\Bookings\Services\BookingAccessControl::class);
        $booking_movement_service = $this->mock(\MYVH\Bookings\Services\BookingMovementService::class);
        $this->booking_query_service = $this->mock(\MYVH\Bookings\Services\BookingQueryService::class);
        $booking_status_transition_dispatcher = $this->mock(\MYVH\Bookings\Services\BookingStatusTransitionDispatcher::class);
        $booking_update_event_dispatcher = $this->mock(\MYVH\Bookings\Services\BookingUpdateEventDispatcher::class);
        $recurring_pattern_service = $this->mock(\MYVH\Bookings\RecurringPatternService::class);
        $recurring_booking_creator = $this->mock(\MYVH\Bookings\Services\RecurringBookingCreator::class);
        $recurring_booking_updater = $this->mock(\MYVH\Bookings\Services\RecurringBookingUpdater::class);

        $this->service = new BookingService(
            $room_service,
            $this->booking_repo,
            $booking_addon_repo,
            $addon_service,
            $validator,
            $booking_addon_sync_service,
            $booking_charge_service,
            $booking_chargeable_hours_calculator,
            $booking_creation_event_dispatcher,
            $booking_deletion_service,
            $booking_lifecycle_event_dispatcher,
            $booking_access_control,
            $booking_movement_service,
            $this->booking_query_service,
            $booking_status_transition_dispatcher,
            $booking_update_event_dispatcher,
            $recurring_pattern_service,
            $recurring_booking_creator,
            $recurring_booking_updater
        );
    }

    public function test_get_by_id_returns_booking_entity(): void
    {
        $booking = $this->createBooking();

        $this->booking_repo->shouldReceive('get')
            ->once()
            ->with(10)
            ->andReturn($booking);

        $result = $this->service->get_by_id(10);

        $this->assertSame($booking, $result);
    }

    public function test_get_by_id_returns_null_when_repository_throws_not_found(): void
    {
        $this->booking_repo->shouldReceive('get')
            ->once()
            ->with(999)
            ->andThrow(new \RuntimeException('not found'));

        $this->assertNull($this->service->get_by_id(999));
    }

    public function test_get_between_returns_booking_entities(): void
    {
        $booking = $this->createBooking();

        $this->booking_query_service->shouldReceive('get_between')
            ->once()
            ->with('2026-06-01', '2026-06-30', 'public', ['room_id' => 5])
            ->andReturn([$booking]);

        $result = $this->service->get_between('2026-06-01', '2026-06-30', 'public', ['room_id' => 5]);

        $this->assertCount(1, $result);
        $this->assertSame($booking, $result[0]);
    }

    private function createBooking(): Booking
    {
        return BookingFactory::make(['Id' => 10, 'RoomId' => 5]);
    }
}