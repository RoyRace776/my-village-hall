<?php

Class MYVH_Pricing_Service {

    private $room_rate_service;
    private $booking_repo;
    private $customer_repo;
    private $booking_addon_repo;

    public function __construct(MYVH_Room_Rate_Service $room_rate_service,
                                MYVH_Booking_Repository $booking_repo,
                                MYVH_Customer_Repository $customer_repo,
                                MYVH_Booking_Addon_Repository $booking_addons_repo) {
        $this->room_rate_service = $room_rate_service;
        $this->booking_repo = $booking_repo;
        $this->customer_repo = $customer_repo;
        $this->booking_addon_repo = $booking_addons_repo;
    }

    public function calculate_price($booking_id) {

        $room_price = 0.0;

        $booking = $this->booking_repo->get_by_id($booking_id);
        if (!$booking) {
            return new WP_Error('No booking', __('No booking found', 'my-village-hall'));
        }

        $customer = $this->customer_repo->get_by_id($booking['CustomerId']);
        if (!$customer) {
            return new WP_Error('no customer', __('No customer found for booking', 'my-village-hall'));
        }

        $room_rate = $this->room_rate_service->get_booking_rate($booking['RoomId'], $booking['CustomerId']);
        if (!$room_rate) {
            return new WP_Error('no room rate', __('No room rate configured', 'my-village-hall'));
        }

        if ($room_rate['RateType'] === 'fixed') {
            $room_price =  floatval($room_rate['Rate']);
        } else {
            // hourly calculation
            $start = strtotime($booking['StartDate'] . ' ' . $booking['StartTime']);
            $end = strtotime($booking['EndDate'] . ' ' . $booking['EndTime']);

            $interval = $end - $start;

            $hours = $interval/60/60;
            $room_price = round($hours * floatval($room_rate['Rate']), 2);
        }

        return $room_price + $this->calculate_addon_price($booking_id);
    }

    private function calculate_addon_price($booking_id) {

        // Get the addons. This might be an empty array if there aren't any
        $booking_addons = $this->booking_addon_repo->get_by_booking_id($booking_id);

        $addon_price = 0.0;

        foreach ($booking_addons as $booking_addon) {
            // The addon price is held on the booking addons table
            $addon_price += $booking_addon['TotalAmount'];
        }

        return $addon_price;
    }
}