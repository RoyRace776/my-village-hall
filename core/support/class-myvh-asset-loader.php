<?php

class MYVH_Asset_Loader {

    private static function asset_version( $relative_path ): string {
        $full_path = MYVH_PLUGIN_DIR . ltrim( $relative_path, '/\\' );

        if ( file_exists( $full_path ) ) {
            $mtime = filemtime( $full_path );
            if ( $mtime ) {
                return (string) $mtime;
            }
        }

        return MYVH_VERSION;
    }

    public static function get_calendar_visible_hours(): array {

        $defaults = [
            'start' => 8,
            'end' => 22,
        ];

        global $myvh_container;

        if ( ! isset( $myvh_container ) ) {
            return $defaults;
        }

        try {
            $availability_service = $myvh_container->get( MYVH_Availability_Service::class );
            $visible_hours = $availability_service->get_calendar_visible_hours();
        } catch ( Throwable $e ) {
            return $defaults;
        }

        if ( empty( $visible_hours ) || ! is_array( $visible_hours ) ) {
            return $defaults;
        }

        return [
            'start' => isset( $visible_hours['start'] ) ? (int) $visible_hours['start'] : $defaults['start'],
            'end'   => isset( $visible_hours['end'] ) ? (int) $visible_hours['end'] : $defaults['end'],
        ];
    }

    public static function init(): void {
        add_action( 'wp_enqueue_scripts',    [ self::class, 'enqueue_frontend' ] );
        add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_admin'    ] );
    }

    // ── Frontend ──────────────────────────────────────────────────────────────

    public static function enqueue_frontend(): void {

        self::register_public_calendar_assets();

        if ( ! is_singular() ) return;

        $content = get_queried_object()->post_content ?? '';

        if ( has_shortcode( $content, 'myvh_portal' ) ) {
            self::enqueue_portal();
        }

        // Public calendar assets are enqueued directly by MYVH_Calendar_Shortcode::render()
        // to support per-instance container IDs and REST config.
    }

    public static function register_public_calendar_assets(): void {

        // Use the plugin-bundled DayPilot build so public calendar works in local/offline environments.
        wp_register_script(
            'daypilot',
            MYVH_PLUGIN_URL . 'assets/js/daypilot-all.min.js',
            [],
            MYVH_VERSION,
            true
        );

        wp_register_script(
            'myvh-public-calendar',
            MYVH_PLUGIN_URL . 'assets/js/public-calendar.js',
            [ 'daypilot' ],
            self::asset_version( 'assets/js/public-calendar.js' ),
            true
        );

        wp_register_style(
            'myvh-calendar-theme',
            MYVH_PLUGIN_URL . 'assets/css/calendar.css',
            [],
            self::asset_version( 'assets/css/calendar.css' )
        );

        wp_register_style(
            'myvh-public-calendar',
            MYVH_PLUGIN_URL . 'assets/css/public-calendar.css',
            [ 'myvh-calendar-theme' ],
            MYVH_VERSION
        );
    }

    // ── Admin ─────────────────────────────────────────────────────────────────

