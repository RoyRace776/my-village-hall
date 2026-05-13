<?php
namespace MYVH\Portal\Ajax;

use MYVH\Bookings\BookingService;
use MYVH\Invoices\InvoiceService;
use MYVH\Payments\PaymentService;

class PortalBillingPageRenderer {
    public function __construct(
        private BookingService $booking_service,
        private InvoiceService $invoice_service,
        private PaymentService $payment_service
    ) {}

    public function render_invoices(array $customer, bool $is_client_admin, bool $has_customer): void {
        $selected_statuses = [];
        if (isset($_GET['statuses'])) {
            $raw_statuses = wp_unslash($_GET['statuses']);

            if (is_string($raw_statuses)) {
                $selected_statuses = array_map('sanitize_text_field', explode(',', $raw_statuses));
            } else {
                $selected_statuses = array_map('sanitize_text_field', (array) $raw_statuses);
            }
        }

        $valid_statuses = $this->invoice_service->get_valid_statuses();
        $selected_statuses = array_values(array_intersect($selected_statuses, $valid_statuses));

        // If no statuses selected, default to all statuses
        if (empty($selected_statuses)) {
            $selected_statuses = $valid_statuses;
        }

        $invoices = [];
        if ($is_client_admin) {
            $invoices = $this->invoice_service->get_with_customers() ?: [];

            if (!empty($selected_statuses)) {
                $invoices = array_values(array_filter($invoices, function ($invoice) use ($selected_statuses) {
                    return in_array($invoice['Status'] ?? '', $selected_statuses, true);
                }));
            }
        } elseif ($has_customer) {
            $customer_id = intval($customer['Id']);
            $invoices = $this->invoice_service->get_for_portal(
                $customer_id,
                !empty($selected_statuses) ? $selected_statuses : []
            );
        }

        $available_statuses = $valid_statuses;

        include MYVH_PLUGIN_DIR . 'templates/Portal/invoices.php';
    }

    public function get_unpaid_invoice_summary(array $customer, int $limit = 6): array {
        $customer_id = intval($customer['Id'] ?? 0);
        if ($customer_id <= 0) {
            return [];
        }

        $unpaid_statuses = ['sent', 'part-paid', 'overdue'];
        $invoices = $this->invoice_service->get_for_portal($customer_id, $unpaid_statuses) ?: [];

        return array_slice($invoices, 0, max(1, $limit));
    }

    public function render_invoice_view(array $customer, bool $is_client_admin): void {
        $invoice_id = intval($_GET['invoice_id'] ?? 0);
        $invoice = null;
        $invoice_items = [];
        $available_statuses = $this->invoice_service->get_valid_statuses();

        if ($invoice_id > 0) {
            $customer_id = !empty($customer['Id']) ? intval($customer['Id']) : 0;
            $invoice = $this->invoice_service->get_detail_for_portal($invoice_id, $customer_id, $is_client_admin);
            $invoice_items = $invoice['Items'] ?? [];
        }

        include MYVH_PLUGIN_DIR . 'templates/Portal/invoice-view.php';
    }

    public function render_payments(bool $is_client_admin): void {
        if (!$is_client_admin) {
            wp_send_json_error('Permission denied', 403);
        }

        $selected_invoice_id = intval($_GET['invoice_id'] ?? 0);
        $payments = $this->payment_service->get_payments($selected_invoice_id);
        $payment_methods = $this->payment_service->get_valid_methods();
        $invoices = $this->invoice_service->get_with_customers() ?: [];

        include MYVH_PLUGIN_DIR . 'templates/Portal/payments.php';
    }

    public function render_invoice_generate(bool $is_client_admin): void {
        if (!$is_client_admin) {
            wp_send_json_error('Permission denied', 403);
        }

        $uninvoiced_bookings = $this->booking_service->get_uninvoiced_bookings([
            'orderby' => 'b.StartDate',
            'order' => 'ASC',
        ]);
        $uninvoiced_by_customer = $this->booking_service->get_uninvoiced_by_customer();
        $uninvoiced_by_organisation = $this->booking_service->get_uninvoiced_by_organisation();

        include MYVH_PLUGIN_DIR . 'templates/Portal/invoice-generate.php';
    }
}