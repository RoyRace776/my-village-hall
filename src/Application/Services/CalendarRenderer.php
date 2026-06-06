<?php

declare(strict_types=1);

namespace MYVH\Application\Services;

use MYVH\Availability\AvailabilityService;
use MYVH\Calendar\CalendarService;
use MYVH\Calendar\CalendarStatusColours;
use MYVH\Calendar\EventDetailShortcode;
use MYVH\Pages\Support\PageContentMatcher;
use MYVH\Rooms\RoomColour;
use MYVH\Rooms\RoomService;
use Throwable;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class CalendarRenderer {
    public const REST_NAMESPACE = 'myvh/v1';
    public const REST_ROUTE = '/public-events';

    public function __construct(
        private CalendarService $calendar_service,
        private AvailabilityService $availability_service,
        private RoomService $room_service
    ) {
    }

    public function register_rest_route(): void {
        register_rest_route(
            self::REST_NAMESPACE,
            self::REST_ROUTE,
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [ $this, 'get_events' ],
                'permission_callback' => '__return_true',
                'args' => [
                    'start' => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
                    'end' => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
                    'venue_id' => [ 'required' => false, 'sanitize_callback' => 'absint' ],
                    'room_id' => [ 'required' => false, 'sanitize_callback' => 'absint' ],
                ],
            ]
        );
    }

    public function get_events( WP_REST_Request $request ): WP_REST_Response {
        $default_label = myvh_setting( 'calendar.public_calendar_booking_label', __( 'Private booking', 'my-village-hall' ) );
        $default_label = apply_filters( 'myvh_public_calendar_booking_label', $default_label );
        $default_label = sanitize_text_field( (string) $default_label );
        if ( $default_label === '' ) {
            $default_label = __( 'Private booking', 'my-village-hall' );
        }

        $start = (string) $request->get_param( 'start' );
        $end = (string) $request->get_param( 'end' );
        $venue_id = (int) $request->get_param( 'venue_id' );
        $room_id = (int) $request->get_param( 'room_id' );

        if ( ! $this->is_valid_date( $start ) || ! $this->is_valid_date( $end ) ) {
            return new WP_REST_Response( [], 400 );
        }

        try {
            $events = $this->calendar_service->get_public_feed_events(
                $start,
                $end,
                is_user_logged_in() ? get_current_user_id() : 0,
                [
                    'venue_id' => $venue_id,
                    'room_id' => $room_id,
                ],
                $default_label
            );
        } catch ( Throwable $exception ) {
            $events = [];
        }

        return new WP_REST_Response( $events, 200 );
    }

    public function render( array $attributes = [] ): string {
        $attributes = shortcode_atts(
            [
                'venue_id' => 0,
                'room_id' => 0,
                'view' => 'month',
                'height' => 600,
            ],
            $attributes
        );

        wp_enqueue_script( 'daypilot' );
        wp_enqueue_script( 'myvh-public-calendar' );
        wp_enqueue_style( 'myvh-public-calendar' );

        $unique_id = 'myvh-cal-' . uniqid();
        $events_url = rest_url( self::REST_NAMESPACE . self::REST_ROUTE );
        $nav_id = $unique_id . '-nav';
        $visible_hours = [ 'start' => 8, 'end' => 22 ];
        $public_rooms = [];

        try {
            $service_hours = $this->availability_service->get_calendar_visible_hours();
            if ( is_array( $service_hours ) ) {
                $visible_hours = [
                    'start' => isset( $service_hours['start'] ) ? (int) $service_hours['start'] : $visible_hours['start'],
                    'end' => isset( $service_hours['end'] ) ? (int) $service_hours['end'] : $visible_hours['end'],
                ];
            }

            $rooms = $this->room_service->get_all_with_venues();
            foreach ( (array) $rooms as $room ) {
                $room_id_value = isset( $room['Id'] ) ? (int) $room['Id'] : 0;
                $venue_id_value = isset( $room['VenueId'] ) ? (int) $room['VenueId'] : 0;

                if ( $room_id_value <= 0 ) {
                    continue;
                }

                if ( (int) $attributes['venue_id'] > 0 && $venue_id_value !== (int) $attributes['venue_id'] ) {
                    continue;
                }

                if ( (int) $attributes['room_id'] > 0 && $room_id_value !== (int) $attributes['room_id'] ) {
                    continue;
                }

                $public_rooms[] = [
                    'id' => $room_id_value,
                    'name' => sanitize_text_field( (string) ( $room['Name'] ?? '' ) ),
                    'venueId' => $venue_id_value,
                    'venue' => sanitize_text_field( (string) ( $room['VenueName'] ?? '' ) ),
                    'colour' => RoomColour::resolve( $room['Colour'] ?? '', $room_id_value ),
                    'roomColour' => RoomColour::resolve( $room['Colour'] ?? '', $room_id_value ),
                ];
            }
        } catch ( Throwable $exception ) {
            // Use defaults when services are unavailable.
        }

        wp_localize_script( 'myvh-public-calendar', 'myvhCalConfig', [
            'containerId' => $unique_id,
            'navContainerId' => $nav_id,
            'eventsUrl' => $events_url,
            'venueId' => (int) $attributes['venue_id'],
            'roomId' => (int) $attributes['room_id'],
            'view' => sanitize_key( (string) $attributes['view'] ),
            'height' => (int) $attributes['height'],
            'rooms' => $public_rooms,
            'headerDateFormat' => myvh_setting( 'calendar.calendar_date_format', 'd MMM' ),
            'startOfWeek' => (int) get_option( 'start_of_week', 1 ),
            'maxBookingDaysAhead' => (int) myvh_setting( 'booking.max_booking_days', 365 ),
            'visibleStartHour' => (int) $visible_hours['start'],
            'visibleEndHour' => (int) $visible_hours['end'],
            'schedulerOrientation' => myvh_setting( 'calendar.scheduler_orientation', 'horizontal' ),
            'statusColors' => CalendarStatusColours::map(),
            'nonce' => wp_create_nonce( 'wp_rest' ),
            'i18n' => [
                'today' => __( 'Today', 'my-village-hall' ),
                'month' => __( 'Month', 'my-village-hall' ),
                'week' => __( 'Week', 'my-village-hall' ),
                'day' => __( 'Day', 'my-village-hall' ),
                'venue' => __( 'Venue', 'my-village-hall' ),
                'booked' => __( 'Booked', 'my-village-hall' ),
                'noEvents' => __( 'No bookings this period.', 'my-village-hall' ),
            ],
        ] );

        $login_url = $this->resolve_login_page_url();
        $register_url = $this->resolve_register_page_url( $login_url );
        $portal_url = $this->resolve_portal_page_url();
        $logout_url = wp_logout_url( get_permalink() ?: home_url( '/' ) );

        ob_start();
        ?>
        <div class="myvh-public-calendar-wrap myvh-cal-full-bleed">
            <div class="myvh-cal-login-cta">
                <?php if ( is_user_logged_in() ) :
                    $current_user = wp_get_current_user();
                    $display_name = $current_user->display_name ?: $current_user->user_login;
                ?>
                    <p><?php printf( esc_html__( 'Logged in as %s', 'my-village-hall' ), '<strong>' . esc_html( $display_name ) . '</strong>' ); ?></p>
                    <div class="myvh-cal-auth-actions">
                        <a class="myvh-cal-btn myvh-cal-login-btn" href="<?php echo esc_url( $portal_url ); ?>"><?php esc_html_e( 'Dashboard', 'my-village-hall' ); ?></a>
                        <a class="myvh-cal-btn myvh-cal-login-btn" href="<?php echo esc_url( $logout_url ); ?>"><?php esc_html_e( 'Log out', 'my-village-hall' ); ?></a>
                    </div>
                <?php else : ?>
                    <p><?php esc_html_e( 'Want to manage bookings? Log in or create an account.', 'my-village-hall' ); ?></p>
                    <div class="myvh-cal-auth-actions">
                        <a class="myvh-cal-btn myvh-cal-login-btn" href="<?php echo esc_url( $login_url ); ?>"><?php esc_html_e( 'Log in', 'my-village-hall' ); ?></a>
                        <a class="myvh-cal-btn myvh-cal-register-btn" href="<?php echo esc_url( $register_url ); ?>"><?php esc_html_e( 'Create account', 'my-village-hall' ); ?></a>
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
                        <div class="myvh-cal-venue-filter" style="display:none;">
                            <label class="screen-reader-text" for="<?php echo esc_attr( $unique_id ); ?>-venue"><?php esc_html_e( 'Venue', 'my-village-hall' ); ?></label>
                            <select id="<?php echo esc_attr( $unique_id ); ?>-venue" class="myvh-cal-venue-select"></select>
                        </div>
                        <div class="myvh-cal-room-filter" style="display:none;"></div>
                        <div class="myvh-cal-views">
                            <button class="myvh-cal-btn myvh-mode-btn active" data-mode="calendar"><?php esc_html_e( 'Calendar', 'my-village-hall' ); ?></button>
                            <button class="myvh-cal-btn myvh-mode-btn" data-mode="scheduler"><?php esc_html_e( 'Scheduler', 'my-village-hall' ); ?></button>
                            <button class="myvh-cal-btn myvh-detail-btn <?php echo 'day' === $attributes['view'] ? 'active' : ''; ?>" data-view="day"><?php esc_html_e( 'Day', 'my-village-hall' ); ?></button>
                            <button class="myvh-cal-btn myvh-detail-btn <?php echo 'week' === $attributes['view'] ? 'active' : ''; ?>" data-view="week"><?php esc_html_e( 'Week', 'my-village-hall' ); ?></button>
                            <button class="myvh-cal-btn myvh-detail-btn <?php echo 'month' === $attributes['view'] ? 'active' : ''; ?>" data-view="month"><?php esc_html_e( 'Month', 'my-village-hall' ); ?></button>
                        </div>
                    </div>

                    <div class="myvh-cal-key" aria-label="<?php esc_attr_e( 'Calendar key', 'my-village-hall' ); ?>">
                        <div class="myvh-cal-key-section">
                            <h3 class="myvh-cal-key-title"><?php esc_html_e( 'Statuses', 'my-village-hall' ); ?></h3>
                            <div class="myvh-cal-key-items myvh-cal-key-status-items"></div>
                        </div>
                        <div class="myvh-cal-key-section">
                            <h3 class="myvh-cal-key-title"><?php esc_html_e( 'Rooms', 'my-village-hall' ); ?></h3>
                            <div class="myvh-cal-key-items myvh-cal-key-room-items"></div>
                        </div>
                    </div>

                    <div id="<?php echo esc_attr( $unique_id ); ?>" class="myvh-daypilot-container" style="height: <?php echo (int) $attributes['height']; ?>px;"></div>
                </div>
            </div>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    private function is_valid_date( string $value ): bool {
        return (bool) preg_match( '/^\d{4}-\d{2}-\d{2}/', $value );
    }

    private function resolve_login_page_url(): string {
        $fallback = home_url( '/login/' );
        $filtered = apply_filters( 'myvh_login_page_url', '' );
        if ( is_string( $filtered ) && $filtered !== '' ) {
            return esc_url_raw( $filtered );
        }

        $template_pages = get_pages([
            'post_type' => 'page',
            'post_status' => 'publish',
            'meta_key' => '_wp_page_template',
            'meta_value' => 'templates/page-login.php',
            'number' => 1,
        ]);

        if ( ! empty( $template_pages ) && ! empty( $template_pages[0]->ID ) ) {
            return (string) get_permalink( (int) $template_pages[0]->ID );
        }

        $candidate_pages = get_posts([
            'post_type' => 'page',
            'post_status' => 'publish',
            'posts_per_page' => 200,
            'orderby' => 'menu_order title',
            'order' => 'ASC',
            'suppress_filters' => false,
        ]);

        foreach ( (array) $candidate_pages as $page ) {
            if ( empty( $page->ID ) ) {
                continue;
            }

            if ( PageContentMatcher::containsFeature( (string) $page->post_content, 'myvh_login', 'myvh/login' ) ) {
                return (string) get_permalink( (int) $page->ID );
            }
        }

        foreach ( [ 'login', 'sign-in', 'signin', 'account-login' ] as $slug ) {
            $login_page = get_page_by_path( $slug );
            if ( $login_page && ! empty( $login_page->ID ) ) {
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
            if ( $register_page && ! empty( $register_page->ID ) ) {
                return (string) get_permalink( (int) $register_page->ID );
            }
        }

        return add_query_arg( 'register', '1', $login_url ) . '#myvh-register-form';
    }

    private function resolve_portal_page_url(): string {
        $fallback = home_url( '/portal/' );
        $filtered = apply_filters( 'myvh_portal_page_url', '' );
        if ( is_string( $filtered ) && $filtered !== '' ) {
            return esc_url_raw( $filtered );
        }

        $portal_page = get_page_by_path( 'portal' );
        if ( $portal_page && ! empty( $portal_page->ID ) ) {
            return (string) get_permalink( (int) $portal_page->ID );
        }

        $candidate_pages = get_posts([
            'post_type' => 'page',
            'post_status' => 'publish',
            'posts_per_page' => 200,
            'orderby' => 'menu_order title',
            'order' => 'ASC',
            'suppress_filters' => false,
        ]);

        foreach ( (array) $candidate_pages as $page ) {
            if ( empty( $page->ID ) ) {
                continue;
            }

            if ( PageContentMatcher::containsFeature( (string) $page->post_content, 'myvh_portal', 'myvh/portal' ) ) {
                return (string) get_permalink( (int) $page->ID );
            }
        }

        foreach ( [ 'portal', 'client-portal', 'account-portal', 'dashboard' ] as $slug ) {
            $candidate = get_page_by_path( $slug );
            if ( $candidate && ! empty( $candidate->ID ) ) {
                return (string) get_permalink( (int) $candidate->ID );
            }
        }

        return $fallback;
    }
}