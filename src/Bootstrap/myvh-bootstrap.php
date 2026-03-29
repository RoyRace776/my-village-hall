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
use MYVH\Portal\PortalShortcode;
use MYVH\Events\BookingListener;
use MYVH\Core\Shortcode\ShortcodeRegistry;


global $myvh_container;

( new BookingListener() )->register();

if ( $myvh_container instanceof Container ) {
    $calendar_ajax = $myvh_container->get( MYVH\Calendar\CalendarAjaxController::class );
    $calendar_ajax->register_routes();

    $registry = new ShortcodeRegistry();
    add_action( 'init', [ $registry, 'register' ] );

    $registry->add( $myvh_container->get( MYVH\Login\LoginShortcode::class ) );
    $registry->add( new PortalShortcode() );

    $login_handler = new MYVH\Login\LoginHandler();
    $login_handler->init();

    $portal_controller = $myvh_container->get( MYVH\Portal\PortalController::class );
    $portal_controller->register();

    $customer_user_sync = $myvh_container->get( MYVH\Customers\CustomerUserSync::class );
    $customer_user_sync->register();
}
