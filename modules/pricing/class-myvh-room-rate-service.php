<?php
if (!defined('ABSPATH')) exit;

class MYVH_Room_Rate_Service {

    private $repo;
    private $customer_repo;

    public function __construct(MYVH_Room_Rate_Repository $repo,
                                MYVH_Customer_Repository $customer_repo) {
        $this->repo = $repo;
        $this->customer_repo = $customer_repo;
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

        if (empty($data['name'])) {
            return new WP_Error('validation', __('Rate name is required', 'my-village-hall'));
        }

        $charge_type = sanitize_text_field($data['charge_type'] ?? '');
        if (!in_array($charge_type, ['per_hour', 'per_day', 'fixed'])) {
            return new WP_Error('validation', __('Invalid charge type', 'my-village-hall'));
        }

        $rate = floatval($data['rate'] ?? 0);
        if ($rate < 0) {
            return new WP_Error('validation', __('Rate must be zero or greater', 'my-village-hall'));
        }

        $record = [
            'RoomId'             => intval($data['room_id']),
            'OrganisationTypeId' => !empty($data['organisation_type_id']) ? intval($data['organisation_type_id']) : null,
            'ChargeType'         => $charge_type,
            'Rate'               => $rate,
            'Name'               => sanitize_text_field($data['name']),
            'Description'        => sanitize_textarea_field($data['description'] ?? ''),
            'MinimumHours'       => !empty($data['minimum_hours']) ? floatval($data['minimum_hours']) : null,
            'IsActive'           => isset($data['is_active']) ? 1 : 0,
            'ValidFrom'          => !empty($data['valid_from']) ? sanitize_text_field($data['valid_from']) : null,
            'ValidTo'            => !empty($data['valid_to'])   ? sanitize_text_field($data['valid_to'])   : null,
            'Priority'           => intval($data['priority'] ?? 0),
        ];

        if (!empty($data['rate_id'])) {
            $result = $this->repo->update($record, ['Id' => intval($data['rate_id'])]);
            return is_wp_error($result) ? $result : intval($data['rate_id']);
        }

        $id = $this->repo->create($record);
        return $id ? $id : new WP_Error('database', __('Failed to save rate', 'my-village-hall'));
    }

    public function get_booking_rate( $room_id, $customer, $organisation ) {

        $org_type_id = $organisation['OrganisationTypeId'];

        // First try a rate specific to this room + organisation type
        $rate = $this->repo->get_active_room_rate( $room_id, $org_type_id );

        // Fall back to a rate with no organisation type restriction
        if ( !$rate ) {
            $rate = $this->repo->get_active_room_rate( $room_id );
        }

        return $rate;
    }

    public function delete($id) {
        return $this->repo->delete($id);
    }
}
