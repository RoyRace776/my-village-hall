<?php
if (!defined('ABSPATH')) exit;

class MYVH_Invoice_Service {

    private $repo;
    private $payment_repo;

    public function __construct(MYVH_Invoice_Repository $repo,
                                MYVH_Payment_Repository $payment_repo) {
        $this->repo         = $repo;
        $this->payment_repo = $payment_repo;
    }

    public function get_all($args = []) {
        return $this->repo->get_all($args);
    }

    public function get($id) {
        return $this->repo->get_by_id($id);
    }

    public function get_with_customers() {
        return $this->repo->get_all_with_customers();
    }

    public function get_by_customer($customer_id) {
        return $this->repo->get_by_customer($customer_id);
    }

    public function get_by_booking($booking_id) {
        return $this->repo->get_by_booking($booking_id);
    }

    public function get_by_status($status) {
        return $this->repo->get_by_status($status);
    }

    public function save($data) {

        if (empty($data['customer_id'])) {
            return new WP_Error('validation', __('Customer is required', 'my-village-hall'));
        }

        if (!isset($data['total_amount']) || floatval($data['total_amount']) < 0) {
            return new WP_Error('validation', __('Valid total amount is required', 'my-village-hall'));
        }

        // Generate invoice number if creating new invoice
        if (empty($data['invoice_id'])) {
            $invoice_number = $this->repo->get_next_invoice_number();
            $invoice_number_formatted = 'INV-' . str_pad($invoice_number, 6, '0', STR_PAD_LEFT);
        } else {
            $invoice_number_formatted = $data['invoice_number'];
        }

        $total_amount = floatval($data['total_amount']);
        $amount_paid  = floatval($data['amount_paid'] ?? 0);

        $record = [
            'InvoiceNumber' => $invoice_number_formatted,
            'CustomerId'    => intval($data['customer_id']),
            'InvoiceDate'   => !empty($data['invoice_date']) ? sanitize_text_field($data['invoice_date']) : date('Y-m-d'),
            'DueDate'       => !empty($data['due_date']) ? sanitize_text_field($data['due_date']) : date('Y-m-d', strtotime('+14 days')),
            'SubTotal'      => floatval($data['sub_total'] ?? 0),
            'TaxAmount'     => floatval($data['tax_amount'] ?? 0),
            'TotalAmount'   => $total_amount,
            'AmountPaid'    => $amount_paid,
            'AmountDue'     => max(0.0, $total_amount - $amount_paid),
            'Status'        => sanitize_text_field($data['status'] ?? 'draft'),
            'Notes'         => sanitize_textarea_field($data['notes'] ?? ''),
        ];

        if (!empty($data['invoice_id'])) {
            $result = $this->repo->update($record, ['Id' => intval($data['invoice_id'])]);
            if ($result === false) {
                return new WP_Error('database', __('Failed to update invoice', 'my-village-hall'));
            }
            return intval($data['invoice_id']);
        }

        $result = $this->repo->create($record);
        if ($result === false) {
            return new WP_Error('database', __('Failed to create invoice', 'my-village-hall'));
        }

        return $result;
    }

    public function delete($id) {
        if ($this->payment_repo->count_by_invoice($id) > 0) {
            return new WP_Error('validation', __('Cannot delete invoice with existing payments', 'my-village-hall'));
        }

        return $this->repo->delete($id);
    }

    /**
     * Get invoices for customer portal with organisation metadata
     *
     * @param int $customer_id The customer ID
     * @param array $statuses Optional array of statuses to filter by
     * @return array Invoice records with organisation information
     */
    public function get_for_portal($customer_id, $statuses = []) {
        return $this->repo->get_for_customer_portal($customer_id, $statuses);
    }

    /**
     * Update invoice status
     *
     * @param int $id Invoice ID
     * @param string $status New status
     * @return bool|WP_Error
     */
    public function update_status($id, $status) {
        $valid_statuses = ['draft', 'sent', 'paid', 'overdue', 'cancelled'];

        if (!in_array($status, $valid_statuses)) {
            return new WP_Error('validation', __('Invalid invoice status', 'my-village-hall'));
        }

        return $this->repo->update(['Status' => $status], ['Id' => $id]);
    }

    /**
     * Record a payment against an invoice
     *
     * @param int $id Invoice ID
     * @param float $amount Payment amount
     * @return bool|WP_Error
     */
    public function record_payment($id, $amount) {
        $invoice = $this->repo->get_by_id($id);

        if (!$invoice) {
            return new WP_Error('not_found', __('Invoice not found', 'my-village-hall'));
        }

        $new_amount_paid = floatval($invoice['AmountPaid']) + floatval($amount);
        $total_amount = floatval($invoice['TotalAmount']);

        $data = [
            'AmountPaid' => $new_amount_paid,
            'AmountDue'  => max(0.0, $total_amount - $new_amount_paid),
        ];

        // Update status if fully paid
        if ($new_amount_paid >= $total_amount) {
            $data['Status'] = 'paid';
        }

        return $this->repo->update($data, ['Id' => $id]);
    }
}
