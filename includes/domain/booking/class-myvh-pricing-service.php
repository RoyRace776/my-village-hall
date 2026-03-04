<?php

Class MYVH_Pricing_Service {

    // We need the room rate repo to make this work
    private $room_rate_repo;

    public function __construct($repo) {
        $this->room_rate_repo = $repo;
    }

    public function calculate_price($room_id, $group_id, $start_time, $end_time) {

        $room_rates = $this->room_rate_repo->get_active_room_rate($room_id, $group_id);

        if (!$room_rates) {
            return new WP_Error('no_room rate', __('No room rate configured', 'my-village-hall'));
        }

        if ($room_rates['RateType'] === 'fixed') {
            return floatval($room_rates['Amount']);
        }

        // hourly calculation
        $start = strtotime($start_time);
        $end   = strtotime($end_time);

        $hours = ($end - $start) / 3600;

        return round($hours * floatval($room_rates['Amount']), 2);
    }
}