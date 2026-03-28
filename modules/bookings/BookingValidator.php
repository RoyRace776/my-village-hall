<?php
class BookingValidator {
    private $customer_repo;
    private $organisation_repo;
    private $room_service;
    private $availability;
    private $pricing;
    private $room_rules;

    public function __construct(CustomerRepository $customer_repo,
                                OrganisationRepository $organisation_repo,
                                RoomService $room_service,
                                AvailabilityService $availability,
                                PricingService $pricing,
                                RoomRulesService $room_rules) {
        $this->customer_repo = $customer_repo;
        $this->organisation_repo = $organisation_repo;
        $this->room_service = $room_service;
        $this->availability = $availability;
        $this->pricing = $pricing;
        $this->room_rules = $room_rules;
    }

    public function validate($data): true|WP_Error {
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

        $room = $this->room_service->get($data['room_id']);
        if (!$room) {
            return new WP_Error('validation', __('Room is required', 'my-village-hall'));
        }


        $start_date = sanitize_text_field($data['start_date']);
        $end_date = !empty($data['end_date']) ? sanitize_text_field($data['end_date']) : $start_date;
        $start_time = sanitize_text_field($data['start_time']);
        $end_time = sanitize_text_field($data['end_time']);

        // Multi-day booking rule
        if ($start_date !== $end_date && !$this->room_rules->allows_multi_day($room)) {
            return new WP_Error('validation', __('Multi day bookings are not allowed for this room', 'my-village-hall'));
        }

        // Duration rule
        $start_dt = new DateTime($start_date . ' ' . $start_time);
        $end_dt = new DateTime($end_date . ' ' . $end_time);
        if ($end_dt <= $start_dt) {
            return new WP_Error('validation', __('End time must be after start time', 'my-village-hall'));
        }
        if (!$this->room_rules->is_duration_allowed($room, $start_dt->format('Y-m-d H:i'), $end_dt->format('Y-m-d H:i'))) {
            return new WP_Error('validation', __('Booking duration is not allowed for this room', 'my-village-hall'));
        }

        // Allowed day rule
        if (!$this->room_rules->is_day_allowed($room, $start_date)) {
            return new WP_Error('validation', __('Bookings are not allowed on this day for this room', 'my-village-hall'));
        }

        // Buffer time rule (stub)
        if (!$this->room_rules->has_buffer_time($room, $start_dt->format('Y-m-d H:i'), $end_dt->format('Y-m-d H:i'))) {
            return new WP_Error('validation', __('Not enough buffer time for this booking', 'my-village-hall'));
        }

        // Opening hours rule (legacy, can be replaced by room_rules->within_opening_hours if desired)
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

        // Pricing/rate validation (optional, can be expanded)
        // $rate_check = $this->pricing->get_charge_snapshot($data['booking_id'] ?? 0);
        // if (is_wp_error($rate_check)) {
        //     return $rate_check;
        // }

        return true;
    }
}