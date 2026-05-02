<?php
namespace Tests\Support\Factories;
use MYVH\Bookings\BookingStatus;
use MYVH\Bookings\Booking;
use MYVH\Bookings\BookingService;
use RuntimeException;
use WP_Error;

class BookingFactory
{
    public static function make(array $overrides = []): Booking
    {
        $faker       = \Faker\Factory::create();
        $start       = (new \DateTimeImmutable())->modify('+' . $faker->numberBetween(1, 30) . ' days')->setTime(10, 0, 0);
        $end         = $start->modify('+2 hours');

        $row = array_merge([
            'Id' => 10,
            'RoomId' => 5,
            'CustomerId' => 3,
            'OrganisationId' => 0,
            'Status' => BookingStatus::CONFIRMED->value,
            'Start' => $start->format('Y-m-d H:i:s'),
            'End' => $end->format('Y-m-d H:i:s'),
            'AdminEmail' => null,
            'Description' => $faker->sentence(3),
            'Public' => true,
            'RoomName' => '',
            'VenueName' => '',
            'IsPublic' => false,
        ], $overrides);

        if (($row['Status'] ?? null) instanceof BookingStatus) {
            $row['Status'] = $row['Status']->value;
        }

        return Booking::fromDatabaseRow($row);
    }

    public static function fromLegacyArray(array $data): Booking
    {
        $normalized = $data;

        if (!array_key_exists('Start', $normalized)) {
            $normalized['Start'] = self::combineLegacyDateTime(
                $data,
                'StartDate',
                'StartTime',
                '2026-06-01',
                '10:00:00'
            );
        }

        if (!array_key_exists('End', $normalized)) {
            $normalized['End'] = self::combineLegacyDateTime(
                $data,
                'EndDate',
                'EndTime',
                '2026-06-01',
                '12:00:00'
            );
        }

        unset(
            $normalized['StartDate'],
            $normalized['StartTime'],
            $normalized['EndDate'],
            $normalized['EndTime']
        );

        return self::make($normalized);
    }

    public static function create(array $overrides = []): Booking
    {
        $customer = isset($overrides['customer_id'])
            ? null
            : CustomerFactory::create();

        $room = isset($overrides['room_id'])
            ? null
            : RoomFactory::create();

        $start_dt = new \DateTimeImmutable('next Monday +' . rand(1, 30) . ' days');

        $data = array_merge([
            'customer_id' => $overrides['customer_id'] ?? $customer['Id'],
            'room_id' => $overrides['room_id'] ?? $room['Id'],
            'start_date' => $start_dt->format('Y-m-d'),
            'end_date' => $start_dt->format('Y-m-d'),
            'start_time' => '10:00:00',
            'end_time' => '12:00:00',
            'status' => BookingStatus::CONFIRMED->value,
        ], $overrides);

        if (!empty($overrides['start']) && (empty($overrides['start_date']) || empty($overrides['start_time']))) {
            $start = new \DateTimeImmutable((string) $overrides['start']);
            if (empty($overrides['start_date'])) {
                $data['start_date'] = $start->format('Y-m-d');
            }
            if (empty($overrides['start_time'])) {
                $data['start_time'] = $start->format('H:i:s');
            }
        }

        if (!empty($overrides['end']) && (empty($overrides['end_date']) || empty($overrides['end_time']))) {
            $end = new \DateTimeImmutable((string) $overrides['end']);
            if (empty($overrides['end_date'])) {
                $data['end_date'] = $end->format('Y-m-d');
            }
            if (empty($overrides['end_time'])) {
                $data['end_time'] = $end->format('H:i:s');
            }
        }

        $service = app(BookingService::class);
        $booking_id = $service->save($data);

        if ($booking_id instanceof WP_Error) {
            throw new RuntimeException('BookingFactory create failed: ' . $booking_id->get_error_message());
        }

        $booking = $service->get_by_id($booking_id);
        if (!$booking) {
            throw new RuntimeException('BookingFactory create failed: booking could not be loaded.');
        }

        return $booking;
    }

    private static function combineLegacyDateTime(
        array $data,
        string $date_key,
        string $time_key,
        string $default_date,
        string $default_time
    ): string {
        $date = (string) ($data[$date_key] ?? $default_date);
        $time = (string) ($data[$time_key] ?? $default_time);

        return $date . ' ' . $time;
    }
}