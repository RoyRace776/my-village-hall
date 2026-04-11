<?php
namespace MYVH\Portal\Ajax;

use MYVH\Bookings\BookingService;
use MYVH\Invoices\InvoiceGeneratorService;
use MYVH\Invoices\InvoiceService;
use MYVH\Payments\PaymentService;
use MYVH\Portal\ClientAdminService;
use MYVH\Portal\Support\PortalAuth;

class PortalBillingAjaxController {
    public function __construct(
        private BookingService $booking_service,
        private InvoiceGeneratorService $invoice_generator_service,
        private InvoiceService $invoice_service,
        private PaymentService $payment_service,
        private ClientAdminService $client_admin_service
    ) {}

    public function register(): void {
        add_action('wp_ajax_myvh_portal_get_uninvoiced_bookings', [$this, 'get_uninvoiced_bookings_for_entity']);
        add_action('wp_ajax_myvh_portal_create_invoice', [$this, 'create_invoice']);
        add_action('wp_ajax_myvh_portal_update_invoice_status', [$this, 'update_invoice_status']);
        add_action('wp_ajax_myvh_portal_create_payment', [$this, 'create_payment']);
        add_action('wp_ajax_myvh_portal_delete_payment', [$this, 'delete_payment']);
    }

    public function get_uninvoiced_bookings_for_entity(): void {
        PortalAuth::require_client_admin($this->client_admin_service);

        $customer_id = intval($_GET['customer_id'] ?? $_POST['customer_id'] ?? 0);
        $organisation_id = intval($_GET['organisation_id'] ?? $_POST['organisation_id'] ?? 0);

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
            wp_send_json_error('Missing customer_id or organisation_id', 400);
        }

        $bookings = $this->booking_service->get_uninvoiced_bookings($args);
        wp_send_json_success(['bookings' => $bookings]);
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
            wp_send_json_error('No bookings selected', 400);
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
            wp_send_json_error($result->get_error_message(), 400);
        }

        $count = count($result);
        wp_send_json_success([
            'message' => sprintf(__('Created %d invoice(s)', 'my-village-hall'), $count),
            'invoice_ids' => $result,
            'count' => $count,
        ]);
    }

    public function create_payment(): void {
        PortalAuth::require_client_admin($this->client_admin_service);

        $payload = wp_unslash($_POST);
        $result = $this->payment_service->create($payload);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()], 400);
        }

        $invoice_id = intval($payload['invoice_id'] ?? 0);
        $invoice = $this->invoice_service->get_detail($invoice_id);

        wp_send_json_success([
            'message' => __('Payment saved.', 'my-village-hall'),
            'redirect' => sanitize_text_field($payload['redirect_route'] ?? ('payments?invoice_id=' . $invoice_id)),
            'status' => $invoice['Status'] ?? '',
            'status_label' => $this->invoice_service->get_status_label((string) ($invoice['Status'] ?? '')),
            'amount_paid' => $invoice['AmountPaid'] ?? 0,
            'amount_due' => $invoice['AmountDue'] ?? 0,
        ]);
    }

    public function delete_payment(): void {
        PortalAuth::require_client_admin($this->client_admin_service);

        $payment_id = intval($_POST['payment_id'] ?? 0);
        $result = $this->payment_service->delete($payment_id);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()], 400);
        }

        $redirect = sanitize_text_field($_POST['redirect_route'] ?? 'payments');
        $invoice_id = intval($_POST['invoice_id'] ?? 0);
        $invoice = $invoice_id > 0 ? $this->invoice_service->get_detail($invoice_id) : null;

        wp_send_json_success([
            'message' => __('Payment deleted.', 'my-village-hall'),
            'redirect' => $redirect,
            'status' => $invoice['Status'] ?? '',
            'status_label' => $this->invoice_service->get_status_label((string) ($invoice['Status'] ?? '')),
            'amount_paid' => $invoice['AmountPaid'] ?? 0,
            'amount_due' => $invoice['AmountDue'] ?? 0,
        ]);
    }

    public function update_invoice_status(): void {
        PortalAuth::require_client_admin($this->client_admin_service);

        $invoice_id = intval($_POST['invoice_id'] ?? 0);
        $status = sanitize_text_field($_POST['status'] ?? '');

        if ($invoice_id <= 0) {
            wp_send_json_error(['message' => 'Invalid invoice'], 400);
        }

        if (!$this->invoice_service->get($invoice_id)) {
            wp_send_json_error(['message' => 'Invoice not found'], 404);
        }

        $result = $this->invoice_service->update_status($invoice_id, $status);
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()], 400);
        }

        wp_send_json_success([
            'message' => 'Invoice status updated.',
            'status' => $status,
            'status_label' => $this->invoice_service->get_status_label($status),
        ]);
    }
}
