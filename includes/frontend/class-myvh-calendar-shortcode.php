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

    /** REST namespace / route */
    const REST_NAMESPACE = 'myvh/v1';
    const REST_ROUTE     = '/public-events';

    public function init() {
        add_shortcode( self::TAG, [ $this, 'render' ] );
        add_action( 'rest_api_init', [ $this, 'register_rest_route' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ] );
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
     *   - Status IN ('confirmed', 'pending')
     * are returned. Customer names are never exposed.
     */
    public function get_events( WP_REST_Request $request ): WP_REST_Response {
        global $wpdb;

        $start    = $request->get_param( 'start' );
        $end      = $request->get_param( 'end' );
        $venue_id = (int) $request->get_param( 'venue_id' );
        $room_id  = (int) $request->get_param( 'room_id' );

        // Basic date validation
        if ( ! $this->is_valid_date( $start ) || ! $this->is_valid_date( $end ) ) {
            return new WP_REST_Response( [], 400 );
        }

        $prefix = $wpdb->prefix;

        $where  = [ '1=1', "b.Public = 1", "b.Status IN ('confirmed','pending')" ];
        $params = [];

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

            $text = esc_html( $row['RoomName'] );
            if ( ! empty( $row['Description'] ) ) {
                $text .= ' – ' . esc_html( $row['Description'] );
            }

            $events[] = [
                'id'       => (int) $row['Id'],
                'start'    => $start_dt,
                'end'      => $end_dt,
                'text'     => $text,
                'resource' => $row['VenueId'],
                'tags'     => [
                    'room'   => $row['RoomName'],
                    'venue'  => $row['VenueName'],
                    'status' => $row['Status'],
                ],
            ];
        }

        return new WP_REST_Response( $events, 200 );
    }

    // ── Assets ────────────────────────────────────────────────────────────────

    public function register_assets() {
        // DayPilot Lite is loaded from their CDN (free, no API key required)
        wp_register_script(
            'daypilot-lite',
            'https://cdn.jsdelivr.net/npm/daypilot-lite@2024.4.6063/daypilot-all.min.js',
            [],
            '2024.4.6063',
            true
        );

        wp_register_script(
            'myvh-public-calendar',
            MYVH_PLUGIN_URL . 'assets/js/public-calendar.js',
            [ 'daypilot-lite' ],
            MYVH_VERSION,
            true
        );

        wp_register_style(
            'myvh-public-calendar',
            MYVH_PLUGIN_URL . 'assets/css/public-calendar.css',
            [],
            MYVH_VERSION
        );
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
        wp_enqueue_script( 'daypilot-lite' );
        wp_enqueue_script( 'myvh-public-calendar' );
        wp_enqueue_style( 'myvh-public-calendar' );

        // Pass config to JS
        $unique_id  = 'myvh-cal-' . uniqid();
        $events_url = rest_url( self::REST_NAMESPACE . self::REST_ROUTE );

        wp_localize_script( 'myvh-public-calendar', 'myvhCalConfig', [
            'containerId' => $unique_id,
            'eventsUrl'   => $events_url,
            'venueId'     => (int) $atts['venue_id'],
            'roomId'      => (int) $atts['room_id'],
            'view'        => sanitize_key( $atts['view'] ),
            'height'      => (int) $atts['height'],
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

        ob_start();
        ?>
        <div class="myvh-public-calendar-wrap">
            <div class="myvh-cal-toolbar" data-target="<?php echo esc_attr( $unique_id ); ?>">
                <div class="myvh-cal-nav">
                    <button class="myvh-cal-btn myvh-cal-prev" aria-label="<?php esc_attr_e( 'Previous', 'my-village-hall' ); ?>">&#8249;</button>
                    <button class="myvh-cal-btn myvh-cal-today"><?php esc_html_e( 'Today', 'my-village-hall' ); ?></button>
                    <button class="myvh-cal-btn myvh-cal-next" aria-label="<?php esc_attr_e( 'Next', 'my-village-hall' ); ?>">&#8250;</button>
                    <span class="myvh-cal-title"></span>
                </div>
                <div class="myvh-cal-views">
                    <button class="myvh-cal-btn myvh-view-btn <?php echo $atts['view'] === 'month' ? 'active' : ''; ?>" data-view="month"><?php esc_html_e( 'Month', 'my-village-hall' ); ?></button>
                    <button class="myvh-cal-btn myvh-view-btn <?php echo $atts['view'] === 'week'  ? 'active' : ''; ?>" data-view="week"><?php esc_html_e( 'Week', 'my-village-hall' ); ?></button>
                    <button class="myvh-cal-btn myvh-view-btn <?php echo $atts['view'] === 'day'   ? 'active' : ''; ?>" data-view="day"><?php esc_html_e( 'Day', 'my-village-hall' ); ?></button>
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
