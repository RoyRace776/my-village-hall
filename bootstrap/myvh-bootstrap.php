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

( new MYVH_Booking_Listener() )->register();
