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
use MYVH\Container\Container;

require_once MYVH_PLUGIN_DIR . 'modules/events/class-myvh-booking-listener.php';
require_once MYVH_PLUGIN_DIR . 'core/shortcode/shortcode-interface.php';
require_once MYVH_PLUGIN_DIR . 'core/shortcode/class-myvh-shortcode-registry.php';
require_once MYVH_PLUGIN_DIR . 'modules/login/class-myvh-login-shortcode.php';
require_once MYVH_PLUGIN_DIR . 'modules/portal/class-myvh-portal-shortcode.php';
require_once MYVH_PLUGIN_DIR . 'modules/portal/class-myvh-portal-controller.php';
require_once MYVH_PLUGIN_DIR . 'modules/login/class-myvh-login-handler.php';

global $myvh_container;

( new Booking_Listener() )->register();

if ( $myvh_container instanceof Container ) {
    $calendar_ajax = $myvh_container->get( Calendar_Ajax_Controller::class );
    $calendar_ajax->register_routes();

    $registry = new MyVH\Infrastructure\Shortcode_Registry();
    add_action( 'init', [ $registry, 'register' ] );

    $registry->add( $myvh_container->get( \MYVH\Shortcodes\Login_Shortcode::class ) );
    $registry->add( new \MYVH\Shortcodes\Portal_Shortcode() );

    $login_handler = new Login_Handler();
    $login_handler->init();

    $portal_controller = $myvh_container->get( Portal_Controller::class );
    $portal_controller->register();

    $customer_user_sync = $myvh_container->get( Customer_User_Sync::class );
    $customer_user_sync->register();
}
