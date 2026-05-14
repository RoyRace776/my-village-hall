<?php
namespace Tests\Support\Seeders;

use MYVH\Availability\AvailabilityService;
use Tests\Support\Factories\BookingFactory;

class BookingSeeder
{
    public static function seedDenseDay(int $roomId, string $date)
    {
        $hours = app(AvailabilityService::class)->get_effective_room_hours_for_date($roomId, $date);

        if (!empty($hours['is_closed']) || empty($hours['opening_time']) || empty($hours['closing_time'])) {
            return;
        }

        $openHour  = (int) substr($hours['opening_time'], 0, 2);
        $closeHour = (int) substr($hours['closing_time'], 0, 2);

        for ($hour = $openHour; $hour < $closeHour; $hour++) {
            try {
                BookingFactory::create([
                    'room_id' => $roomId,
                    'start'   => "$date $hour:00:00",
                    'end'     => "$date " . ($hour + 1) . ":00:00",
                ]);
            } catch (\RuntimeException $e) {
                // Slot already taken — continue seeding the remaining hours.
            }
        }
    }
}