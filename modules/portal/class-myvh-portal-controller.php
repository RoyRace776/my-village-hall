<?php

class MYVH_Portal_Controller {
    private $booking_service;

    public function __construct(MYVH_Booking_Service $booking_service ) {
        $this->booking_service = $booking_service;
    }

    public function register() {

        add_action('wp_ajax_myvh_portal_page', [$this, 'load_page']);
    }

    public function load_page() {

        $page = sanitize_text_field($_GET['page'] ?? 'dashboard');

        switch ($page) {

            case 'bookings':
                include MYVH_PLUGIN_DIR . 'modules/portal/templates/bookings.php';
                break;

            case 'calendar':
                include MYVH_PLUGIN_DIR . 'modules/portal/templates/calendar.php';
                break;

            default:
                $bookings = $this->booking_service->get_upcoming_bookings(get_current_user_id());
                include MYVH_PLUGIN_DIR . 'modules/portal/templates/dashboard.php';
        }

        wp_die();
    }
}
