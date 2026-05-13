<?php
namespace MYVH\Portal\Ajax;

use MYVH\Customers\CustomerService;
use MYVH\Organisations\OrganisationService;
use MYVH\Organisations\SaveOrganisationRequest;
use MYVH\Portal\ClientAdminService;
use MYVH\Portal\Support\PortalAuth;
use MYVH\Portal\Support\AjaxResponse;

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
            AjaxResponse::error(__('Please choose an organisation', 'my-village-hall'));
        }

        $result = $this->organisation_service->create_membership_request($org_id, (int) $customer['Id'], $message);

        if (is_wp_error($result)) {
            AjaxResponse::error($result->get_error_message());
        }

        AjaxResponse::success([], __('Membership request sent', 'my-village-hall'));
    }

    public function approve_organisation_membership_request(): void {
        $customer = $this->get_authenticated_customer();
        $request_id = intval($_POST['request_id'] ?? 0);

        if ($request_id <= 0) {
            AjaxResponse::error(__('Request ID is required', 'my-village-hall'));
        }

        $result = $this->organisation_service->approve_membership_request($request_id, (int) $customer['Id']);

        if (is_wp_error($result)) {
            AjaxResponse::error($result->get_error_message());
        }

        AjaxResponse::success([], __('Request approved', 'my-village-hall'));
    }

    public function reject_organisation_membership_request(): void {
        $customer = $this->get_authenticated_customer();
        $request_id = intval($_POST['request_id'] ?? 0);

        if ($request_id <= 0) {
            AjaxResponse::error(__('Request ID is required', 'my-village-hall'));
        }

        $result = $this->organisation_service->reject_membership_request($request_id, (int) $customer['Id']);

        if (is_wp_error($result)) {
            AjaxResponse::error($result->get_error_message());
        }

        AjaxResponse::success([], __('Request rejected', 'my-village-hall'));
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
            AjaxResponse::error($saved->get_error_message());
        }

        $organisation_id = (int) $saved;

        if ($organisation_id <= 0) {
            AjaxResponse::error(__('Organisation save failed', 'my-village-hall'));
        }

        $membership = $this->organisation_service->add_member($organisation_id, (int) $customer['Id'], true);

        if (is_wp_error($membership) || !$membership) {
            $this->organisation_service->delete($organisation_id);
            $message = is_wp_error($membership)
                ? $membership->get_error_message()
                : __('Organisation membership assignment failed', 'my-village-hall');
            AjaxResponse::error($message);
        }

        AjaxResponse::success([
            'organisation_id' => $organisation_id,
        ], __('Organisation created', 'my-village-hall'));
    }

    public function save_organisation_type_assignment(): void {
        PortalAuth::require_client_admin($this->client_admin_service);

        $organisation_id = intval($_POST['organisation_id'] ?? 0);
        if ($organisation_id <= 0) {
            AjaxResponse::error(__('Organisation is required', 'my-village-hall'));
        }

        $existing = $this->organisation_service->get_by_id($organisation_id);
        if (empty($existing['Id'])) {
            AjaxResponse::not_found(__('Organisation not found', 'my-village-hall'));
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
            'recurring_booking_auto_invoice_rule_id' => intval($existing['RecurringBookingAutoInvoiceRuleId'] ?? 0),
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
            AjaxResponse::error($saved->get_error_message());
        }

        if (!$saved) {
            AjaxResponse::error(__('Organisation update failed', 'my-village-hall'));
        }

        AjaxResponse::success([], __('Organisation type updated', 'my-village-hall'));
    }

    public function delete_organisation(): void {
        PortalAuth::require_user();

        $org_id = intval($_POST['organisation_id'] ?? 0);

        if ($org_id <= 0) {
            AjaxResponse::error(__('Organisation is required', 'my-village-hall'));
        }

        $is_client_admin = $this->current_user_is_client_admin();

        if (!$is_client_admin) {
            $customer = $this->customer_service->get_by_user_id(get_current_user_id());
            if (empty($customer['Id'])) {
                AjaxResponse::error(__('Customer profile not found', 'my-village-hall'));
            }
            $is_org_admin = $this->organisation_service->is_customer_admin_for_organisation($org_id, (int) $customer['Id']);
            if (!$is_org_admin) {
                AjaxResponse::permission_error(__('Only organisation admins can delete this organisation', 'my-village-hall'));
            }
        }

        $result = $this->organisation_service->delete($org_id);
        if (is_wp_error($result)) {
            AjaxResponse::error($result->get_error_message());
        }
        if (!$result) {
            AjaxResponse::error(__('Organisation delete failed', 'my-village-hall'));
        }

        AjaxResponse::success([], __('Organisation deleted', 'my-village-hall'));
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
                'recurring_booking_auto_invoice_rule_id' => intval($_POST['recurring_booking_auto_invoice_rule_id'] ?? 0),
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
            AjaxResponse::error($result->get_error_message());
        }

        AjaxResponse::success([], __('Organisation billing details updated', 'my-village-hall'));
    }

    public function organisation_add_member(): void {
        $customer = $this->get_authenticated_customer();

        $org_id = intval($_POST['organisation_id'] ?? 0);
        $email = sanitize_email($_POST['email'] ?? '');
        $is_admin = !empty($_POST['is_admin']);

        if ($org_id <= 0) {
            AjaxResponse::error(__('Organisation is required', 'my-village-hall'));
        }

        if ($email === '' || !is_email($email)) {
            AjaxResponse::error(__('Valid member email is required', 'my-village-hall'));
        }

        $target_customer = $this->customer_service->get_by_email($email);
        if (empty($target_customer['Id'])) {
            AjaxResponse::error(__('No customer exists with that email address', 'my-village-hall'));
        }

        $result = $this->organisation_service->add_member_by_admin(
            $org_id,
            (int) $customer['Id'],
            (int) $target_customer['Id'],
            $is_admin
        );

        if (is_wp_error($result)) {
            AjaxResponse::error($result->get_error_message());
        }

        AjaxResponse::success([], __('Member added', 'my-village-hall'));
    }

    public function organisation_remove_member(): void {
        $customer = $this->get_authenticated_customer();
        $member_id = intval($_POST['member_id'] ?? 0);

        if ($member_id <= 0) {
            AjaxResponse::error(__('Member ID is required', 'my-village-hall'));
        }

        $result = $this->organisation_service->remove_member_by_admin($member_id, (int) $customer['Id']);

        if (is_wp_error($result)) {
            AjaxResponse::error($result->get_error_message());
        }

        AjaxResponse::success([], __('Member removed', 'my-village-hall'));
    }

    public function organisation_set_member_admin(): void {
        $customer = $this->get_authenticated_customer();

        $member_id = intval($_POST['member_id'] ?? 0);
        $is_admin = !empty($_POST['is_admin']);

        if ($member_id <= 0) {
            AjaxResponse::error(__('Member ID is required', 'my-village-hall'));
        }

        $result = $this->organisation_service->set_member_admin_status_by_admin($member_id, (int) $customer['Id'], $is_admin);

        if (is_wp_error($result)) {
            AjaxResponse::error($result->get_error_message());
        }

        AjaxResponse::success([], __('Member status updated', 'my-village-hall'));
    }

    private function get_authenticated_customer(): array {
        PortalAuth::require_user();

        $customer = $this->customer_service->get_by_user_id(get_current_user_id());
        if (empty($customer['Id'])) {
            AjaxResponse::error(__('Customer profile not found', 'my-village-hall'));
        }

        return $customer;
    }

    private function current_user_is_client_admin(): bool {
        return $this->client_admin_service->can_administer_blog(get_current_user_id(), get_current_blog_id());
    }
}
