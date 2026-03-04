<?php
if (!defined('ABSPATH')) exit;

class MYVH_Addon_Service {

    private $repo;

    public function __construct($repo) {
        $this->repo = $repo;
    }

    public function get_all($args = []) {
        return $this->repo->get_all($args);
    }

    public function get($id) {
        return $this->repo->get_by_id($id);
    }

    public function get_with_relations() {
        return $this->repo->get_all_with_relations();
    }

    public function get_by_room($room_id) {
        return $this->repo->get_by_room($room_id);
    }

    public function get_by_venue($venue_id) {
        return $this->repo->get_by_venue($venue_id);
    }

    public function save($data) {

        if (empty($data['name'])) {
            return new WP_Error('validation', __('Add-on name is required', 'my-village-hall'));
        }

        if (empty($data['price']) || floatval($data['price']) < 0) {
            return new WP_Error('validation', __('Valid price is required', 'my-village-hall'));
        }

        if (empty($data['charge_type'])) {
            return new WP_Error('validation', __('Charge type is required', 'my-village-hall'));
        }

        $record = [
            'Name'              => sanitize_text_field($data['name']),
            'Description'       => sanitize_textarea_field($data['description'] ?? ''),
            'Price'             => floatval($data['price']),
            'ChargeType'        => sanitize_text_field($data['charge_type']),
            'CustomerGroupId'   => !empty($data['customer_group_id']) ? intval($data['customer_group_id']) : null,
            'RoomId'            => !empty($data['room_id']) ? intval($data['room_id']) : null,
            'VenueId'           => !empty($data['venue_id']) ? intval($data['venue_id']) : null,
            'IsActive'          => isset($data['is_active']) ? 1 : 0,
            'DisplayOrder'      => intval($data['display_order'] ?? 0),
        ];

        if (!empty($data['addon_id'])) {
            $result = $this->repo->update($record, ['Id' => intval($data['addon_id'])]);
            if ($result === false) {
                return new WP_Error('database', __('Failed to update add-on', 'my-village-hall'));
            }
            return intval($data['addon_id']);
        }

        $result = $this->repo->create($record);
        if ($result === false) {
            return new WP_Error('database', __('Failed to create add-on', 'my-village-hall'));
        }
        
        return $result;
    }

    public function delete($id) {
        return $this->repo->delete($id);
    }
}
