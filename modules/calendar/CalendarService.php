<?php

class CalendarService {

    private $booking_service;
    private $room_repository;
    private $customer_service;
    private $client_admin_service;

    public function __construct(
        BookingService $booking_service,
        RoomRepository $room_repository,
        CustomerService $customer_service,
        ClientAdminService $client_admin_service
    ) {
        $this->booking_service = $booking_service;
        $this->room_repository = $room_repository;
        $this->customer_service = $customer_service;
        $this->client_admin_service = $client_admin_service;
    }

    public function get_events($start, $end, $context = 'public', $viewer_user_id = 0, $filters = []): array {
        $bookings = $this->booking_service->get_between($start, $end, $context, $filters);
        $room_names = $this->get_room_names();
        $viewer_scope = $this->resolve_viewer_scope($context, $viewer_user_id);
        $default_label = $this->get_private_booking_label();
        $events = [];

        foreach ((array) $bookings as $booking) {
            $event = $this->map_booking_to_event($booking, $context, $room_names, $viewer_scope, $default_label);
            $events[] = $event;
        }

        return $events;
    }

    public function get_public_feed_events($start, $end, $viewer_user_id = 0, $filters = [], $default_label = 'Private booking'): array {
        $context = $viewer_user_id > 0 ? 'portal' : 'public';
        $status_filters = [BookingStatus::CONFIRMED, BookingStatus::PENDING, BookingStatus::COMPLETED];
        $filters = wp_parse_args($filters, [
            'room_id' => 0,
            'venue_id' => 0,
            'statuses' => $status_filters,
        ]);

        $bookings = $this->booking_service->get_between($start, $end, $context, $filters);
        $room_meta = $this->get_room_metadata();
        $viewer_scope = $this->resolve_viewer_scope($context, $viewer_user_id);
        $events = [];

        foreach ((array) $bookings as $booking) {
            $events[] = $this->map_booking_to_public_feed_event($booking, $room_meta, $viewer_scope, $default_label, $context);
        }

        return $events;
    }

    public function create_event($request, $context = 'admin', $viewer_user_id = 0): array|WP_Error {
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

        $data = [
            'start_date' => $start_date,
            'start_time' => $start_time,
            'end_date' => $end_date,
            'end_time' => $end_time,
            'description' => sanitize_text_field($request['text'] ?? ''),
            'status' => sanitize_text_field($request['status'] ?? BookingStatus::PENDING),
            'room_id' => intval($request['room_id'] ?? 0),
            'customer_id' => intval($request['customer_id'] ?? 0),
            'organisation_id' => intval($request['organisation_id'] ?? 0),
            'addons' => $addons,
            'is_recurring' => $is_recurring ? 1 : 0,
            'recurrence_type' => $recurrence_type,
            'recurrence_interval' => max(1, $recurrence_interval),
            'recurrence_week' => sanitize_text_field($request['recurrence_week'] ?? ''),
            'recurrence_day' => sanitize_text_field($request['recurrence_day'] ?? ''),
            'recurrence_end_type' => sanitize_text_field($request['recurrence_end_type'] ?? 'date'),
            'recurrence_end_date' => sanitize_text_field($request['recurrence_end_date'] ?? ''),
            'max_occurrences' => intval($request['max_occurrences'] ?? 0),
        ];

        if (array_key_exists('public', $request)) {
            $data['public'] = intval($request['public']);
        }

        if ($context === 'portal') {
            $is_client_admin = $this->client_admin_service->can_administer_blog($viewer_user_id, get_current_blog_id());

            if (!$is_client_admin) {
                $portal_customer = $this->customer_service->get_by_user_id($viewer_user_id);

                if (empty($portal_customer['Id'])) {
                    return new WP_Error('validation', __('No customer profile found', 'my-village-hall'));
                }

                $allowed_org_ids = $this->resolve_allowed_organisation_ids($viewer_user_id, (int) $portal_customer['Id']);
                $selected_org_id = (int) $data['organisation_id'];

                if (!empty($allowed_org_ids) && !in_array($selected_org_id, $allowed_org_ids, true)) {
                    $selected_org_id = (int) $allowed_org_ids[0];
                }

                $data['customer_id'] = (int) $portal_customer['Id'];
                $data['organisation_id'] = $selected_org_id;
                $data['status'] = BookingStatus::PENDING;
            }
        }

        $validation = $this->validate_calendar_booking_payload($data);
        if (is_wp_error($validation)) {
            return $validation;
        }

        $id = $this->booking_service->save($data);
        if (is_wp_error($id)) {
            return $id;
        }

        $response = ['id' => $id];
        $warnings = $this->booking_service->get_last_warnings();
        if (!empty($warnings)) {
            $response['warning'] = implode(' ', $warnings);
        }

        return $response;
    }

