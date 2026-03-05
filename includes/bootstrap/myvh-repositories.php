<?php
/**
 * Instantiate all repositories and register them in MYVH_Registry.
 *
 * Each repository file no longer self-registers into a global variable —
 * the two lines at the bottom of each class file (global $x; $x = new X();)
 * have been removed. All instances live here.
 */

require_once MYVH_PLUGIN_DIR . 'includes/bootstrap/class-myvh-registry.php';

require_once MYVH_PLUGIN_DIR . 'includes/repositories/class-addon-repository.php';
require_once MYVH_PLUGIN_DIR . 'includes/repositories/class-booking-addon-repository.php';
require_once MYVH_PLUGIN_DIR . 'includes/repositories/class-booking-charge-repository.php';
require_once MYVH_PLUGIN_DIR . 'includes/repositories/class-booking-discount-repository.php';
require_once MYVH_PLUGIN_DIR . 'includes/repositories/class-booking-repository.php';
require_once MYVH_PLUGIN_DIR . 'includes/repositories/class-customer-group-repository.php';
require_once MYVH_PLUGIN_DIR . 'includes/repositories/class-customer-repository.php';
require_once MYVH_PLUGIN_DIR . 'includes/repositories/class-discount-repository.php';
require_once MYVH_PLUGIN_DIR . 'includes/repositories/class-invoice-item-repository.php';
require_once MYVH_PLUGIN_DIR . 'includes/repositories/class-invoice-repository.php';
require_once MYVH_PLUGIN_DIR . 'includes/repositories/class-payment-repository.php';
require_once MYVH_PLUGIN_DIR . 'includes/repositories/class-recurring-pattern-repository.php';
require_once MYVH_PLUGIN_DIR . 'includes/repositories/class-room-rate-repository.php';
require_once MYVH_PLUGIN_DIR . 'includes/repositories/class-room-repository.php';
require_once MYVH_PLUGIN_DIR . 'includes/repositories/class-venue-repository.php';

global $wpdb;

MYVH_Registry::set('addon_repo',              new MYVH_Addon_Repository($wpdb));
MYVH_Registry::set('booking_addon_repo',      new MYVH_Booking_Addon_Repository($wpdb));
MYVH_Registry::set('booking_charge_repo',     new MYVH_Booking_Charge_Repository($wpdb));
MYVH_Registry::set('booking_discount_repo',   new MYVH_Booking_Discount_Repository($wpdb));
MYVH_Registry::set('booking_repo',            new MYVH_Booking_Repository($wpdb));
MYVH_Registry::set('customer_group_repo',     new MYVH_Customer_Group_Repository($wpdb));
MYVH_Registry::set('customer_repo',           new MYVH_Customer_Repository($wpdb));
MYVH_Registry::set('discount_repo',           new MYVH_Discount_Repository($wpdb));
MYVH_Registry::set('invoice_item_repo',       new MYVH_Invoice_Item_Repository($wpdb));
MYVH_Registry::set('invoice_repo',            new MYVH_Invoice_Repository($wpdb));
MYVH_Registry::set('payment_repo',            new MYVH_Payment_Repository($wpdb));
MYVH_Registry::set('recurring_pattern_repo',  new MYVH_Recurring_Pattern_Repository($wpdb));
MYVH_Registry::set('room_rate_repo',          new MYVH_Room_Rate_Repository($wpdb));
MYVH_Registry::set('room_repo',               new MYVH_Room_Repository($wpdb));
MYVH_Registry::set('venue_repo',              new MYVH_Venue_Repository($wpdb));
