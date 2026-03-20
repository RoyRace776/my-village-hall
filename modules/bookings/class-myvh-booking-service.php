<?php
if (!defined('ABSPATH')) exit;

require_once MYVH_PLUGIN_DIR . 'modules/events/booking-events.php';
require_once MYVH_PLUGIN_DIR . 'modules/events/class-myvh-event-dispatcher.php';
require_once MYVH_PLUGIN_DIR . 'modules/bookings/class-myvh-booking-status.php';

class MYVH_Booking_Service {

    private $room_service;
    private $booking_repo;
    private $booking_addon_repo;
    private $validator;
    private $availability;
    private $room_rules;
    private $pricing;
    private $customer_repo;
    private $recurring_pattern_service = null;
    private $last_warnings = [];

    public function __construct(
        MYVH_Room_Service $room_service,
        MYVH_Booking_Repository $booking_repo,
        MYVH_Booking_Addon_Repository $booking_addon_repo,
        MYVH_Booking_Validator $validator,
        MYVH_Availability_Service $availability,
        MYVH_Room_Rules_Service $room_rules,
        MYVH_Pricing_Service $pricing,
        MYVH_Customer_Repository $customer_repo,
        MYVH_Recurring_Pattern_Service $recurring_pattern_service
    ) {
        $this->room_service = $room_service;
        $this->booking_repo = $booking_repo;
        $this->booking_addon_repo = $booking_addon_repo;
        $this->validator = $validator;
        $this->availability = $availability;
        $this->room_rules = $room_rules;
        $this->pricing = $pricing;
        $this->customer_repo = $customer_repo;
        $this->recurring_pattern_service = $recurring_pattern_service;
    }

