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

        wp_localize_script( 'myvh-portal-app', 'myvhPortal', [
            'ajax_url'      => admin_url( 'admin-ajax.php' ),
            'nonce'         => wp_create_nonce( 'myvh_portal' ),
            'isClientAdmin' => $portal_data['is_client_admin'] ? 1 : 0,
        ] );

        // myvhCal is consumed by calendar-core.js via calendar-portal.js
        wp_localize_script( 'myvh-calendar-portal', 'myvhCal', [
            'ajax_url'              => admin_url( 'admin-ajax.php' ),
            'nonce'                 => wp_create_nonce( 'myvh_calendar' ),
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

        ob_start();
        include MYVH_PLUGIN_DIR . 'templates/Portal/portal-shell.php';
        return (string) ob_get_clean();
    }
}
