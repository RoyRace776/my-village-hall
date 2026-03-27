<?php
if (!defined('ABSPATH')) exit;

require_once MYVH_PLUGIN_DIR . 'modules/events/booking-events.php';
require_once MYVH_PLUGIN_DIR . 'modules/events/class-myvh-event-dispatcher.php';
require_once MYVH_PLUGIN_DIR . 'modules/bookings/class-myvh-booking-status.php';

class Booking_Service {

    private $room_service;
    private $booking_repo;
    private $booking_charge_repo;
    private $booking_addon_repo;
    private $addon_repo;
    private $addon_service;
    private $validator;
    private $availability;
    private $room_rules;
    private $pricing;
    private $customer_repo;
    private $organisation_repo;
    private $recurring_pattern_service = null;
    private $last_warnings = [];

    public function __construct(
        Room_Service $room_service,
        Booking_Repository $booking_repo,
        Booking_Charge_Repository $booking_charge_repo,
        Booking_Addon_Repository $booking_addon_repo,
        Addon_Repository $addon_repo,
        Addon_Service $addon_service,
        Booking_Validator $validator,
        Availability_Service $availability,
        Room_Rules_Service $room_rules,
        Pricing_Service $pricing,
        Customer_Repository $customer_repo,
        Organisation_Repository $organisation_repo,
        Recurring_Pattern_Service $recurring_pattern_service
    ) {
        $this->room_service = $room_service;
        $this->booking_repo = $booking_repo;
        $this->booking_charge_repo = $booking_charge_repo;
        $this->booking_addon_repo = $booking_addon_repo;
        $this->addon_repo = $addon_repo;
        $this->addon_service = $addon_service;
        $this->validator = $validator;
        $this->availability = $availability;
        $this->room_rules = $room_rules;
        $this->pricing = $pricing;
        $this->customer_repo = $customer_repo;
        $this->organisation_repo = $organisation_repo;
        $this->recurring_pattern_service = $recurring_pattern_service;
    }

    public function save($data): int|WP_Error {

        $this->last_warnings = [];
        $this->booking_repo->begin();
        try {
            // Dispatch before create/update event
            if (!empty($data['booking_id'])) {
                Event_Dispatcher::dispatch(
                    Booking_Events::BEFORE_UPDATE,
                    $data
                );
            } else {
                Event_Dispatcher::dispatch(
                    Booking_Events::BEFORE_CREATE,
                    $data
                );
            }

            $validation_result = $this->validator->validate($data);
            if (is_wp_error($validation_result)) {
                $this->booking_repo->rollback();
                return $validation_result;
            }

            $customer_id = intval($data['customer_id']);
            $organisation_id = !empty($data['organisation_id']) ? intval($data['organisation_id']) : 0;

            $room = $this->room_service->get($data['room_id']);
            $start_date = sanitize_text_field($data['start_date']);
            $end_date = !empty($data['end_date']) ? sanitize_text_field($data['end_date']) : $start_date;
            $start_time = sanitize_text_field($data['start_time']);
            $end_time = sanitize_text_field($data['end_time']);

            if (!isset($data['chargeable_hours'])) {
                $chargeable_hours = $this->calculate_chargeable_hours(
                    $start_date,
                    $start_time,
                    $end_date,
                    $end_time,
                    $room
                );
            } else {
                $chargeable_hours = $data['chargeable_hours'];
            }

            $booking_id = !empty($data['booking_id']) ? intval($data['booking_id']) : 0;
            $public_visibility = $this->resolve_public_visibility($data, $organisation_id, $booking_id);

            $record = [
                'CustomerId'        => $customer_id,
                'OrganisationId'    => $organisation_id > 0 ? $organisation_id : null,
                'RoomId'            => intval($data['room_id']),
                'Status'            => sanitize_text_field($data['status'] ?? (myvh_setting('booking.require_approval', true) ? BookingStatus::PENDING : BookingStatus::CONFIRMED)),
                'StartDate'         => $start_date,
                'EndDate'           => $end_date,
                'StartTime'         => $start_time,
                'EndTime'           => $end_time,
                'Description'       => sanitize_textarea_field($data['description'] ?? ''),
                'Public'            => $public_visibility,
                'ChargeableHours'   => $chargeable_hours
            ];

            // Update existing booking
            if (!empty($data['booking_id'])) {
                $result = $this->update_booking($data, $record);
            } else {
                $result = $this->create_booking($data, $record);
            }

            if (is_wp_error($result)) {
                $this->booking_repo->rollback();
                return $result;
            }

            $this->booking_repo->commit();
            return $result;
        } catch (Exception $e) {
            $this->booking_repo->rollback();
            return new WP_Error('transaction', __('A database error occurred: ', 'my-village-hall') . $e->getMessage());
        }

    }

