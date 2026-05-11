<?php
namespace MYVH\Portal\Ajax;

use MYVH\AutoInvoicing\SingleBookingAutoInvoiceRuleRepository;
use MYVH\AutoInvoicing\RecurringBookingAutoInvoiceRuleRepository;
use MYVH\Customers\CustomerService;
use MYVH\Portal\ClientAdminService;

class PortalPeoplePageRenderer {
    public function __construct(
        private ClientAdminService $client_admin_service,
        private CustomerService $customer_service,
        private SingleBookingAutoInvoiceRuleRepository $rule_repository,
        private RecurringBookingAutoInvoiceRuleRepository $recurring_booking_rule_repository
    ) {}

    public function render_account(): void {
        include MYVH_PLUGIN_DIR . 'templates/Portal/account.php';
    }

    public function render_client_admins(bool $is_client_admin): void {
        if (!$is_client_admin) {
            wp_send_json_error('Permission denied', 403);
        }

        $client_admins = $this->client_admin_service->get_assigned_users_for_blog(get_current_blog_id());
        $accessible_sites = $this->client_admin_service->get_accessible_sites_for_user(get_current_user_id());
        include MYVH_PLUGIN_DIR . 'templates/Portal/client-admins.php';
    }

    public function render_customers(bool $is_client_admin): void {
        if (!$is_client_admin) {
            wp_send_json_error('Permission denied', 403);
        }

        $customers = $this->customer_service->get_all([
            'orderby' => 'Name',
            'order' => 'ASC',
        ]);

        include MYVH_PLUGIN_DIR . 'templates/Portal/customers.php';
    }

    public function render_customer_edit(bool $is_client_admin): void {
        if (!$is_client_admin) {
            wp_send_json_error('Permission denied', 403);
        }

        $customer_id = intval($_GET['id'] ?? 0);

        if (!$customer_id) {
            wp_send_json_error('Invalid customer ID', 400);
        }

        $customer = $this->customer_service->get($customer_id);
        $single_booking_rule_options = $this->rule_repository->get_rule_options();
        $recurring_booking_rule_options = $this->recurring_booking_rule_repository->get_rule_options();
        include MYVH_PLUGIN_DIR . 'templates/Portal/customer-edit.php';
    }

    public function render_customer_add(bool $is_client_admin): void {
        if (!$is_client_admin) {
            wp_send_json_error('Permission denied', 403);
        }

        $single_booking_rule_options = $this->rule_repository->get_rule_options();
        $recurring_booking_rule_options = $this->recurring_booking_rule_repository->get_rule_options();
        include MYVH_PLUGIN_DIR . 'templates/Portal/customer-add.php';
    }
}