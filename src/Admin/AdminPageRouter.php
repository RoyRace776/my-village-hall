<?php

namespace MYVH\Admin;

use MYVH\Admin\Contracts\AdminRequestInterface;
use MYVH\Admin\Contracts\ClientAdminActionHandlerInterface;
use MYVH\Audit\AuditTrail;
use MYVH\Availability\AvailabilityService;
use MYVH\Calendar\CalendarStatusColours;
use MYVH\Container\Container;
use MYVH\Pages\Support\PageContentMatcher;
use MYVH\Portal\ClientAdminService;
use WP_Post;

class AdminPageRouter {
    public function __construct(
        private Container $container,
        private AdminRequestInterface $request,
        private ClientAdminService $client_admin_service,
        private ClientAdminActionHandlerInterface $client_admin_action_handler
    ) {
    }

    public function hide_portal_page_title(): void {
        if ( is_admin() || ! is_singular( 'page' ) ) {
            return;
        }

        $post = get_queried_object();
        if ( ! ( $post instanceof WP_Post ) ) {
            return;
        }

        $content = (string) ( $post->post_content ?? '' );
        if ( $content === '' ) {
            return;
        }

        $titleless_shortcodes = [
            [ 'myvh_portal', 'myvh/portal' ],
            [ 'myvh_public_calendar', 'myvh/public-calendar' ],
            [ 'myvh_create_site', 'myvh/create-site' ],
        ];

        $has_titleless_shortcode = false;
        foreach ( $titleless_shortcodes as $feature ) {
            [ $shortcode_tag, $block_name ] = $feature;
            if ( PageContentMatcher::containsFeature( $content, $shortcode_tag, $block_name ) ) {
                $has_titleless_shortcode = true;
                break;
            }
        }

        if ( ! $has_titleless_shortcode ) {
            return;
        }

        echo '<style id="myvh-hide-portal-page-title">'
            . '.page .entry-title,'
            . '.page .page-title,'
            . '.page h1.wp-block-post-title{display:none !important;}'
            . '</style>';
    }

    public function render_bookings_page(): void { $this->render_page( 'bookings', true ); }

    public function render_customers_page(): void { $this->render_page( 'customers' ); }

    public function render_org_types_page(): void { $this->render_page( 'org-types' ); }

    public function render_org_members_page(): void { $this->render_page( 'org-members' ); }

    public function render_payments_page(): void { $this->render_page( 'payments' ); }

    public function render_customer_add_page(): void { $this->render_page( 'customer-add' ); }

    public function render_organisation_add_page(): void { $this->render_page( 'organisation-add' ); }

    public function render_venues_page(): void { $this->render_page( 'venues' ); }

    public function render_rooms_page(): void { $this->render_page( 'rooms' ); }

    public function render_room_rates_page(): void { $this->render_page( 'room-rates' ); }

    public function render_addons_page(): void { $this->render_page( 'addons' ); }

    public function render_invoices_page(): void { $this->render_page( 'invoices', true ); }

    public function render_invoice_generate_page(): void { $this->render_page( 'invoice-generate' ); }

    public function render_single_booking_invoice_rules_page(): void { $this->render_page( 'single-booking-invoice-rules' ); }

    public function render_recurring_booking_invoice_rules_page(): void { $this->render_page( 'recurring-booking-invoice-rules' ); }

    public function render_report_builder_page(): void { $this->render_page( 'report-builder' ); }

    public function render_recurring_page(): void { $this->render_page( 'recurring' ); }

    public function render_audit_log_page(): void { $this->render_page( 'audit-log' ); }

    public function render_organisations_page(): void {
        if ( isset( $_GET['add'] ) ) {
            wp_safe_redirect( admin_url( 'admin.php?page=myvh-organisation-add' ) );
            exit;
        }

        $this->render_page( 'organisations' );
    }

