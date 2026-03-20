<?php
/**
 * Customer-facing calendar shortcode powered by DayPilot Lite.
 *
 * Usage: [myvh_calendar]
 *        [myvh_calendar venue_id="3"]
 *        [myvh_calendar view="week"]
 *
 * @package MyVillageHall
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MYVH_Calendar_Shortcode {

    /** Shortcode tag */
    const TAG = 'myvh_calendar';
    const TAG_PUBLIC = 'myvh_public_calendar';

    /** REST namespace / route */
    const REST_NAMESPACE = 'myvh/v1';
    const REST_ROUTE     = '/public-events';

    public function init() {
        add_shortcode( self::TAG, [ $this, 'render' ] );
        add_shortcode( self::TAG_PUBLIC, [ $this, 'render' ] );
        add_action( 'rest_api_init', [ $this, 'register_rest_route' ] );
    }

    // ── REST endpoint ─────────────────────────────────────────────────────────

    public function register_rest_route() {
        register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE, [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'get_events' ],
            'permission_callback' => '__return_true',   // public read
            'args'                => [
                'start'    => [ 'required' => true,  'sanitize_callback' => 'sanitize_text_field' ],
                'end'      => [ 'required' => true,  'sanitize_callback' => 'sanitize_text_field' ],
                'venue_id' => [ 'required' => false, 'sanitize_callback' => 'absint' ],
                'room_id'  => [ 'required' => false, 'sanitize_callback' => 'absint' ],
            ],
        ] );
    }

    /**
     * Return events as a JSON array in the DayPilot event format.
     *
     * Only bookings that are:
     *   - Public = 1
     *   - Status IN (BookingStatus::CONFIRMED, BookingStatus::PENDING)
     * are returned. Customer names are never exposed.
     */
    public function get_events( WP_REST_Request $request ): WP_REST_Response {
        global $wpdb;

        $default_label = myvh_setting(
            'general.public_calendar_booking_label',
            __( 'Private booking', 'my-village-hall' )
        );
        $default_label = apply_filters( 'myvh_public_calendar_booking_label', $default_label );
        $default_label = sanitize_text_field( (string) $default_label );
        if ( $default_label === '' ) {
            $default_label = __( 'Private booking', 'my-village-hall' );
        }

        $start    = $request->get_param( 'start' );
        $end      = $request->get_param( 'end' );
        $venue_id = (int) $request->get_param( 'venue_id' );
        $room_id  = (int) $request->get_param( 'room_id' );

        // Basic date validation
        if ( ! $this->is_valid_date( $start ) || ! $this->is_valid_date( $end ) ) {
            return new WP_REST_Response( [], 400 );
        }

        $prefix = $wpdb->prefix;

        $where  = [ '1=1', 'b.Status IN (%s, %s, %s)' ];
        $params = [];

        $params[] = BookingStatus::CONFIRMED;
        $params[] = BookingStatus::PENDING;
        $params[] = BookingStatus::COMPLETED;

        // Date range – bookings that overlap the requested window
        $where[]  = 'b.StartDate < %s AND b.EndDate >= %s';
        $params[] = $end;
        $params[] = $start;

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
                    b.Description,
                    b.Status,
                    b.Public AS IsPublic,
                        r.Id    AS RoomId,
                    r.Name  AS RoomName,
                    v.Id    AS VenueId,
                    v.Name  AS VenueName
                FROM {$prefix}myvh_bookings b
                LEFT JOIN {$prefix}myvh_rooms  r ON b.RoomId  = r.Id
                LEFT JOIN {$prefix}myvh_venues v ON r.VenueId = v.Id
                WHERE {$where_sql}
                ORDER BY b.StartDate ASC, b.StartTime ASC";

        $sql     = $wpdb->prepare( $sql, ...$params );
        $rows    = $wpdb->get_results( $sql, ARRAY_A );
        $events  = [];

        foreach ( ( $rows ?: [] ) as $row ) {
            // DayPilot expects ISO-8601 date-times
            $start_dt = $row['StartDate'] . 'T' . $row['StartTime'];
            $end_dt   = $row['EndDate']   . 'T' . $row['EndTime'];

            $is_public = !empty($row['IsPublic']) || !empty($row['Public']);

            $text = $default_label;
            if ($is_public) {
                $text = !empty($row['Description']) ? (string) $row['Description'] : (string) $row['RoomName'];
            }

            $events[] = [
                'id'       => (int) $row['Id'],
                'start'    => $start_dt,
                'end'      => $end_dt,
                'text'     => sanitize_text_field( $text ),
                    'resource' => (int) $row['RoomId'],
                'tags'     => [
                        'roomId' => (int) $row['RoomId'],
                    'room'   => $row['RoomName'],
                    'venue'  => $row['VenueName'],
                    'status' => $row['Status'],
                    'isPublic' => $is_public,
                ],
            ];
        }

        return new WP_REST_Response( $events, 200 );
    }

    // ── Shortcode renderer ────────────────────────────────────────────────────

    /**
     * @param array $atts  Shortcode attributes.
     * @return string      HTML output.
     */
    public function render( $atts ): string {
        $atts = shortcode_atts( [
            'venue_id' => 0,
            'room_id'  => 0,
            'view'     => 'month',   // month | week | day
            'height'   => 600,
        ], $atts, self::TAG );

        // Enqueue assets only when the shortcode is actually used
        wp_enqueue_script( 'daypilot' );
        wp_enqueue_script( 'myvh-public-calendar' );
        wp_enqueue_style( 'myvh-public-calendar' );

        // Pass config to JS
        $unique_id  = 'myvh-cal-' . uniqid();
        $events_url = rest_url( self::REST_NAMESPACE . self::REST_ROUTE );
        $visible_hours = [ 'start' => 8, 'end' => 22 ];
        $public_rooms = [];
        global $myvh_container;

        if ( isset( $myvh_container ) ) {
            try {
                $availability_service = $myvh_container->get( MYVH_Availability_Service::class );
                $service_hours = $availability_service->get_calendar_visible_hours();

                if ( is_array( $service_hours ) ) {
                    $visible_hours = [
                        'start' => isset( $service_hours['start'] ) ? (int) $service_hours['start'] : $visible_hours['start'],
                        'end'   => isset( $service_hours['end'] ) ? (int) $service_hours['end'] : $visible_hours['end'],
                    ];
                }

                $room_service = $myvh_container->get( MYVH_Room_Service::class );
                $rooms = $room_service->get_all_with_venues();

                foreach ( (array) $rooms as $room ) {
                    $room_id = isset( $room['Id'] ) ? (int) $room['Id'] : 0;
                    $venue_id = isset( $room['VenueId'] ) ? (int) $room['VenueId'] : 0;

                    if ( $room_id <= 0 ) {
                        continue;
                    }

                    if ( (int) $atts['venue_id'] > 0 && $venue_id !== (int) $atts['venue_id'] ) {
                        continue;
                    }

                    if ( (int) $atts['room_id'] > 0 && $room_id !== (int) $atts['room_id'] ) {
                        continue;
                    }

                    $public_rooms[] = [
                        'id' => $room_id,
                        'name' => sanitize_text_field( (string) ( $room['Name'] ?? '' ) ),
                        'venueId' => $venue_id,
                        'venue' => sanitize_text_field( (string) ( $room['VenueName'] ?? '' ) ),
                    ];
                }
            } catch ( Throwable $e ) {
                // Keep defaults when services are unavailable.
            }
        }

        wp_localize_script( 'myvh-public-calendar', 'myvhCalConfig', [
            'containerId' => $unique_id,
            'eventsUrl'   => $events_url,
            'venueId'     => (int) $atts['venue_id'],
            'roomId'      => (int) $atts['room_id'],
            'view'        => sanitize_key( $atts['view'] ),
            'height'      => (int) $atts['height'],
            'rooms'       => $public_rooms,
            'headerDateFormat' => myvh_setting( 'general.calendar_date_format', 'd MMM' ),
            'maxBookingDaysAhead' => (int) myvh_setting( 'booking.general.max_booking_days', 365 ),
            'visibleStartHour' => (int) $visible_hours['start'],
            'visibleEndHour'   => (int) $visible_hours['end'],
            'nonce'       => wp_create_nonce( 'wp_rest' ),
            'i18n'        => [
                'today'    => __( 'Today',    'my-village-hall' ),
                'month'    => __( 'Month',    'my-village-hall' ),
                'week'     => __( 'Week',     'my-village-hall' ),
                'day'      => __( 'Day',      'my-village-hall' ),
                'booked'   => __( 'Booked',   'my-village-hall' ),
                'noEvents' => __( 'No bookings this period.', 'my-village-hall' ),
            ],
        ] );

        $login_url = wp_login_url( get_permalink() ?: home_url('/') );
        $logout_url = wp_logout_url( get_permalink() ?: home_url('/') );

        ob_start();
        ?>
        <div class="myvh-public-calendar-wrap">
            <div class="myvh-cal-login-cta">
                <?php if ( is_user_logged_in() ) : ?>
                    <p><?php esc_html_e( 'You are currently logged in.', 'my-village-hall' ); ?></p>
                    <a class="myvh-cal-btn myvh-cal-login-btn" href="<?php echo esc_url( $logout_url ); ?>">
                        <?php esc_html_e( 'Log out', 'my-village-hall' ); ?>
                    </a>
                <?php else : ?>
                    <p><?php esc_html_e( 'Already have an account?', 'my-village-hall' ); ?></p>
                    <a class="myvh-cal-btn myvh-cal-login-btn" href="<?php echo esc_url( $login_url ); ?>">
                        <?php esc_html_e( 'Log in', 'my-village-hall' ); ?>
                    </a>
                <?php endif; ?>
            </div>

            <div class="myvh-cal-toolbar" data-target="<?php echo esc_attr( $unique_id ); ?>">
                <div class="myvh-cal-nav">
                    <button class="myvh-cal-btn myvh-cal-prev" aria-label="<?php esc_attr_e( 'Previous', 'my-village-hall' ); ?>">&#8249;</button>
                    <button class="myvh-cal-btn myvh-cal-today"><?php esc_html_e( 'Today', 'my-village-hall' ); ?></button>
                    <button class="myvh-cal-btn myvh-cal-next" aria-label="<?php esc_attr_e( 'Next', 'my-village-hall' ); ?>">&#8250;</button>
                    <span class="myvh-cal-title"></span>
                </div>
                <div class="myvh-cal-views">
                    <button class="myvh-cal-btn myvh-mode-btn active" data-mode="calendar"><?php esc_html_e( 'Calendar', 'my-village-hall' ); ?></button>
                    <button class="myvh-cal-btn myvh-mode-btn" data-mode="scheduler"><?php esc_html_e( 'Scheduler', 'my-village-hall' ); ?></button>
                    <button class="myvh-cal-btn myvh-detail-btn <?php echo $atts['view'] === 'month' ? 'active' : ''; ?>" data-view="month"><?php esc_html_e( 'Month', 'my-village-hall' ); ?></button>
                    <button class="myvh-cal-btn myvh-detail-btn <?php echo $atts['view'] === 'week'  ? 'active' : ''; ?>" data-view="week"><?php esc_html_e( 'Week', 'my-village-hall' ); ?></button>
                    <button class="myvh-cal-btn myvh-detail-btn <?php echo $atts['view'] === 'day'   ? 'active' : ''; ?>" data-view="day"><?php esc_html_e( 'Day', 'my-village-hall' ); ?></button>
                </div>
            </div>

            <div id="<?php echo esc_attr( $unique_id ); ?>" class="myvh-daypilot-container" style="height: <?php echo (int) $atts['height']; ?>px;"></div>

            <div class="myvh-cal-legend">
                <span class="myvh-legend-dot myvh-legend-confirmed"></span><?php esc_html_e( 'Confirmed', 'my-village-hall' ); ?>
                <span class="myvh-legend-dot myvh-legend-pending"></span><?php esc_html_e( 'Pending', 'my-village-hall' ); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function is_valid_date( string $value ): bool {
        return (bool) preg_match( '/^\d{4}-\d{2}-\d{2}/', $value );
    }
}
