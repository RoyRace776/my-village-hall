<?php
if (!defined('ABSPATH')) exit;

class MYVH_Calendar_Ajax_Controller {

    private $booking_service;
    private $room_repository;
    private $customer_service;

    public function __construct(
        MYVH_Booking_Service $booking_service,
        MYVH_Room_Repository $room_repository,
        MYVH_Customer_Service $customer_service) {

        $this->booking_service = $booking_service;
        $this->room_repository = $room_repository;
        $this->customer_service = $customer_service;
    }

    public function register_routes() {

        add_action('wp_ajax_myvh_calendar_rooms', [$this, 'get_rooms']);
        add_action('wp_ajax_myvh_calendar_events', [$this, 'get_events']);
        add_action('wp_ajax_myvh_move_booking', [$this, 'move_booking']);
        add_action('wp_ajax_myvh_create_event', [$this, 'create_event']);
        add_action('wp_ajax_myvh_update_event', [$this, 'update_event']);
        add_action('wp_ajax_myvh_customers', [$this, 'get_customers']);
        add_action('wp_ajax_myvh_organisations', [$this, 'get_organisations']);

    }

    public function get_customers() {

        if (!is_user_logged_in()) {
            wp_send_json_error('Not logged in', 401);
        }

        $user_id = get_current_user_id();

        // Map WP user → customer
        $customer = $this->customer_service->get_by_user_id($user_id);

        wp_send_json([
            [
                'id' => $customer['Id'],
                'name' => $customer['Name']
            ]
        ]);
    }

    public function get_organisations() {

        if (current_user_can('manage_myvh')) {
            $customer_id = intval($_GET['customer_id'] ?? $_POST['customer_id'] ?? 0);

            if ($customer_id > 0) {
                $orgs = $this->customer_service->get_organisations_for_customer($customer_id);
                wp_send_json($orgs ?: []);
            }
        }

        $orgs = $this->customer_service->get_organisations_for_user_id(get_current_user_id());

        wp_send_json($orgs);
    }

    public function get_rooms() {

        check_ajax_referer('myvh_calendar', 'nonce');

        $context = sanitize_text_field($_GET['context'] ?? $_POST['context'] ?? 'admin');

        if ( $context === 'portal' ) {
            $this->authorize_user();
        } elseif ( ! current_user_can( 'manage_myvh' ) ) {
            wp_send_json_error( 'Permission denied', 403 );
        }

        $rooms = $this->room_repository->get_all_with_venues();

        $columns = [];

        foreach ($rooms as $room) {

            $columns[] = [
                "name" => $room['Name'],
                "id"   => $room['Id'],
                "venue_id" => !empty($room['VenueId']) ? (int) $room['VenueId'] : 0,
                "venue" => $room['VenueName'] ?? '',
                "allow_multiday" => !empty($room['AllowMultiDayBookings']) ? 1 : 0
            ];
        }

        wp_send_json($columns);
    }

    public function get_events() {

        //TODO: Fix this to make sure it returns the right data for the right context

        $context = $_GET['context'] ?? 'public';

        switch ($context) {
            case 'admin':
                $this->authorize_admin();
                break;

            case 'portal':
                $this->authorize_user();
                break;

            case 'public':
            default:
                // no auth needed
                break;
        }

        check_ajax_referer('myvh_calendar', 'nonce');

        $start = sanitize_text_field($_GET['start'] ?? null);
        $end   = sanitize_text_field($_GET['end'] ?? null);
        $context = sanitize_text_field($_GET['context'] ?? 'public');

        $bookings = $this->booking_service->get_between($start, $end, $context);

        $events = [];

        foreach ($bookings as $b) {

            $event = [
                "id" => $b['Id'],
                "text" => $b['Description'],
                "start" => $b['StartDate'] . "T" . $b['StartTime'],
                "end"   => $b['EndDate'] . "T" . $b['EndTime'],
                "resource" => $b['RoomId']
            ];

            // 🔐 Public safety
            if ($context === 'public') {
                unset($event['id']); // optional
                $event['text'] = 'Booked';
            }

            $events[] = $event;
        }

        wp_send_json($events);
    }

