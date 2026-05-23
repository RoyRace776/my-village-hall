<?php
namespace MYVH\Portal\Ajax;

use MYVH\Bookings\BookingService;
use MYVH\Customers\CustomerService;
use MYVH\Email\EmailService;
use MYVH\Invoices\InvoiceGeneratorService;
use MYVH\Invoices\InvoiceService;
use MYVH\Payments\PaymentService;
use MYVH\Portal\ClientAdminService;
use MYVH\Portal\Support\AjaxResponse;
use MYVH\Portal\Support\PortalAuth;
use MYVH\Subscriptions\Repositories\AccountRepository;
use MYVH\Subscriptions\Repositories\PlanRepository;
use MYVH\Subscriptions\Services\AccountService;
use MYVH\Subscriptions\Services\BillingService;
use MYVH\Subscriptions\Services\PlanChangePolicyService;
use MYVH\Subscriptions\Services\TrialService;

class PortalBillingAjaxController {
    public function __construct(
        private BookingService $booking_service,
        private CustomerService $customer_service,
        private InvoiceGeneratorService $invoice_generator_service,
        private InvoiceService $invoice_service,
        private PaymentService $payment_service,
        private ClientAdminService $client_admin_service,
        private ?AccountRepository $account_repository = null,
        private ?AccountService $account_service = null,
        private ?PlanRepository $plan_repository = null,
        private ?BillingService $billing_service = null,
        private ?PlanChangePolicyService $plan_change_policy_service = null,
        private ?TrialService $trial_service = null
    ) {}

    public function register(): void {
        add_action('wp_ajax_myvh_portal_get_uninvoiced_bookings', [$this, 'get_uninvoiced_bookings_for_entity']);
        add_action('wp_ajax_myvh_portal_create_invoice', [$this, 'create_invoice']);
        add_action('wp_ajax_myvh_portal_view_invoice_pdf', [$this, 'view_invoice_pdf']);
        add_action('wp_ajax_myvh_portal_email_invoice', [$this, 'email_invoice']);
        add_action('wp_ajax_myvh_portal_update_invoice_status', [$this, 'update_invoice_status']);
        add_action('wp_ajax_myvh_portal_create_payment', [$this, 'create_payment']);
        add_action('wp_ajax_myvh_portal_delete_payment', [$this, 'delete_payment']);
        add_action('wp_ajax_myvh_portal_settle_invoice_deposit', [$this, 'settle_invoice_deposit']);
        add_action('wp_ajax_myvh_portal_change_subscription_plan', [$this, 'change_subscription_plan']);
        add_action('wp_ajax_myvh_portal_update_trial_start_date', [$this, 'update_trial_start_date']);
    }

