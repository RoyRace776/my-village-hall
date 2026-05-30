<?php

namespace MYVH\Hooks;

use MYVH\Addons\AddonController;
use MYVH\Admin\AdminPageRouter;
use MYVH\AutoInvoicing\RecurringBookingAutoInvoiceRuleController;
use MYVH\AutoInvoicing\SingleBookingAutoInvoiceRuleController;
use MYVH\Bookings\BookingController;
use MYVH\Bookings\RecurringPatternController;
use MYVH\Container\Container;
use MYVH\Customers\CustomerController;
use MYVH\Invoices\InvoiceController;
use MYVH\Organisations\OrganisationController;
use MYVH\Organisations\OrganisationTypeController;
use MYVH\Payments\PaymentController;
use MYVH\Pricing\RoomRateController;
use MYVH\Rooms\RoomController;
use MYVH\Venues\VenueController;

class AdminPostRegistrar {
    /** @var array<string, array{class: class-string, method: string}> */
    private array $controller_actions = [
        'myvh_save_booking' => [ 'class' => BookingController::class, 'method' => 'save' ],
        'myvh_cancel_booking' => [ 'class' => BookingController::class, 'method' => 'cancel' ],
        'myvh_save_recurring_pattern' => [ 'class' => RecurringPatternController::class, 'method' => 'save' ],
        'myvh_delete_recurring_pattern' => [ 'class' => RecurringPatternController::class, 'method' => 'delete' ],
        'myvh_deactivate_recurring_pattern' => [ 'class' => RecurringPatternController::class, 'method' => 'deactivate' ],
        'myvh_delete_future_bookings' => [ 'class' => RecurringPatternController::class, 'method' => 'delete_future_bookings' ],
        'myvh_process_patterns' => [ 'class' => RecurringPatternController::class, 'method' => 'process_patterns' ],
        'myvh_save_venue' => [ 'class' => VenueController::class, 'method' => 'save' ],
        'myvh_delete_venue' => [ 'class' => VenueController::class, 'method' => 'delete' ],
        'myvh_save_room' => [ 'class' => RoomController::class, 'method' => 'save' ],
        'myvh_delete_room' => [ 'class' => RoomController::class, 'method' => 'delete' ],
        'myvh_save_customer' => [ 'class' => CustomerController::class, 'method' => 'save' ],
        'myvh_delete_customer' => [ 'class' => CustomerController::class, 'method' => 'delete' ],
        'myvh_save_organisation' => [ 'class' => OrganisationController::class, 'method' => 'save' ],
        'myvh_delete_organisation' => [ 'class' => OrganisationController::class, 'method' => 'delete' ],
        'myvh_add_org_member' => [ 'class' => OrganisationController::class, 'method' => 'add_member' ],
        'myvh_remove_org_member' => [ 'class' => OrganisationController::class, 'method' => 'remove_member' ],
        'myvh_save_org_type' => [ 'class' => OrganisationTypeController::class, 'method' => 'save' ],
        'myvh_delete_org_type' => [ 'class' => OrganisationTypeController::class, 'method' => 'delete' ],
        'myvh_save_rate' => [ 'class' => RoomRateController::class, 'method' => 'save' ],
        'myvh_delete_rate' => [ 'class' => RoomRateController::class, 'method' => 'delete' ],
        'myvh_save_addon' => [ 'class' => AddonController::class, 'method' => 'save' ],
        'myvh_delete_addon' => [ 'class' => AddonController::class, 'method' => 'delete' ],
        'myvh_save_invoice' => [ 'class' => InvoiceController::class, 'method' => 'save' ],
        'myvh_generate_invoices' => [ 'class' => InvoiceController::class, 'method' => 'generate_from_bookings' ],
        'myvh_view_invoice_pdf' => [ 'class' => InvoiceController::class, 'method' => 'view_pdf' ],
        'myvh_email_invoice' => [ 'class' => InvoiceController::class, 'method' => 'email_invoice' ],
        'myvh_delete_invoice' => [ 'class' => InvoiceController::class, 'method' => 'delete' ],
        'myvh_update_invoice_status' => [ 'class' => InvoiceController::class, 'method' => 'update_status' ],
        'myvh_settle_invoice_deposit' => [ 'class' => InvoiceController::class, 'method' => 'settle_deposit' ],
        'myvh_save_single_booking_auto_invoice_rules' => [ 'class' => SingleBookingAutoInvoiceRuleController::class, 'method' => 'save' ],
        'myvh_save_recurring_booking_auto_invoice_rules' => [ 'class' => RecurringBookingAutoInvoiceRuleController::class, 'method' => 'save' ],
        'myvh_record_payment' => [ 'class' => PaymentController::class, 'method' => 'create' ],
        'myvh_delete_payment' => [ 'class' => PaymentController::class, 'method' => 'delete' ],
        'myvh_send_payment_receipt' => [ 'class' => PaymentController::class, 'method' => 'send_receipt' ],
    ];

    public function __construct(
        private Container $container,
        private AdminPageRouter $admin_page_router
    ) {
    }

    public function register(): void {
        foreach ( array_keys( $this->controller_actions ) as $action ) {
            add_action( "admin_post_{$action}", [ $this, 'dispatch' ] );
        }

        add_action( 'admin_post_myvh_reset_audit_log', [ $this->admin_page_router, 'reset_audit_log' ] );
    }

    public function dispatch(): void {
        $hook = (string) current_action();
        $action = str_replace( 'admin_post_', '', $hook );

        if ( ! isset( $this->controller_actions[ $action ] ) ) {
            return;
        }

        $handler = $this->controller_actions[ $action ];
        $controller = $this->container->get( $handler['class'] );
        $method = $handler['method'];

        $controller->{$method}();
    }
}
