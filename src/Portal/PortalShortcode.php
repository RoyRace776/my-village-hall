<?php
namespace MYVH\Portal;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use MYVH\Calendar\CalendarStatusColours;
use MYVH\Core\Shortcode\ShortcodeInterface;
use MYVH\Core\Support\AssetLoader;

class PortalShortcode implements ShortcodeInterface
{
    private PortalBootstrapDataService $bootstrap;

    public function __construct( PortalBootstrapDataService $bootstrap ) {
        $this->bootstrap = $bootstrap;
    }

    public function tag(): string
    {
        return 'myvh_portal';
    }

    public function render($atts = [], $content = null): string
    {
        if ( ! is_user_logged_in() ) {
            return do_shortcode( '[myvh_login]' );
        }

        $portal_data = $this->bootstrap->get();

        AssetLoader::enqueue_portal_assets();
        wp_enqueue_style( 'dashicons' );
        wp_enqueue_editor();

        wp_localize_script( 'myvh-portal-app', 'myvhPortal', [
            'ajax_url'      => admin_url( 'admin-ajax.php' ),
            'nonce'         => wp_create_nonce( 'myvh_portal' ),
            'isClientAdmin' => $portal_data['is_client_admin'] ? 1 : 0,
            'site_name'     => get_bloginfo( 'name' ),
        ] );

        // myvhCal is consumed by calendar-core.js via calendar-portal.js
        wp_localize_script( 'myvh-calendar-portal', 'myvhCal', [
            'ajax_url'              => admin_url( 'admin-ajax.php' ),
            'nonce'                 => wp_create_nonce( 'myvh_calendar' ),
            'portalNonce'           => wp_create_nonce( 'myvh_portal' ),
            'site_name'             => get_bloginfo( 'name' ),
            'headerDateFormat'      => myvh_setting( 'calendar.calendar_date_format', 'd MMM' ),
            'startOfWeek'           => (int) get_option( 'start_of_week', 1 ),
            'maxBookingDaysAhead'   => (int) myvh_setting( 'booking.max_booking_days', 365 ),
            'currentCustomerId'     => $portal_data['current_customer_id'],
            'defaultOrganisationId' => $portal_data['default_organisation_id'],
            'isClientAdmin'         => $portal_data['is_client_admin'] ? 1 : 0,
            'visibleStartHour'      => $portal_data['visible_hours']['start'],
            'visibleEndHour'        => $portal_data['visible_hours']['end'],
            'schedulerOrientation'  => myvh_setting( 'calendar.scheduler_orientation', 'horizontal' ),
            'statusColors'          => CalendarStatusColours::map(),
        ] );

        // Unpack for the template — avoids array-access syntax inside the view.
        $accessible_sites = $portal_data['accessible_sites'];
        $is_client_admin  = $portal_data['is_client_admin'];
        $has_customer     = $portal_data['has_customer'];
        $portal_branding  = $portal_data['portal_branding'] ?? [];
        $portal_login_url = $this->resolve_login_page_url();
        $portal_logout_url = wp_logout_url( $portal_login_url );

        ob_start();
        include MYVH_PLUGIN_DIR . 'templates/Portal/portal-shell.php';
        return (string) ob_get_clean();
    }

    private function resolve_login_page_url(): string
    {
        $fallback = home_url('/portal/');

        $filtered = apply_filters( 'myvh_login_page_url', '' );
        if ( is_string( $filtered ) && $filtered !== '' ) {
            return esc_url_raw( $filtered );
        }

        $template_pages = get_pages([
            'post_type'   => 'page',
            'post_status' => 'publish',
            'meta_key'    => '_wp_page_template',
            'meta_value'  => 'templates/page-login.php',
            'number'      => 1,
        ]);

        if ( ! empty( $template_pages ) && ! empty( $template_pages[0]->ID ) ) {
            return (string) get_permalink( (int) $template_pages[0]->ID );
        }

        $candidate_pages = get_posts([
            'post_type'        => 'page',
            'post_status'      => 'publish',
            'posts_per_page'   => 200,
            'orderby'          => 'menu_order title',
            'order'            => 'ASC',
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

        foreach ( [ 'login', 'sign-in', 'signin', 'account-login' ] as $slug ) {
            $login_page = get_page_by_path( $slug );
            if ( $login_page && ! empty( $login_page->ID ) ) {
                return (string) get_permalink( (int) $login_page->ID );
            }
        }

        return $fallback;
    }

}
