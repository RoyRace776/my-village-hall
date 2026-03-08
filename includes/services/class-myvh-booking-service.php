<?php
if (!defined('ABSPATH')) exit;

require_once MYVH_PLUGIN_DIR . 'includes/events/booking-events.php';
require_once MYVH_PLUGIN_DIR . 'includes/events/class-myvh-event-dispatcher.php';
require_once MYVH_PLUGIN_DIR . 'includes/domain/booking/class-myvh-booking-status.php';

class MYVH_Booking_Service {

    private $room_service;
    private $booking_repo;
    private $booking_addon_repo;
    private $validator;
    private $availability;
    private $room_rules;
    private $pricing;
    private $recurring_pattern_service = null;

    public function set_recurring_pattern_service($service): void {
        $this->recurring_pattern_service = $service;
    }

    public function __construct(
        $room_service,
        $booking_repo,
        $booking_addon_repo,
        $validator,
        $availability,
        $room_rules,
        $pricing
    ) {
        $this->room_service = $room_service;
        $this->booking_repo = $booking_repo;
        $this->booking_addon_repo = $booking_addon_repo;
        $this->validator = $validator;
        $this->availability = $availability;
        $this->room_rules = $room_rules;
        $this->pricing = $pricing;
    }

    public function create_booking($data) {

        $room_id   = intval($data['room_id']);
        $group_id  = intval($data['customer_group_id']);
        $start     = $data['start_time'];
        $end       = $data['end_time'];

        // Validate input
        $result = $this->validator->validate($data);
        if (is_wp_error($result)) return $result;

        $room = $this->room_service->get($data['room_id']);
        if (!$room) {
            return new WP_Error('invalid_room');
        }

        if (!$this->room_rules->within_opening_hours(
            $room,
            $data['start_time'],
            $data['end_time']
        )) {
            return new WP_Error('invalid_time');
        }

        if (!$this->availability->room_is_available(
            $data['room_id'],
            $data['start_time'],
            $data['end_time']
        )) {
            return new WP_Error('not_available');
        }

        // TODO: Fix pricing calcualtion
        //$price = $this->pricing->calculate_booking_price(...);
        $price = 0;

        // 5️⃣ Save booking
        $booking = [
            'RoomId'          => $room_id,
            'CustomerGroupId' => $group_id,
            'StartTime'       => $start,
            'EndTime'         => $end,
            'TotalAmount'     => $price,
            'Status'          => 'Confirmed', //TODO: Change this to set the status from the data
            'CreatedAt'       => current_time('mysql')
        ];

        $booking_id = $this->booking_repo->create($booking);

        If ($booking_id > 0) {
            MYVH_Event_Dispatcher::dispatch(
                MYVH_Booking_Events::CREATED,
                [
                    'booking_id' => $booking_id,
                    'room_id' => $data['room_id'],
                    'start' => $data['start_time'],
                    'end' => $data['end_time']
                ]
            );

            if ($data['status'] == 'Confirmed') {
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
        }
        
        return $booking_id;
    }

    private function within_opening_hours($room, $start, $end) {

        $room_open  = strtotime($room['OpeningTime']);
        $room_close = strtotime($room['ClosingTime']);

        $start_time = strtotime(date('H:i', strtotime($start)));
        $end_time   = strtotime(date('H:i', strtotime($end)));

        return ($start_time >= $room_open && $end_time <= $room_close);
    }

    private function is_available($room_id, $start, $end) {

        $conflicts = $this->booking_repo->get_conflicts(
            $room_id,
            $start,
            $end
        );

        return empty($conflicts);
    }

    public function save($data) {
        if (!empty($data['is_recurring']) && $this->recurring_pattern_service) {
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

        // Validate time
        $start = strtotime($data['start_time']);
        $end = strtotime($data['end_time']);
        if ($end <= $start) {
            return new WP_Error('validation', __('End time must be after start time', 'my-village-hall'));
        }

        $record = [
            'CustomerId'  => intval($data['customer_id']),
            'RoomId'      => intval($data['room_id']),
            'Status'      => sanitize_text_field($data['status'] ?? BookingStatus::PENDING),
            'StartDate'   => sanitize_text_field($data['start_date']),
            'EndDate'     => !empty($data['end_date']) ? sanitize_text_field($data['end_date']) : sanitize_text_field($data['start_date']),
            'StartTime'   => sanitize_text_field($data['start_time']),
            'EndTime'     => sanitize_text_field($data['end_time']),
            'Description' => sanitize_textarea_field($data['description'] ?? ''),
            'Public'      => isset($data['public']) ? 1 : 0,
        ];

        // Update existing booking
        if (!empty($data['booking_id'])) {
            $result = $this->booking_repo->update($record, ['Id' => intval($data['booking_id'])]);
            if ($result === false) {
                return new WP_Error('database', __('Failed to update booking', 'my-village-hall'));
            }
            // Replace addons: delete existing then re-save
            $this->save_addons(intval($data['booking_id']), $data['addons'] ?? [], true);

            $this->dispatch_update_event($data);

            return intval($data['booking_id']);
        }

        // Create new booking
        $booking_id = $this->booking_repo->create($record);
        if ($booking_id === false) {
            return new WP_Error('database', __('Failed to create booking', 'my-village-hall'));
        }

        MYVH_Event_Dispatcher::dispatch(
            MYVH_Booking_Events::CREATED,
            [
                'booking_id' => $booking_id,
                'room_id' => $data['room_id'],
                'start' => $data['start_time'],
                'end' => $data['end_time']
            ]
        );

        if ($data['status'] == 'Confirmed') {
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

            $this->recurring_pattern_service->save($pattern_data);
        }

        return $booking_id;
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

    private function dispatch_update_event($data) {
        // TODO: Update this to handle status changes
        $new_status = $data['status'];
        $current_record = $this->booking_repo->get_by_id($data['booking_id']);
        $current_status = $current_record['Status'];

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
    
}