    public static function enqueue_admin( $hook ): void {

        if ( strpos( $hook, 'myvh' ) === false && strpos( $hook, 'my-village-hall' ) === false ) return;

        // Shared across all plugin admin pages
        wp_enqueue_style(
            'myvh-admin',
            MYVH_PLUGIN_URL . 'assets/css/admin.css',
            [],
            self::asset_version( 'assets/css/admin.css' )
        );

        wp_enqueue_script(
            'myvh-admin',
            MYVH_PLUGIN_URL . 'assets/js/admin.js',
            [ 'jquery' ],
            self::asset_version( 'assets/js/admin.js' ),
            true
        );

        // Bookings list page
        if ( strpos( $hook, 'my-village-hall' ) !== false ) {
            wp_enqueue_script(
                'myvh-bookings',
                MYVH_PLUGIN_URL . 'assets/js/bookings.js',
                [ 'jquery', 'myvh-admin' ],
                MYVH_VERSION,
                true
            );
        }

        // Calendar page only
        if ( strpos( $hook, 'myvh-calendar' ) !== false ) {

            $visible_hours = self::get_calendar_visible_hours();

            wp_enqueue_style(
                'myvh-calendar-theme',
                MYVH_PLUGIN_URL . 'assets/css/calendar.css',
                [],
                self::asset_version( 'assets/css/calendar.css' )
            );

            wp_enqueue_script(
                'daypilot',
                MYVH_PLUGIN_URL . 'assets/js/daypilot-all.min.js',
                [],
                MYVH_VERSION,
                true
            );

            wp_enqueue_script(
                'myvh-calendar-core',
                MYVH_PLUGIN_URL . 'assets/js/calendar-core.js',
                ['daypilot'],
                self::asset_version( 'assets/js/calendar-core.js' ),
                true
            );

            wp_enqueue_script(
                'myvh-booking-modal',
                MYVH_PLUGIN_URL . 'assets/js/booking-modal.js',
                ['myvh-calendar-core'], // optional but safe
                '1.0',
                true
            );

            wp_enqueue_script(
                'myvh-calendar-admin',
                MYVH_PLUGIN_URL . 'assets/js/calendar-admin.js',
                ['jquery', 'myvh-booking-modal'], // ✅ critical
                self::asset_version( 'assets/js/calendar-admin.js' ),
                true
            );

            wp_localize_script( 'myvh-calendar-admin', 'myvhCal', [
                'ajax_url'              => admin_url( 'admin-ajax.php' ),
                'nonce'                 => wp_create_nonce( 'myvh_calendar' ),
                'headerDateFormat'      => myvh_setting( 'general.calendar_date_format', 'd MMM' ),
                'maxBookingDaysAhead'   => (int) myvh_setting( 'booking.general.max_booking_days', 365 ),
                'visibleStartHour'      => $visible_hours['start'],
                'visibleEndHour'        => $visible_hours['end'],
                'schedulerOrientation'  => myvh_setting( 'general.scheduler_orientation', 'horizontal' ),
            ] );
        }
    }

    // ── Portal (frontend, [myvh_portal] shortcode) ────────────────────────────

