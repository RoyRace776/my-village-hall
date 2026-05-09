<?php
namespace MYVH\Portal\Ajax;

use MYVH\Customers\CustomerService;
use MYVH\Organisations\OrganisationService;
use MYVH\Organisations\SaveOrganisationRequest;
use MYVH\Portal\ClientAdminService;
use MYVH\Portal\Support\PortalAuth;

class PortalOrganisationAjaxController {
    public function __construct(
        private OrganisationService $organisation_service,
        private CustomerService $customer_service,
        private ClientAdminService $client_admin_service
    ) {}

    public function register(): void {
        add_action('wp_ajax_myvh_portal_request_org_membership', [$this, 'request_organisation_membership']);
        add_action('wp_ajax_myvh_portal_approve_org_request', [$this, 'approve_organisation_membership_request']);
        add_action('wp_ajax_myvh_portal_reject_org_request', [$this, 'reject_organisation_membership_request']);
        add_action('wp_ajax_myvh_portal_add_organisation', [$this, 'add_organisation']);
        add_action('wp_ajax_myvh_portal_delete_organisation', [$this, 'delete_organisation']);
        add_action('wp_ajax_myvh_portal_save_org_type_assignment', [$this, 'save_organisation_type_assignment']);
        add_action('wp_ajax_myvh_portal_save_org_billing', [$this, 'save_organisation_billing']);
        add_action('wp_ajax_myvh_portal_org_add_member', [$this, 'organisation_add_member']);
        add_action('wp_ajax_myvh_portal_org_remove_member', [$this, 'organisation_remove_member']);
        add_action('wp_ajax_myvh_portal_org_set_admin', [$this, 'organisation_set_member_admin']);
    }