    public function update_event($request) {
        [$start_date, $start_time] = $this->split_datetime($request['start'] ?? '');
        [$end_date, $end_time] = $this->split_datetime($request['end'] ?? '', $start_date);

        $data = [
            'booking_id' => sanitize_text_field($request['booking_id'] ?? $request['id'] ?? ''),
            'start_date' => $start_date,
            'start_time' => $start_time,
            'end_date' => $end_date,
            'end_time' => $end_time,
            'description' => sanitize_text_field($request['text'] ?? ''),
            'room_id' => intval($request['room_id'] ?? 0),
            'customer_id' => intval($request['customer_id'] ?? 0),
            'organisation_id' => intval($request['organisation_id'] ?? 0),
        ];

        $validation = $this->validate_calendar_update_payload($data);
        if (is_wp_error($validation)) {
            return $validation;
        }

        $id = $this->booking_service->save($data);
        if (is_wp_error($id)) {
            return $id;
        }

        return ['id' => $id];
    }

    private function get_room_names() {
        $room_meta = $this->get_room_metadata();
        $room_names = [];

        foreach ($room_meta as $room_id => $meta) {
            $room_names[$room_id] = $meta['name'];
        }

        return $room_names;
    }

    private function get_room_metadata() {
        $rooms = $this->room_repository->get_all_with_venues();
        $room_meta = [];

        foreach ((array) $rooms as $room) {
            $room_id = isset($room['Id']) ? (int) $room['Id'] : 0;
            if ($room_id <= 0) {
                continue;
            }

            $room_meta[$room_id] = [
                'name' => isset($room['Name']) ? (string) $room['Name'] : '',
                'venue' => isset($room['VenueName']) ? (string) $room['VenueName'] : '',
            ];
        }

        return $room_meta;
    }

    private function resolve_viewer_scope($context, $viewer_user_id) {
        $scope = [
            'customer_id' => 0,
            'organisation_ids' => [],
            'is_client_admin' => false,
        ];

        if ($context !== 'portal' || $viewer_user_id <= 0) {
            return $scope;
        }

        $scope['is_client_admin'] = $this->client_admin_service->can_administer_blog($viewer_user_id, get_current_blog_id());

        if ($scope['is_client_admin']) {
            return $scope;
        }

        $viewer_customer = $this->customer_service->get_by_user_id($viewer_user_id);
        $scope['customer_id'] = (int) ($viewer_customer['Id'] ?? 0);

        $orgs_by_user = $this->customer_service->get_organisations_for_user_id($viewer_user_id);
        $scope['organisation_ids'] = array_map(static function ($org) {
            return (int) ($org['Id'] ?? 0);
        }, is_array($orgs_by_user) ? $orgs_by_user : []);

        if ($scope['customer_id'] > 0) {
            $orgs_by_customer = $this->customer_service->get_organisations_for_customer($scope['customer_id']);
            $scope['organisation_ids'] = array_merge(
                $scope['organisation_ids'],
                array_map(static function ($org) {
                    return (int) ($org['Id'] ?? 0);
                }, is_array($orgs_by_customer) ? $orgs_by_customer : [])
            );
        }

        $scope['organisation_ids'] = array_values(array_unique(array_filter($scope['organisation_ids'])));

        return $scope;
    }

    private function map_booking_to_event($booking, $context, $room_names, $viewer_scope, $default_label) {
        $room_id_raw = isset($booking['RoomId']) ? (string) $booking['RoomId'] : '';
        $room_id = (int) $room_id_raw;
        $resource_id = $room_id_raw !== '' ? $room_id_raw : (string) $room_id;
        $room_name = $room_names[$room_id] ?? '';
        $description = isset($booking['Description']) ? (string) $booking['Description'] : '';
        $is_public = !empty($booking['Public']);
        $booking_customer_id = isset($booking['CustomerId']) ? (int) $booking['CustomerId'] : 0;
        $organisation_id = isset($booking['OrganisationId']) ? (int) $booking['OrganisationId'] : 0;
        $can_view_private = $this->can_view_private_booking(
            $context,
            $is_public,
            $booking_customer_id,
            $organisation_id,
            $viewer_scope
        );

        $display_text = $description;
        $safe_description = $description;

        if ($context === 'portal' && !$is_public && !$can_view_private) {
            $display_text = $default_label;
            $safe_description = '';
        }

        $event = [
            'id' => $booking['Id'],
            'text' => $display_text,
            'start' => $booking['StartDate'] . 'T' . $booking['StartTime'],
            'end' => $booking['EndDate'] . 'T' . $booking['EndTime'],
            'resource' => $resource_id,
            'tags' => [
                'roomId' => $resource_id,
                'roomName' => $room_name,
                'description' => $safe_description,
                'isPublic' => $is_public,
                'organisationId' => $organisation_id,
                'canViewPrivate' => $can_view_private,
                'privateLabel' => $default_label,
            ],
        ];

        if ($context === 'public') {
            unset($event['id']);
            $event['text'] = 'Booked';
        }

        return $event;
    }