    public static function enqueue_portal(): void {

        $visible_hours = self::get_calendar_visible_hours();

        $current_customer_id = 0;
        $default_organisation_id = 0;
        $is_client_admin = false;

        if ( is_user_logged_in() ) {
            global $myvh_container;

            if ( isset( $myvh_container ) ) {
                $client_admin_service = $myvh_container->get( MYVH_Client_Admin_Service::class );
                $customer_service = $myvh_container->get( MYVH_Customer_Service::class );
                $customer = $customer_service->get_by_user_id( get_current_user_id() );
                $organisations = $customer_service->get_organisations_for_user_id( get_current_user_id() );
                $is_client_admin = $client_admin_service->can_administer_blog( get_current_user_id(), get_current_blog_id() );

                $current_customer_id = ! empty( $customer['Id'] ) ? (int) $customer['Id'] : 0;
                $default_organisation_id = ! empty( $organisations[0]['Id'] ) ? (int) $organisations[0]['Id'] : 0;
            }
        }

        // Google Fonts (filterable so the theme can suppress the duplicate)
        $fonts_url = apply_filters(
            'myvh_portal_fonts_url',
            'https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,500;0,700;1,500&family=DM+Sans:wght@300;400;500&display=swap'
        );

        if ( $fonts_url ) {
            wp_enqueue_style( 'myvh-google-fonts', $fonts_url, [], null );
        }

        wp_enqueue_style(
            'myvh-dashboard',
            MYVH_PLUGIN_URL . 'assets/css/dashboard.css',
            $fonts_url ? [ 'myvh-google-fonts' ] : [],
            self::asset_version( 'assets/css/dashboard.css' )
        );

        wp_enqueue_style( 'myvh-calendar-theme' );

        wp_enqueue_style(
            'myvh-login',
            MYVH_PLUGIN_URL . 'assets/css/login.css',
            [ 'myvh-dashboard' ],
            MYVH_VERSION
        );

        wp_enqueue_script(
            'daypilot',
            MYVH_PLUGIN_URL . 'assets/js/daypilot-all.min.js',
            [],
            MYVH_VERSION,
            true
        );

        wp_enqueue_script(
            'myvh-calendar-core',
            MYVH_PLUGIN_URL . 'assets/js/calendar-core.js',
            [ 'daypilot' ],
            self::asset_version( 'assets/js/calendar-core.js' ),
            true
        );

        wp_enqueue_script(
            'myvh-booking-modal',
            MYVH_PLUGIN_URL . 'assets/js/booking-modal.js',
            ['myvh-calendar-core'], // optional but safe
            '1.0',
            true
        );

        wp_enqueue_script(
            'myvh-calendar-portal',
            MYVH_PLUGIN_URL . 'assets/js/calendar-portal.js',
            [ 'myvh-booking-modal' ],
            self::asset_version( 'assets/js/calendar-portal.js' ),
            true
        );

        wp_enqueue_script(
            'myvh-bookings',
            MYVH_PLUGIN_URL . 'assets/js/bookings.js',
            [ 'jquery' ],
            MYVH_VERSION,
            true
        );

        wp_enqueue_script(
            'myvh-dashboard',
            MYVH_PLUGIN_URL . 'assets/js/dashboard.js',
            [ 'jquery', 'myvh-calendar-portal', 'myvh-bookings' ],
            MYVH_VERSION,
            true
        );

        wp_enqueue_script(
            'myvh-portal-app',
            MYVH_PLUGIN_URL . 'assets/js/portal-app.js',
            [ 'myvh-dashboard' ],
            MYVH_VERSION,
            true
        );

        wp_localize_script( 'myvh-portal-app', 'myvhPortal', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'myvh_portal' ),
            'isClientAdmin' => $is_client_admin ? 1 : 0,
        ] );

        // myvhCal used by calendar-core.js via calendar-portal.js
        wp_localize_script( 'myvh-calendar-portal', 'myvhCal', [
            'ajax_url'              => admin_url( 'admin-ajax.php' ),
            'nonce'                 => wp_create_nonce( 'myvh_calendar' ),
            'headerDateFormat'      => myvh_setting( 'general.calendar_date_format', 'd MMM' ),
            'maxBookingDaysAhead'   => (int) myvh_setting( 'booking.general.max_booking_days', 365 ),
            'currentCustomerId'     => $current_customer_id,
            'defaultOrganisationId' => $default_organisation_id,
            'isClientAdmin'         => $is_client_admin ? 1 : 0,
            'visibleStartHour'      => $visible_hours['start'],
            'visibleEndHour'        => $visible_hours['end'],
            'schedulerOrientation'  => myvh_setting( 'general.scheduler_orientation', 'horizontal' ),
        ] );
    }

    // ── Public calendar ([myvh_public_calendar] shortcode) ────────────────────

    public static function enqueue_public_calendar(): void {

        wp_enqueue_script(
            'daypilot',
            MYVH_PLUGIN_URL . 'assets/js/daypilot-all.min.js',
            [],
            MYVH_VERSION,
            true
        );

        wp_enqueue_script(
            'myvh-calendar-core',
            MYVH_PLUGIN_URL . 'assets/js/calendar-core.js',
            [ 'daypilot' ],
            MYVH_VERSION,
            true
        );

        wp_enqueue_script(
            'myvh-calendar-public',
            MYVH_PLUGIN_URL . 'assets/js/calendar-public.js',
            [ 'myvh-calendar-core' ],
            MYVH_VERSION,
            true
        );

        wp_localize_script( 'myvh-calendar-public', 'myvhCalConfig', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'myvh_calendar' ),
            'view'    => 'month',
        ] );
    }
}
