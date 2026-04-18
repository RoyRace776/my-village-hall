<?php
namespace MYVH\Calendar;

use MYVH\Availability\AvailabilityService;
use MYVH\Bookings\Booking;
use MYVH\Bookings\BookingService;
use MYVH\Rooms\RoomRepository;
use MYVH\Rooms\RoomColour;
use MYVH\Customers\CustomerService;
use MYVH\Portal\ClientAdminService;
use MYVH\Bookings\BookingStatus;

use WP_Error;

class CalendarService {

    private $booking_service;
    private $room_repository;
    private $availability_service;
    private $customer_service;
    private $client_admin_service;

    public function __construct(
        BookingService $booking_service,
        RoomRepository $room_repository,
        AvailabilityService $availability_service,
        CustomerService $customer_service,
        ClientAdminService $client_admin_service
    ) {
        $this->booking_service = $booking_service;
        $this->room_repository = $room_repository;
        $this->availability_service = $availability_service;
        $this->customer_service = $customer_service;
        $this->client_admin_service = $client_admin_service;
    }

    // Internal cache for room metadata during a request, used by is_room_visible()
    private $_room_meta_cache = [];

    public function get_events($start, $end, $context = 'public', $viewer_user_id = 0, $filters = []): array {
        /** @var Booking[] $bookings */
        $bookings = $this->booking_service->get_between($start, $end, $context, $filters);
        $room_meta = $this->get_room_metadata();
        $this->_room_meta_cache = $room_meta;
        $viewer_scope = $this->resolve_viewer_scope($context, $viewer_user_id);
        $default_label = $this->get_private_booking_label();
        $events = [];

        $show_separately = (bool) $this->get_setting_with_fallback('booking.show_buffer_times_separately', 'booking.buffers.show_buffer_times_separately', false);
        $setup_minutes   = (int)  $this->get_setting_with_fallback('booking.set_up_minutes', 'booking.buffers.set_up_minutes', 0);
        $tidy_minutes    = (int)  $this->get_setting_with_fallback('booking.tidy_up_minutes', 'booking.buffers.tidy_up_minutes', 0);
        $buffer_text     = (string) $this->get_setting_with_fallback('booking.buffer_text', 'booking.buffers.buffer_text', 'Buffer');

        foreach ((array) $bookings as $booking) {
            $room_id = $booking->roomId();
            if (!$this->is_room_visible($room_id, $context, $viewer_scope)) {
                continue;
            }
            $event = $this->map_booking_to_event($booking, $context, $room_meta, $viewer_scope, $default_label);

            [$eff_setup, $eff_tidy] = $this->compute_effective_buffers(
                $booking,
                $room_id,
                $room_meta[$room_id] ?? [],
                $setup_minutes,
                $tidy_minutes
            );

            if ( $eff_setup > 0 || $eff_tidy > 0 ) {
                if ( $show_separately ) {
                    $events[] = $event;
                    foreach ( $this->generate_separate_buffer_events(
                        $booking, $event, $eff_setup, $eff_tidy, $buffer_text, $room_meta[$room_id] ?? []
                    ) as $buf_event ) {
                        $events[] = $buf_event;
                    }
                } else {
                    $events[] = $this->apply_merged_buffers( $event, $booking, $eff_setup, $eff_tidy );
                }
            } else {
                $events[] = $event;
            }
        }

        return $events;
    }