    public function save($data) {
        $this->last_warnings = [];

        if (empty($data['customer_id'])) {
            return new WP_Error('validation', __('Customer is required', 'my-village-hall'));
        }

        if (empty($data['room_id'])) {
            return new WP_Error('validation', __('Room is required', 'my-village-hall'));
        }

        if (empty($data['start_date'])) {
            return new WP_Error('validation', __('Start date is required', 'my-village-hall'));
        }

        if (empty($data['start_time']) || empty($data['end_time'])) {
            return new WP_Error('validation', __('Start and end times are required', 'my-village-hall'));
        }

        $customer_id = intval($data['customer_id']);
        $organisation_id = !empty($data['organisation_id']) ? intval($data['organisation_id']) : 0;

        if ($organisation_id > 0) {
            $customer_organisations = $this->customer_repo->get_organisations_for_customer($customer_id);
            $allowed_organisation_ids = array_map(static function ($org) {
                return intval($org['Id'] ?? 0);
            }, $customer_organisations ?: []);

            if (!in_array($organisation_id, $allowed_organisation_ids, true)) {
                return new WP_Error(
                    'validation',
                    __('Selected organisation is not linked to this customer.', 'my-village-hall')
                );
            }
        }

        // Validate time
        $room = $this->room_service->get($data['room_id']);
        if (!$room) {
            return new WP_Error('validation', __('Room is required', 'my-village-hall'));
        }

        $start_date = sanitize_text_field($data['start_date']);
        $end_date = !empty($data['end_date']) ? sanitize_text_field($data['end_date']) : $start_date;
        if ($start_date <> $end_date) {
            if (!$room['AllowMultiDayBookings'] ){
                return new WP_Error('validation', __('Multi day bookings are not allowed for this room', 'my-village-hall'));
            }
        }
        $start_datetime = new DateTime($start_date . ' ' . $data['start_time']);
        $end_datetime = new DateTime($end_date . ' ' . $data['end_time']);

        if ($end_datetime <= $start_datetime) {
            return new WP_Error('validation', __('End time must be after start time', 'my-village-hall'));
        }

        $start_time = sanitize_text_field($data['start_time']);
        $end_time = sanitize_text_field($data['end_time']);

        $within_opening_hours = $this->availability->booking_within_opening_hours(
            intval($data['room_id']),
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
                    substr((string) $room['OpeningTime'], 0, 5),
                    substr((string) $room['ClosingTime'], 0, 5)
                )
            );
        }

        $is_available = $this->availability->room_is_available(
            intval($data['room_id']),
            $start_date,
            $start_time,
            $end_time,
            $end_date,
            !empty($data['booking_id']) ? intval($data['booking_id']) : null
        );

        if (!$is_available) {
            return new WP_Error(
                'validation',
                __('This room is already booked for part or all of the selected time.', 'my-village-hall')
            );
        }

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
            'Public'            => isset($data['public']) ? 1 : 0,
            'ChargeableHours'   => $chargeable_hours
        ];

        // Update existing booking
        if (!empty($data['booking_id'])) {
            return $this->update_booking($data, $record);

        }

        // Create new booking
        return $this->create_booking($data, $record);

    }

    private function update_booking($data, $record) {
        // Capture the current status BEFORE overwriting the row, so that
        // dispatch_update_event() can compare old vs new correctly.
        $current_record = $this->booking_repo->get_by_id( intval( $data['booking_id'] ) );

        $result = $this->booking_repo->update($record, ['Id' => intval($data['booking_id'])]);
        if ($result === false) {
            return new WP_Error('database', __('Failed to update booking', 'my-village-hall'));
        }
        // Replace addons: delete existing then re-save.  This saves the price for theadd ons
        $this->save_addons(intval($data['booking_id']), $data['addons'] ?? [], true);

        //TODO: Add in the row to the booking charges table to fix the charges, but only if
        //      the booking is still pending

        $this->dispatch_update_event($data, $current_record['Status'] ?? null);

        return intval($data['booking_id']);

    }

    private function create_booking($data, $record) {
        $booking_id = $this->booking_repo->create($record);
        if ($booking_id === false) {
            return new WP_Error('database', __('Failed to create booking', 'my-village-hall'));
        }

        //TODO: Add in the row to the booking charges table to fix the charges, but only if
        //      the booking is still pending

        MYVH_Event_Dispatcher::dispatch(
            MYVH_Booking_Events::CREATED,
            [
                'booking_id' => $booking_id,
                'room_id' => $data['room_id'],
                'start' => $data['start_time'],
                'end' => $data['end_time']
            ]
        );

        if ($data['status'] == BookingStatus::CONFIRMED) {
            MYVH_Event_Dispatcher::dispatch(
                MYVH_Booking_Events::CONFIRMED,
                [
                    'booking_id' => $booking_id,
                    'room_id' => $data['room_id'],
                    'start' => $data['start_time'],
                    'end' => $data['end_time']
                ]
            );
        }

        // Save addons
        $this->save_addons($booking_id, $data['addons'] ?? []);

        // Handle recurring pattern if requested
        if (!empty($data['is_recurring']) && $this->recurring_pattern_service) {
            $pattern_data = [
                'parent_booking_id'   => $booking_id,
                'recurrence_type'     => sanitize_text_field($data['recurrence_type']),
                'recurrence_interval' => intval($data['recurrence_interval'] ?? 1),
                'recurrence_day'      => sanitize_text_field($data['recurrence_day'] ?? ''),
                'recurrence_week'     => sanitize_text_field($data['recurrence_week'] ?? ''),
                'start_date'          => sanitize_text_field($data['start_date']),
                'is_active'           => 1,
            ];

            // Set end condition
            if ($data['recurrence_end_type'] === 'date') {
                $pattern_data['end_date'] = sanitize_text_field($data['recurrence_end_date']);
            } else {
                $pattern_data['max_occurrences'] = intval($data['max_occurrences']);
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
        }

        return $booking_id;
    }

    public function get_last_warnings() {
        return $this->last_warnings;
    }

    /**
     * Save addons for a booking. Optionally delete existing ones first (for updates).
     *
     * @param int   $booking_id
     * @param array $addons     Array of ['addon_id' => int, 'quantity' => float, 'unit_price' => float, 'description' => string]
     * @param bool  $replace    If true, delete all existing addons before saving
     */
    public function save_addons($booking_id, $addons, $replace = false) {
        if (!$this->booking_addon_repo) return;

        if ($replace) {
            $this->booking_addon_repo->delete_by_booking_id($booking_id);
        }

        if (empty($addons) || !is_array($addons)) return;

        foreach ($addons as $addon) {
            $addon_id   = intval($addon['addon_id'] ?? 0);
            $quantity   = floatval($addon['quantity'] ?? 1);
            $unit_price = floatval($addon['unit_price'] ?? 0);

            if ($addon_id <= 0 || $quantity <= 0) continue;

            $this->booking_addon_repo->create([
                'BookingId'   => $booking_id,
                'AddonId'     => $addon_id,
                'Quantity'    => $quantity,
                'UnitPrice'   => $unit_price,
                'TotalAmount' => round($quantity * $unit_price, 2),
                'Description' => sanitize_text_field($addon['description'] ?? ''),
            ]);
        }
    }

    /**
     * Get all addons for a booking (with addon name and type).
     *
     * @param int $booking_id
     * @return array
     */
    public function get_addons_for_booking($booking_id) {
        if (!$this->booking_addon_repo) return [];
        return $this->booking_addon_repo->get_by_booking_id($booking_id) ?? [];
    }

    public function get_by_id($booking_id) {
        return $this->booking_repo->get_by_id($booking_id);
    }

    public function cancel($id) {
        return $this->booking_repo->update(
            ['Status' => BookingStatus::CANCELLED],
            ['Id' => $id]
        );
    }

    public function get_all_with_details($args = []) {
        return $this->booking_repo->get_all_with_details($args);
    }

    public function get_booking_list($filters = []) {
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

    public function get_between($start, $end, $context = null) {

        $bookings = $this->booking_repo->get_between($start, $end, $context = null);
        return $bookings;
    }

    public function move_booking($id, $start, $end, $room) {
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

    public function get_upcoming_bookings($wp_user) {
        $customer_id = $this->customer_repo->get_customer_id($wp_user);

        return $this->booking_repo->get_upcoming_bookings($customer_id);
    }

    private function group_bookings($bookings) {
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

    private function find_next_booking($bookings, $today) {
        foreach (array_reverse($bookings) as $b) {

            if ($b['StartDate'] >= $today && $b['Status'] !== BookingStatus::CANCELLED) {
                return $b;
            }

        }

        return null;
    }

    private function determine_group_status($members) {
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

    private function dispatch_update_event($data, $old_status = null) {
        // TODO: Update this to handle status changes
        $new_status = $data['status'];
        $current_status = $old_status;

        if ($new_status=='Confirmed' && $current_status<>'Confirmed') {
            MYVH_Event_Dispatcher::dispatch(
                MYVH_Booking_Events::CONFIRMED,
                [
                    'booking_id' => $data['booking_id'],
                    'room_id' => $data['room_id'],
                    'start' => $data['start_time'],
                    'end' => $data['end_time']
                ]
            );
        }

        if ($new_status==BookingStatus::CANCELLED && $current_status<>BookingStatus::CANCELLED) {
            MYVH_Event_Dispatcher::dispatch(
                MYVH_Booking_Events::CANCELLED,
                [
                    'booking_id' => $data['booking_id'],
                    'room_id' => $data['room_id'],
                    'start' => $data['start_time'],
                    'end' => $data['end_time']
                ]
            );
        }

        MYVH_Event_Dispatcher::dispatch(
            MYVH_Booking_Events::UPDATED,
            [
                'booking_id' => $data['booking_id'],
                'room_id' => $data['room_id'],
                'start' => $data['start_time'],
                'end' => $data['end_time']
            ]
        );

    }

    private function calculate_chargeable_hours($startdate, $start_time, $enddate, $end_time, $room) {
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

}