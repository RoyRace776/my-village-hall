<?php
if (!defined('ABSPATH')) {
    exit;
}

$selected_invoice_id = isset($selected_invoice_id) ? intval($selected_invoice_id) : 0;
$payments = isset($payments) && is_array($payments) ? $payments : [];
$payment_methods = isset($payment_methods) && is_array($payment_methods) ? $payment_methods : [];
$invoices = isset($invoices) && is_array($invoices) ? $invoices : [];
$selected_start_date = isset($selected_start_date) ? (string) $selected_start_date : date('Y-m-d', strtotime('-1 month', current_time('timestamp')));
$selected_end_date = isset($selected_end_date) ? (string) $selected_end_date : current_time('Y-m-d');
$payment_quick_date_ranges = isset($payment_quick_date_ranges) && is_array($payment_quick_date_ranges) ? $payment_quick_date_ranges : [];

$redirect_route_params = [
    'start_date' => $selected_start_date,
    'end_date' => $selected_end_date,
];

if ($selected_invoice_id > 0) {
    $redirect_route_params['invoice_id'] = $selected_invoice_id;
}

$redirect_route = 'payments?' . http_build_query($redirect_route_params);
?>

<div class="myvh-dashboard-section myvh-client-settings-page myvh-payments-page">
    <div class="myvh-account-header">
        <div>
            <h2>Payments</h2>
            <p>Manage recorded payments across invoices or focus on a single invoice.</p>
        </div>
        <?php if ($selected_invoice_id > 0): ?>
            <a href="#payments" class="myvh-button">View All Payments</a>
        <?php endif; ?>
    </div>

    <div class="myvh-account-grid">
        <div class="myvh-account-card">
            <div class="myvh-account-card-head">
                <h3>Add Payment</h3>
                <span><?php echo $selected_invoice_id > 0 ? 'For selected invoice' : 'Choose an invoice'; ?></span>
            </div>
            <form class="myvh-account-form" data-portal-action="myvh_portal_create_payment" data-message-target="myvh-payment-create-message">
                <?php if ($selected_invoice_id > 0): ?>
                    <input type="hidden" name="invoice_id" value="<?php echo esc_attr((string) $selected_invoice_id); ?>">
                <?php endif; ?>
                <input type="hidden" name="redirect_route" value="<?php echo esc_attr($redirect_route); ?>">

                <?php if ($selected_invoice_id <= 0): ?>
                    <div class="myvh-account-field">
                        <label for="myvh-portal-payment-invoice"><strong>Invoice</strong></label>
                        <select id="myvh-portal-payment-invoice" name="invoice_id" required>
                            <option value="">Select an invoice</option>
                            <?php foreach ($invoices as $invoice): ?>
                                <?php
                                $amount_due = isset($invoice['AmountDue'])
                                    ? floatval($invoice['AmountDue'])
                                    : max(0.0, floatval($invoice['TotalAmount'] ?? 0) - floatval($invoice['AmountPaid'] ?? 0));
                                ?>
                                <option
                                    value="<?php echo esc_attr((string) intval($invoice['Id'] ?? 0)); ?>"
                                    data-amount-due="<?php echo esc_attr(number_format($amount_due, 2, '.', '')); ?>">
                                    <?php echo esc_html(($invoice['InvoiceNumber'] ?? '') . ' - ' . ($invoice['CustomerName'] ?? 'Unknown')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="myvh-account-field">
                    <label for="myvh-portal-payment-date"><strong>Payment Date</strong></label>
                    <input id="myvh-portal-payment-date" type="text" name="payment_date" data-myvh-picker="date" autocomplete="off" value="<?php echo esc_attr(current_time('Y-m-d')); ?>" required>
                </div>

                <div class="myvh-account-field">
                    <label for="myvh-portal-payment-amount"><strong id="myvh-portal-payment-amount-label" data-base-label="Amount">Amount</strong></label>
                    <input id="myvh-portal-payment-amount" type="number" name="payment_amount" min="0.01" step="0.01" required>
                </div>

                <div class="myvh-account-field">
                    <label for="myvh-portal-payment-method"><strong>Payment Type</strong></label>
                    <select id="myvh-portal-payment-method" name="payment_method" required>
                        <option value="">Select a payment type</option>
                        <?php foreach ($payment_methods as $method): ?>
                            <option value="<?php echo esc_attr($method); ?>"><?php echo esc_html(ucwords($method)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="myvh-account-field">
                    <label for="myvh-portal-payment-reference"><strong>Reference</strong></label>
                    <input id="myvh-portal-payment-reference" type="text" name="payment_reference">
                </div>

                <div class="myvh-account-field">
                    <label for="myvh-portal-payment-comment"><strong>Comment</strong></label>
                    <textarea id="myvh-portal-payment-comment" name="payment_comment" rows="4"></textarea>
                </div>

                <button type="submit" class="myvh-portal-add-btn">
                    <span class="myvh-portal-add-btn__icon" aria-hidden="true">✓</span>
                    <span>Save Payment</span>
                </button>
                <p class="myvh-muted" id="myvh-payment-create-message"></p>
            </form>
        </div>

        <div class="myvh-account-card">
            <div class="myvh-account-card-head">
                <h3><?php echo $selected_invoice_id > 0 ? 'Invoice Payments' : 'Recent Payments'; ?></h3>
                <span><?php echo esc_html((string) count($payments)); ?> record<?php echo count($payments) === 1 ? '' : 's'; ?></span>
            </div>

            <div class="myvh-invoice-filter-section" style="margin-bottom: 16px;">
                <form id="myvh-payment-filter-form" class="myvh-invoice-filter-form">
                    <?php if ($selected_invoice_id > 0): ?>
                        <input type="hidden" name="invoice_id" value="<?php echo esc_attr((string) $selected_invoice_id); ?>">
                    <?php endif; ?>

                    <div class="myvh-invoice-filter-group">
                        <span class="myvh-invoice-filter-label">Filter by payment date</span>
                        <div class="myvh-invoice-date-filter">
                            <label class="myvh-invoice-date-field">
                                <span>Start date</span>
                                <input type="date" name="start_date" value="<?php echo esc_attr($selected_start_date); ?>">
                            </label>
                            <label class="myvh-invoice-date-field">
                                <span>End date</span>
                                <input type="date" name="end_date" value="<?php echo esc_attr($selected_end_date); ?>">
                            </label>
                        </div>

                        <?php if (!empty($payment_quick_date_ranges)): ?>
                            <div class="myvh-invoice-date-quick-actions" aria-label="Quick payment date ranges">
                                <?php foreach ($payment_quick_date_ranges as $key => $range): ?>
                                    <button
                                        type="button"
                                        class="myvh-filter-date-preset"
                                        data-payment-date-range="<?php echo esc_attr((string) $key); ?>"
                                        data-start-date="<?php echo esc_attr((string) ($range['start_date'] ?? '')); ?>"
                                        data-end-date="<?php echo esc_attr((string) ($range['end_date'] ?? '')); ?>">
                                        <?php echo esc_html((string) ($range['label'] ?? $key)); ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="myvh-invoice-filter-actions">
                        <button type="submit" class="myvh-invoice-filter-submit">Apply Filter</button>
                        <button type="button" class="myvh-invoice-filter-submit myvh-invoice-filter-reset" data-payment-filter-reset>
                            Reset to Last Month
                        </button>
                    </div>
                </form>
            </div>

            <?php if (empty($payments)): ?>
                <p>No payments found for the selected date range.</p>
            <?php else: ?>
                <div class="myvh-invoices-table-wrap">
                    <table class="myvh-customer-list-table myvh-invoices-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Invoice</th>
                                <th>Customer</th>
                                <th>Type</th>
                                <th class="myvh-invoices-table__amount">Amount</th>
                                <th>Reference</th>
                                <th>Comment</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $payment): ?>
                                <?php
                                $payment_id = intval($payment['Id'] ?? 0);
                                $invoice_id = intval($payment['InvoiceId'] ?? 0);
                                $message_id = 'myvh-payment-delete-message-' . $payment_id;
                                ?>
                                <tr>
                                    <td><?php echo esc_html(date('j M Y', strtotime((string) ($payment['PaymentDate'] ?? 'now')))); ?></td>
                                    <td><a href="#invoice-view?invoice_id=<?php echo $invoice_id; ?>"><?php echo esc_html($payment['InvoiceNumber'] ?? ''); ?></a></td>
                                    <td><?php echo esc_html($payment['CustomerName'] ?? 'Unknown'); ?></td>
                                    <td><?php echo esc_html(ucwords((string) ($payment['PaymentMethod'] ?? 'other'))); ?></td>
                                    <td class="myvh-amount">£<?php echo number_format((float) ($payment['Amount'] ?? 0), 2); ?></td>
                                    <td><?php echo esc_html($payment['TransactionReference'] ?? ''); ?></td>
                                    <td><?php echo esc_html($payment['Notes'] ?? ''); ?></td>
                                    <td>
                                        <form class="myvh-inline-form" data-portal-action="myvh_portal_delete_payment" data-message-target="<?php echo esc_attr($message_id); ?>" data-confirm="Delete this payment?">
                                            <input type="hidden" name="payment_id" value="<?php echo esc_attr((string) $payment_id); ?>">
                                            <input type="hidden" name="invoice_id" value="<?php echo esc_attr((string) $invoice_id); ?>">
                                            <input type="hidden" name="redirect_route" value="<?php echo esc_attr($redirect_route); ?>">
                                            <button type="submit" class="myvh-button myvh-button-small">Delete</button>
                                        </form>
                                        <p class="myvh-muted" id="<?php echo esc_attr($message_id); ?>"></p>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
