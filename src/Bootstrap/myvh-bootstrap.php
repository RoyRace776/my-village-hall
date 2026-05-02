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
use MYVH\Events\BookingListener;
use MYVH\Events\CustomerListener;
use MYVH\Events\OrganisationListener;
use MYVH\Core\Shortcode\ShortcodeRegistry;


global $myvh_container;

( new BookingListener() )->register();
( new CustomerListener() )->register();
( new OrganisationListener() )->register();

if ( $myvh_container instanceof Container ) {
    $calendar_ajax = $myvh_container->get( MYVH\Calendar\CalendarAjaxController::class );
    $calendar_ajax->register_routes();

    $registry = new ShortcodeRegistry();
    add_action( 'init', [ $registry, 'register' ] );

    $registry->add( $myvh_container->get( MYVH\Login\LoginShortcode::class ) );
    $registry->add( $myvh_container->get( MYVH\Portal\PortalShortcode::class ) );
    $registry->add( $myvh_container->get( MYVH\Network\CreateSiteShortcode::class ) );

    $login_handler = $myvh_container->get( MYVH\Login\LoginHandler::class );
    $login_handler->init();

    $portal_controller = $myvh_container->get( MYVH\Portal\PortalController::class );
    $portal_controller->register();

    $portal_account_ajax = $myvh_container->get( MYVH\Portal\Ajax\PortalAccountAjaxController::class );
    $portal_account_ajax->register();

    $portal_admin_config_ajax = $myvh_container->get( MYVH\Portal\Ajax\PortalAdminConfigAjaxController::class );
    $portal_admin_config_ajax->register();

    $portal_people_ajax = $myvh_container->get( MYVH\Portal\Ajax\PortalPeopleAjaxController::class );
    $portal_people_ajax->register();

    $portal_billing_ajax = $myvh_container->get( MYVH\Portal\Ajax\PortalBillingAjaxController::class );
    $portal_billing_ajax->register();

    $portal_organisation_ajax = $myvh_container->get( MYVH\Portal\Ajax\PortalOrganisationAjaxController::class );
    $portal_organisation_ajax->register();

    $portal_booking_ajax = $myvh_container->get( MYVH\Portal\Ajax\PortalBookingAjaxController::class );
    $portal_booking_ajax->register();

    $auto_invoicing_ajax = $myvh_container->get( MYVH\AutoInvoicing\AutoInvoicingAjaxController::class );
    $auto_invoicing_ajax->register();

    $invoice_auto_send_listener = $myvh_container->get( MYVH\Invoices\InvoiceAutoSendListener::class );
    $invoice_auto_send_listener->register();

    $customer_user_sync = $myvh_container->get( MYVH\Customers\CustomerUserSync::class );
    $customer_user_sync->register();

    // Admin password reset AJAX handler
    $admin_password_reset = new MYVH\Admin\AdminPasswordResetHandler(
        $myvh_container->get( MYVH\Customers\CustomerService::class )
    );
    $admin_password_reset->register();
}