    private function update_booking($data, $record): int|WP_Error {

        // Capture the current status BEFORE overwriting the row, so that
        // dispatch_update_event() can compare old vs new correctly.
        $current_record = $this->booking_repo->get_by_id( intval( $data['booking_id'] ) );

        // Dispatch before status change if status is changing
        if (isset($current_record['Status']) && $current_record['Status'] !== $data['status']) {
            Event_Dispatcher::dispatch(
                Booking_Events::BEFORE_STATUS_CHANGE,
                [
                    'booking_id' => $data['booking_id'],
                    'old_status' => $current_record['Status'],
                    'new_status' => $data['status'],
                    'data' => $data
                ]
            );
        }

        $result = $this->booking_repo->update($record, ['Id' => intval($data['booking_id'])]);
        if ($result === false) {
            return new WP_Error('database', __('Failed to update booking', 'my-village-hall'));
        }

        // Addons before/after events
        Event_Dispatcher::dispatch(
            Booking_Events::BEFORE_ADDONS_CHANGE,
            [
                'booking_id' => $data['booking_id'],
                'addons' => $data['addons'] ?? [],
                'replace' => true
            ]
        );
        $this->save_addons(intval($data['booking_id']), $data['addons'] ?? [], true);
        Event_Dispatcher::dispatch(
            Booking_Events::AFTER_ADDONS_CHANGE,
            [
                'booking_id' => $data['booking_id'],
                'addons' => $data['addons'] ?? [],
                'replace' => true
            ]
        );

        $charge_result = $this->recalculate_booking_charges(intval($data['booking_id']));
        if (is_wp_error($charge_result)) {
            return $charge_result;
        }

        $this->dispatch_update_event($data, $current_record['Status'] ?? null);

        // After status change event
        if (isset($current_record['Status']) && $current_record['Status'] !== $data['status']) {
            Event_Dispatcher::dispatch(
                Booking_Events::AFTER_STATUS_CHANGE,
                [
                    'booking_id' => $data['booking_id'],
                    'old_status' => $current_record['Status'],
                    'new_status' => $data['status'],
                    'data' => $data
                ]
            );
        }

        Event_Dispatcher::dispatch(
            Booking_Events::AFTER_UPDATE,
            $data
        );

        return intval($data['booking_id']);

    }

