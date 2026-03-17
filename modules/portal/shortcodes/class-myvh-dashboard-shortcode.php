<?php
namespace MYVH\Shortcodes;

class MYVH_Dashboard_Shortcode implements MYVH_Shortcode_Interface
{
    private $booking_service;

    public function __construct(\MYVH_Booking_Service $booking_service) {
        $this->booking_service = $booking_service;
    }

    public function tag(): string
    {
        return 'myvh_dashboard';
    }

    public function render($atts = [], $content = null): string
    {

        $user_id = null;
        if (!is_user_logged_in()) {
            // TODO: go to the login page
            wp_die("Not logged in", "log in");
        }
        else {
            $user_id = get_current_user_id();
        }
        wp_enqueue_script(
            'myvh-dashboard',
            MYVH_PLUGIN_URL . 'module/portal/css/dashboard.css',
            null,
            '1.0',
            true
        );

        $atts = shortcode_atts([
            'room_id' => null
        ], $atts);

        $bookings = $this->booking_service->get_upcoming_bookings($user_id);

        ob_start();

        include MYVH_PLUGIN_DIR . 'modules/portal/templates/dashboard.php';

        return ob_get_clean();
    }
}
