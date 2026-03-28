<?php

class CalendarEventTransformer {

    public static function for_portal($bookings): array {

        return array_map(function($b) {
            return [
                'id'    => $b['Id'],
                'title' => $b['RoomName'],
                'start' => $b['StartTime'],
                'end'   => $b['EndTime'],
                'meta'  => [
                    'customer' => $b['CustomerName']
                ]
            ];
        }, $bookings);
    }

    public static function for_public($bookings): array {

        return array_map(function($b) {
            return [
                'start' => $b['StartTime'],
                'end'   => $b['EndTime'],
                'title' => 'Booked'
            ];
        }, $bookings);
    }

    public static function for_admin($bookings): array {

        return array_map(function($b) {
            return [
                'id'    => $b['Id'],
                'title' => $b['RoomName'] . ' - ' . $b['CustomerName'],
                'start' => $b['StartTime'],
                'end'   => $b['EndTime'],
                'meta'  => [
                    'customer' => $b['CustomerName'],
                    'email'    => $b['Email'],
                    'status'   => $b['Status'],
                    'price'    => $b['TotalPrice'],
                    'notes'    => $b['Notes'],
                ]
            ];
        }, $bookings);
    }

}
