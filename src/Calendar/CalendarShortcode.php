<?php
namespace MYVH\Calendar;

use MYVH\Rooms\RoomService;
use MYVH\Availability\AvailabilityService;

use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use Throwable;

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

class CalendarShortcode {

    /** Shortcode tag */
    const TAG = 'myvh_calendar';
    const TAG_PUBLIC = 'myvh_public_calendar';

    /** REST namespace / route */
    const REST_NAMESPACE = 'myvh/v1';
    const REST_ROUTE     = '/public-events';

    public function init(): void {
        add_shortcode( self::TAG, [ $this, 'render' ] );
        add_shortcode( self::TAG_PUBLIC, [ $this, 'render' ] );
        add_action( 'rest_api_init', [ $this, 'register_rest_route' ] );
    }

    // ── REST endpoint ─────────────────────────────────────────────────────────

    public function register_rest_route(): void {
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
        global $myvh_container;

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

        if ( ! isset( $myvh_container ) ) {
            return new WP_REST_Response( [], 200 );
        }

        try {
                $calendar_service = $myvh_container->get( CalendarService::class );
            $events = $calendar_service->get_public_feed_events(
                $start,
                $end,
                is_user_logged_in() ? get_current_user_id() : 0,
                [
                    'venue_id' => $venue_id,
                    'room_id' => $room_id,
                ],
                $default_label
            );
        } catch ( Throwable $e ) {
            $events = [];
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
        $nav_id = $unique_id . '-nav';
        $visible_hours = [ 'start' => 8, 'end' => 22 ];
        $public_rooms = [];
        global $myvh_container;

        if ( isset( $myvh_container ) ) {
            try {
                $availability_service = $myvh_container->get( AvailabilityService::class );
                    $availability_service = $myvh_container->get( AvailabilityService::class );
                $service_hours = $availability_service->get_calendar_visible_hours();

                if ( is_array( $service_hours ) ) {
                    $visible_hours = [
                        'start' => isset( $service_hours['start'] ) ? (int) $service_hours['start'] : $visible_hours['start'],
                        'end'   => isset( $service_hours['end'] ) ? (int) $service_hours['end'] : $visible_hours['end'],
                    ];
                }

                $room_service = $myvh_container->get( RoomService::class );
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
            'containerId'         => $unique_id,
            'navContainerId'      => $nav_id,
            'eventsUrl'           => $events_url,
            'venueId'             => (int) $atts['venue_id'],
            'roomId'              => (int) $atts['room_id'],
            'view'                => sanitize_key( $atts['view'] ),
            'height'              => (int) $atts['height'],
            'rooms'               => $public_rooms,
            'headerDateFormat'    => myvh_setting( 'general.calendar_date_format', 'd MMM' ),
            'maxBookingDaysAhead' => (int) myvh_setting( 'booking.general.max_booking_days', 365 ),
            'visibleStartHour'    => (int) $visible_hours['start'],
            'visibleEndHour'      => (int) $visible_hours['end'],
            'schedulerOrientation' => myvh_setting( 'general.scheduler_orientation', 'horizontal' ),
            'nonce'               => wp_create_nonce( 'wp_rest' ),
            'i18n'                => [
                'today'    => __( 'Today',    'my-village-hall' ),
                'month'    => __( 'Month',    'my-village-hall' ),
                'week'     => __( 'Week',     'my-village-hall' ),
                'day'      => __( 'Day',      'my-village-hall' ),
                'booked'   => __( 'Booked',   'my-village-hall' ),
                'noEvents' => __( 'No bookings this period.', 'my-village-hall' ),
            ],
        ] );

        $login_url = $this->resolve_login_page_url();
        $register_url = $this->resolve_register_page_url( $login_url );
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
                    <p><?php esc_html_e( 'Want to manage bookings? Log in or create an account.', 'my-village-hall' ); ?></p>
                    <div class="myvh-cal-auth-actions">
                        <a class="myvh-cal-btn myvh-cal-login-btn" href="<?php echo esc_url( $login_url ); ?>">
                            <?php esc_html_e( 'Log in', 'my-village-hall' ); ?>
                        </a>
                        <a class="myvh-cal-btn myvh-cal-register-btn" href="<?php echo esc_url( $register_url ); ?>">
                            <?php esc_html_e( 'Create account', 'my-village-hall' ); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <div class="myvh-cal-main">
                <div class="myvh-cal-sidebar">
                    <div id="<?php echo esc_attr( $nav_id ); ?>" class="myvh-cal-nav-picker"></div>
                </div>

                <div class="myvh-cal-content">
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
                            <button class="myvh-cal-btn myvh-detail-btn <?php echo $atts['view'] === 'day'   ? 'active' : ''; ?>" data-view="day"><?php esc_html_e( 'Day', 'my-village-hall' ); ?></button>
                            <button class="myvh-cal-btn myvh-detail-btn <?php echo $atts['view'] === 'week'  ? 'active' : ''; ?>" data-view="week"><?php esc_html_e( 'Week', 'my-village-hall' ); ?></button>
                            <button class="myvh-cal-btn myvh-detail-btn <?php echo $atts['view'] === 'month' ? 'active' : ''; ?>" data-view="month"><?php esc_html_e( 'Month', 'my-village-hall' ); ?></button>
                        </div>
                    </div>

                    <div id="<?php echo esc_attr( $unique_id ); ?>" class="myvh-daypilot-container" style="height: <?php echo (int) $atts['height']; ?>px;"></div>

                    <div class="myvh-cal-legend">
                        <span class="myvh-legend-dot myvh-legend-confirmed"></span><?php esc_html_e( 'Confirmed', 'my-village-hall' ); ?>
                        <span class="myvh-legend-dot myvh-legend-pending"></span><?php esc_html_e( 'Pending', 'my-village-hall' ); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function is_valid_date( string $value ): bool {
        return (bool) preg_match( '/^\d{4}-\d{2}-\d{2}/', $value );
    }

    private function resolve_login_page_url(): string {
        $fallback = home_url('/login/');

        // Allow theme/site to force a specific customer login/register page URL.
        $filtered = apply_filters( 'myvh_login_page_url', '' );
        if ( is_string( $filtered ) && $filtered !== '' ) {
            return esc_url_raw( $filtered );
        }

        // 1) Theme login template page (most explicit signal).
        $template_pages = get_pages([
            'post_type'   => 'page',
            'post_status' => 'publish',
            'meta_key'    => '_wp_page_template',
            'meta_value'  => 'templates/page-login.php',
            'number'      => 1,
        ]);

        if ( !empty($template_pages) && !empty($template_pages[0]->ID) ) {
            return (string) get_permalink( (int) $template_pages[0]->ID );
        }

        // 2) Any published page containing [myvh_login] shortcode.
        $candidate_pages = get_posts([
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'posts_per_page' => 200,
            'orderby'        => 'menu_order title',
            'order'          => 'ASC',
            'suppress_filters' => false,
        ]);

        foreach ( (array) $candidate_pages as $page ) {
            if ( empty( $page->ID ) ) {
                continue;
            }

            if ( has_shortcode( (string) $page->post_content, 'myvh_login' ) ) {
                return (string) get_permalink( (int) $page->ID );
            }
        }

        // 3) Conventional slugs as final custom-page guesses.
        $slug_guesses = [ 'login', 'sign-in', 'signin', 'account-login' ];

        foreach ( $slug_guesses as $slug ) {
            $login_page = get_page_by_path( $slug );
            if ( $login_page && !empty( $login_page->ID ) ) {
                return (string) get_permalink( (int) $login_page->ID );
            }
        }

        return $fallback;
    }

    private function resolve_register_page_url( string $login_url ): string {
        $filtered = apply_filters( 'myvh_register_page_url', '' );
        if ( is_string( $filtered ) && $filtered !== '' ) {
            return esc_url_raw( $filtered );
        }

        foreach ( [ 'register', 'create-account', 'signup', 'sign-up' ] as $slug ) {
            $register_page = get_page_by_path( $slug );
            if ( $register_page && !empty( $register_page->ID ) ) {
                return (string) get_permalink( (int) $register_page->ID );
            }
        }

        return add_query_arg( 'register', '1', $login_url ) . '#myvh-register-form';
    }
}
