<?php
namespace MYVH\Rooms;

use MYVH\Portal\ClientAdminService;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * RoomVisibilityService
 *
 * Handles room visibility logic based on user permissions and room settings.
 * Determines whether a user can view/access a room based on the room's IsPublic flag
 * and the user's role (WordPress admin, client admin, or normal user).
 */
class RoomVisibilityService {

    private $client_admin_service;
    private $room_repository;

    public function __construct(
        ClientAdminService $client_admin_service,
        RoomRepository $room_repository
    ) {
        $this->client_admin_service = $client_admin_service;
        $this->room_repository = $room_repository;
    }

    /**
     * Check if a user can view a specific room.
     *
     * @param int $user_id The user ID. If 0, uses current user.
     * @param int $room_id The room ID to check.
     * @return bool True if the user can view the room, false otherwise.
     */
    public function can_view_room($user_id = 0, $room_id = 0): bool {
        if ($room_id <= 0) {
            return false;
        }

        $user_id = $user_id > 0 ? $user_id : get_current_user_id();

        $room = $this->room_repository->get_by_id($room_id);
        if (!$room) {
            return false;
        }

        // WordPress admins can view all rooms
        if ($this->client_admin_service->is_global_admin($user_id)) {
            return true;
        }

        // If room is public, allow viewing
        if (!empty($room['IsPublic'])) {
            return true;
        }

        // Private room: check if user is a client admin for the room's venue
        if ($this->is_client_admin_for_venue($user_id, $room['VenueId'])) {
            return true;
        }

        return false;
    }

    /**
     * Filter an array of rooms based on user visibility permissions.
     *
     * @param array $rooms Array of room records with 'Id' and 'IsPublic' keys.
     * @param int   $user_id The user ID. If 0, uses current user.
     * @return array Filtered array of rooms the user can view.
     */
    public function filter_rooms_for_user($rooms = [], $user_id = 0): array {
        if (empty($rooms)) {
            return [];
        }

        $user_id = $user_id > 0 ? $user_id : get_current_user_id();

        // WordPress admins can view all rooms
        if ($this->client_admin_service->is_global_admin($user_id)) {
            return $rooms;
        }

        // Get list of venues where this user is a client admin
        $admin_venues = $this->get_admin_venues($user_id);

        // Filter rooms: show public rooms + private rooms in user's venues
        return array_filter($rooms, function ($room) use ($admin_venues) {
            // Public rooms are always visible
            if (!empty($room['IsPublic'])) {
                return true;
            }

            // Private rooms: only visible if user is admin for that venue
            $venue_id = !empty($room['VenueId']) ? intval($room['VenueId']) : 0;
            return in_array($venue_id, $admin_venues, true);
        });
    }

    /**
     * Check if a user is a client admin for a specific venue.
     * This is determined by checking if the user is a client admin for the organization.
     *
     * @param int $user_id The user ID.
     * @param int $venue_id The venue ID.
     * @return bool True if the user is a client admin for the venue.
     */
    private function is_client_admin_for_venue($user_id, $venue_id): bool {
        if ($user_id <= 0 || $venue_id <= 0) {
            return false;
        }

        // For now, we check if the user is a client admin for the current blog.
        // In the future, if we need venue-specific client admin assignment, this would be enhanced.
        return $this->client_admin_service->can_administer_blog($user_id, get_current_blog_id());
    }

    /**
     * Get list of venue IDs where the user is a client admin.
     *
     * @param int $user_id The user ID.
     * @return array List of venue IDs (currently, for client admins, all venues in the blog).
     */
    private function get_admin_venues($user_id): array {
        if ($user_id <= 0) {
            return [];
        }

        // If user is a client admin, they can manage all venues in their blog.
        // This could be made more granular in the future if venue-level permissions are added.
        if ($this->client_admin_service->can_administer_blog($user_id, get_current_blog_id())) {
            // Get all venues
            global $wpdb;
            $results = $wpdb->get_results(
                "SELECT Id FROM {$wpdb->prefix}myvh_venues",
                ARRAY_A
            );
            return array_map(function ($row) {
                return intval($row['Id']);
            }, $results ?? []);
        }

        return [];
    }
}
