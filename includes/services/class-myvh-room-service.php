<?php
if (!defined('ABSPATH')) exit;

class MYVH_Room_Service {

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

    public function get_all_with_venues() {
        return $this->repo->get_all_with_venues();
    }

    public function save($data) {

        if (empty($data['name'])) {
            return new WP_Error('validation', __('Room name is required', 'my-village-hall'));
        }

        if (empty($data['venue_id'])) {
            return new WP_Error('validation', __('Venue is required', 'my-village-hall'));
        }

        $record = [
            'Name'         => sanitize_text_field($data['name']),
            'VenueId'      => intval($data['venue_id']),
            'Capacity'     => intval($data['capacity']),
            'Description'  => sanitize_textarea_field($data['description']),
            'OpeningTime'  => sanitize_text_field($data['opening_time']),
            'ClosingTime'  => sanitize_text_field($data['closing_time']),
        ];

        if (!empty($data['room_id'])) {
            return $this->repo->update($record, ['Id' => intval($data['room_id'])]);
        }

        return $this->repo->create($record);
    }

    public function delete($id) {
        return $this->repo->delete($id);
    }
}