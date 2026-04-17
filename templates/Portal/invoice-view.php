<?php
if (!defined('ABSPATH')) {
    exit;
}

$invoice_bookings = $invoice['BookingsSummary'] ?? [];
$invoice_payments = $invoice['Payments'] ?? [];
$available_statuses = $available_statuses ?? [];
$has_payments = !empty($invoice_payments);
?>

<div class="myvh-dashboard-section">
    <?php
        $portal_pdf_url = add_query_arg([
            'action' => 'myvh_portal_view_invoice_pdf',
            'invoice_id' => intval($invoice['Id'] ?? 0),
            'nonce' => wp_create_nonce('myvh_portal'),
        ], admin_url('admin-ajax.php'));
    ?>
    <div class="myvh-account-header">
        <div>
            <h2>View Invoice</h2>
            <p>Review invoice details, related bookings, and current status.</p>
        </div>
        <div>
            <?php if (!empty($invoice['Id'])): ?>
                <a href="<?php echo esc_url($portal_pdf_url); ?>" class="myvh-button" target="_blank" rel="noopener noreferrer">View PDF</a>
            <?php endif; ?>
            <?php if ($is_client_admin): ?>
                <?php if (($invoice['Status'] ?? '') !== 'cancelled'): ?>
                    <a href="#payments?invoice_id=<?php echo intval($invoice['Id'] ?? 0); ?>" class="myvh-button">Payments</a>
                <?php endif; ?>
            <?php endif; ?>
            <a href="#invoices" class="myvh-button">Back to Invoices</a>
        </div>
    </div>

    <div class="myvh-surface-panel myvh-bookings-panel">
        <?php if (empty($invoice)): ?>
            <div class="myvh-card">
                <p>Invoice not found or you do not have permission to view it.</p>
            </div>
        <?php else: ?>
            <div class="myvh-card myvh-account-card">
                <div class="myvh-account-card-head">
                    <h3><?php echo esc_html($invoice['InvoiceNumber'] ?? 'Invoice'); ?></h3>
                    <span><?php echo !empty($invoice['CustomerName']) ? 'For ' . esc_html($invoice['CustomerName']) : 'Invoice details'; ?></span>
                </div>

                <div class="myvh-account-grid">
                    <div class="myvh-account-card">
                        <div class="myvh-account-card-head">
                            <h3>Status &amp; Dates</h3>
                        </div>
                        <p><strong>Status:</strong> <span class="myvh-status-badge myvh-status-<?php echo esc_attr($invoice['Status'] ?? 'draft'); ?>" data-invoice-status-badge><?php echo esc_html(ucwords(str_replace('-', ' ', (string) ($invoice['Status'] ?? 'draft')))); ?></span></p>
                        <p><strong>Current status:</strong> <span data-current-invoice-status><?php echo esc_html(ucwords(str_replace('-', ' ', (string) ($invoice['Status'] ?? 'draft')))); ?></span></p>
                        <p><strong>Invoice date:</strong> <?php echo esc_html(date('j M Y', strtotime((string) ($invoice['InvoiceDate'] ?? 'now')))); ?></p>
                        <p><strong>Due date:</strong> <?php echo esc_html(date('j M Y', strtotime((string) ($invoice['DueDate'] ?? 'now')))); ?></p>
                    </div>

                    <div class="myvh-account-card">
                        <div class="myvh-account-card-head">
                            <h3>Billing</h3>
                        </div>
                        <p><strong>Billed to:</strong> <?php echo esc_html($invoice['BillingOrganisationName'] ?: ($invoice['BillingName'] ?: 'Unassigned')); ?></p>
                        <?php if (!empty($invoice['BillingEmail'])): ?>
                            <p><strong>Email:</strong> <?php echo esc_html($invoice['BillingEmail']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($invoice['BillingReference'])): ?>
                            <p><strong>Reference:</strong> <?php echo esc_html($invoice['BillingReference']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($invoice['BillingAddressLine1']) || !empty($invoice['BillingTownCity']) || !empty($invoice['BillingPostcode'])): ?>
                            <p><strong>Address:</strong> <?php echo esc_html(trim(implode(', ', array_filter([
                                $invoice['BillingAddressLine1'] ?? '',
                                $invoice['BillingAddressLine2'] ?? '',
                                $invoice['BillingTownCity'] ?? '',
                                $invoice['BillingPostcode'] ?? '',
                            ])), ', ')); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($invoice['OrganisationName'])): ?>
                            <p><strong>Organisation:</strong> <?php echo esc_html($invoice['OrganisationName']); ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="myvh-account-card">
                        <div class="myvh-account-card-head">
                            <h3>Amounts</h3>
                        </div>
                        <p><strong>Total:</strong> £<?php echo number_format(floatval($invoice['TotalAmount'] ?? 0), 2); ?></p>
                        <p><strong>Paid:</strong> £<?php echo number_format(floatval($invoice['AmountPaid'] ?? 0), 2); ?></p>
                        <p><strong>Due:</strong> £<?php echo number_format(floatval($invoice['AmountDue'] ?? 0), 2); ?></p>
                        <p><strong>Bookings linked:</strong> <?php echo intval($invoice['BookingCount'] ?? count($invoice_bookings)); ?></p>
                    </div>
                </div>

                <?php if ($is_client_admin): ?>
                    <div class="myvh-account-card" style="margin-top: 16px;">
                        <div class="myvh-account-card-head">
                            <h3>Amend Status</h3>
                        </div>
                        <?php if ($has_payments): ?>
                            <p>Status is managed by recorded payments for this invoice.</p>
                        <?php else: ?>
                            <form id="myvh-portal-invoice-status-form">
                                <input type="hidden" name="invoice_id" value="<?php echo esc_attr((string) intval($invoice['Id'])); ?>">
                                <div class="myvh-account-grid">
                                    <div>
                                        <label for="myvh-portal-invoice-status"><strong>Status</strong></label>
                                        <select id="myvh-portal-invoice-status" name="status">
                                            <?php foreach ($available_statuses as $status): ?>
                                                <?php if (in_array($status, ['draft', 'sent', 'overdue', 'cancelled'], true)): ?>
                                                    <option value="<?php echo esc_attr($status); ?>" <?php selected(($invoice['Status'] ?? '') === $status); ?>><?php echo esc_html(ucwords(str_replace('-', ' ', $status))); ?></option>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div style="align-self: end;">
                                        <button type="submit" class="myvh-button myvh-button-primary">Update Status</button>
                                    </div>
                                </div>
                                <p class="myvh-muted" data-invoice-status-message></p>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="myvh-account-card" style="margin-top: 16px;">
                    <div class="myvh-account-card-head">
                        <h3>Payments</h3>
                        <span><?php echo count($invoice_payments); ?> payment<?php echo count($invoice_payments) === 1 ? '' : 's'; ?></span>
                    </div>

                    <?php if (empty($invoice_payments)): ?>
                        <p>No payments have been recorded for this invoice.</p>
                    <?php else: ?>
                        <div class="myvh-invoices-table-wrap">
                            <table class="myvh-customer-list-table myvh-invoices-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th class="myvh-invoices-table__amount">Amount</th>
                                        <th>Reference</th>
                                        <th>Comment</th>
                                        <?php if ($is_client_admin): ?>
                                            <th>Actions</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($invoice_payments as $payment): ?>
                                        <?php $payment_message_id = 'myvh-portal-payment-delete-message-' . intval($payment['Id'] ?? 0); ?>
                                        <tr>
                                            <td><?php echo esc_html(date('j M Y', strtotime((string) ($payment['PaymentDate'] ?? 'now')))); ?></td>
                                            <td><?php echo esc_html(ucwords((string) ($payment['PaymentMethod'] ?? 'other'))); ?></td>
                                            <td class="myvh-amount">£<?php echo number_format(floatval($payment['Amount'] ?? 0), 2); ?></td>
                                            <td><?php echo esc_html($payment['TransactionReference'] ?? ''); ?></td>
                                            <td><?php echo esc_html($payment['Notes'] ?? ''); ?></td>
                                            <?php if ($is_client_admin): ?>
                                                <td>
                                                    <form class="myvh-inline-form" data-portal-action="myvh_portal_delete_payment" data-message-target="<?php echo esc_attr($payment_message_id); ?>" data-confirm="Delete this payment?">
                                                        <input type="hidden" name="payment_id" value="<?php echo esc_attr((string) intval($payment['Id'] ?? 0)); ?>">
                                                        <input type="hidden" name="invoice_id" value="<?php echo esc_attr((string) intval($invoice['Id'] ?? 0)); ?>">
                                                        <input type="hidden" name="redirect_route" value="invoice-view?invoice_id=<?php echo intval($invoice['Id'] ?? 0); ?>">
                                                        <button type="submit" class="myvh-button myvh-button-small">Delete</button>
                                                    </form>
                                                    <p class="myvh-muted" id="<?php echo esc_attr($payment_message_id); ?>"></p>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($is_client_admin): ?>
                    <div class="myvh-account-card" style="margin-top: 16px;">
                        <div class="myvh-account-card-head">
                            <h3>Add Payment</h3>
                        </div>
                        <?php if (($invoice['Status'] ?? '') === 'cancelled'): ?>
                            <p>Reopen this invoice before recording payments.</p>
                        <?php elseif (floatval($invoice['AmountDue'] ?? 0) <= 0): ?>
                            <p>This invoice has already been fully paid.</p>
                        <?php else: ?>
                            <form class="myvh-account-form" data-portal-action="myvh_portal_create_payment" data-message-target="myvh-portal-payment-create-message">
                                <input type="hidden" name="invoice_id" value="<?php echo esc_attr((string) intval($invoice['Id'] ?? 0)); ?>">
                                <input type="hidden" name="redirect_route" value="invoice-view?invoice_id=<?php echo intval($invoice['Id'] ?? 0); ?>">
                                <div class="myvh-account-grid">
                                    <div>
                                        <label for="myvh-portal-payment-date"><strong>Payment Date</strong></label>
                                        <input id="myvh-portal-payment-date" type="text" name="payment_date" data-myvh-picker="date" autocomplete="off" value="<?php echo esc_attr(current_time('Y-m-d')); ?>" required>
                                    </div>
                                    <div>
                                        <label for="myvh-portal-payment-amount"><strong>Amount</strong></label>
                                        <input id="myvh-portal-payment-amount" type="number" name="payment_amount" min="0.01" step="0.01" required>
                                    </div>
                                    <div>
                                        <label for="myvh-portal-payment-method"><strong>Payment Type</strong></label>
                                        <select id="myvh-portal-payment-method" name="payment_method" required>
                                            <option value="">Select a payment type</option>
                                            <option value="cash">Cash</option>
                                            <option value="card">Card</option>
                                            <option value="cheque">Cheque</option>
                                            <option value="transfer">Transfer</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="myvh-portal-payment-reference"><strong>Reference</strong></label>
                                        <input id="myvh-portal-payment-reference" type="text" name="payment_reference">
                                    </div>
                                </div>
                                <p>
                                    <label for="myvh-portal-payment-comment"><strong>Comment</strong></label>
                                </p>
                                <p>
                                    <textarea id="myvh-portal-payment-comment" name="payment_comment" rows="4"></textarea>
                                </p>
                                <button type="submit" class="myvh-button myvh-button-primary">Save Payment</button>
                                <p class="myvh-muted" id="myvh-portal-payment-create-message"></p>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="myvh-account-card" style="margin-top: 16px;">
                    <div class="myvh-account-card-head">
                        <h3>Related Bookings</h3>
                        <span><?php echo count($invoice_bookings); ?> booking<?php echo count($invoice_bookings) === 1 ? '' : 's'; ?></span>
                    </div>

                    <?php if (empty($invoice_bookings)): ?>
                        <p>No bookings were found on this invoice.</p>
                    <?php else: ?>
                        <div class="myvh-invoices-table-wrap">
                            <table class="myvh-customer-list-table myvh-invoices-table">
                                <thead>
                                    <tr>
                                        <th>Booking</th>
                                        <th>Description</th>
                                        <th>Date</th>
                                        <th>Room</th>
                                        <th>Organisation</th>
                                        <th class="myvh-invoices-table__amount">Line Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($invoice_bookings as $booking): ?>
                                        <tr>
                                            <td>
                                                <a href="#booking-view?booking_id=<?php echo intval($booking['BookingId']); ?>">#<?php echo intval($booking['BookingId']); ?></a>
                                            </td>
                                            <td>
                                                <strong><?php echo esc_html($booking['Description'] ?? ''); ?></strong>
                                            </td>
                                            <td>
                                                <?php if (!empty($booking['StartDate'])): ?>
                                                    <?php echo esc_html(date('j M Y', strtotime((string) $booking['StartDate']))); ?>
                                                    <?php if (!empty($booking['StartTime']) && !empty($booking['EndTime'])): ?>
                                                        <span class="myvh-invoice-meta"><?php echo esc_html(date('H:i', strtotime((string) $booking['StartTime'])) . ' - ' . date('H:i', strtotime((string) $booking['EndTime']))); ?></span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo esc_html($booking['RoomName'] ?? '-'); ?></td>
                                            <td><?php echo esc_html($booking['OrganisationName'] ?? '-'); ?></td>
                                            <td class="myvh-amount">£<?php echo number_format(floatval($booking['TotalAmount'] ?? 0), 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>