<?php
/**
 * Event listeners & late wiring
 *
 * This file runs after the container is fully configured.  Its job is to
 * instantiate anything that needs to listen for WordPress events or that
 * didn't fit cleanly into a service provider.
 *
 * Currently: registers the booking domain event listener.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once MYVH_PLUGIN_DIR . 'modules/events/class-myvh-booking-listener.php';
require_once MYVH_PLUGIN_DIR . 'core/shortcode/shortcode-interface.php';
require_once MYVH_PLUGIN_DIR . 'core/shortcode/class-myvh-shortcode-registry.php';
require_once MYVH_PLUGIN_DIR . 'modules/login/class-myvh-login-shortcode.php';
require_once MYVH_PLUGIN_DIR . 'modules/portal/class-myvh-portal-shortcode.php';
require_once MYVH_PLUGIN_DIR . 'modules/portal/class-myvh-portal-controller.php';
require_once MYVH_PLUGIN_DIR . 'modules/login/class-myvh-login-handler.php';

global $myvh_container;

( new MYVH_Booking_Listener() )->register();

if ( $myvh_container instanceof MYVH_Container ) {
    $calendar_ajax = $myvh_container->get( MYVH_Calendar_Ajax_Controller::class );
    $calendar_ajax->register_routes();

    $registry = new MyVH\Infrastructure\MYVH_Shortcode_Registry();
    add_action( 'init', [ $registry, 'register' ] );

    $registry->add( $myvh_container->get( \MYVH\Shortcodes\MYVH_Login_Shortcode::class ) );

    $login_handler = new MYVH_Login_Handler();
    $login_handler->init();

    $portal_shortcode = new MYVH_Portal_Shortcode();
    $portal_shortcode->register();

    $portal_controller = $myvh_container->get( MYVH_Portal_Controller::class );
    $portal_controller->register();

    $customer_user_sync = $myvh_container->get( MYVH_Customer_User_Sync::class );
    $customer_user_sync->register();
}
