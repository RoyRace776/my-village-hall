<?php
/**
 * Instantiate all repositories
 *
 * Each repository file no longer self-registers into a global variable —
 * the two lines at the bottom of each class file (global $x; $x = new X();)
 * have been removed. All instances live here.
 */

require_once MYVH_PLUGIN_DIR . 'modules/addons/class-addon-repository.php';
require_once MYVH_PLUGIN_DIR . 'modules/bookings/class-booking-addon-repository.php';
require_once MYVH_PLUGIN_DIR . 'modules/bookings/class-booking-charge-repository.php';
require_once MYVH_PLUGIN_DIR . 'modules/bookings/class-booking-discount-repository.php';
require_once MYVH_PLUGIN_DIR . 'modules/bookings/class-booking-repository.php';
require_once MYVH_PLUGIN_DIR . 'modules/customer-groups/class-customer-group-repository.php';
require_once MYVH_PLUGIN_DIR . 'modules/customers/class-customer-repository.php';
require_once MYVH_PLUGIN_DIR . 'modules/pricing/class-discount-repository.php';
require_once MYVH_PLUGIN_DIR . 'modules/invoices/class-invoice-item-repository.php';
require_once MYVH_PLUGIN_DIR . 'modules/invoices/class-invoice-repository.php';
require_once MYVH_PLUGIN_DIR . 'modules/payments/class-payment-repository.php';
require_once MYVH_PLUGIN_DIR . 'modules/bookings/class-recurring-pattern-repository.php';
require_once MYVH_PLUGIN_DIR . 'modules/pricing/class-room-rate-repository.php';
require_once MYVH_PLUGIN_DIR . 'modules/rooms/class-room-repository.php';
require_once MYVH_PLUGIN_DIR . 'modules/venues/class-venue-repository.php';
