<?php
namespace MYVH\Portal\Ajax;

use MYVH\Addons\AddonService;
use MYVH\Organisations\OrganisationTypeService;
use MYVH\Pricing\RoomRateService;
use MYVH\Rooms\RoomService;
use MYVH\Settings\SettingsRegistry;
use MYVH\Venues\VenueService;

class PortalAdminConfigPageRenderer {
    public function __construct(
        private AddonService $addon_service,
        private RoomService $room_service,
        private RoomRateService $room_rate_service,
        private VenueService $venue_service,
        private OrganisationTypeService $organisation_type_service
    ) {}

    public function render_rooms(bool $is_client_admin): void {
        if (!$is_client_admin) {
            wp_send_json_error('Permission denied', 403);
        }

        $rooms = $this->room_service->get_all_with_venues();
        include MYVH_PLUGIN_DIR . 'templates/Portal/rooms.php';
    }

    public function render_venues(bool $is_client_admin): void {
        if (!$is_client_admin) {
            wp_send_json_error('Permission denied', 403);
        }

        $venues = $this->venue_service->get_all();
        $rooms = $this->room_service->get_all_with_venues();
        $venue_room_counts = [];
        foreach ($rooms as $room) {
            $venue_id = !empty($room['VenueId']) ? (int) $room['VenueId'] : 0;
            if ($venue_id <= 0) {
                continue;
            }

            $venue_room_counts[$venue_id] = ($venue_room_counts[$venue_id] ?? 0) + 1;
        }
        include MYVH_PLUGIN_DIR . 'templates/Portal/venues.php';
    }

    public function render_venue_add(bool $is_client_admin): void {
        if (!$is_client_admin) {
            wp_send_json_error('Permission denied', 403);
        }

        include MYVH_PLUGIN_DIR . 'templates/Portal/venue-add.php';
    }

    public function render_venue_edit(bool $is_client_admin): void {
        if (!$is_client_admin) {
            wp_send_json_error('Permission denied', 403);
        }

        $venue_id = intval($_GET['id'] ?? 0);
        if (!$venue_id) {
            wp_send_json_error('Invalid venue ID', 400);
        }

        $venue = $this->venue_service->get($venue_id);
        include MYVH_PLUGIN_DIR . 'templates/Portal/venue-edit.php';
    }

    public function render_room_add(bool $is_client_admin): void {
        if (!$is_client_admin) {
            wp_send_json_error('Permission denied', 403);
        }

        $venues = $this->venue_service->get_all();
        include MYVH_PLUGIN_DIR . 'templates/Portal/room-add.php';
    }

    public function render_room_edit(bool $is_client_admin): void {
        if (!$is_client_admin) {
            wp_send_json_error('Permission denied', 403);
        }

        $room_id = intval($_GET['id'] ?? 0);
        if (!$room_id) {
            wp_send_json_error('Invalid room ID', 400);
        }

        $room = $this->room_service->get($room_id);
        $venues = $this->venue_service->get_all();
        include MYVH_PLUGIN_DIR . 'templates/Portal/room-edit.php';
    }

    public function render_room_rates(bool $is_client_admin): void {
        if (!$is_client_admin) {
            wp_send_json_error('Permission denied', 403);
        }

        $rates = $this->room_rate_service->get_all();
        $rooms = $this->room_service->get_all_with_venues();
        $organisation_types = $this->organisation_type_service->get_all();
        include MYVH_PLUGIN_DIR . 'templates/Portal/room-rates.php';
    }

    public function render_addons(bool $is_client_admin): void {
        if (!$is_client_admin) {
            wp_send_json_error('Permission denied', 403);
        }

        $addons = $this->addon_service->get_with_relations();
        include MYVH_PLUGIN_DIR . 'templates/Portal/addons.php';
    }

    public function render_addon_add(bool $is_client_admin): void {
        if (!$is_client_admin) {
            wp_send_json_error('Permission denied', 403);
        }

        $rooms = $this->room_service->get_all_with_venues();
        include MYVH_PLUGIN_DIR . 'templates/Portal/addon-add.php';
    }

    public function render_addon_edit(bool $is_client_admin): void {
        if (!$is_client_admin) {
            wp_send_json_error('Permission denied', 403);
        }

        $addon_id = intval($_GET['id'] ?? 0);
        if (!$addon_id) {
            wp_send_json_error('Invalid add-on ID', 400);
        }

        $addon = $this->addon_service->get($addon_id);
        if (!$addon) {
            wp_send_json_error('Add-on not found', 404);
        }

        $rooms = $this->room_service->get_all_with_venues();
        include MYVH_PLUGIN_DIR . 'templates/Portal/addon-edit.php';
    }

    public function render_room_rate_add(bool $is_client_admin): void {
        if (!$is_client_admin) {
            wp_send_json_error('Permission denied', 403);
        }

        $rooms = $this->room_service->get_all_with_venues();
        $organisation_types = $this->organisation_type_service->get_all();
        $selected_room_id = intval($_GET['room_id'] ?? 0);
        include MYVH_PLUGIN_DIR . 'templates/Portal/room-rate-add.php';
    }

    public function render_room_rate_edit(bool $is_client_admin): void {
        if (!$is_client_admin) {
            wp_send_json_error('Permission denied', 403);
        }

        $rate_id = intval($_GET['id'] ?? 0);
        if (!$rate_id) {
            wp_send_json_error('Invalid rate ID', 400);
        }

        $rate = $this->room_rate_service->get($rate_id);
        if (!$rate) {
            wp_send_json_error('Room rate not found', 404);
        }

        $rooms = $this->room_service->get_all_with_venues();
        $organisation_types = $this->organisation_type_service->get_all();
        include MYVH_PLUGIN_DIR . 'templates/Portal/room-rate-edit.php';
    }

    public function render_settings(bool $is_client_admin): void {
        if (!$is_client_admin) {
            wp_send_json_error('Permission denied', 403);
        }

        $settings_groups = [];
        $current_user_id = get_current_user_id();

        foreach (SettingsRegistry::groups() as $group_key => $group_meta) {
            $settings = SettingsRegistry::get($group_key);

            if (!$settings) {
                continue;
            }

            if (!$settings->is_visible_to_client_admin($current_user_id)) {
                continue;
            }

            $settings_groups[] = [
                'key' => $group_key,
                'label' => $group_meta['label'] ?? ucfirst((string) $group_key),
                'schema' => $settings->schema(),
                'values' => $settings->all(),
            ];
        }

        include MYVH_PLUGIN_DIR . 'templates/Portal/settings.php';
    }
}