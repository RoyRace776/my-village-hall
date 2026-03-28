require_once MYVH_PLUGIN_DIR . 'modules/portal/class-myvh-portal-shortcode.php';
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
use MYVH\Shortcodes\PortalShortcode;

require_once MYVH_PLUGIN_DIR . 'modules/events/class-myvh-booking-listener.php';
require_once MYVH_PLUGIN_DIR . 'core/shortcode/shortcode-interface.php';
require_once MYVH_PLUGIN_DIR . 'core/shortcode/class-myvh-shortcode-registry.php';
require_once MYVH_PLUGIN_DIR . 'modules/login/LoginShortcode.php';
require_once MYVH_PLUGIN_DIR . 'modules/portal/class-myvh-portal-shortcode.php';
require_once MYVH_PLUGIN_DIR . 'modules/portal/class-myvh-portal-controller.php';
require_once MYVH_PLUGIN_DIR . 'modules/login/LoginHandler.php';

global $myvh_container;

( new BookingListener() )->register();

if ( $myvh_container instanceof Container ) {
    $calendar_ajax = $myvh_container->get( CalendarAjaxController::class );
    $calendar_ajax->register_routes();

    $registry = new MyVH\Infrastructure\ShortcodeRegistry();
    add_action( 'init', [ $registry, 'register' ] );

    $registry->add( $myvh_container->get( \MYVH\Shortcodes\LoginShortcode::class ) );
    $registry->add( new PortalShortcode() );

    $login_handler = new LoginHandler();
    $login_handler->init();

    $portal_controller = $myvh_container->get( PortalController::class );
    $portal_controller->register();

    $customer_user_sync = $myvh_container->get( CustomerUserSync::class );
    $customer_user_sync->register();
}
