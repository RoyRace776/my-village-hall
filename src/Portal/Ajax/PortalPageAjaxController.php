<?php
namespace MYVH\Portal\Ajax;

use MYVH\Bookings\BookingService;
use MYVH\Customers\CustomerService;
use MYVH\Portal\ClientAdminService;

class PortalPageAjaxController {
    public function __construct(
        private BookingService $booking_service,
        private CustomerService $customer_service,
        private ClientAdminService $client_admin_service,
        private PortalBillingPageRenderer $billing_page_renderer,
        private PortalPeoplePageRenderer $people_page_renderer,
        private PortalOrganisationPageRenderer $organisation_page_renderer,
        private PortalAdminConfigPageRenderer $admin_config_page_renderer
    ) {}

    public function register(): void {
        add_action('wp_ajax_myvh_portal_page', [$this, 'load_page']);
    }

    public function load_page(): void {
        if (!is_user_logged_in()) {
            wp_send_json_error('Not logged in', 401);
        }

        check_ajax_referer('myvh_portal', 'nonce');

        $page = sanitize_text_field($_GET['page'] ?? 'dashboard');
        $customer = $this->customer_service->get_by_user_id(get_current_user_id());
        $is_client_admin = $this->current_user_is_client_admin();
        $has_customer = !empty($customer['Id']);

        switch ($page) {
            case 'bookings':
                $groups = $this->get_portal_booking_groups($customer, $is_client_admin);
                $can_delete_booking = function(array $booking): bool {
                    $delete_rules = $this->booking_service->can_delete($booking);
                    return !empty($delete_rules['can_delete']);
                };
                include MYVH_PLUGIN_DIR . 'templates/Portal/bookings.php';
                break;

            case 'calendar':
            case 'bookings-new':
            case 'booking-view':
            case 'booking-edit':
            case 'booking-delete':
                $can_create_booking = $is_client_admin || $has_customer;
                include MYVH_PLUGIN_DIR . 'templates/Portal/calendar.php';
                break;

            case 'invoices':
                $this->billing_page_renderer->render_invoices($customer, $is_client_admin, $has_customer);
                break;

            case 'invoice-view':
                $this->billing_page_renderer->render_invoice_view($customer, $is_client_admin);
                break;

            case 'payments':
                $this->billing_page_renderer->render_payments($is_client_admin);
                break;

            case 'invoice-generate':
                $this->billing_page_renderer->render_invoice_generate($is_client_admin);
                break;

            case 'account':
                $this->people_page_renderer->render_account();
                break;

            case 'client-admins':
                $this->people_page_renderer->render_client_admins($is_client_admin);
                break;

            case 'customers':
                $this->people_page_renderer->render_customers($is_client_admin);
                break;

            case 'customer-edit':
                $this->people_page_renderer->render_customer_edit($is_client_admin);
                break;

            case 'customer-add':
                $this->people_page_renderer->render_customer_add($is_client_admin);
                break;

            case 'organisation-add':
                $this->organisation_page_renderer->render_organisation_add($customer, $is_client_admin);
                break;

            case 'rooms':
                $this->admin_config_page_renderer->render_rooms($is_client_admin);
                break;

            case 'venues':
                $this->admin_config_page_renderer->render_venues($is_client_admin);
                break;

            case 'venue-add':
                $this->admin_config_page_renderer->render_venue_add($is_client_admin);
                break;

            case 'venue-edit':
                $this->admin_config_page_renderer->render_venue_edit($is_client_admin);
                break;

            case 'room-add':
                $this->admin_config_page_renderer->render_room_add($is_client_admin);
                break;

            case 'room-edit':
                $this->admin_config_page_renderer->render_room_edit($is_client_admin);
                break;

            case 'room-rates':
                $this->admin_config_page_renderer->render_room_rates($is_client_admin);
                break;

            case 'addons':
                $this->admin_config_page_renderer->render_addons($is_client_admin);
                break;

            case 'addon-add':
                $this->admin_config_page_renderer->render_addon_add($is_client_admin);
                break;

            case 'addon-edit':
                $this->admin_config_page_renderer->render_addon_edit($is_client_admin);
                break;

            case 'room-rate-add':
                $this->admin_config_page_renderer->render_room_rate_add($is_client_admin);
                break;

            case 'room-rate-edit':
                $this->admin_config_page_renderer->render_room_rate_edit($is_client_admin);
                break;

            case 'organisation-types':
                $this->organisation_page_renderer->render_organisation_types($is_client_admin);
                break;

            case 'organisation-type-add':
                $this->organisation_page_renderer->render_organisation_type_add($is_client_admin);
                break;

            case 'organisation-type-edit':
                $this->organisation_page_renderer->render_organisation_type_edit($is_client_admin);
                break;

            case 'settings':
                $this->admin_config_page_renderer->render_settings($is_client_admin);
                break;

            case 'organisations':
                $this->organisation_page_renderer->render_organisations($customer, $is_client_admin);
                break;

            default:
                $groups = $this->get_portal_booking_groups($customer, $is_client_admin);
                $can_delete_booking = function(array $booking): bool {
                    $delete_rules = $this->booking_service->can_delete($booking);
                    return !empty($delete_rules['can_delete']);
                };
                include MYVH_PLUGIN_DIR . 'templates/Portal/dashboard.php';
        }

        wp_die();
    }

    private function get_portal_booking_groups($customer, bool $is_client_admin): array {
        if ($is_client_admin) {
            $result = $this->booking_service->get_booking_list();
            return $result['groups'];
        }

        if (empty($customer['Id'])) {
            return [];
        }

        $result = $this->booking_service->get_booking_list([
            'customer_id' => $customer['Id'],
        ]);

        return $result['groups'];
    }

    private function current_user_is_client_admin(): bool {
        return $this->client_admin_service->can_administer_blog(get_current_user_id(), get_current_blog_id());
    }
}