    private function create_booking($data, $record): int|WP_Error {
        // Before create event already dispatched in save()
        $booking_id = $this->booking_repo->create($record);
        if ($booking_id === false) {
            return new WP_Error('database', __('Failed to create booking', 'my-village-hall'));
        }

        // Addons before/after events
        Event_Dispatcher::dispatch(
            Booking_Events::BEFORE_ADDONS_CHANGE,
            [
                'booking_id' => $booking_id,
                'addons' => $data['addons'] ?? [],
                'replace' => false
            ]
        );
        $this->save_addons($booking_id, $data['addons'] ?? []);
        Event_Dispatcher::dispatch(
            Booking_Events::AFTER_ADDONS_CHANGE,
            [
                'booking_id' => $booking_id,
                'addons' => $data['addons'] ?? [],
                'replace' => false
            ]
        );

        $charge_result = $this->recalculate_booking_charges($booking_id);
        if (is_wp_error($charge_result)) {
            $this->booking_addon_repo->delete_by_booking_id($booking_id);
            $this->booking_repo->delete($booking_id);
            return $charge_result;
        }

        Event_Dispatcher::dispatch(
            Booking_Events::CREATED,
            [
                'booking_id' => $booking_id,
                'room_id' => $data['room_id'],
                'start' => $data['start_time'],
                'end' => $data['end_time']
            ]
        );

        if ($data['status'] == BookingStatus::CONFIRMED) {
            Event_Dispatcher::dispatch(
                Booking_Events::CONFIRMED,
                [
                    'booking_id' => $booking_id,
                    'room_id' => $data['room_id'],
                    'start' => $data['start_time'],
                    'end' => $data['end_time']
                ]
            );
        }

        // Recurring pattern events
        if (!empty($data['is_recurring']) && $this->recurring_pattern_service) {
            Event_Dispatcher::dispatch(
                Booking_Events::BEFORE_RECURRING,
                [
                    'parent_booking_id' => $booking_id,
                    'data' => $data
                ]
            );
            $pattern_data = [
                'parent_booking_id'   => $booking_id,
                'recurrence_type'     => $data['recurrence_type'] ?? '',
                'recurrence_interval' => $data['recurrence_interval'] ?? 1,
                'recurrence_day'      => $data['recurrence_day'] ?? '',
                'recurrence_week'     => $data['recurrence_week'] ?? '',
                'start_date'          => $data['start_date'] ?? '',
                'is_active'           => 1,
            ];
            // Set end condition
            if (($data['recurrence_end_type'] ?? '') === 'date') {
                $pattern_data['end_date'] = $data['recurrence_end_date'] ?? '';
            } else {
                $pattern_data['max_occurrences'] = $data['max_occurrences'] ?? null;
            }
            $pattern_result = $this->recurring_pattern_service->save($pattern_data);
            if (is_wp_error($pattern_result)) {
                return $pattern_result;
            }
            $booking_results = $this->recurring_pattern_service->get_last_booking_results();
            if (is_array($booking_results)) {
                if (!empty($booking_results['conflicts'])) {
                    $this->last_warnings[] = sprintf(
                        __('Some recurring bookings were not created due to conflicts: %s', 'my-village-hall'),
                        implode(', ', $booking_results['conflicts'])
                    );
                }
                if (!empty($booking_results['errors'])) {
                    $this->last_warnings[] = implode(' ', $booking_results['errors']);
                }
            }
            Event_Dispatcher::dispatch(
                Booking_Events::AFTER_RECURRING,
                [
                    'parent_booking_id' => $booking_id,
                    'data' => $data
                ]
            );
        }

        Event_Dispatcher::dispatch(
            Booking_Events::AFTER_CREATE,
            $data + ['booking_id' => $booking_id]
        );

        return $booking_id;
    }

    /**
     * Recalculate and persist booking charge snapshot rows.
     *
     * TODO: Decide all lifecycle events that should trigger automatic
     * recalculation (status transitions, room/time edits, addon changes, etc.).
     *
     * @param int $booking_id
     * @return true|WP_Error
     */
    public function recalculate_booking_charges($booking_id): true|WP_Error {
        $booking_id = intval($booking_id);
        if ($booking_id <= 0) {
            return new WP_Error('validation', __('Invalid booking id for charge recalculation', 'my-village-hall'));
        }

        $snapshot = $this->pricing->get_charge_snapshot($booking_id);
        if (is_wp_error($snapshot)) {
            return $snapshot;
        }

        $deleted = $this->booking_charge_repo->delete_by_booking_id($booking_id);
        if ($deleted === false) {
            return new WP_Error('database', __('Failed to clear previous booking charge rows', 'my-village-hall'));
        }

        $created = $this->booking_charge_repo->create($snapshot);
        if ($created === false) {
            return new WP_Error('database', __('Failed to save booking charge snapshot', 'my-village-hall'));
        }

        return true;
    }

