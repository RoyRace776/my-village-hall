<?php
namespace Tests\Support\Seeders;
use Tests\Support\Factories\BookingFactory;
use Tests\Support\Factories\CustomerFactory;
use Tests\Support\Factories\RoomFactory;

class BookingScenarios
{
    public static function fullyBookedDay(): array
    {
        $room = RoomFactory::create();

        BookingSeeder::seedDenseDay($room['Id'], '2026-01-01');

        return [$room];
    }

    public static function overlappingBookings(): array
    {
        $room = RoomFactory::create();

        BookingFactory::create([
            'room_id' => $room['Id'],
            'start' => '2026-01-01 10:00:00',
            'end'   => '2026-01-01 12:00:00',
        ]);

        BookingFactory::create([
            'room_id' => $room['Id'],
            'start' => '2026-01-01 11:00:00',
            'end'   => '2026-01-01 13:00:00',
        ]);

        return [$room];
    }
}