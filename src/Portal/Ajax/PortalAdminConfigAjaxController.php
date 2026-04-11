<?php
namespace MYVH\Portal\Ajax;

use MYVH\Addons\AddonRequestValidator;
use MYVH\Addons\AddonService;
use MYVH\Addons\SaveAddonRequest;
use MYVH\Organisations\OrganisationTypeService;
use MYVH\Organisations\SaveOrganisationTypeRequest;
use MYVH\Portal\ClientAdminService;
use MYVH\Portal\Support\PortalAuth;
use MYVH\Pricing\RoomRateRequestValidator;
use MYVH\Pricing\RoomRateService;
use MYVH\Pricing\SaveRoomRateRequest;
use MYVH\Rooms\RoomRequestValidator;
use MYVH\Rooms\RoomService;
use MYVH\Rooms\SaveRoomRequest;
use MYVH\Settings\SettingsRegistry;
use MYVH\Venues\SaveVenueRequest;
use MYVH\Venues\VenueRequestValidator;
use MYVH\Venues\VenueService;

use Throwable;

class PortalAdminConfigAjaxController {

    public function __construct(
        private OrganisationTypeService $organisation_type_service,
        private ClientAdminService $client_admin_service,
        private RoomService $room_service,
        private RoomRequestValidator $room_request_validator,
        private RoomRateService $room_rate_service,
        private RoomRateRequestValidator $room_rate_request_validator,
        private VenueService $venue_service,
        private VenueRequestValidator $venue_request_validator,
        private AddonService $addon_service,
        private AddonRequestValidator $addon_request_validator
    ) {}

    public function register(): void {
        add_action('wp_ajax_myvh_portal_save_org_type', [$this, 'save_organisation_type']);
        add_action('wp_ajax_myvh_portal_delete_org_type', [$this, 'delete_organisation_type']);
        add_action('wp_ajax_myvh_portal_save_room', [$this, 'save_room']);
        add_action('wp_ajax_myvh_portal_delete_room', [$this, 'delete_room']);
        add_action('wp_ajax_myvh_portal_save_venue', [$this, 'save_venue']);
        add_action('wp_ajax_myvh_portal_delete_venue', [$this, 'delete_venue']);
        add_action('wp_ajax_myvh_portal_save_room_rate', [$this, 'save_room_rate']);
        add_action('wp_ajax_myvh_portal_delete_room_rate', [$this, 'delete_room_rate']);
        add_action('wp_ajax_myvh_portal_save_addon', [$this, 'save_addon']);
        add_action('wp_ajax_myvh_portal_delete_addon', [$this, 'delete_addon']);
        add_action('wp_ajax_myvh_portal_save_client_settings', [$this, 'save_client_settings']);
    }

    public function save_organisation_type(): void {
        PortalAuth::require_client_admin($this->client_admin_service);

        $payload = SaveOrganisationTypeRequest::from_post(wp_unslash($_POST));

        try {
            $saved = $this->organisation_type_service->save($payload);
        } catch (Throwable $throwable) {
            wp_send_json_error($throwable->getMessage(), 400);
        }

        if (is_wp_error($saved)) {
            wp_send_json_error($saved->get_error_message(), 400);
        }

        if (!$saved) {
            wp_send_json_error('Organisation type save failed', 400);
        }

        wp_send_json_success([
            'message' => !empty($payload['org_type_id']) ? 'Organisation type updated' : 'Organisation type created',
            'org_type_id' => !empty($payload['org_type_id']) ? (int) $payload['org_type_id'] : (int) $saved,
        ]);
    }

    public function delete_organisation_type(): void {
        PortalAuth::require_client_admin($this->client_admin_service);

        $org_type_id = intval($_POST['org_type_id'] ?? 0);

        if ($org_type_id <= 0) {
            wp_send_json_error('Organisation type ID is required', 400);
        }

        $deleted = $this->organisation_type_service->delete($org_type_id);

        if (is_wp_error($deleted)) {
            wp_send_json_error($deleted->get_error_message(), 400);
        }

        if (!$deleted) {
            wp_send_json_error('Failed to delete organisation type', 400);
        }

        wp_send_json_success(['message' => 'Organisation type deleted']);
    }

    public function save_room(): void {
        PortalAuth::require_client_admin($this->client_admin_service);

        $payload = SaveRoomRequest::from_post(wp_unslash($_POST));
        $validation = $this->room_request_validator->validate($payload);

        if (is_wp_error($validation)) {
            wp_send_json_error($validation->get_error_message(), 400);
        }

        try {
            $saved = $this->room_service->save($payload);
        } catch (Throwable $throwable) {
            wp_send_json_error($throwable->getMessage(), 400);
        }

        if (is_wp_error($saved)) {
            wp_send_json_error($saved->get_error_message(), 400);
        }

        if (!$saved) {
            wp_send_json_error('Room save failed', 400);
        }

        wp_send_json_success([
            'message' => !empty($payload['room_id']) ? 'Room updated' : 'Room created',
            'room_id' => !empty($payload['room_id']) ? (int) $payload['room_id'] : (int) $saved,
            'redirect' => !empty($payload['room_id'])
                ? 'rooms'
                : ('room-rate-add?room_id=' . (int) $saved),
        ]);
    }