    public function get_public_feed_events($start, $end, $viewer_user_id = 0, $filters = [], $default_label = 'Private booking'): array {
        $context = $viewer_user_id > 0 ? 'portal' : 'public';
        $status_filters = [
            BookingStatus::CONFIRMED->value,
            BookingStatus::PENDING->value,
            BookingStatus::COMPLETED->value,
        ];
        $filters = wp_parse_args($filters, [
            'room_id' => 0,
            'venue_id' => 0,
            'statuses' => $status_filters,
        ]);

        /** @var Booking[] $bookings */
        $bookings = $this->booking_service->get_between($start, $end, $context, $filters);
        $room_meta = $this->get_room_metadata();
        $this->_room_meta_cache = $room_meta;
        $viewer_scope = $this->resolve_viewer_scope($context, $viewer_user_id);

        $show_separately = (bool) $this->get_setting_with_fallback('booking.show_buffer_times_separately', 'booking.buffers.show_buffer_times_separately', false);
        $setup_minutes   = (int)  $this->get_setting_with_fallback('booking.set_up_minutes', 'booking.buffers.set_up_minutes', 0);
        $tidy_minutes    = (int)  $this->get_setting_with_fallback('booking.tidy_up_minutes', 'booking.buffers.tidy_up_minutes', 0);
        $buffer_text     = (string) $this->get_setting_with_fallback('booking.buffer_text', 'booking.buffers.buffer_text', 'Buffer');

        $events = [];

        foreach ((array) $bookings as $booking) {
            $room_id = $booking->roomId();
            if (!$this->is_room_visible($room_id, $context, $viewer_scope)) {
                continue;
            }
            $event = $this->map_booking_to_public_feed_event($booking, $room_meta, $viewer_scope, $default_label, $context);

            [$eff_setup, $eff_tidy] = $this->compute_effective_buffers(
                $booking,
                $room_id,
                $room_meta[$room_id] ?? [],
                $setup_minutes,
                $tidy_minutes
            );

            if ( $eff_setup > 0 || $eff_tidy > 0 ) {
                if ( $show_separately ) {
                    $events[] = $event;
                    foreach ( $this->generate_separate_buffer_events(
                        $booking, $event, $eff_setup, $eff_tidy, $buffer_text, $room_meta[$room_id] ?? []
                    ) as $buf_event ) {
                        $events[] = $buf_event;
                    }
                } else {
                    $events[] = $this->apply_merged_buffers( $event, $booking, $eff_setup, $eff_tidy );
                }
            } else {
                $events[] = $event;
            }
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
            'status' => sanitize_text_field($request['status'] ?? BookingStatus::PENDING->value),
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

        if ($this->can_manage_no_invoice_required($context, $viewer_user_id) && array_key_exists('no_invoice_required', $request)) {
            $data['no_invoice_required'] = intval($request['no_invoice_required']);
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
                $data['status'] = BookingStatus::PENDING->value;
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
        $booking_id = intval($request['booking_id'] ?? $request['id'] ?? 0);
        $existing_booking = $booking_id > 0 ? $this->booking_service->get_by_id($booking_id) : null;
        $current_status = $existing_booking instanceof Booking
            ? $existing_booking->status()->value
            : BookingStatus::PENDING->value;
        $addons = $this->normalize_addons($request['addons'] ?? []);

        $data = [
            'booking_id' => $booking_id,
            'start_date' => $start_date,
            'start_time' => $start_time,
            'end_date' => $end_date,
            'end_time' => $end_time,
            'description' => sanitize_text_field($request['text'] ?? ''),
            'status' => sanitize_text_field($request['status'] ?? $current_status),
            'room_id' => intval($request['room_id'] ?? 0),
            'customer_id' => intval($request['customer_id'] ?? 0),
            'organisation_id' => intval($request['organisation_id'] ?? 0),
            'addons' => $addons,
            'edit_scope' => sanitize_text_field($request['edit_scope'] ?? ''),
        ];

        if (array_key_exists('public', $request)) {
            $data['public'] = intval($request['public']);
        }

        if ($this->can_manage_no_invoice_required(
            sanitize_text_field($request['context'] ?? 'admin'),
            get_current_user_id()
        ) && array_key_exists('no_invoice_required', $request)) {
            $data['no_invoice_required'] = intval($request['no_invoice_required']);
        }

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

    private function can_manage_no_invoice_required(string $context, int $viewer_user_id): bool {
        if ($context !== 'portal') {
            return current_user_can('manage_myvh');
        }

        return $this->client_admin_service->can_administer_blog($viewer_user_id, get_current_blog_id());
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
                'name'         => isset($room['Name'])        ? (string) $room['Name']        : '',
                'venue'        => isset($room['VenueName'])   ? (string) $room['VenueName']   : '',
                'colour'       => RoomColour::resolve($room['Colour'] ?? '', $room_id),
                'is_public'    => !empty($room['IsPublic']),
                'opening_time' => isset($room['OpeningTime']) ? (string) $room['OpeningTime'] : '',
                'closing_time' => isset($room['ClosingTime']) ? (string) $room['ClosingTime'] : '',
            ];
        }

        return $room_meta;
    }

    /**
     * Returns true if the room is visible to the viewer in the given context.
     * Private rooms are only visible to admins and client admins.
     */
    private function is_room_visible($room_id, $context, $viewer_scope): bool {
        // Rooms not found in metadata are treated as public (safe default)
        if (!isset($this->_room_meta_cache[$room_id])) {
            return true;
        }

        if ($this->_room_meta_cache[$room_id]['is_public']) {
            return true;
        }

        // Private room: admins always see it
        if ($context === 'admin') {
            return true;
        }

        // Private room in portal: only client admins see it
        if ($context === 'portal' && !empty($viewer_scope['is_client_admin'])) {
            return true;
        }

        return false;
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

    private function map_booking_to_event(Booking $booking, $context, $room_meta, $viewer_scope, $default_label) {
        $room_id_raw = (string) $booking->roomId();
        $room_id = (int) $room_id_raw;
        $resource_id = $room_id_raw !== '' ? $room_id_raw : (string) $room_id;
        $status = strtolower( sanitize_text_field( $booking->status()->value ) );
        $room_name = $room_meta[$room_id]['name'] ?? '';
        $room_colour = $room_meta[$room_id]['colour'] ?? RoomColour::fallback($room_id);
        $description = $booking->description();
        $is_public = $booking->isPublic();
        $booking_customer_id = $booking->customerId();
        $organisation_id = $booking->organisationId();
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
            'id' => $booking->id(),
            'text' => $display_text,
            'start' => $booking->start()->format('Y-m-d\TH:i:s'),
            'end' => $booking->end()->format('Y-m-d\TH:i:s'),
            'resource' => $resource_id,
            'tags' => [
                'roomId' => $resource_id,
                'roomName' => $room_name,
                'roomColour' => $room_colour,
                'status' => $status,
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

    /**
     * Compute the effective setup and tidy buffer minutes for a booking.
     *
     * Setup is waived (0) when the booking starts exactly at the room's opening
     * time; tidy is waived when it ends exactly at closing time.
     *
    * @param Booking $booking        Booking entity.
     * @param array $room_meta_entry  Room metadata entry (opening_time, closing_time).
     * @param int   $setup_minutes    Configured setup minutes from settings.
     * @param int   $tidy_minutes     Configured tidy minutes from settings.
     * @return array{int, int}        [effective_setup, effective_tidy].
     */
    private function compute_effective_buffers( Booking $booking, int $room_id, array $room_meta_entry, int $setup_minutes, int $tidy_minutes ): array {
        if ( $setup_minutes <= 0 && $tidy_minutes <= 0 ) {
            return [ 0, 0 ];
        }

        $booking_start = $booking->start()->format('H:i:s');
        $booking_end   = $booking->end()->format('H:i:s');

        $room_open = $this->normalize_time((string) ($room_meta_entry['opening_time'] ?? '00:00:00')) ?? '00:00:00';
        $room_close = $this->normalize_time((string) ($room_meta_entry['closing_time'] ?? '23:59:59')) ?? '23:59:59';

        if ($room_id > 0) {
            $start_date = sanitize_text_field($booking->start()->format('Y-m-d'));
            $end_date = sanitize_text_field($booking->end()->format('Y-m-d'));

            if ($start_date !== '') {
                $start_day_hours = $this->availability_service->get_effective_room_hours_for_date($room_id, $start_date);
                if (!is_wp_error($start_day_hours) && empty($start_day_hours['is_closed'])) {
                    $start_open = $this->normalize_time((string) ($start_day_hours['opening_time'] ?? ''));
                    if ($start_open !== null) {
                        $room_open = $start_open;
                    }
                }
            }

            if ($end_date !== '') {
                $end_day_hours = $this->availability_service->get_effective_room_hours_for_date($room_id, $end_date);
                if (!is_wp_error($end_day_hours) && empty($end_day_hours['is_closed'])) {
                    $end_close = $this->normalize_time((string) ($end_day_hours['closing_time'] ?? ''));
                    if ($end_close !== null) {
                        $room_close = $end_close;
                    }
                }
            }
        }

        $eff_setup = ( $booking_start === $room_open  ) ? 0 : $setup_minutes;
        $eff_tidy  = ( $booking_end   === $room_close ) ? 0 : $tidy_minutes;

        return [ $eff_setup, $eff_tidy ];
    }

    private function normalize_time(string $value): ?string {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $ts = strtotime('1970-01-01 ' . $value);
        if ($ts === false) {
            return null;
        }

        return date('H:i:s', $ts);
    }

    private function get_setting_with_fallback(string $primary_key, string $legacy_key, $default = null) {
        $primary = myvh_setting($primary_key, null);
        if ($primary !== null && $primary !== '') {
            return $primary;
        }

        return myvh_setting($legacy_key, $default);
    }

    /**
     * Extend a booking calendar event's start/end to include buffer windows
     * (merged display mode). Stores actual booking times in tags so tooltips
     * can still show the real booking time range.
     */
    private function apply_merged_buffers( array $event, Booking $booking, int $eff_setup, int $eff_tidy ): array {
        $actual_start = $booking->start()->format('Y-m-d\TH:i:s');
        $actual_end   = $booking->end()->format('Y-m-d\TH:i:s');

        if ( $eff_setup > 0 ) {
            $ts = $booking->start()->getTimestamp() - $eff_setup * 60;
            $event['start'] = $booking->start()->setTimestamp($ts)->format('Y-m-d\TH:i:s');
        }

        if ( $eff_tidy > 0 ) {
            $ts = $booking->end()->getTimestamp() + $eff_tidy * 60;
            $event['end'] = $booking->end()->setTimestamp($ts)->format('Y-m-d\TH:i:s');
        }

        // Preserve actual booking times in tags for front-end tooltip rendering.
        $event['tags']['actualStart'] = $actual_start;
        $event['tags']['actualEnd']   = $actual_end;

        return $event;
    }

    /**
     * Build synthetic setup and/or tidy buffer calendar events for a booking
     * (separate display mode). These events carry no database ID and are
     * marked with tags.isBuffer = true so the front-end can identify them as
     * non-interactive visual aids.
     *
     * Hover text uses the room name per specification; event text uses the
     * configured buffer label.
     *
     * @return array  Zero, one, or two synthetic event arrays.
     */
    private function generate_separate_buffer_events( Booking $booking, array $booking_event, int $eff_setup, int $eff_tidy, string $buffer_text, array $room_meta_entry ): array {
        $events     = [];
        $resource   = $booking_event['resource'] ?? '';
        $room_name  = $room_meta_entry['name']   ?? '';
        $room_colour = $room_meta_entry['colour'] ?? '';
        $safe_text  = sanitize_text_field( $buffer_text );

        $actual_start = $booking->start()->format('Y-m-d\TH:i:s');
        $actual_end   = $booking->end()->format('Y-m-d\TH:i:s');
        $booking_id = $booking->id();

        if ( $eff_setup > 0 ) {
            $ts = $booking->start()->getTimestamp() - $eff_setup * 60;
            $events[] = [
                'id'       => 'buffer_setup_' . $booking_id,
                'text'     => $safe_text,
                'start'    => $booking->start()->setTimestamp($ts)->format('Y-m-d\TH:i:s'),
                'end'      => $actual_start,
                'resource' => $resource,
                'tags'     => [
                    'isBuffer'   => true,
                    'bufferType' => 'setup',
                    'roomId'     => $resource,
                    'roomName'   => $room_name,
                    'roomColour' => $room_colour,
                    'status'     => 'buffer',
                ],
            ];
        }

        if ( $eff_tidy > 0 ) {
            $ts = $booking->end()->getTimestamp() + $eff_tidy * 60;
            $events[] = [
                'id'       => 'buffer_tidy_' . $booking_id,
                'text'     => $safe_text,
                'start'    => $actual_end,
                'end'      => $booking->end()->setTimestamp($ts)->format('Y-m-d\TH:i:s'),
                'resource' => $resource,
                'tags'     => [
                    'isBuffer'   => true,
                    'bufferType' => 'tidy',
                    'roomId'     => $resource,
                    'roomName'   => $room_name,
                    'roomColour' => $room_colour,
                    'status'     => 'buffer',
                ],
            ];
        }

        return $events;
    }

    private function get_private_booking_label(): string {        $default_label = myvh_setting(
            'calendar.public_calendar_booking_label',
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

    private function map_booking_to_public_feed_event(Booking $booking, $room_meta, $viewer_scope, $default_label, $context) {
        $room_id = $booking->roomId();
        $status = strtolower( sanitize_text_field( $booking->status()->value ) );
        $room_name = $room_meta[$room_id]['name'] ?? $booking->roomName();
        $venue_name = $room_meta[$room_id]['venue'] ?? $booking->venueName();
        $room_colour = $room_meta[$room_id]['colour'] ?? RoomColour::fallback($room_id);
        $description = $booking->description();
        $is_public = $booking->isPublic() || $booking->roomIsPublic();
        $booking_customer_id = $booking->customerId();
        $organisation_id = $booking->organisationId();
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
            'id' => $booking->id(),
            'start' => $booking->start()->format('Y-m-d\TH:i:s'),
            'end' => $booking->end()->format('Y-m-d\TH:i:s'),
            'text' => sanitize_text_field((string) $text),
            'resource' => $room_id,
            'tags' => [
                'roomId' => $room_id,
                'room' => $room_name,
                'roomColour' => $room_colour,
                'venue' => $venue_name,
                'status' => $status,
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