    private function resolve_public_visibility($data, $organisation_id, $booking_id): int {
        if (array_key_exists('public', $data)) {
            return !empty($data['public']) ? 1 : 0;
        }

        if ($booking_id > 0) {
            $existing = $this->booking_repo->get_by_id($booking_id);
            if (is_array($existing) && array_key_exists('Public', $existing)) {
                return !empty($existing['Public']) ? 1 : 0;
            }
        }

        if ($organisation_id > 0) {
            $organisation = $this->organisation_repo->get_by_id($organisation_id);
            if (is_array($organisation) && array_key_exists('DefaultPublic', $organisation)) {
                return !empty($organisation['DefaultPublic']) ? 1 : 0;
            }
        }

        return 0;
    }

    public function get_last_warnings(): array {
        return $this->last_warnings;
    }

    /**
     * Save addons for a booking. Optionally delete existing ones first (for updates).
     *
     * @param int   $booking_id
     * @param array $addons     Array of ['addon_id' => int, 'quantity' => float, 'unit_price' => float, 'description' => string]
     * @param bool  $replace    If true, delete all existing addons before saving
     */

    public function save_addons($booking_id, $addons, $replace = false): void {
        // Delegate to Addon_Service (implement a method if needed)
        if (!$this->addon_service) return;
        $this->addon_service->save_booking_addons($booking_id, $addons, $replace);
    }

    /**
     * Get all addons for a booking (with addon name and type).
     *
     * @param int $booking_id
     * @return array
     */
    public function get_addons_for_booking($booking_id): array {
        if (!$this->addon_service) return [];
        return $this->addon_service->get_addons_for_booking($booking_id);
    }

    public function get_by_id($booking_id): ?array {
        return $this->booking_repo->get_by_id($booking_id);
    }

    public function cancel($id): int|false {
        return $this->booking_repo->update(
            ['Status' => BookingStatus::CANCELLED],
            ['Id' => $id]
        );
    }

    public function delete($id): true|WP_Error {
        $booking_id = intval($id);
        if ($booking_id <= 0) {
            return new WP_Error('validation', __('Booking ID is required', 'my-village-hall'));
        }

        $this->booking_repo->begin();
        try {
            Event_Dispatcher::dispatch(
                Booking_Events::BEFORE_DELETE,
                ['booking_id' => $booking_id]
            );

            if ($this->booking_addon_repo && !$this->booking_addon_repo->delete_by_booking_id($booking_id)) {
                $this->booking_repo->rollback();
                return new WP_Error('database', __('Failed to remove booking addons', 'my-village-hall'));
            }

            if ($this->booking_charge_repo && !$this->booking_charge_repo->delete_by_booking_id($booking_id)) {
                $this->booking_repo->rollback();
                return new WP_Error('database', __('Failed to remove booking charges', 'my-village-hall'));
            }

            $deleted = $this->booking_repo->delete($booking_id);
            if (!$deleted) {
                $this->booking_repo->rollback();
                return new WP_Error('database', __('Failed to delete booking', 'my-village-hall'));
            }

            Event_Dispatcher::dispatch(
                Booking_Events::DELETED,
                ['booking_id' => $booking_id]
            );
            Event_Dispatcher::dispatch(
                Booking_Events::AFTER_DELETE,
                ['booking_id' => $booking_id]
            );

            $this->booking_repo->commit();
            return true;
        } catch (Exception $e) {
            $this->booking_repo->rollback();
            return new WP_Error('transaction', __('A database error occurred: ', 'my-village-hall') . $e->getMessage());
        }
    }

    public function get_all_with_details($args = []): array {
        return $this->booking_repo->get_all_with_details($args);
    }