    public function save_venue(): void {
        PortalAuth::require_client_admin($this->client_admin_service);

        $payload = SaveVenueRequest::from_post(wp_unslash($_POST));
        $validation = $this->venue_request_validator->validate($payload);

        if (is_wp_error($validation)) {
            wp_send_json_error($validation->get_error_message(), 400);
        }

        try {
            $saved = $this->venue_service->save($payload);
        } catch (Throwable $throwable) {
            wp_send_json_error($throwable->getMessage(), 400);
        }

        if (is_wp_error($saved)) {
            wp_send_json_error($saved->get_error_message(), 400);
        }

        if (!$saved) {
            wp_send_json_error('Venue save failed', 400);
        }

        wp_send_json_success([
            'message' => !empty($payload['venue_id']) ? 'Venue updated' : 'Venue created',
            'venue_id' => !empty($payload['venue_id']) ? (int) $payload['venue_id'] : (int) $saved,
            'redirect' => 'venues',
        ]);
    }

    public function delete_room(): void {
        PortalAuth::require_client_admin($this->client_admin_service);

        $room_id = intval($_POST['room_id'] ?? 0);

        if ($room_id <= 0) {
            wp_send_json_error('Room ID is required', 400);
        }

        $deleted = $this->room_service->delete($room_id);

        if (is_wp_error($deleted)) {
            wp_send_json_error($deleted->get_error_message(), 400);
        }

        if (!$deleted) {
            wp_send_json_error('Failed to delete room', 400);
        }

        wp_send_json_success(['message' => 'Room deleted']);
    }

    public function delete_venue(): void {
        PortalAuth::require_client_admin($this->client_admin_service);

        $venue_id = intval($_POST['venue_id'] ?? 0);

        if ($venue_id <= 0) {
            wp_send_json_error('Venue ID is required', 400);
        }

        $deleted = $this->venue_service->delete($venue_id);

        if (is_wp_error($deleted)) {
            wp_send_json_error($deleted->get_error_message(), 400);
        }

        if (!$deleted) {
            wp_send_json_error('Failed to delete venue', 400);
        }

        wp_send_json_success(['message' => 'Venue deleted']);
    }

    public function save_room_rate(): void {
        PortalAuth::require_client_admin($this->client_admin_service);

        $payload = SaveRoomRateRequest::from_post(wp_unslash($_POST));
        $validation = $this->room_rate_request_validator->validate($payload);

        if (is_wp_error($validation)) {
            wp_send_json_error($validation->get_error_message(), 400);
        }

        try {
            $saved = $this->room_rate_service->save($payload);
        } catch (Throwable $throwable) {
            wp_send_json_error($throwable->getMessage(), 400);
        }

        if (is_wp_error($saved)) {
            wp_send_json_error($saved->get_error_message(), 400);
        }

        if (!$saved) {
            wp_send_json_error('Room rate save failed', 400);
        }

        wp_send_json_success([
            'message' => !empty($payload['rate_id']) ? 'Room rate updated' : 'Room rate created',
            'rate_id' => !empty($payload['rate_id']) ? (int) $payload['rate_id'] : (int) $saved,
            'redirect' => !empty($payload['rate_id']) ? 'room-rates' : 'room-rates',
        ]);
    }

    public function delete_room_rate(): void {
        PortalAuth::require_client_admin($this->client_admin_service);

        $rate_id = intval($_POST['rate_id'] ?? 0);

        if ($rate_id <= 0) {
            wp_send_json_error('Room rate ID is required', 400);
        }

        $deleted = $this->room_rate_service->delete($rate_id);

        if (!$deleted) {
            wp_send_json_error('Failed to delete room rate', 400);
        }

        wp_send_json_success(['message' => 'Room rate deleted']);
    }

    public function save_addon(): void {
        PortalAuth::require_client_admin($this->client_admin_service);

        $payload = SaveAddonRequest::from_post(wp_unslash($_POST));
        $validation = $this->addon_request_validator->validate($payload);

        if (is_wp_error($validation)) {
            wp_send_json_error($validation->get_error_message(), 400);
        }

        try {
            $saved = $this->addon_service->save($payload);
        } catch (Throwable $throwable) {
            wp_send_json_error($throwable->getMessage(), 400);
        }

        if (is_wp_error($saved)) {
            wp_send_json_error($saved->get_error_message(), 400);
        }

        if (!$saved) {
            wp_send_json_error('Add-on save failed', 400);
        }

        wp_send_json_success([
            'message' => !empty($payload['addon_id']) ? 'Add-on updated' : 'Add-on created',
            'addon_id' => !empty($payload['addon_id']) ? (int) $payload['addon_id'] : (int) $saved,
            'redirect' => 'addons',
        ]);
    }

    public function delete_addon(): void {
        PortalAuth::require_client_admin($this->client_admin_service);

        $addon_id = intval($_POST['addon_id'] ?? 0);

        if ($addon_id <= 0) {
            wp_send_json_error('Add-on ID is required', 400);
        }

        $deleted = $this->addon_service->delete($addon_id);

        if (!$deleted) {
            wp_send_json_error('Failed to archive add-on', 400);
        }

        wp_send_json_success(['message' => 'Add-on archived']);
    }

    public function save_client_settings(): void {
        PortalAuth::require_client_admin($this->client_admin_service);

        $group = sanitize_key($_POST['settings_group'] ?? '');

        if ($group === '') {
            wp_send_json_error('Settings group is required', 400);
        }

        $settings = SettingsRegistry::get($group);

        if (!$settings) {
            wp_send_json_error('Settings group not found', 404);
        }

        $current_user_id = get_current_user_id();
        if (!$settings->is_visible_to_client_admin($current_user_id) || !$settings->user_can_access($current_user_id)) {
            wp_send_json_error('Permission denied for this settings group', 403);
        }

        $input = wp_unslash($_POST);
        unset($input['action'], $input['nonce'], $input['settings_group']);

        $settings->save($input);

        wp_send_json_success(['message' => 'Client settings updated']);
    }
}