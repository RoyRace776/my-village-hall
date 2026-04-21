<?php
namespace Tests\Support\Seeders;
use Tests\Support\Factories\BookingFactory;

class BookingSeeder
{
    public static function seedDenseDay(int $roomId, string $date)
    {
        for ($hour = 8; $hour < 18; $hour++) {
            BookingFactory::create([
                'room_id' => $roomId,
                'start' => "$date $hour:00:00",
                'end'   => "$date " . ($hour + 1) . ":00:00",
            ]);
        }
    }
}