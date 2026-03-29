<?php
if (!defined('ABSPATH')) exit;

add_action('wp_ajax_myvh_portal_get_booking', function() {
    check_ajax_referer('myvh_portal', 'nonce');
    if (!is_user_logged_in()) {
        wp_send_json_error('Not logged in', 401);
    }
    $booking_id = intval($_GET['booking_id'] ?? $_POST['booking_id'] ?? 0);
    if (!$booking_id) {
        wp_send_json_error('Missing booking_id', 400);
    }
    $customer_service = $GLOBALS['myvh_container']->get('Customer_Service');
    $client_admin_service = $GLOBALS['myvh_container']->get('Client_Admin_Service');
    $booking_service = $GLOBALS['myvh_container']->get('Booking_Service');
    $customer = $customer_service->get_by_user_id(get_current_user_id());
    $is_client_admin = $client_admin_service->can_administer_blog(get_current_user_id(), get_current_blog_id());
    if ($is_client_admin) {
        $booking = $booking_service->get_by_id_with_details($booking_id);
    } else {
        $bookings = $booking_service->get_all_with_details(['customer_id' => $customer['Id']]);
        $booking = null;
        foreach ($bookings as $b) {
            if (intval($b['Id']) === $booking_id) {
                $booking = $b;
                break;
            }
        }
    }
    if (!$booking) {
        wp_send_json_error('Booking not found or not accessible', 404);
    }
    wp_send_json_success(['booking' => $booking]);
});
