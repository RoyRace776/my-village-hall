<?php
namespace MYVH\Venues;

use MYVH\Rooms\RoomRepository;
use WP_Error;

if (!defined('ABSPATH')) exit;

class VenueService {

    private $repo;
    private $venue_hours_repository;
    private $room_repository;

    public function __construct(VenueRepository $repo, VenueHoursRepository $venue_hours_repository, RoomRepository $room_repository) {
        $this->repo = $repo;
        $this->venue_hours_repository = $venue_hours_repository;
        $this->room_repository = $room_repository;
    }

    public function get_all(): array {
        return $this->repo->get_all();
    }

    public function get($id): ?array {
        return $this->repo->get_by_id($id);
    }

    public function save($data): int|WP_Error {

        if (empty($data['name'])) {
            return new WP_Error('validation', __('Venue name is required', 'my-village-hall'));
        }

        $record = [
            'Name'         => sanitize_text_field($data['name']),
            'ShortName'    => sanitize_text_field($data['short_name']),
            'PostCode'     => sanitize_text_field($data['post_code']),
            'AddressLine1' => sanitize_text_field($data['address_line1']),
            'OpeningTime'  => sanitize_text_field($data['opening_time']),
            'ClosingTime'  => sanitize_text_field($data['closing_time']),
        ];

        if (!empty($data['venue_id'])) {
            $venue_id = intval($data['venue_id']);
            $updated = $this->repo->update($record, ['Id' => $venue_id]);
            if (!$updated) {
                return new WP_Error('save', __('Venue update failed', 'my-village-hall'));
            }

            if (!empty($data['opening_hours_by_day']) && is_array($data['opening_hours_by_day'])) {
                $saved = $this->venue_hours_repository->replace_for_venue($venue_id, $data['opening_hours_by_day']);
                if (!$saved) {
                    return new WP_Error('save', __('Venue opening hours could not be saved', 'my-village-hall'));
                }
            }

            return $venue_id;
        }

        $created = $this->repo->create($record);
        if (!$created) {
            return new WP_Error('save', __('Venue create failed', 'my-village-hall'));
        }

        $venue_id = intval($created);

        if (!empty($data['opening_hours_by_day']) && is_array($data['opening_hours_by_day'])) {
            $saved = $this->venue_hours_repository->replace_for_venue($venue_id, $data['opening_hours_by_day']);
            if (!$saved) {
                return new WP_Error('save', __('Venue opening hours could not be saved', 'my-village-hall'));
            }
        }

        return $venue_id;
    }

    public function delete($id): bool|WP_Error {
        $room_count = count($this->room_repository->get_by_venue((int) $id));

        if ($room_count > 0) {
            return new WP_Error(
                'venue_has_rooms',
                __('Delete the rooms in this venue before deleting the venue', 'my-village-hall')
            );
        }

        return $this->repo->delete_by_id($id);
    }
}