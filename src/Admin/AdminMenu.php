<?php

namespace MYVH\Admin;

use MYVH\Audit\AuditTrail;
use MYVH\UI\MenuSeparator;

class AdminMenu {
    public function __construct(
        private AdminPageRouter $router,
        private MenuSeparator $menu_separator
    ) {
    }

    public function register(): void {
        add_menu_page(
            __( 'My Village Hall', 'my-village-hall' ),
            __( 'My Village Hall', 'my-village-hall' ),
            'manage_options',
            'my-village-hall',
            [ $this->router, 'render_bookings_page' ],
            'dashicons-calendar-alt',
            30
        );

        add_submenu_page(
            'my-village-hall',
            __( 'All Bookings', 'my-village-hall' ),
            self::menu_label( 'dashicons-list-view', __( 'All Bookings', 'my-village-hall' ) ),
            'manage_options',
            'my-village-hall',
            [ $this->router, 'render_bookings_page' ]
        );

        add_submenu_page(
            'my-village-hall',
            __( 'Booking Calendar', 'my-village-hall' ),
            self::menu_label( 'dashicons-calendar-alt', __( 'Calendar', 'my-village-hall' ) ),
            'manage_options',
            'myvh-calendar',
            [ $this->router, 'render_calendar_page' ]
        );

        $this->menu_separator->add( 'my-village-hall' );

        add_submenu_page(
            'my-village-hall',
            __( 'Customers', 'my-village-hall' ),
            self::menu_label( 'dashicons-groups', __( 'Customers', 'my-village-hall' ) ),
            'manage_options',
            'myvh-customers',
            [ $this->router, 'render_customers_page' ]
        );

        $this->menu_separator->add( 'my-village-hall' );

        add_submenu_page(
            'my-village-hall',
            __( 'Organisations', 'my-village-hall' ),
            self::menu_label( 'dashicons-admin-multisite', __( 'Organisations', 'my-village-hall' ) ),
            'manage_options',
            'myvh-organisations',
            [ $this->router, 'render_organisations_page' ]
        );

        add_submenu_page(
            '',
            __( 'Add Organisation', 'my-village-hall' ),
            self::menu_label( 'dashicons-plus-alt', __( 'Add Organisation', 'my-village-hall' ) ),
            'manage_options',
            'myvh-organisation-add',
            [ $this->router, 'render_organisation_add_page' ]
        );

        add_submenu_page(
            'my-village-hall',
            __( 'Organisation Types', 'my-village-hall' ),
            self::menu_label( 'dashicons-category', __( 'Org Types', 'my-village-hall' ) ),
            'manage_options',
            'myvh-org-types',
            [ $this->router, 'render_org_types_page' ]
        );

        add_submenu_page(
            'my-village-hall',
            __( 'Organisation Members', 'my-village-hall' ),
            self::menu_label( 'dashicons-id-alt', __( 'Org Members', 'my-village-hall' ) ),
            'manage_options',
            'myvh-org-members',
            [ $this->router, 'render_org_members_page' ]
        );

        add_submenu_page(
            'my-village-hall',
            __( 'Client Administrators', 'my-village-hall' ),
            self::menu_label( 'dashicons-admin-users', __( 'Client Admins', 'my-village-hall' ) ),
            'manage_options',
            'myvh-client-admins-network',
            [ $this->router, 'render_client_admins_network_page' ]
        );

        $this->menu_separator->add( 'my-village-hall' );

        add_submenu_page(
            'my-village-hall',
            __( 'Venues', 'my-village-hall' ),
            self::menu_label( 'dashicons-location-alt', __( 'Venues', 'my-village-hall' ) ),
            'manage_options',
            'myvh-venues',
            [ $this->router, 'render_venues_page' ]
        );

        add_submenu_page(
            'my-village-hall',
            __( 'Rooms', 'my-village-hall' ),
            self::menu_label( 'dashicons-admin-home', __( 'Rooms', 'my-village-hall' ) ),
            'manage_options',
            'myvh-rooms',
            [ $this->router, 'render_rooms_page' ]
        );

        $this->menu_separator->add( 'my-village-hall' );

        add_submenu_page(
            'my-village-hall',
            __( 'Room Rates', 'my-village-hall' ),
            self::menu_label( 'dashicons-money-alt', __( 'Room Rates', 'my-village-hall' ) ),
            'manage_options',
            'myvh-room-rates',
            [ $this->router, 'render_room_rates_page' ]
        );

        add_submenu_page(
            'my-village-hall',
            __( 'Add-ons', 'my-village-hall' ),
            self::menu_label( 'dashicons-admin-plugins', __( 'Add-ons', 'my-village-hall' ) ),
            'manage_options',
            'myvh-addons',
            [ $this->router, 'render_addons_page' ]
        );

        add_submenu_page(
            'my-village-hall',
            __( 'Manage Invoices', 'my-village-hall' ),
            self::menu_label( 'dashicons-media-spreadsheet', __( 'Manage Invoices', 'my-village-hall' ) ),
            'manage_options',
            'myvh-invoices',
            [ $this->router, 'render_invoices_page' ]
        );

        add_submenu_page(
            'my-village-hall',
            __( 'Generate Invoices', 'my-village-hall' ),
            self::menu_label( 'dashicons-media-document', __( '&nbsp;&nbsp;&nbsp;Generate Invoices', 'my-village-hall' ) ),
            'manage_options',
            'myvh-invoice-generate',
            [ $this->router, 'render_invoice_generate_page' ]
        );

        add_submenu_page(
            'my-village-hall',
            __( 'Single Booking Invoice Rules', 'my-village-hall' ),
            self::menu_label( 'dashicons-filter', __( '&nbsp;&nbsp;&nbsp;Single Invoice Rules', 'my-village-hall' ) ),
            'manage_options',
            'myvh-single-booking-invoice-rules',
            [ $this->router, 'render_single_booking_invoice_rules_page' ]
        );

        add_submenu_page(
            'my-village-hall',
            __( 'Recurring Booking Invoice Rules', 'my-village-hall' ),
            self::menu_label( 'dashicons-filter', __( '&nbsp;&nbsp;&nbsp;Recurring Invoice Rules', 'my-village-hall' ) ),
            'manage_options',
            'myvh-recurring-booking-invoice-rules',
            [ $this->router, 'render_recurring_booking_invoice_rules_page' ]
        );

        add_submenu_page(
            'my-village-hall',
            __( 'Payments', 'my-village-hall' ),
            self::menu_label( 'dashicons-money', __( '&nbsp;&nbsp;&nbsp;Payments', 'my-village-hall' ) ),
            'manage_options',
            'myvh-payments',
            [ $this->router, 'render_payments_page' ]
        );

        $this->menu_separator->add( 'my-village-hall' );

        add_submenu_page(
            'my-village-hall',
            __( 'Recurring Bookings', 'my-village-hall' ),
            self::menu_label( 'dashicons-update-alt', __( 'Recurring Bookings', 'my-village-hall' ) ),
            'manage_options',
            'myvh-recurring',
            [ $this->router, 'render_recurring_page' ]
        );

        if ( AuditTrail::is_enabled() ) {
            add_submenu_page(
                'my-village-hall',
                __( 'Audit Log', 'my-village-hall' ),
                self::menu_label( 'dashicons-visibility', __( 'Audit Log', 'my-village-hall' ) ),
                'manage_options',
                'myvh-audit-log',
                [ $this->router, 'render_audit_log_page' ]
            );
        }
    }

    public static function menu_label( string $icon, string $label ): string {
        return $label;
    }
}