    public function update_trial_start_date(): void {
        PortalAuth::require_global_admin($this->client_admin_service);

        if (
            !$this->account_repository instanceof AccountRepository
            || !$this->trial_service instanceof TrialService
        ) {
            wp_send_json_error([
                'message' => __('Subscription services are unavailable right now.', 'my-village-hall'),
            ], 500);
        }

        $blog_id = function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 0;
        $account = $blog_id > 0 ? $this->account_repository->get_by_blog_id($blog_id) : null;
        if (!is_array($account) && $blog_id > 0 && $this->account_service instanceof AccountService) {
            $account = $this->account_service->resolveAccountFromBlogId($blog_id);
        }

        if (!is_array($account) && $this->account_service instanceof AccountService) {
            $site_name = sanitize_text_field((string) get_bloginfo('name'));
            $admin_email = sanitize_email((string) get_option('admin_email', ''));
            if ($site_name !== '' && $admin_email !== '') {
                $account = $this->account_service->createAccount($site_name, $admin_email);
            }
        }

        $account_id = is_array($account) ? (int) ($account['id'] ?? 0) : 0;
        if ($account_id <= 0) {
            wp_send_json_error([
                'message' => __('No billing account is linked to this site.', 'my-village-hall'),
            ], 404);
        }

        $trial_start_date = sanitize_text_field((string) ($_POST['trial_start_date'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $trial_start_date)) {
            wp_send_json_error([
                'message' => __('Please provide a valid trial start date.', 'my-village-hall'),
            ], 400);
        }

        $updated_subscription = $this->trial_service->updateTrialStartDate($account_id, $trial_start_date);
        if (!$updated_subscription instanceof \MYVH\Subscriptions\Entities\Subscription) {
            wp_send_json_error([
                'message' => __('Trial start date can only be changed for an active trial subscription.', 'my-village-hall'),
            ], 422);
        }

        wp_send_json_success([
            'message' => __('Trial start date updated.', 'my-village-hall'),
            'redirect' => 'subscription-upgrade',
        ]);
    }

    public function change_subscription_plan(): void {
        PortalAuth::require_client_admin($this->client_admin_service);

        if (
            !$this->account_repository instanceof AccountRepository
            || !$this->plan_repository instanceof PlanRepository
            || !$this->billing_service instanceof BillingService
            || !$this->plan_change_policy_service instanceof PlanChangePolicyService
        ) {
            wp_send_json_error([
                'message' => __('Subscription services are unavailable right now.', 'my-village-hall'),
            ], 500);
        }

        $blog_id = function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 0;
        $account = $blog_id > 0 ? $this->account_repository->get_by_blog_id($blog_id) : null;
        if (!is_array($account) && $blog_id > 0 && $this->account_service instanceof AccountService) {
            $account = $this->account_service->resolveAccountFromBlogId($blog_id);
        }

        if (!is_array($account) && $this->account_service instanceof AccountService) {
            $site_name = sanitize_text_field((string) get_bloginfo('name'));
            $admin_email = sanitize_email((string) get_option('admin_email', ''));
            if ($site_name !== '' && $admin_email !== '') {
                $account = $this->account_service->createAccount($site_name, $admin_email);
            }
        }

        $account_id = is_array($account) ? (int) ($account['id'] ?? 0) : 0;

        if ($account_id <= 0) {
            wp_send_json_error([
                'message' => __('No billing account is linked to this site.', 'my-village-hall'),
            ], 404);
        }

        if ($this->trial_service instanceof TrialService) {
            $subscription = $this->trial_service->checkAndExpireTrial($account_id);
            if (!$subscription instanceof \MYVH\Subscriptions\Entities\Subscription) {
                $this->trial_service->createTrialSubscription($account_id);
            }
        }

        $plan_code = sanitize_key((string) ($_POST['plan_code'] ?? ''));
        $payment_method = sanitize_key((string) ($_POST['payment_method'] ?? 'stripe'));

        if (!in_array($payment_method, ['stripe', 'invoice'], true)) {
            wp_send_json_error([
                'message' => __('Unsupported payment method.', 'my-village-hall'),
            ], 400);
        }

        $plan = $this->plan_repository->getByCode($plan_code);
        if (!$plan instanceof \MYVH\Subscriptions\Entities\Plan) {
            wp_send_json_error([
                'message' => __('Selected plan was not found.', 'my-village-hall'),
            ], 404);
        }

        $decision = $this->plan_change_policy_service->canChangeToPlan($account_id, $plan);
        if (empty($decision['allowed'])) {
            wp_send_json_error([
                'message' => (string) ($decision['message'] ?? __('Plan change is not allowed.', 'my-village-hall')),
            ], 422);
        }

        $result = $this->billing_service->requestPlanChange($account_id, $plan_code, $payment_method);
        if (!is_array($result) || empty($result['success'])) {
            $message = $payment_method === 'stripe'
                ? __('Stripe is not configured for this site yet. Choose Invoice or configure Stripe keys in Billing Settings.', 'my-village-hall')
                : __('Unable to start the plan change flow.', 'my-village-hall');

            wp_send_json_error([
                'message' => $message,
            ], 422);
        }

        $scheduled = !empty($result['scheduled']);
        $message = $scheduled
            ? __('Downgrade scheduled for the end of your current billing period.', 'my-village-hall')
            : __('Plan change started successfully.', 'my-village-hall');

        wp_send_json_success([
            'message' => $message,
            'scheduled' => $scheduled,
            'effective_at' => (string) ($result['effective_at'] ?? ''),
            'redirect' => 'subscription-upgrade',
        ]);
    }

    public function view_invoice_pdf(): void {
        PortalAuth::require_user();

        $invoice_id = \intval($_GET['invoice_id'] ?? 0);
        if ($invoice_id <= 0) {
            wp_die(__('Invalid invoice ID', 'my-village-hall'));
        }

        $user_id = get_current_user_id();
        $is_client_admin = $this->client_admin_service->can_administer_blog($user_id, get_current_blog_id());
        $customer = $this->customer_service->get_by_user_id($user_id);
        $customer_id = \intval($customer['Id'] ?? 0);

        if (!$is_client_admin && $customer_id <= 0) {
            wp_die(__('Permission denied', 'my-village-hall'));
        }

        $invoice = $this->invoice_service->get_detail_for_portal($invoice_id, $customer_id, $is_client_admin);
        if (!$invoice) {
            wp_die(__('Invoice not found or you do not have permission to view it.', 'my-village-hall'));
        }

        $result = $this->invoice_service->get_invoice_pdf_url($invoice_id);
        if (is_wp_error($result)) {
            wp_die($result->get_error_message());
        }

        wp_safe_redirect($result);
        exit;
    }

    public function get_uninvoiced_bookings_for_entity(): void {
        PortalAuth::require_client_admin($this->client_admin_service);

        $customer_id = \intval($_GET['customer_id'] ?? $_POST['customer_id'] ?? 0);
        $organisation_id = \intval($_GET['organisation_id'] ?? $_POST['organisation_id'] ?? 0);

        $args = [
            'orderby' => 'b.StartDate',
            'order' => 'ASC',
        ];

        if ($customer_id) {
            $args['customer_id'] = $customer_id;
        }

        if ($organisation_id) {
            $args['organisation_id'] = $organisation_id;
        }

        if (!$customer_id && !$organisation_id) {
            AjaxResponse::error(__('Missing customer_id or organisation_id', 'my-village-hall'));
        }

        $bookings = $this->booking_service->get_uninvoiced_bookings($args);
        AjaxResponse::success(['bookings' => $bookings]);
    }

    public function create_invoice(): void {
        PortalAuth::require_client_admin($this->client_admin_service);

        $booking_ids_raw = $_POST['booking_ids'] ?? [];
        if (is_string($booking_ids_raw)) {
            $booking_ids_raw = explode(',', $booking_ids_raw);
        }

        $booking_ids = array_map('intval', (array) $booking_ids_raw);
        $booking_ids = array_filter($booking_ids);

        if (empty($booking_ids)) {
            AjaxResponse::error(__('No bookings selected', 'my-village-hall'));
        }

        $group_by = sanitize_key($_POST['group_by'] ?? 'per_booking');
        if (!in_array($group_by, ['per_booking', 'by_customer', 'by_organisation'], true)) {
            $group_by = 'per_booking';
        }

        $options = [
            'group_by' => $group_by,
            'trigger_event' => 'manual',
        ];

        $result = $this->invoice_generator_service->generate_invoices_from_bookings($booking_ids, $options);

        if (is_wp_error($result)) {
            AjaxResponse::error($result->get_error_message());
        }

        $count = count($result);
        AjaxResponse::success([
            'invoice_ids' => $result,
            'count' => $count,
        ], sprintf(__('Created %d invoice(s)', 'my-village-hall'), $count));
    }

    public function create_payment(): void {
        PortalAuth::require_client_admin($this->client_admin_service);

        $payload = wp_unslash($_POST);
        $result = $this->payment_service->create($payload);

        if (is_wp_error($result)) {
            AjaxResponse::error($result->get_error_message());
        }

        $invoice_id = \intval($payload['invoice_id'] ?? 0);
        $invoice = $this->invoice_service->get_detail($invoice_id);

        AjaxResponse::success([
            'redirect' => sanitize_text_field($payload['redirect_route'] ?? ('payments?invoice_id=' . $invoice_id)),
            'status' => $invoice['Status'] ?? '',
            'status_label' => $this->invoice_service->get_status_label((string) ($invoice['Status'] ?? ''), $invoice),
            'amount_paid' => $invoice['AmountPaid'] ?? 0,
            'amount_due' => $invoice['AmountDue'] ?? 0,
        ], __('Payment saved', 'my-village-hall'));
    }

    public function delete_payment(): void {
        PortalAuth::require_client_admin($this->client_admin_service);

        $payment_id = \intval($_POST['payment_id'] ?? 0);
        $result = $this->payment_service->delete($payment_id);

        if (is_wp_error($result)) {
            AjaxResponse::error($result->get_error_message());
        }

        $redirect = sanitize_text_field($_POST['redirect_route'] ?? 'payments');
        $invoice_id = \intval($_POST['invoice_id'] ?? 0);
        $invoice = $invoice_id > 0 ? $this->invoice_service->get_detail($invoice_id) : null;

        AjaxResponse::success([
            'redirect' => $redirect,
            'status' => $invoice['Status'] ?? '',
            'status_label' => $this->invoice_service->get_status_label((string) ($invoice['Status'] ?? ''), $invoice),
            'amount_paid' => $invoice['AmountPaid'] ?? 0,
            'amount_due' => $invoice['AmountDue'] ?? 0,
        ], __('Payment deleted', 'my-village-hall'));
    }

    public function update_invoice_status(): void {
        PortalAuth::require_client_admin($this->client_admin_service);

        $invoice_id = \intval($_POST['invoice_id'] ?? 0);
        $status = sanitize_text_field($_POST['status'] ?? '');

        if ($invoice_id <= 0) {
            AjaxResponse::error(__('Invalid invoice', 'my-village-hall'));
        }

        if (!$this->invoice_service->get($invoice_id)) {
            AjaxResponse::not_found(__('Invoice not found', 'my-village-hall'));
        }

        $result = $this->invoice_service->update_status($invoice_id, $status);
        if (is_wp_error($result)) {
            AjaxResponse::error($result->get_error_message());
        }

        $invoice = $this->invoice_service->get_detail($invoice_id);

        $updated_at_local = '';
        if (!empty($invoice['Updated'])) {
            try {
                $updated_at_local = (new \DateTimeImmutable((string) $invoice['Updated'], new \DateTimeZone('UTC')))
                    ->setTimezone(wp_timezone())
                    ->format('j M Y g:i a');
            } catch (\Exception $e) {
                $updated_at_local = '';
            }
        }

        AjaxResponse::success([
            'status' => $invoice['Status'] ?? $status,
            'status_label' => $this->invoice_service->get_status_label((string) ($invoice['Status'] ?? $status), $invoice ?: null),
            'updated_at_local' => $updated_at_local,
        ], __('Invoice status updated', 'my-village-hall'));
    }

    public function settle_invoice_deposit(): void {
        PortalAuth::require_client_admin($this->client_admin_service);

        $invoice_id = \intval($_POST['invoice_id'] ?? 0);
        $outcome = sanitize_text_field($_POST['deposit_outcome'] ?? '');

        if ($invoice_id <= 0) {
            AjaxResponse::error(__('Invalid invoice', 'my-village-hall'));
        }

        $result = $this->invoice_service->settle_deposit($invoice_id, $outcome);
        if (is_wp_error($result)) {
            AjaxResponse::error($result->get_error_message());
        }

        $invoice = $this->invoice_service->get_detail($invoice_id);

        AjaxResponse::success([
            'status' => $invoice['Status'] ?? 'paid',
            'status_label' => $this->invoice_service->get_status_label((string) ($invoice['Status'] ?? 'paid'), $invoice ?: null),
            'amount_paid' => $invoice['AmountPaid'] ?? 0,
            'amount_due' => $invoice['AmountDue'] ?? 0,
        ], $outcome === 'refunded'
            ? __('Deposit marked as refunded and refund payment recorded', 'my-village-hall')
            : __('Deposit marked as retained', 'my-village-hall'));
    }

    public function email_invoice(): void {
        PortalAuth::require_client_admin($this->client_admin_service);

        $invoice_id = \intval($_POST['invoice_id'] ?? 0);
        if ($invoice_id <= 0) {
            AjaxResponse::error(__('Invalid invoice ID', 'my-village-hall'));
        }

        $invoice = $this->invoice_service->get_detail($invoice_id);
        if (empty($invoice)) {
            AjaxResponse::not_found(__('Invoice not found', 'my-village-hall'));
        }

        $recipient = $this->resolve_recipient($invoice);
        if ($recipient === '') {
            AjaxResponse::error(__('No valid recipient email found for this invoice', 'my-village-hall'));
        }

        $pdf_path = $this->invoice_service->generate_pdf($invoice_id);
        if (is_wp_error($pdf_path)) {
            AjaxResponse::error($pdf_path->get_error_message());
        }

        $email_service = new EmailService();
        $send_result = $email_service->send([
            'to' => $recipient,
            'template' => 'invoice',
            'template_vars' => $this->build_template_vars($invoice_id, $invoice),
            'attachments' => [$pdf_path],
        ]);

        if (!$send_result) {
            AjaxResponse::server_error(__('Failed to send invoice email', 'my-village-hall'));
        }

        $status_result = $this->invoice_service->update_status($invoice_id, 'sent');
        if (is_wp_error($status_result) || $status_result === false) {
            $message = is_wp_error($status_result)
                ? $status_result->get_error_message()
                : __('Unknown error while updating invoice status', 'my-village-hall');
            AjaxResponse::server_error($message);
        }

        AjaxResponse::success([
            'status' => 'sent',
            'status_label' => $this->invoice_service->get_status_label('sent'),
        ], __('Invoice emailed and marked as sent', 'my-village-hall'));
    }

    private function resolve_recipient(array $invoice): string {
        $billing_email = sanitize_email((string) ($invoice['BillingEmail'] ?? ''));
        if ($billing_email !== '' && is_email($billing_email)) {
            return $billing_email;
        }

        $customer_email = sanitize_email((string) ($invoice['CustomerEmail'] ?? ''));
        if ($customer_email !== '' && is_email($customer_email)) {
            return $customer_email;
        }

        return '';
    }

    private function build_template_vars(int $invoice_id, array $invoice): array {
        $email_service = new EmailService();
        $branding = $email_service->get_branding();
        $invoice_url = '';

        $pdf_url = $this->invoice_service->get_invoice_pdf_url($invoice_id);
        if (!is_wp_error($pdf_url)) {
            $invoice_url = $pdf_url;
        }

        return array_merge($branding, [
            'customer_name' => (string) ($invoice['BillingName'] ?? $invoice['CustomerName'] ?? ''),
            'customer_address' => $this->build_address_line($invoice),
            'invoice_ref' => (string) ($invoice['InvoiceNumber'] ?? ('INV-' . $invoice_id)),
            'invoice_total' => number_format((float) ($invoice['TotalAmount'] ?? 0), 2, '.', ''),
            'invoice_due_date' => (string) ($invoice['DueDate'] ?? ''),
            'invoice_status' => $this->invoice_service->get_status_label('sent'),
            'invoice_url' => $invoice_url,
            'organisation_name' => (string) ($invoice['BillingOrganisationName'] ?? $invoice['OrganisationName'] ?? ''),
            'booking_details' => $this->build_booking_details($invoice),
        ]);
    }

    private function build_address_line(array $invoice): string {
        $parts = array_filter([
            (string) ($invoice['BillingAddressLine1'] ?? ''),
            (string) ($invoice['BillingAddressLine2'] ?? ''),
            (string) ($invoice['BillingTownCity'] ?? ''),
            (string) ($invoice['BillingPostcode'] ?? ''),
        ]);

        return implode(', ', $parts);
    }

    private function build_booking_details(array $invoice): string {
        $bookings = is_array($invoice['BookingsSummary'] ?? null) ? $invoice['BookingsSummary'] : [];
        $booking_count = \intval($invoice['BookingCount'] ?? count($bookings));
        if ($booking_count <= 0) {
            return '';
        }

        $first_booking = $bookings[0] ?? [];
        if ($booking_count === 1 && !empty($first_booking['Description'])) {
            return (string) $first_booking['Description'];
        }

        return sprintf(__('Includes %d booking(s).', 'my-village-hall'), $booking_count);
    }
}
