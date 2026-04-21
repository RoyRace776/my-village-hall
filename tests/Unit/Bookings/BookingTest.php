<?php

namespace MYVH\Tests\Unit\Bookings;

use MYVH\Bookings\Booking;
use MYVH\Bookings\BookingStatus;
use MYVH\Tests\Unit\UnitTestCase;
use Tests\Support\Factories\BookingFactory;

class BookingTest extends UnitTestCase
{
    public function test_from_database_row_rejects_end_before_or_equal_to_start(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Booking::fromDatabaseRow([
            'Id' => 1,
            'CustomerId' => 2,
            'RoomId' => 3,
            'OrganisationId' => 4,
            'Status' => BookingStatus::PENDING,
            'Start' => new \DateTimeImmutable('2026-06-01 10:00:00'),
            'End' => new \DateTimeImmutable('2026-06-01 10:00:00'),
            'AdminEmail' => null,
        ]);
    }

    public function test_status_returns_booking_status_enum(): void
    {
        $booking = $this->createBooking();

        $this->assertSame(BookingStatus::PENDING, $booking->status());
    }

    public function test_confirm_returns_new_booking_and_keeps_original_unchanged(): void
    {
        $booking = $this->createBooking();
        $confirmed = $booking->confirm();

        $this->assertNotSame($booking, $confirmed);
        $this->assertSame(BookingStatus::PENDING, $booking->status());
        $this->assertSame(BookingStatus::CONFIRMED, $confirmed->status());
    }

    public function test_cancel_returns_new_booking_and_keeps_original_unchanged(): void
    {
        $booking = $this->createBooking();
        $cancelled = $booking->cancel();

        $this->assertNotSame($booking, $cancelled);
        $this->assertSame(BookingStatus::PENDING, $booking->status());
        $this->assertSame(BookingStatus::CANCELLED, $cancelled->status());
    }

    private function createBooking(): Booking
    {
        return BookingFactory::make(['Status' => BookingStatus::PENDING]);
    }
}