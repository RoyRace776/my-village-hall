<?php
namespace MYVH\Rooms;

use MYVH\Availability\AvailabilityService;

use WP_Error;

if (!defined('ABSPATH')) exit;

class RoomService {

    private $repo;
    private $room_hours_repository;
    private $availability;
    private $room_deposit_repository;

    public function __construct(
        RoomRepository $repo,
        RoomHoursRepository $room_hours_repository,
        AvailabilityService $availability,
        RoomDepositRepository $room_deposit_repository
    ) {
        $this->repo = $repo;
        $this->room_hours_repository = $room_hours_repository;
        $this->availability = $availability;
        $this->room_deposit_repository = $room_deposit_repository;
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

        if (!empty($data['opening_hours_by_day']) && is_array($data['opening_hours_by_day'])) {
            $hours_by_day_allowed = $this->availability->room_opening_hours_by_day_allowed(
                $data['opening_hours_by_day'],
                $venue_id
            );

            if (is_wp_error($hours_by_day_allowed)) {
                return $hours_by_day_allowed;
            }

            if (!$hours_by_day_allowed) {
                return new WP_Error(
                    'validation',
                    __('Room daily opening hours must be within the venue daily opening hours', 'my-village-hall')
                );
            }
        }

        $record = [
            'Name'         => sanitize_text_field($data['name']),
            'Colour'       => RoomColour::resolve($data['room_colour'] ?? ($data['room_color'] ?? ''), intval($data['room_id'] ?? 0)),
            'VenueId'      => $venue_id,
            'Capacity'     => intval($data['capacity']),
            'Description'  => sanitize_textarea_field($data['description']),
            'OpeningTime'  => $opening_time,
            'ClosingTime'  => $closing_time,
            'AllowMultiDayBookings' => !empty($data['allow-multi-day-bookings']) ? 1 : 0,
            'CalcClosedHours' => !empty($data['calc-closed-hours']) ? 1 : 0,
            'IsPublic'      => !empty($data['is-public']) ? 1 : 0
        ];

        if (!empty($data['room_id'])) {
            $updated = $this->repo->update($record, ['Id' => intval($data['room_id'])]);
            if (!$updated) {
                $db_error = $this->repo->last_error();
                $message = __('Room update failed', 'my-village-hall');
                if ($db_error !== '') {
                    $message .= ': ' . $db_error;
                }
                return new WP_Error('save', $message);
            }

            if (!empty($data['opening_hours_by_day']) && is_array($data['opening_hours_by_day'])) {
                $saved = $this->room_hours_repository->replace_for_room(intval($data['room_id']), $data['opening_hours_by_day']);
                if (!$saved) {
                    return new WP_Error('save', __('Room opening hours could not be saved', 'my-village-hall'));
                }
            }

            $this->room_deposit_repository->save((int) $data['room_id'], [
                'enabled' => !empty($data['deposit_enabled']),
                'days' => $data['deposit_days'] ?? [],
                'end_after' => $data['deposit_end_after'] ?? null,
                'amount' => $data['deposit_amount'] ?? 0,
                'action' => $data['deposit_action'] ?? 'auto_add',
            ]);

            return intval($data['room_id']);
        }

        $created = $this->repo->create($record);
        if (!$created) {
            $db_error = $this->repo->last_error();
            $message = __('Room create failed', 'my-village-hall');
            if ($db_error !== '') {
                $message .= ': ' . $db_error;
            }
            return new WP_Error('save', $message);
        }

        if (!empty($data['opening_hours_by_day']) && is_array($data['opening_hours_by_day'])) {
            $saved = $this->room_hours_repository->replace_for_room((int) $created, $data['opening_hours_by_day']);
            if (!$saved) {
                return new WP_Error('save', __('Room opening hours could not be saved', 'my-village-hall'));
            }
        }

        $this->room_deposit_repository->save((int) $created, [
            'enabled' => !empty($data['deposit_enabled']),
            'days' => $data['deposit_days'] ?? [],
            'end_after' => $data['deposit_end_after'] ?? null,
            'amount' => $data['deposit_amount'] ?? 0,
            'action' => $data['deposit_action'] ?? 'auto_add',
        ]);

        return (int) $created;
    }

    public function delete($id) {
        $return = $this->repo->delete_by_id($id);

        if (!$return) {
            return new WP_Error('delete', __('Room delete failed', 'my-village-hall'));
        }

        return $return;
    }
}