    public function get_by_id_with_details(int $booking_id): ?array {
        if ($booking_id <= 0) {
            return null;
        }

        $results = $this->booking_repo->get_all_with_details([
            'booking_id' => $booking_id,
        ]);

        return $results[0] ?? null;
    }

    public function get_by_id_with_details_for_customer(int $booking_id, int $customer_id): ?array {
        if ($booking_id <= 0 || $customer_id <= 0) {
            return null;
        }

        $results = $this->booking_repo->get_all_with_details([
            'booking_id' => $booking_id,
            'customer_id' => $customer_id,
        ]);

        return $results[0] ?? null;
    }

    public function get_booking_list($filters = []): array {
        $defaults = [
            'status'      => '',
            'room_id'     => 0,
            'customer_id' => 0,
        ];

        $filters = wp_parse_args($filters, $defaults);

        $query_args = [
            'orderby'     => 'b.StartDate',
            'order'       => 'DESC',
            'status'      => $filters['status'],
            'room_id'     => $filters['room_id'],
            'customer_id' => $filters['customer_id'],
        ];

        $bookings = $this->booking_repo->get_all_with_details($query_args);

        $groups = $this->group_bookings($bookings);

        return [
            'groups'           => $groups,
            'total'            => count($bookings),
            'recurring_groups' => count(array_filter($groups, fn($g) => $g['type'] === 'recurring')),
        ];
    }

    public function get_between($start, $end, $context = null, $filters = []): array {

        $bookings = $this->booking_repo->get_between($start, $end, $context, $filters);
        return $bookings;
    }

    public function move_booking($id, $start, $end, $room): int|WP_Error {
        $room_id = intval($room);

        [$start_date, $start_time] = $this->split_datetime($start);
        [$end_date, $end_time] = $this->split_datetime($end, $start_date);

        if (!$room_id || !$start_date || !$start_time || !$end_date || !$end_time) {
            return new WP_Error('validation', __('Invalid booking movement data', 'my-village-hall'));
        }

        $room_record = $this->room_service->get($room_id);
        if (!$room_record) {
            return new WP_Error('validation', __('Room is required', 'my-village-hall'));
        }

        if ($start_date !== $end_date && empty($room_record['AllowMultiDayBookings'])) {
            return new WP_Error('validation', __('Multi day bookings are not allowed for this room', 'my-village-hall'));
        }

        $within_opening_hours = $this->availability->booking_within_opening_hours(
            $room_id,
            $start_time,
            $end_time
        );

        if (is_wp_error($within_opening_hours)) {
            return $within_opening_hours;
        }

        if (!$within_opening_hours) {
            return new WP_Error(
                'validation',
                sprintf(
                    __('Booking times must be within the room opening hours of %1$s to %2$s.', 'my-village-hall'),
                    substr((string) $room_record['OpeningTime'], 0, 5),
                    substr((string) $room_record['ClosingTime'], 0, 5)
                )
            );
        }

        $is_available = $this->availability->room_is_available(
            $room_id,
            $start_date,
            $start_time,
            $end_time,
            $end_date,
            intval($id)
        );

        if (!$is_available) {
            return new WP_Error('validation', __('This room is not available for the selected time.', 'my-village-hall'));
        }

        $return =  $this->booking_repo->move_booking($id, $start, $end, $room);

        if ($return <> 1) {
            return new WP_Error('Move booking', __('Something went wrong', 'my-village-hall'));
        }

        return $return;
    }

