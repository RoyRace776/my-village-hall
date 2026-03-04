<?php
if (!defined('ABSPATH')) exit;

class MYVH_Room_Rate_Service {

    private $repo;

    public function __construct($repo) {
        $this->repo = $repo;
    }

    public function get_all() {
        return $this->repo->get_all();
    }

    public function get_by_room($room_id) {
        return $this->repo->get_by_room($room_id);
    }

    public function get($id) {
        return $this->repo->get_by_id($id);
    }

    public function save($data) {

        if (empty($data['room_id'])) {
            return new WP_Error('validation', __('Room is required', 'my-village-hall'));
        }

        if (empty($data['customer_group_id'])) {
            return new WP_Error('validation', __('Customer group is required', 'my-village-hall'));
        }

        if (!in_array($data['room-rate_type'], ['hourly', 'fixed'])) {
            return new WP_Error('validation', __('Invalid room rate type', 'my-village-hall'));
        }

        $amount = floatval($data['amount']);

        if ($amount <= 0) {
            return new WP_Error('validation', __('Amount must be greater than zero', 'my-village-hall'));
        }

        $record = [
            'RoomId'          => intval($data['room_id']),
            'CustomerGroupId' => intval($data['customer_group_id']),
            'RateType'        => sanitize_text_field($data['room_rate_type']),
            'Amount'          => $amount,
            'IsActive'        => isset($data['is_active']) ? 1 : 0,
        ];

        if (!empty($data['room_rate_id'])) {
            return $this->repo->update($record, ['Id' => intval($data['room_rate_id'])]);
        }

        return $this->repo->create($record);
    }

    public function delete($id) {
        return $this->repo->delete($id);
    }

    /**
     * Pricing calculation logic (used later in booking service)
     */
    public function calculate_price($room_id, $group_id, $start_time, $end_time) {

        $room_rates = $this->repo->get_active_room_rate($room_id, $group_id);

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