    public function render_calendar_page(): void {
        $visible_hours = [ 'start' => 8, 'end' => 22 ];

        try {
            $hours = $this->container->get( AvailabilityService::class )->get_calendar_visible_hours();
            if ( ! empty( $hours ) && is_array( $hours ) ) {
                $visible_hours = [
                    'start' => (int) ( $hours['start'] ?? 8 ),
                    'end' => (int) ( $hours['end'] ?? 22 ),
                ];
            }
        } catch ( \Throwable $e ) {
            // Fall back to defaults silently.
        }

        wp_localize_script( 'myvh-calendar-admin', 'myvhCal', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'myvh_calendar' ),
            'headerDateFormat' => myvh_setting( 'calendar.calendar_date_format', 'd MMM' ),
            'portalBookingsDateFormat' => myvh_setting( 'general.portal_bookings_date_format', 'd MMM' ),
            'startOfWeek' => (int) get_option( 'start_of_week', 1 ),
            'maxBookingDaysAhead' => (int) myvh_setting( 'booking.max_booking_days', 365 ),
            'visibleStartHour' => $visible_hours['start'],
            'visibleEndHour' => $visible_hours['end'],
            'schedulerOrientation' => myvh_setting( 'calendar.scheduler_orientation', 'horizontal' ),
            'statusColors' => CalendarStatusColours::map(),
        ] );

        $this->render_page( 'calendar' );
    }

    public function render_client_admins_network_page(): void {
        if ( is_multisite() && current_user_can( 'manage_network_options' ) ) {
            wp_safe_redirect( add_query_arg(
                [
                    'page' => 'myvh-network-client-admins',
                    'blog_id' => get_current_blog_id(),
                ],
                network_admin_url( 'admin.php' )
            ) );
            exit;
        }

        if ( ! class_exists( ClientAdminService::class ) ) {
            echo '<div class="wrap">';
            echo '<h1>' . esc_html__( 'Client Administrators', 'my-village-hall' ) . '</h1>';
            echo '<div class="notice notice-error"><p>' . esc_html__( 'Client admin service is not available.', 'my-village-hall' ) . '</p></div>';
            echo '</div>';
            return;
        }

        if ( is_multisite() ) {
            echo '<div class="wrap">';
            echo '<h1>' . esc_html__( 'Client Administrators', 'my-village-hall' ) . '</h1>';
            echo '<div class="notice notice-warning"><p>'
                . esc_html__( 'Only network administrators can manage cross-site client admin assignments. Ask a network admin to use Network Admin' )
                . '</p></div>';
            echo '</div>';
            return;
        }

        $blog_id = get_current_blog_id();
        $page_url = add_query_arg( [ 'page' => 'myvh-client-admins-network' ], admin_url( 'admin.php' ) );

        $this->client_admin_action_handler->handle();

        $notices = [
            'added' => [ 'success', __( 'Client administrator added.', 'my-village-hall' ) ],
            'removed' => [ 'success', __( 'Client administrator removed.', 'my-village-hall' ) ],
            'missing_user' => [ 'error', __( 'Email address or username is required.', 'my-village-hall' ) ],
            'user_not_found' => [ 'error', __( 'No WordPress user was found with that email or username.', 'my-village-hall' ) ],
            'invalid_user' => [ 'error', __( 'Please select a valid user.', 'my-village-hall' ) ],
        ];

        $notice_key = $this->request->query_string( 'myvh_notice' );
        $assigned_users = $this->client_admin_service->get_assigned_users_for_blog( $blog_id );

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Client Administrators', 'my-village-hall' ) . '</h1>';
        echo '<p>' . esc_html__( 'Assign users who can administer this client site in the portal.', 'my-village-hall' ) . '</p>';

        if ( isset( $notices[ $notice_key ] ) ) {
            [ $type, $text ] = $notices[ $notice_key ];
            echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $text ) . '</p></div>';
        }

        echo '<form method="post" action="' . esc_url( $page_url ) . '" style="max-width:620px;margin-bottom:24px;">';
        wp_nonce_field( 'myvh_site_client_admins' );
        echo '<input type="hidden" name="myvh_client_admin_action" value="add">';
        echo '<table class="form-table" role="presentation"><tbody><tr>';
        echo '<th scope="row"><label for="myvh-user-identifier">' . esc_html__( 'Email or username', 'my-village-hall' ) . '</label></th>';
        echo '<td><input id="myvh-user-identifier" type="text" name="user_identifier" class="regular-text" required></td>';
        echo '</tr></tbody></table>';
        submit_button( __( 'Add Client Admin', 'my-village-hall' ) );
        echo '</form>';

        echo '<h2>' . esc_html__( 'Assigned Client Admins', 'my-village-hall' ) . '</h2>';
        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__( 'Name', 'my-village-hall' ) . '</th>';
        echo '<th>' . esc_html__( 'Email', 'my-village-hall' ) . '</th>';
        echo '<th>' . esc_html__( 'Username', 'my-village-hall' ) . '</th>';
        echo '<th style="width:120px;">' . esc_html__( 'Action', 'my-village-hall' ) . '</th>';
        echo '</tr></thead><tbody>';

        if ( empty( $assigned_users ) ) {
            echo '<tr><td colspan="4">' . esc_html__( 'No explicit client admin assignments for this site.', 'my-village-hall' ) . '</td></tr>';
        } else {
            foreach ( $assigned_users as $u ) {
                $confirm_js = esc_js( __( 'Remove this client admin assignment?', 'my-village-hall' ) );
                echo '<tr>';
                echo '<td>' . esc_html( $u['display_name'] ?: $u['user_login'] ) . '</td>';
                echo '<td>' . esc_html( $u['user_email'] ) . '</td>';
                echo '<td>' . esc_html( $u['user_login'] ) . '</td>';
                echo '<td>';
                echo '<form method="post" action="' . esc_url( $page_url ) . '" onsubmit="return confirm(\'' . $confirm_js . '\');">';
                wp_nonce_field( 'myvh_site_client_admins' );
                echo '<input type="hidden" name="myvh_client_admin_action" value="remove">';
                echo '<input type="hidden" name="user_id" value="' . esc_attr( (int) $u['ID'] ) . '">';
                submit_button( __( 'Remove', 'my-village-hall' ), 'small', '', false );
                echo '</form>';
                echo '</td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table>';
        echo '</div>';
    }

    public function reset_audit_log(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die(
                esc_html__( 'You do not have sufficient permissions to access this page.', 'my-village-hall' ),
                esc_html__( 'Access Denied', 'my-village-hall' ),
                [ 'response' => 403 ]
            );
        }

        check_admin_referer( 'myvh_reset_audit_log' );
        AuditTrail::reset();

        wp_safe_redirect( add_query_arg(
            [ 'page' => 'myvh-audit-log', 'reset' => 1 ],
            admin_url( 'admin.php' )
        ) );
        exit;
    }

    private function render_page( string $page, bool $has_detail_view = false ): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die(
                esc_html__( 'You do not have sufficient permissions to access this page.', 'my-village-hall' ),
                esc_html__( 'Access Denied', 'my-village-hall' ),
                [ 'response' => 403 ]
            );
        }

        if ( $has_detail_view && ( isset( $_GET['add'] ) || isset( $_GET['edit'] ) || isset( $_GET['view'] ) ) ) {
            include MYVH_PLUGIN_DIR . "templates/Admin/{$page}-form-page.php";
            return;
        }

        include MYVH_PLUGIN_DIR . "templates/Admin/{$page}-page.php";
    }
}