    public function can_delete($booking): array {
        if (empty($booking) || empty($booking['StartDate']) || empty($booking['StartTime'])) {
            return [
                'can_delete' => false,
                'reason' => 'Booking details are incomplete.',
            ];
        }

        $status = strtolower((string) ($booking['Status'] ?? ''));
        $start_ts = strtotime((string) $booking['StartDate'] . ' ' . (string) $booking['StartTime']);
        $now_ts = wp_timestamp();
        $min_notice_hours = max(0, intval(myvh_setting('booking.general.min_notice_hours', 24)));
        $min_notice_days = (int) ceil($min_notice_hours / 24);

        if (!$start_ts || $start_ts <= $now_ts) {
            return [
                'can_delete' => false,
                'reason' => 'Past bookings cannot be deleted.',
                'min_notice_hours' => $min_notice_hours,
                'min_notice_days' => $min_notice_days,
            ];
        }

        if ($status === BookingStatus::PENDING || $status === strtolower(BookingStatus::PENDING)) {
            return [
                'can_delete' => true,
                'reason' => '',
                'min_notice_hours' => $min_notice_hours,
                'min_notice_days' => $min_notice_days,
            ];
        }

        if ($status === BookingStatus::CONFIRMED || $status === strtolower(BookingStatus::CONFIRMED)) {
            $threshold_ts = $now_ts + ($min_notice_hours * HOUR_IN_SECONDS);

            if ($start_ts > $threshold_ts) {
                return [
                    'can_delete' => true,
                    'reason' => '',
                    'min_notice_hours' => $min_notice_hours,
                    'min_notice_days' => $min_notice_days,
                ];
            }

            return [
                'can_delete' => false,
                'reason' => sprintf(
                    'Confirmed bookings can only be deleted when more than %d day(s) away.',
                    $min_notice_days
                ),
                'min_notice_hours' => $min_notice_hours,
                'min_notice_days' => $min_notice_days,
            ];
        }

        return [
            'can_delete' => false,
            'reason' => 'Only pending or confirmed bookings can be deleted.',
            'min_notice_hours' => $min_notice_hours,
            'min_notice_days' => $min_notice_days,
        ];

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

    public function get_upcoming_bookings($wp_user): array {
        $customer_id = $this->customer_repo->get_customer_id($wp_user);

        return $this->booking_repo->get_upcoming_bookings($customer_id);
    }

    private function group_bookings($bookings): array {
        $groups = [];
        $today = date('Y-m-d');

        foreach ($bookings as $b) {

            if ($b['RecurringPatternId']) {

                $key = 'r_' . $b['RecurringPatternId'];

                if (!isset($groups[$key])) {

                    $groups[$key] = [
                        'type'     => 'recurring',
                        'pattern'  => [
                            'Id'                 => $b['RecurringPatternId'],
                            'RecurrenceType'     => $b['RecurrenceType'],
                            'RecurrenceInterval' => $b['RecurrenceInterval'],
                            'RecurrenceDay'      => $b['RecurrenceDay'],
                            'RecurrenceWeek'     => $b['RecurrenceWeek'],
                            'StartDate'          => $b['PatternStartDate'],
                            'EndDate'            => $b['PatternEndDate'],
                            'IsActive'           => $b['PatternIsActive'],
                        ],
                        'bookings' => [],
                    ];
                }

                $groups[$key]['bookings'][] = $b;

            } else {

                $groups['b_' . $b['Id']] = [
                    'type'     => 'standalone',
                    'bookings' => [$b],
                ];
            }
        }

        // Add computed fields
        foreach ($groups as &$group) {

            if ($group['type'] === 'recurring') {

                $group['next_booking'] = $this->find_next_booking($group['bookings'], $today);
                $group['status']       = $this->determine_group_status($group['bookings']);
                $group['count']        = count($group['bookings']);

            } else {

                $b = $group['bookings'][0];

                $group['next_booking'] = $b;
                $group['status']       = $b['Status'];
                $group['count']        = 1;
            }
        }

        return $groups;
    }

    private function find_next_booking($bookings, $today): ?array {
        foreach (array_reverse($bookings) as $b) {

            if ($b['StartDate'] >= $today && $b['Status'] !== BookingStatus::CANCELLED) {
                return $b;
            }

        }

        return null;
    }

    private function determine_group_status($members): string {
        $statuses = array_column($members, 'Status');

        foreach ($statuses as $status) {
            if ($status === BookingStatus::CONFIRMED) {
                return BookingStatus::CONFIRMED;
            }
        }

        foreach ($statuses as $status) {
            if ($status === BookingStatus::PENDING) {
                return BookingStatus::PENDING;
            }
        }

        return $statuses[0] ?? BookingStatus::COMPLETED;
    }

    private function dispatch_update_event($data, $old_status = null): void {
        // TODO: Update this to handle status changes
        $new_status = $data['status'];
        $current_status = $old_status;

        if ($new_status==BookingStatus::CONFIRMED && $current_status<>BookingStatus::CONFIRMED) {
            Event_Dispatcher::dispatch(
                Booking_Events::CONFIRMED,
                [
                    'booking_id' => $data['booking_id'],
                    'room_id' => $data['room_id'],
                    'start' => $data['start_time'],
                    'end' => $data['end_time']
                ]
            );
        }

        if ($new_status==BookingStatus::CANCELLED && $current_status<>BookingStatus::CANCELLED) {
            Event_Dispatcher::dispatch(
                Booking_Events::CANCELLED,
                [
                    'booking_id' => $data['booking_id'],
                    'room_id' => $data['room_id'],
                    'start' => $data['start_time'],
                    'end' => $data['end_time']
                ]
            );
        }

        Event_Dispatcher::dispatch(
            Booking_Events::UPDATED,
            [
                'booking_id' => $data['booking_id'],
                'room_id' => $data['room_id'],
                'start' => $data['start_time'],
                'end' => $data['end_time']
            ]
        );

    }

    private function calculate_chargeable_hours($startdate, $start_time, $enddate, $end_time, $room): float {
        $start = new DateTime($startdate . ' ' . $start_time);
        $end   = new DateTime($enddate . ' ' . $end_time);
        $interval = $start->diff($end);

        $hours =
            ($interval->days * 24) +
            $interval->h +
            ($interval->i / 60);

        if (!$room['CalcClosedHours']) {
            $room_open = strtotime($room['OpeningTime']);
            $room_close = strtotime($room['ClosingTime']);

            if ($room_open !== false && $room_close !== false) {
                $open_hours = ($room_close - $room_open) / 3600;

                // Handle schedules that wrap past midnight (e.g. 20:00 to 02:00).
                if ($open_hours <= 0) {
                    $open_hours += 24;
                }

                $closed_hours_per_day = max(0, 24 - $open_hours);

                $start_day = (clone $start)->setTime(0, 0, 0);
                $end_day = (clone $end)->setTime(0, 0, 0);
                $days_crossed = $start_day->diff($end_day)->days;

                $hours -= ($closed_hours_per_day * $days_crossed);
            }
        }

        return max(0, $hours);
    }

    /**
     * Get uninvoiced bookings for reporting
     *
     * @param array $args Optional filters (orderby, order, limit, offset, organisation_id, customer_id)
     * @return array Array of uninvoiced bookings with details
     */
    public function get_uninvoiced_bookings($args = []): array {
        return $this->booking_repo->get_uninvoiced_bookings($args);
    }

    /**
     * Get uninvoiced booking counts by organisation
     *
     * @return array Array of [OrganisationId, OrganisationName, UninvoicedCount]
     */
    public function get_uninvoiced_by_organisation(): array {
        return $this->booking_repo->count_uninvoiced_by_organisation();
    }

    /**
     * Get uninvoiced booking counts by customer
     *
     * @return array Array of [CustomerId, CustomerName, CustomerEmail, UninvoicedCount]
     */
    public function get_uninvoiced_by_customer(): array {
        return $this->booking_repo->count_uninvoiced_by_customer();
    }

    /**
     * Check if a booking has been invoiced (mutual exclusivity check)
     *
     * @param int $booking_id The booking ID to check
     * @return bool True if booking has any non-cancelled invoices
     */
    public function booking_has_invoices($booking_id): bool {
        return $this->booking_repo->has_invoiced_items($booking_id);
    }

}