<?php
/**
 * Admin calendar AJAX handlers.
 *
 * Replaces the old FullCalendar vbc_* handlers with a clean set of
 * wp_ajax_myvh_cal_* actions consumed by the DayPilot admin calendar.
 *
 * All handlers require the user to be logged-in and have manage_myvh capability.
 *
 * @package MyVillageHall
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MYVH_Calendar_Ajax {

    public function register() {
        $actions = [
            'myvh_cal_events'         => 'get_events',
            'myvh_cal_rooms'          => 'get_rooms',
            'myvh_cal_booking_detail' => 'get_booking_detail',
            'myvh_cal_save_booking'   => 'save_booking',
            'myvh_cal_cancel_booking' => 'cancel_booking',
            'myvh_cal_customers'      => 'get_customers',
        ];

        foreach ( $actions as $action => $method ) {
            add_action( 'wp_ajax_' . $action, [ $this, $method ] );
        }
    }

    // ── Security helper ───────────────────────────────────────────────────────

    private function check() {
        if ( ! current_user_can( 'manage_myvh' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied', 'my-village-hall' ) ], 403 );
        }
        check_ajax_referer( 'myvh-ajax-nonce', 'nonce' );
    }

    // ── GET events ────────────────────────────────────────────────────────────

    /**
     * Returns bookings in DayPilot event format.
     *
     * GET params: start (Y-m-d), end (Y-m-d), venue_id, room_id, show_cancelled
     */
    public function get_events() {
        $this->check();

        global $wpdb;

        $start          = sanitize_text_field( $_GET['start']          ?? '' );
        $end            = sanitize_text_field( $_GET['end']            ?? '' );
        $venue_id       = absint( $_GET['venue_id']                    ?? 0  );
        $room_id        = absint( $_GET['room_id']                     ?? 0  );
        $show_cancelled = filter_var( $_GET['show_cancelled'] ?? false, FILTER_VALIDATE_BOOLEAN );

        if ( ! $start || ! $end ) {
            wp_send_json_error( [ 'message' => 'Missing date range' ], 400 );
        }

        $prefix = $wpdb->prefix;
        $where  = [ '1=1' ];
        $params = [];

        // Overlap: booking starts before window end AND ends after window start
        $where[]  = 'b.StartDate < %s AND b.EndDate >= %s';
        $params[] = $end;
        $params[] = $start;

        if ( ! $show_cancelled ) {
            $where[] = "b.Status != ". BookingStatus::CANCELLED;
        }

        if ( $venue_id > 0 ) {
            $where[]  = 'v.Id = %d';
            $params[] = $venue_id;
        }

        if ( $room_id > 0 ) {
            $where[]  = 'b.RoomId = %d';
            $params[] = $room_id;
        }

        $where_sql = implode( ' AND ', $where );

        $sql = "SELECT
                    b.Id,
                    b.StartDate,
                    b.EndDate,
                    b.StartTime,
                    b.EndTime,
                    b.Status,
                    b.Public,
                    b.Description,
                    b.CustomerId,
                    b.RecurringPatternId,
                    c.Name  AS CustomerName,
                    r.Id    AS RoomId,
                    r.Name  AS RoomName,
                    v.Id    AS VenueId,
                    v.Name  AS VenueName
                FROM {$prefix}myvh_bookings b
                LEFT JOIN {$prefix}myvh_customers c ON b.CustomerId = c.Id
                LEFT JOIN {$prefix}myvh_rooms     r ON b.RoomId     = r.Id
                LEFT JOIN {$prefix}myvh_venues    v ON r.VenueId    = v.Id
                WHERE {$where_sql}
                ORDER BY b.StartDate ASC, b.StartTime ASC";

        if ( $params ) {
            $sql = $wpdb->prepare( $sql, ...$params );
        }

        $rows   = $wpdb->get_results( $sql, ARRAY_A ) ?: [];
        $events = [];

        foreach ( $rows as $row ) {
            $events[] = $this->row_to_event( $row );
        }

        wp_send_json( $events );
    }

    /**
     * Convert a DB row to a DayPilot event object.
     */
    private function row_to_event( array $row ): array {
        $colour = $this->status_colour( $row['Status'] );
        $label  = esc_html( $row['CustomerName'] ?? '' );
        if ( $row['RoomName'] ) {
            $label .= ' (' . esc_html( $row['RoomName'] ) . ')';
        }

        return [
            'id'         => (int) $row['Id'],
            'start'      => $row['StartDate'] . 'T' . $row['StartTime'],
            'end'        => $row['EndDate']   . 'T' . $row['EndTime'],
            'text'       => $label,
            'backColor'  => $colour,
            'fontColor'  => '#ffffff',
            'borderColor'=> 'darker',
            'resource'   => (int) $row['RoomId'],
            'tags'       => [
                'customerId'   => (int) $row['CustomerId'],
                'customerName' => $row['CustomerName'],
                'roomId'       => (int) $row['RoomId'],
                'roomName'     => $row['RoomName'],
                'venueId'      => (int) $row['VenueId'],
                'venueName'    => $row['VenueName'],
                'status'       => $row['Status'],
                'public'       => (bool) $row['Public'],
                'description'  => $row['Description'],
                'recurringId'  => $row['RecurringPatternId'] ? (int) $row['RecurringPatternId'] : null,
            ],
        ];
    }

    private function status_colour( string $status ): string {
        $map = [
            BookingStatus::CONFIRMED => '#2271b1',
            BookingStatus::PENDING   => '#f0a500',
            BookingStatus::CANCELLED => '#888888',
            BookingStatus::COMPLETED => '#46b450',
        ];
        return $map[ strtolower( $status ) ] ?? '#2271b1';
    }

    // ── GET rooms ─────────────────────────────────────────────────────────────

    /**
     * Returns rooms (optionally filtered by venue) for the booking modal.
     */
    public function get_rooms() {
        global $myvh_container;
        $this->check();

        $venue_id = absint( $_POST['venue_id'] ?? 0 );

        $repo  = $myvh_container->get( 'room_repo' );
        $rooms = $repo->get_all_with_venues();

        if ( $venue_id > 0 ) {
            $rooms = array_values( array_filter( $rooms, fn( $r ) => (int) $r['VenueId'] === $venue_id ) );
        }

        wp_send_json_success( [ 'rooms' => $rooms ] );
    }

    // ── GET booking detail ────────────────────────────────────────────────────

    public function get_booking_detail() {
        global $myvh_container;
        $this->check();

        $id      = absint( $_POST['booking_id'] ?? 0 );
        $service = $myvh_container->get( 'booking_service' );
        $booking = $service->get_by_id( $id );

        if ( ! $booking ) {
            wp_send_json_error( [ 'message' => __( 'Booking not found', 'my-village-hall' ) ], 404 );
        }

        wp_send_json_success( [ 'booking' => $booking ] );
    }

    // ── GET customers (search) ────────────────────────────────────────────────

    public function get_customers() {
        global $myvh_container;
        $this->check();

        $search = sanitize_text_field( $_POST['search'] ?? '' );
        $repo   = $myvh_container->get( 'customer_repo' );

        $customers = $search
            ? $repo->search( $search )
            : $repo->get_all( [ 'orderby' => 'Name', 'order' => 'ASC', 'limit' => 50 ] );

        wp_send_json_success( [ 'customers' => $customers ] );
    }

    // ── SAVE booking ──────────────────────────────────────────────────────────

    /**
     * Create or update a booking from the calendar modal.
     *
     * Expected POST keys mirror the booking form: booking_id (optional),
     * customer_id, room_id, start_date, start_time, end_time, status,
     * description, public.
     */
    public function save_booking() {
        global $myvh_container;
        $this->check();

        $data = [
            'booking_id'  => absint( $_POST['booking_id']  ?? 0    ),
            'customer_id' => absint( $_POST['customer_id'] ?? 0    ),
            'room_id'     => absint( $_POST['room_id']     ?? 0    ),
            'start_date'  => sanitize_text_field( $_POST['start_date']  ?? '' ),
            'end_date'    => sanitize_text_field( $_POST['end_date']    ?? '' ),
            'start_time'  => sanitize_text_field( $_POST['start_time']  ?? '' ),
            'end_time'    => sanitize_text_field( $_POST['end_time']    ?? '' ),
            'status'      => sanitize_text_field( $_POST['status']      ?? BookingStatus::PENDING ),
            'description' => sanitize_textarea_field( $_POST['description'] ?? '' ),
            'public'      => isset( $_POST['public'] ) ? 1 : 0,
            'addons'      => [],
        ];

        if ( empty( $data['booking_id'] ) ) {
            unset( $data['booking_id'] );
        }

        $service = $myvh_container->get( 'booking_service' );
        $result  = $service->save( $data );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( [ 'message' => $result->get_error_message() ] );
        }

        $verb    = empty( $data['booking_id'] ) ? 'created' : 'updated';
        $message = $verb === 'created'
            ? __( 'Booking created successfully', 'my-village-hall' )
            : __( 'Booking updated successfully', 'my-village-hall' );

        wp_send_json_success( [ 'booking_id' => $result, 'message' => $message ] );
    }

    // ── CANCEL booking ────────────────────────────────────────────────────────

    public function cancel_booking() {
        global $myvh_container;
        $this->check();

        $id      = absint( $_POST['booking_id'] ?? 0 );
        $service = $myvh_container->get( 'booking_service' );
        $service->cancel( $id );

        wp_send_json_success( [ 'message' => __( 'Booking cancelled', 'my-village-hall' ) ] );
    }
}
