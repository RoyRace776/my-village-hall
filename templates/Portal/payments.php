<?php
if (!defined('ABSPATH')) {
    exit;
}

$selected_invoice_id = isset($selected_invoice_id) ? \intval($selected_invoice_id) : 0;
$payments = isset($payments) && is_array($payments) ? $payments : [];
$payment_methods = isset($payment_methods) && is_array($payment_methods) ? $payment_methods : [];
$invoices = isset($invoices) && is_array($invoices) ? $invoices : [];
$selected_invoice_amount_due = null;

if ($selected_invoice_id > 0) {
    foreach ($invoices as $invoice) {
        $invoice_id = \intval($invoice['Id'] ?? 0);
        if ($invoice_id !== $selected_invoice_id) {
            continue;
        }

        $selected_invoice_amount_due = isset($invoice['AmountDue'])
            ? max(0.0, \floatval($invoice['AmountDue']))
            : max(0.0, \floatval($invoice['TotalAmount'] ?? 0) - \floatval($invoice['AmountPaid'] ?? 0));
        break;
    }
}

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
            <form class="myvh-account-form" data-portal-action="myvh_portal_create_payment" data-message-target="myvh-payment-create-message"<?php echo $selected_invoice_amount_due !== null ? ' data-selected-amount-due="' . esc_attr(number_format($selected_invoice_amount_due, 2, '.', '')) . '"' : ''; ?>>
                <?php if ($selected_invoice_id > 0): ?>
                    <input type="hidden" name="invoice_id" value="<?php echo esc_attr((string) $selected_invoice_id); ?>">
                <?php endif; ?>
                <input type="hidden" name="redirect_route" value="<?php echo esc_attr($redirect_route); ?>">
                <input type="hidden" name="send_receipt" value="0">

                <?php if ($selected_invoice_id <= 0): ?>
                    <div class="myvh-account-field">
                        <label for="myvh-portal-payment-invoice"><strong>Invoice</strong></label>
                        <select id="myvh-portal-payment-invoice" name="invoice_id" required>
                            <option value="">Select an invoice</option>
                            <?php foreach ($invoices as $invoice): ?>
                                <?php
                                $amount_due = isset($invoice['AmountDue'])
                                    ? max(0.0, \floatval($invoice['AmountDue']))
                                    : max(0.0, \floatval($invoice['TotalAmount'] ?? 0) - \floatval($invoice['AmountPaid'] ?? 0));
                                ?>
                                <option
                                    value="<?php echo esc_attr((string) \intval($invoice['Id'] ?? 0)); ?>"
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

                <div class="myvh-account-actions">
                    <button type="submit" class="myvh-portal-add-btn" data-send-receipt="0">
                        <span class="myvh-portal-add-btn__icon" aria-hidden="true">✓</span>
                        <span>Save Payment</span>
                    </button>
                    <button type="submit" class="myvh-portal-add-btn" data-send-receipt="1">
                        <span class="myvh-portal-add-btn__icon" aria-hidden="true">✉</span>
                        <span>Save and Send Receipt</span>
                    </button>
                </div>
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
                <div class="myvh-invoices-table-wrap myvh-recent-payments-table-wrap" data-myvh-scroll-after="10">
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
                                $payment_id = \intval($payment['Id'] ?? 0);
                                $invoice_id = \intval($payment['InvoiceId'] ?? 0);
                                $message_id = 'myvh-payment-action-message-' . $payment_id;
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
                                        <form class="myvh-inline-form" style="display:inline-block; margin-right:10px;" data-portal-action="myvh_portal_send_payment_receipt" data-message-target="<?php echo esc_attr($message_id); ?>">
                                            <input type="hidden" name="payment_id" value="<?php echo esc_attr((string) $payment_id); ?>">
                                            <input type="hidden" name="invoice_id" value="<?php echo esc_attr((string) $invoice_id); ?>">
                                            <input type="hidden" name="redirect_route" value="<?php echo esc_attr($redirect_route); ?>">
                                            <button type="submit" class="myvh-action-icon" aria-label="Send receipt" title="Send receipt" style="background:none; border:none; padding:0; margin:0; cursor:pointer;">📧</button>
                                        </form>
                                        <form class="myvh-inline-form" style="display:inline-block;" data-portal-action="myvh_portal_delete_payment" data-message-target="<?php echo esc_attr($message_id); ?>" data-confirm="Delete this payment?">
                                            <input type="hidden" name="payment_id" value="<?php echo esc_attr((string) $payment_id); ?>">
                                            <input type="hidden" name="invoice_id" value="<?php echo esc_attr((string) $invoice_id); ?>">
                                            <input type="hidden" name="redirect_route" value="<?php echo esc_attr($redirect_route); ?>">
                                            <button type="submit" class="myvh-action-icon" aria-label="Delete payment" title="Delete payment" style="background:none; border:none; padding:0; margin:0; cursor:pointer;">🗑</button>
                                        </form>
                                        <p class="myvh-muted" id="<?php echo esc_attr($message_id); ?>"></p>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <script>
                (function () {
                    var wrapper = document.querySelector('.myvh-payments-page .myvh-recent-payments-table-wrap[data-myvh-scroll-after]');
                    if (!wrapper) {
                        return;
                    }

                    var table = wrapper.querySelector('table');
                    if (!table) {
                        return;
                    }

                    function applyScrollLimit() {
                        var rows = table.querySelectorAll('tbody tr');
                        var scrollAfter = parseInt(wrapper.getAttribute('data-myvh-scroll-after'), 10);

                        wrapper.style.maxHeight = '';
                        wrapper.style.overflowY = '';

                        if (!scrollAfter || rows.length <= scrollAfter) {
                            return;
                        }

                        var maxHeight = 0;
                        var header = table.querySelector('thead');
                        if (header) {
                            maxHeight += header.getBoundingClientRect().height;
                        }

                        for (var i = 0; i < scrollAfter; i++) {
                            maxHeight += rows[i].getBoundingClientRect().height;
                        }

                        wrapper.style.maxHeight = Math.ceil(maxHeight + 2) + 'px';
                        wrapper.style.overflowY = 'auto';
                    }

                    applyScrollLimit();
                    window.addEventListener('resize', applyScrollLimit);
                })();
                </script>
            <?php endif; ?>
        </div>
    </div>
</div>
