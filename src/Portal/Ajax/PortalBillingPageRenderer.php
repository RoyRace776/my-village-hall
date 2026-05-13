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
        $available_statuses = $is_client_admin
            ? $valid_statuses
            : array_values(array_filter($valid_statuses, static fn(string $status): bool => $status !== 'draft'));
        $selected_statuses = array_values(array_intersect($selected_statuses, $available_statuses));

        $default_statuses = $available_statuses;

        // If no statuses selected, default to the role-appropriate set
        if (empty($selected_statuses)) {
            $selected_statuses = $default_statuses;
        }

        $client_name = $is_client_admin ? $this->normalize_search_value($_GET['client_name'] ?? '') : '';
        $client_name_match = $is_client_admin ? $this->normalize_match_type($_GET['client_name_match'] ?? 'contains') : 'contains';
        $invoice_number = $is_client_admin ? $this->normalize_search_value($_GET['invoice_number'] ?? '') : '';
        $invoice_number_match = $is_client_admin ? $this->normalize_match_type($_GET['invoice_number_match'] ?? 'contains') : 'contains';

        $date_range = $this->get_invoice_date_range();
        $selected_start_date = $date_range['start_date'];
        $selected_end_date = $date_range['end_date'];

        $invoices = [];
        if ($is_client_admin) {
            $invoices = $this->invoice_service->get_with_customers($selected_start_date, $selected_end_date) ?: [];

            $invoices = array_values(array_filter($invoices, function (array $invoice) use ($client_name, $client_name_match, $invoice_number, $invoice_number_match): bool {
                $customer_name = (string) ($invoice['CustomerName'] ?? '');
                $invoice_ref = (string) ($invoice['InvoiceNumber'] ?? '');

                if ($client_name !== '' && !$this->matches_search_value($customer_name, $client_name, $client_name_match)) {
                    return false;
                }

                if ($invoice_number !== '' && !$this->matches_search_value($invoice_ref, $invoice_number, $invoice_number_match)) {
                    return false;
                }

                return true;
            }));

            if (!empty($selected_statuses)) {
                $invoices = array_values(array_filter($invoices, function ($invoice) use ($selected_statuses) {
                    return in_array($invoice['Status'] ?? '', $selected_statuses, true);
                }));
            }
        } elseif ($has_customer) {
            $customer_id = intval($customer['Id']);
            $invoices = $this->invoice_service->get_for_portal(
                $customer_id,
                !empty($selected_statuses) ? $selected_statuses : [],
                $selected_start_date,
                $selected_end_date
            );
        }

        $quick_date_ranges = $this->get_quick_date_ranges();
        $selected_client_name = $client_name;
        $selected_client_name_match = $client_name_match;
        $selected_invoice_number = $invoice_number;
        $selected_invoice_number_match = $invoice_number_match;

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

    private function get_invoice_date_range(): array {
        $default_end_date = current_time('Y-m-d');
        $default_start_date = date('Y-m-d', strtotime('-1 month', current_time('timestamp')));

        $start_date = $this->normalize_date_value($_GET['start_date'] ?? null, $default_start_date);
        $end_date = $this->normalize_date_value($_GET['end_date'] ?? null, $default_end_date);

        if ($start_date > $end_date) {
            [$start_date, $end_date] = [$end_date, $start_date];
        }

        return [
            'start_date' => $start_date,
            'end_date' => $end_date,
        ];
    }

    private function get_quick_date_ranges(): array {
        $today = current_time('Y-m-d');

        return [
            'last_month' => [
                'label' => 'Last month',
                'start_date' => date('Y-m-d', strtotime('-1 month', current_time('timestamp'))),
                'end_date' => $today,
            ],
            'last_6_months' => [
                'label' => 'Last 6 months',
                'start_date' => date('Y-m-d', strtotime('-6 months', current_time('timestamp'))),
                'end_date' => $today,
            ],
        ];
    }

    private function normalize_date_value(mixed $value, string $default): string {
        $value = is_string($value) ? sanitize_text_field(wp_unslash($value)) : '';
        if ($value === '') {
            return $default;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            return $default;
        }

        return $value;
    }

    private function normalize_search_value(mixed $value): string {
        $value = is_string($value) ? sanitize_text_field(wp_unslash($value)) : '';
        return trim($value);
    }

    private function normalize_match_type(mixed $value): string {
        $value = is_string($value) ? sanitize_key(wp_unslash($value)) : 'contains';
        return in_array($value, ['begins_with', 'contains'], true) ? $value : 'contains';
    }

    private function matches_search_value(string $value, string $term, string $match_type): bool {
        $value = function_exists('mb_strtolower') ? mb_strtolower(trim($value)) : strtolower(trim($value));
        $term = function_exists('mb_strtolower') ? mb_strtolower(trim($term)) : strtolower(trim($term));

        if ($term === '') {
            return true;
        }

        if ($match_type === 'begins_with') {
            return strpos($value, $term) === 0;
        }

        return strpos($value, $term) !== false;
    }
}