    private function get_private_booking_label(): string {
        $default_label = myvh_setting(
            'general.public_calendar_booking_label',
            __('Private booking', 'my-village-hall')
        );

        $default_label = apply_filters('myvh_public_calendar_booking_label', $default_label);
        $default_label = sanitize_text_field((string) $default_label);

        if ($default_label === '') {
            return __('Private booking', 'my-village-hall');
        }

        return $default_label;
    }

    private function can_view_private_booking($context, $is_public, $booking_customer_id, $organisation_id, $viewer_scope) {
        if ($context === 'admin') {
            return true;
        }

        if ($is_public) {
            return true;
        }

        if ($context !== 'portal') {
            return false;
        }

        if (!empty($viewer_scope['is_client_admin'])) {
            return true;
        }

        return !empty($viewer_scope['customer_id'])
            && $booking_customer_id === (int) $viewer_scope['customer_id'];
    }

    private function map_booking_to_public_feed_event($booking, $room_meta, $viewer_scope, $default_label, $context) {
        $room_id = isset($booking['RoomId']) ? (int) $booking['RoomId'] : 0;
        $room_name = $room_meta[$room_id]['name'] ?? (string) ($booking['RoomName'] ?? '');
        $venue_name = $room_meta[$room_id]['venue'] ?? (string) ($booking['VenueName'] ?? '');
        $description = isset($booking['Description']) ? (string) $booking['Description'] : '';
        $is_public = !empty($booking['Public']) || !empty($booking['IsPublic']);
        $booking_customer_id = isset($booking['CustomerId']) ? (int) $booking['CustomerId'] : 0;
        $organisation_id = isset($booking['OrganisationId']) ? (int) $booking['OrganisationId'] : 0;
        $can_view_private = $this->can_view_private_booking(
            $context,
            $is_public,
            $booking_customer_id,
            $organisation_id,
            $viewer_scope
        );

        $text = $default_label;
        if ($is_public || $can_view_private) {
            $text = $description !== '' ? $description : $room_name;
        }

        return [
            'id' => (int) $booking['Id'],
            'start' => $booking['StartDate'] . 'T' . $booking['StartTime'],
            'end' => $booking['EndDate'] . 'T' . $booking['EndTime'],
            'text' => sanitize_text_field((string) $text),
            'resource' => $room_id,
            'tags' => [
                'roomId' => $room_id,
                'room' => $room_name,
                'venue' => $venue_name,
                'status' => $booking['Status'] ?? '',
                'isPublic' => $is_public,
                'canViewPrivate' => $can_view_private,
                'description' => $description,
            ],
        ];
    }

    private function resolve_allowed_organisation_ids($viewer_user_id, $viewer_customer_id) {
        $organisation_ids = array_map(static function ($org) {
            return (int) ($org['Id'] ?? 0);
        }, (array) $this->customer_service->get_organisations_for_user_id($viewer_user_id));

        if ($viewer_customer_id > 0) {
            $organisation_ids = array_merge(
                $organisation_ids,
                array_map(static function ($org) {
                    return (int) ($org['Id'] ?? 0);
                }, (array) $this->customer_service->get_organisations_for_customer($viewer_customer_id))
            );
        }

        return array_values(array_unique(array_filter($organisation_ids)));
    }

    private function validate_calendar_booking_payload($data) {
        if (empty($data['room_id'])) {
            return new WP_Error('validation', __('Room is required', 'my-village-hall'));
        }

        if (empty($data['customer_id'])) {
            return new WP_Error('validation', __('Customer is required', 'my-village-hall'));
        }

        if (empty($data['organisation_id'])) {
            return new WP_Error('validation', __('Organisation is required', 'my-village-hall'));
        }

        if (!empty($data['is_recurring'])) {
            if (empty($data['recurrence_type'])) {
                return new WP_Error('validation', __('Recurrence type is required', 'my-village-hall'));
            }

            if (($data['recurrence_end_type'] ?? '') === 'count' && intval($data['max_occurrences'] ?? 0) < 1) {
                return new WP_Error('validation', __('Max occurrences must be at least 1', 'my-village-hall'));
            }

            if (($data['recurrence_end_type'] ?? '') !== 'count' && empty($data['recurrence_end_date'])) {
                return new WP_Error('validation', __('Recurrence end date is required', 'my-village-hall'));
            }
        }

        return true;
    }

    private function validate_calendar_update_payload($data) {
        if (empty($data['room_id'])) {
            return new WP_Error('validation', __('Room is required', 'my-village-hall'));
        }

        if (empty($data['customer_id'])) {
            return new WP_Error('validation', __('Customer is required', 'my-village-hall'));
        }

        return true;
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
