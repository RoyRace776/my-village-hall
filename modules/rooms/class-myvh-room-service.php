<?php
if (!defined('ABSPATH')) exit;

class Room_Service {

    private $repo;
    private $availability;

    public function __construct(
        Room_Repository $repo,
        Availability_Service $availability
    ) {
        $this->repo = $repo;
        $this->availability = $availability;
    }

    public function get_all($args = []): array {
        return $this->repo->get_all($args);
    }

    public function get($id): ?array {
        return $this->repo->get_by_id($id);
    }

    public function get_all_with_venues(): array {
        return $this->repo->get_all_with_venues();
    }

    public function save($data): int|WP_Error {

        if (empty($data['name'])) {
            return new WP_Error('validation', __('Room name is required', 'my-village-hall'));
        }

        if (empty($data['venue_id'])) {
            return new WP_Error('validation', __('Venue is required', 'my-village-hall'));
        }

        $venue_id = intval($data['venue_id']);
        $opening_time = sanitize_text_field($data['opening_time']);
        $closing_time = sanitize_text_field($data['closing_time']);

        $hours_allowed = $this->availability->room_opening_hours_allowed(
            $opening_time,
            $closing_time,
            $venue_id
        );

        if (is_wp_error($hours_allowed)) {
            return $hours_allowed;
        }

        if (!$hours_allowed) {
            return new WP_Error(
                'validation',
                __('Room opening/closing hours must be within the venue opening hours', 'my-village-hall')
            );
        }

        $record = [
            'Name'         => sanitize_text_field($data['name']),
            'VenueId'      => $venue_id,
            'Capacity'     => intval($data['capacity']),
            'Description'  => sanitize_textarea_field($data['description']),
            'OpeningTime'  => $opening_time,
            'ClosingTime'  => $closing_time,
            'AllowMultiDayBookings' => isset($data['allow-multi-day-bookings']) ? 1 : 0,
            'CalcClosedHours' => isset($data['calc-closed-hours']) ? 1 : 0
        ];

        if (!empty($data['room_id'])) {
            return $this->repo->update($record, ['Id' => intval($data['room_id'])]);
        }

        return $this->repo->create($record);
    }

    public function delete($id) {
        $return = $this->repo->delete_by_id($id);

        if (!$return) {
            return new WP_Error('delete', __('Room delete failed', 'my-village-hall'));
        }

        return $return;
    }
}