    public function request_organisation_membership(): void {
        $customer = $this->get_authenticated_customer();

        $org_id = intval($_POST['organisation_id'] ?? 0);
        $message = sanitize_text_field($_POST['message'] ?? '');

        if ($org_id <= 0) {
            wp_send_json_error('Please choose an organisation', 400);
        }

        $result = $this->organisation_service->create_membership_request($org_id, (int) $customer['Id'], $message);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message(), 400);
        }

        wp_send_json_success(['message' => 'Membership request sent']);
    }

    public function approve_organisation_membership_request(): void {
        $customer = $this->get_authenticated_customer();
        $request_id = intval($_POST['request_id'] ?? 0);

        if ($request_id <= 0) {
            wp_send_json_error('Request ID is required', 400);
        }

        $result = $this->organisation_service->approve_membership_request($request_id, (int) $customer['Id']);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message(), 400);
        }

        wp_send_json_success(['message' => 'Request approved']);
    }

    public function reject_organisation_membership_request(): void {
        $customer = $this->get_authenticated_customer();
        $request_id = intval($_POST['request_id'] ?? 0);

        if ($request_id <= 0) {
            wp_send_json_error('Request ID is required', 400);
        }

        $result = $this->organisation_service->reject_membership_request($request_id, (int) $customer['Id']);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message(), 400);
        }

        wp_send_json_success(['message' => 'Request rejected']);
    }

    public function add_organisation(): void {
        $customer = $this->get_authenticated_customer();

        $allow_type_changes = $this->current_user_is_client_admin();
        $payload = SaveOrganisationRequest::from_post(wp_unslash($_POST), false);
        if ($allow_type_changes) {
            $payload['organisation_type_id'] = intval($_POST['organisation_type_id'] ?? 0);
        }
        $payload['is_active'] = 1;

        $saved = $this->organisation_service->save($payload, $allow_type_changes);

        if (is_wp_error($saved)) {
            wp_send_json_error($saved->get_error_message(), 400);
        }

        $organisation_id = (int) $saved;

        if ($organisation_id <= 0) {
            wp_send_json_error('Organisation save failed', 400);
        }

        $membership = $this->organisation_service->add_member($organisation_id, (int) $customer['Id'], true);

        if (is_wp_error($membership) || !$membership) {
            $this->organisation_service->delete($organisation_id);
            $message = is_wp_error($membership)
                ? $membership->get_error_message()
                : 'Organisation membership assignment failed';
            wp_send_json_error($message, 400);
        }

        wp_send_json_success([
            'message' => 'Organisation created',
            'organisation_id' => $organisation_id,
        ]);
    }

    public function save_organisation_type_assignment(): void {
        PortalAuth::require_client_admin($this->client_admin_service);

        $organisation_id = intval($_POST['organisation_id'] ?? 0);
        if ($organisation_id <= 0) {
            wp_send_json_error('Organisation is required', 400);
        }

        $existing = $this->organisation_service->get_by_id($organisation_id);
        if (empty($existing['Id'])) {
            wp_send_json_error('Organisation not found', 404);
        }

        $payload = [
            'organisation_id' => $organisation_id,
            'name' => $existing['Name'] ?? '',
            'contact_email' => $existing['ContactEmail'] ?? '',
            'contact_phone' => $existing['ContactPhone'] ?? '',
            'website_url' => $existing['WebsiteUrl'] ?? null,
            'organisation_type_id' => intval($_POST['organisation_type_id'] ?? 0),
            'invoice_organisation_bookings' => !empty($existing['InvoiceOrganisationBookings']) ? 1 : 0,
            'send_booking_emails_to_organisation' => !empty($existing['SendBookingEmailsToOrganisation']) ? 1 : 0,
            'single_booking_auto_invoice_rule_id' => intval($existing['SingleBookingAutoInvoiceRuleId'] ?? 0),
            'billing_contact_name' => $existing['BillingContactName'] ?? '',
            'billing_email' => $existing['BillingEmail'] ?? '',
            'billing_address_line1' => $existing['BillingAddressLine1'] ?? '',
            'billing_address_line2' => $existing['BillingAddressLine2'] ?? '',
            'billing_town_city' => $existing['BillingTownCity'] ?? '',
            'billing_postcode' => $existing['BillingPostcode'] ?? '',
            'billing_reference' => $existing['BillingReference'] ?? '',
            'is_active' => !empty($existing['IsActive']) ? 1 : 0,
            'is_default' => !empty($existing['IsDefault']) ? 1 : 0,
            'default_public' => !empty($existing['DefaultPublic']) ? 1 : 0,
        ];

        $saved = $this->organisation_service->save($payload, true);

        if (is_wp_error($saved)) {
            wp_send_json_error($saved->get_error_message(), 400);
        }

        if (!$saved) {
            wp_send_json_error('Organisation update failed', 400);
        }

        wp_send_json_success(['message' => 'Organisation type updated']);
    }

    public function delete_organisation(): void {
        PortalAuth::require_user();

        $org_id = intval($_POST['organisation_id'] ?? 0);

        if ($org_id <= 0) {
            wp_send_json_error('Organisation is required', 400);
        }

        $is_client_admin = $this->current_user_is_client_admin();

        if (!$is_client_admin) {
            $customer = $this->customer_service->get_by_user_id(get_current_user_id());
            if (empty($customer['Id'])) {
                wp_send_json_error('Customer profile not found', 400);
            }
            $is_org_admin = $this->organisation_service->is_customer_admin_for_organisation($org_id, (int) $customer['Id']);
            if (!$is_org_admin) {
                wp_send_json_error('Only organisation admins can delete this organisation', 403);
            }
        }

        $result = $this->organisation_service->delete($org_id);
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message(), 400);
        }
        if (!$result) {
            wp_send_json_error('Organisation delete failed', 400);
        }

        wp_send_json_success(['message' => 'Organisation deleted']);
    }

    public function save_organisation_billing(): void {
        $customer = $this->get_authenticated_customer();
        $org_id = intval($_POST['organisation_id'] ?? 0);

        if ($org_id <= 0) {
            wp_send_json_error('Organisation is required', 400);
        }

        $result = $this->organisation_service->update_billing_details_by_admin(
            $org_id,
            (int) $customer['Id'],
            [
                'contact_email' => sanitize_email($_POST['contact_email'] ?? ''),
                'contact_phone' => sanitize_text_field($_POST['contact_phone'] ?? ''),
                'send_booking_emails_to_organisation' => !empty($_POST['send_booking_emails_to_organisation']) ? 1 : 0,
                'invoice_organisation_bookings' => !empty($_POST['invoice_organisation_bookings']) ? 1 : 0,
                'single_booking_auto_invoice_rule_id' => intval($_POST['single_booking_auto_invoice_rule_id'] ?? 0),
                'billing_contact_name' => sanitize_text_field($_POST['billing_contact_name'] ?? ''),
                'billing_email' => sanitize_email($_POST['billing_email'] ?? ''),
                'billing_address_line1' => sanitize_text_field($_POST['billing_address_line1'] ?? ''),
                'billing_address_line2' => sanitize_text_field($_POST['billing_address_line2'] ?? ''),
                'billing_town_city' => sanitize_text_field($_POST['billing_town_city'] ?? ''),
                'billing_postcode' => sanitize_text_field($_POST['billing_postcode'] ?? ''),
                'billing_reference' => sanitize_text_field($_POST['billing_reference'] ?? ''),
            ]
        );

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message(), 400);
        }

        wp_send_json_success(['message' => 'Organisation billing details updated']);
    }

    public function organisation_add_member(): void {
        $customer = $this->get_authenticated_customer();

        $org_id = intval($_POST['organisation_id'] ?? 0);
        $email = sanitize_email($_POST['email'] ?? '');
        $is_admin = !empty($_POST['is_admin']);

        if ($org_id <= 0) {
            wp_send_json_error('Organisation is required', 400);
        }

        if ($email === '' || !is_email($email)) {
            wp_send_json_error('Valid member email is required', 400);
        }

        $target_customer = $this->customer_service->get_by_email($email);
        if (empty($target_customer['Id'])) {
            wp_send_json_error('No customer exists with that email address', 400);
        }

        $result = $this->organisation_service->add_member_by_admin(
            $org_id,
            (int) $customer['Id'],
            (int) $target_customer['Id'],
            $is_admin
        );

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message(), 400);
        }

        wp_send_json_success(['message' => 'Member added']);
    }

    public function organisation_remove_member(): void {
        $customer = $this->get_authenticated_customer();
        $member_id = intval($_POST['member_id'] ?? 0);

        if ($member_id <= 0) {
            wp_send_json_error('Member ID is required', 400);
        }

        $result = $this->organisation_service->remove_member_by_admin($member_id, (int) $customer['Id']);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message(), 400);
        }

        wp_send_json_success(['message' => 'Member removed']);
    }

    public function organisation_set_member_admin(): void {
        $customer = $this->get_authenticated_customer();

        $member_id = intval($_POST['member_id'] ?? 0);
        $is_admin = !empty($_POST['is_admin']);

        if ($member_id <= 0) {
            wp_send_json_error('Member ID is required', 400);
        }

        $result = $this->organisation_service->set_member_admin_status_by_admin($member_id, (int) $customer['Id'], $is_admin);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message(), 400);
        }

        wp_send_json_success(['message' => 'Member status updated']);
    }

    private function get_authenticated_customer(): array {
        PortalAuth::require_user();

        $customer = $this->customer_service->get_by_user_id(get_current_user_id());
        if (empty($customer['Id'])) {
            wp_send_json_error('Customer profile not found', 400);
        }

        return $customer;
    }

    private function current_user_is_client_admin(): bool {
        return $this->client_admin_service->can_administer_blog(get_current_user_id(), get_current_blog_id());
    }
}