    public function move_booking() {

        check_ajax_referer('myvh_calendar', 'nonce');

        if ( ! current_user_can( 'manage_myvh' ) ) {
            wp_send_json_error( 'Permission denied', 403 );
        }

        $request = $this->get_request_data();

        $id = intval($request['id'] ?? 0);
        $start = sanitize_text_field($request['start'] ?? '');
        $end   = sanitize_text_field($request['end'] ?? '');
        $room  = intval($request['room_id'] ?? 0);

        if (!$id || !$start || !$end) {
            wp_send_json_error('Missing booking movement data', 400);
        }

        $result = $this->booking_service->move_booking(
            $id,
            $start,
            $end,
            $room
        );

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success();
    }

    public function create_event() {

        check_ajax_referer('myvh_calendar', 'nonce');

        $request = $this->get_request_data();
        $context = sanitize_text_field($request['context'] ?? $_GET['context'] ?? $_POST['context'] ?? 'admin');

        if ($context === 'portal') {
            $this->authorize_user();
        } elseif (!current_user_can('manage_myvh')) {
            wp_send_json_error('Permission denied', 403);
        }

        [$start_date, $start_time] = $this->split_datetime($request['start'] ?? '');
        [$end_date, $end_time] = $this->split_datetime($request['end'] ?? '', $start_date);

        $addons = $this->normalize_addons($request['addons'] ?? []);

        $is_recurring = !empty($request['is_recurring']);

        $recurrence_type = sanitize_text_field($request['recurrence_type'] ?? 'weekly');
        $recurrence_interval = intval($request['recurrence_interval'] ?? 1);
        $recurrence_interval_md = intval($request['recurrence_interval_md'] ?? 1);

        if ($recurrence_type === 'monthly_day') {
            $recurrence_interval = max(1, $recurrence_interval_md);
        }

        $recurrence_end_type = sanitize_text_field($request['recurrence_end_type'] ?? 'date');

        $data = [
            'start_date'      => $start_date,
            'start_time'      => $start_time,
            'end_date'        => $end_date,
            'end_time'        => $end_time,
            'description'     => sanitize_text_field($request['text'] ?? ''),
            'status'          => sanitize_text_field($request['status'] ?? BookingStatus::PENDING),
            'room_id'         => intval($request['room_id'] ?? 0),
            'customer_id'     => intval($request['customer_id'] ?? 0),
            'organisation_id' => intval($request['organisation_id'] ?? 0),
            'addons'          => $addons,
            'is_recurring'    => $is_recurring ? 1 : 0,
            'recurrence_type' => $recurrence_type,
            'recurrence_interval' => max(1, $recurrence_interval),
            'recurrence_week' => sanitize_text_field($request['recurrence_week'] ?? ''),
            'recurrence_day'  => sanitize_text_field($request['recurrence_day'] ?? ''),
            'recurrence_end_type' => $recurrence_end_type,
            'recurrence_end_date' => sanitize_text_field($request['recurrence_end_date'] ?? ''),
            'max_occurrences' => intval($request['max_occurrences'] ?? 0),
        ];

        if ($context === 'portal') {
            $customer = $this->customer_service->get_by_user_id(get_current_user_id());

            if (empty($customer['Id'])) {
                wp_send_json_error('No customer profile found', 400);
            }

            $allowed_orgs = $this->customer_service->get_organisations_for_user_id(get_current_user_id());
            $allowed_org_ids = array_map(static function ($org) {
                return (int) ($org['Id'] ?? 0);
            }, $allowed_orgs);

            $selected_org_id = (int) $data['organisation_id'];

            if (!empty($allowed_org_ids) && !in_array($selected_org_id, $allowed_org_ids, true)) {
                $selected_org_id = (int) $allowed_org_ids[0];
            }

            $data['customer_id'] = (int) $customer['Id'];
            $data['organisation_id'] = $selected_org_id;
            $data['status'] = BookingStatus::PENDING;
        }

        // Basic validation
        if (!$data['room_id']) {
            wp_send_json_error('Room is required');
        }

        if (!$data['customer_id']) {
            wp_send_json_error('Customer is required');
        }

        if (!$data['organisation_id']) {
            wp_send_json_error('Organisation is required');
        }

        if ($data['is_recurring']) {
            if (empty($data['recurrence_type'])) {
                wp_send_json_error('Recurrence type is required');
            }

            if ($data['recurrence_end_type'] === 'count' && $data['max_occurrences'] < 1) {
                wp_send_json_error('Max occurrences must be at least 1');
            }

            if ($data['recurrence_end_type'] !== 'count' && empty($data['recurrence_end_date'])) {
                wp_send_json_error('Recurrence end date is required');
            }
        }

        try {
            $id = $this->booking_service->save($data);

            if (is_wp_error($id)) {
                wp_send_json_error($id->get_error_message());
            }

            $response = ['id' => $id];
            $warnings = $this->booking_service->get_last_warnings();
            if (!empty($warnings)) {
                $response['warning'] = implode(' ', $warnings);
            }

            wp_send_json_success($response);

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function update_event() {

        check_ajax_referer('myvh_calendar', 'nonce');

        if (!current_user_can('manage_myvh')) {
            wp_send_json_error('Permission denied', 403);
        }

        $request = $this->get_request_data();
        [$start_date, $start_time] = $this->split_datetime($request['start'] ?? '');
        [$end_date, $end_time] = $this->split_datetime($request['end'] ?? '', $start_date);

        $data = [
            'booking_id'      => sanitize_text_field($request['booking_id'] ?? $request['id'] ?? ''),
            'start_date'      => $start_date,
            'start_time'      => $start_time,
            'end_date'        => $end_date,
            'end_time'        => $end_time,
            'description'     => sanitize_text_field($request['text'] ?? ''),
            'room_id'         => intval($request['room_id'] ?? 0),
            'customer_id'     => intval($request['customer_id'] ?? 0),
            'organisation_id' => intval($request['organisation_id'] ?? 0),
        ];

        // Basic validation
        if (!$data['room_id']) {
            wp_send_json_error('Room is required');
        }

        if (!$data['customer_id']) {
            wp_send_json_error('Customer is required');
        }

        try {
            $id = $this->booking_service->save($data);

            if (is_wp_error($id)) {
                wp_send_json_error($id->get_error_message());
            }

            wp_send_json_success(['id' => $id]);

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
}


    private function authorize_admin() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized', 403);
        }
    }

    private function authorize_user() {
        if (!is_user_logged_in()) {
            wp_send_json_error('Login required', 401);
        }
    }

    private function get_request_data() {

        $data = wp_unslash($_POST);

        if (!empty($data)) {
            return $data;
        }

        $raw = file_get_contents('php://input');

        if (!$raw) {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function split_datetime($value, $default_date = '') {

        $raw = trim((string) $value);

        if ($raw === '') {
            return [sanitize_text_field($default_date), ''];
        }

        $timestamp = strtotime($raw);

        if ($timestamp !== false) {
            return [
                date('Y-m-d', $timestamp),
                date('H:i:s', $timestamp),
            ];
        }

        $parts = preg_split('/[T\s]/', $raw);
        $date = sanitize_text_field($parts[0] ?? $default_date);
        $time = sanitize_text_field($parts[1] ?? '');

        if (preg_match('/^\d{2}:\d{2}$/', $time)) {
            $time .= ':00';
        }

        return [$date, $time];
    }

    private function normalize_addons($raw_addons) {

        if (!is_array($raw_addons)) {
            return [];
        }

        $normalized = [];

        foreach ($raw_addons as $addon) {
            if (!is_array($addon)) {
                continue;
            }

            $addon_id = intval($addon['addon_id'] ?? 0);
            if ($addon_id <= 0) {
                continue;
            }

            $enabled_raw = strtolower(trim((string) ($addon['enabled'] ?? '')));
            $enabled = in_array($enabled_raw, ['1', 'true', 'on', 'yes'], true);

            // Some clients may omit an explicit enabled flag; keep rows with valid values.
            if (!$enabled && array_key_exists('enabled', $addon)) {
                continue;
            }

            $quantity = floatval($addon['quantity'] ?? 1);
            if ($quantity <= 0) {
                continue;
            }

            $normalized[] = [
                'addon_id' => $addon_id,
                'quantity' => $quantity,
                'unit_price' => floatval($addon['unit_price'] ?? 0),
                'description' => sanitize_text_field($addon['description'] ?? ''),
            ];
        }

        return $normalized;
    }


}