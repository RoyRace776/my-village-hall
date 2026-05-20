<?php
if (!defined('ABSPATH')) {
    exit;
}

$invoice = $invoice ?? [];
$is_client_admin = !empty($is_client_admin);
$invoice_items = is_array($invoice['Items'] ?? null) ? $invoice['Items'] : [];
$invoice_bookings = $invoice['BookingsSummary'] ?? [];
$invoice_payments = $invoice['Payments'] ?? [];
$available_statuses = $available_statuses ?? [];
$has_payments = !empty($invoice_payments);
$invoice_status = (string) ($invoice['Status'] ?? 'draft');
$has_invoice_deposit = isset($invoice_service) && $invoice_service->has_deposit_items($invoice);
$invoice_deposit_total = isset($invoice_service) ? $invoice_service->get_deposit_total($invoice) : 0.0;
$invoice_status_label = isset($invoice_service)
    ? $invoice_service->get_status_label($invoice_status, $invoice)
    : ucwords(str_replace('-', ' ', $invoice_status));
$invoice_total = \floatval($invoice['TotalAmount'] ?? 0);
$invoice_paid = \floatval($invoice['AmountPaid'] ?? 0);
$invoice_due = \floatval($invoice['AmountDue'] ?? 0);
$invoice_booking_count = \intval($invoice['BookingCount'] ?? count($invoice_bookings));
$invoice_item_count = count($invoice_items);
$invoice_payment_count = count($invoice_payments);
$invoice_customer_name = !empty($invoice['CustomerName']) ? (string) $invoice['CustomerName'] : 'Invoice details';
$invoice_billing_name = (string) ($invoice['BillingOrganisationName'] ?: ($invoice['BillingName'] ?: 'Unassigned'));
$format_invoice_datetime = static function ($value): string {
    $raw_value = is_scalar($value) ? trim((string) $value) : '';
    if ($raw_value === '') {
        return 'Not available';
    }

    try {
        $utc = new \DateTimeImmutable($raw_value, new \DateTimeZone('UTC'));
        return $utc->setTimezone(wp_timezone())->format('j M Y g:i a');
    } catch (\Exception $e) {
        return 'Not available';
    }
};
$invoice_created_at = $format_invoice_datetime($invoice['Created'] ?? '');
$invoice_updated_at = $format_invoice_datetime($invoice['Updated'] ?? '');
?>

<div class="myvh-dashboard-section myvh-client-settings-page myvh-invoices-page myvh-invoice-view-page">
    <?php
        $portal_pdf_url = add_query_arg([
            'action' => 'myvh_portal_view_invoice_pdf',
            'invoice_id' => \intval($invoice['Id'] ?? 0),
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
                <a href="<?php echo esc_url($portal_pdf_url); ?>" class="button button-primary myvh-portal-add-button" target="_blank" rel="noopener noreferrer">View PDF</a>
            <?php endif; ?>
            <?php if ($is_client_admin): ?>
                <?php if (!empty($invoice['Id']) && $invoice_status !== 'draft'): ?>
                    <button type="button" class="button button-primary myvh-portal-add-button" data-invoice-email="<?php echo \intval($invoice['Id']); ?>" aria-label="Resend invoice to customer" title="Resend invoice to customer">Resend Invoice</button>
                <?php endif; ?>
                <?php if ($invoice_status !== 'cancelled'): ?>
                    <a href="#payments?invoice_id=<?php echo \intval($invoice['Id'] ?? 0); ?>" class="button button-primary myvh-portal-add-button">Payments</a>
                <?php endif; ?>
            <?php endif; ?>
            <a href="#invoices" class="button button-primary myvh-portal-add-button">Back to Invoices</a>
        </div>
    </div>

    <div class="myvh-surface-panel myvh-bookings-panel">
        <?php if (empty($invoice)): ?>
            <div class="myvh-card">
                <p>Invoice not found or you do not have permission to view it.</p>
            </div>
        <?php else: ?>
            <div class="myvh-card myvh-account-card myvh-invoice-view-hero">
                <div class="myvh-invoice-view-hero__header">
                    <div>
                        <span class="myvh-invoice-view-kicker">Invoice <?php echo esc_html((string) ($invoice['InvoiceNumber'] ?? '')); ?></span>
                        <h3><?php echo esc_html($invoice_customer_name); ?></h3>
                        <p>Review the financial summary, status history, and any linked bookings below.</p>
                    </div>
                    <span class="myvh-status-badge myvh-status-<?php echo esc_attr($invoice_status); ?>" data-invoice-status-badge><?php echo esc_html($invoice_status_label); ?></span>
                </div>

                <?php if ($has_invoice_deposit): ?>
                    <p><strong>Deposit included:</strong> Yes (<?php echo '£' . esc_html(number_format($invoice_deposit_total, 2)); ?>)</p>
                <?php endif; ?>

                <div class="myvh-invoice-view-stats">
                    <div class="myvh-invoice-view-stat">
                        <span>Status</span>
                        <strong data-current-invoice-status><?php echo esc_html($invoice_status_label); ?></strong>
                    </div>
                    <div class="myvh-invoice-view-stat">
                        <span>Invoice date</span>
                        <strong><?php echo esc_html(date('j M Y', strtotime((string) ($invoice['InvoiceDate'] ?? 'now')))); ?></strong>
                    </div>
                    <div class="myvh-invoice-view-stat">
                        <span>Due date</span>
                        <strong><?php echo esc_html(date('j M Y', strtotime((string) ($invoice['DueDate'] ?? 'now')))); ?></strong>
                    </div>
                    <div class="myvh-invoice-view-stat">
                        <span>Total</span>
                        <strong>£<?php echo number_format($invoice_total, 2); ?></strong>
                    </div>
                    <div class="myvh-invoice-view-stat">
                        <span>Paid</span>
                        <strong>£<?php echo number_format($invoice_paid, 2); ?></strong>
                    </div>
                    <div class="myvh-invoice-view-stat">
                        <span>Due</span>
                        <strong>£<?php echo number_format($invoice_due, 2); ?></strong>
                    </div>
                </div>
            </div>

            <div class="myvh-card myvh-account-card">
                <div class="myvh-account-card-head">
                    <h3>Invoice Details</h3>
                    <span><?php echo !empty($invoice['CustomerName']) ? 'For ' . esc_html($invoice['CustomerName']) : 'Invoice details'; ?></span>
                </div>

                <div class="myvh-account-grid">
                    <div class="myvh-account-card">
                        <div class="myvh-account-card-head">
                            <h3>Status &amp; Dates</h3>
                        </div>
                        <p><strong>Status:</strong> <span class="myvh-status-badge myvh-status-<?php echo esc_attr($invoice_status); ?>" data-invoice-status-badge><?php echo esc_html($invoice_status_label); ?></span></p>
                        <p><strong>Current status:</strong> <span data-current-invoice-status><?php echo esc_html($invoice_status_label); ?></span></p>
                        <p><strong>Invoice date:</strong> <?php echo esc_html(date('j M Y', strtotime((string) ($invoice['InvoiceDate'] ?? 'now')))); ?></p>
                        <p><strong>Due date:</strong> <?php echo esc_html(date('j M Y', strtotime((string) ($invoice['DueDate'] ?? 'now')))); ?></p>
                        <p><strong>Created:</strong> <?php echo esc_html($invoice_created_at); ?></p>
                        <p><strong>Last updated:</strong> <span data-invoice-updated-at><?php echo esc_html($invoice_updated_at); ?></span></p>
                    </div>

                    <div class="myvh-account-card">
                        <div class="myvh-account-card-head">
                            <h3>Billing</h3>
                        </div>
                        <p><strong>Billed to:</strong> <?php echo esc_html($invoice_billing_name); ?></p>
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
                        <p><strong>Total:</strong> £<?php echo number_format($invoice_total, 2); ?></p>
                        <p><strong>Paid:</strong> £<?php echo number_format($invoice_paid, 2); ?></p>
                        <p><strong>Due:</strong> £<?php echo number_format($invoice_due, 2); ?></p>
                        <?php if ($has_invoice_deposit): ?>
                            <p><strong>Deposit(s):</strong> £<?php echo number_format($invoice_deposit_total, 2); ?></p>
                        <?php endif; ?>
                        <p><strong>Bookings linked:</strong> <?php echo esc_html((string) $invoice_booking_count); ?></p>
                    </div>

                    <?php if ($is_client_admin): ?>
                        <div class="myvh-account-card myvh-invoice-view-section">
                            <div class="myvh-account-card-head">
                                <h3>Amend Status</h3>
                            </div>
                            <?php if ($has_payments): ?>
                                <p>Status is managed by recorded payments for this invoice.</p>
                            <?php else: ?>
                                <form id="myvh-portal-invoice-status-form">
                                    <input type="hidden" name="invoice_id" value="<?php echo esc_attr((string) \intval($invoice['Id'])); ?>">
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
                                            <button type="submit" class="button button-primary">Update Status</button>
                                        </div>
                                    </div>
                                    <p class="myvh-muted" data-invoice-status-message></p>
                                </form>
                            <?php endif; ?>
                        </div>

                        <?php if ($has_invoice_deposit && $invoice_status === 'paid'): ?>
                            <div class="myvh-account-card myvh-invoice-view-section">
                                <div class="myvh-account-card-head">
                                    <h3>Settle Deposit</h3>
                                </div>
                                <p>Mark the deposit as retained or refunded. Refunded creates a negative refund payment entry.</p>
                                <?php $deposit_settle_message_id = 'myvh-portal-deposit-settle-message-' . \intval($invoice['Id'] ?? 0); ?>
                                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                    <form class="myvh-inline-form" data-portal-action="myvh_portal_settle_invoice_deposit" data-message-target="<?php echo esc_attr($deposit_settle_message_id); ?>" data-confirm="Mark this deposit as retained?" data-reload-page="invoice-view?invoice_id=<?php echo \intval($invoice['Id'] ?? 0); ?>">
                                        <input type="hidden" name="invoice_id" value="<?php echo esc_attr((string) \intval($invoice['Id'] ?? 0)); ?>">
                                        <input type="hidden" name="deposit_outcome" value="retained">
                                        <button type="submit" class="button">Mark Retained</button>
                                    </form>

                                    <form class="myvh-inline-form" data-portal-action="myvh_portal_settle_invoice_deposit" data-message-target="<?php echo esc_attr($deposit_settle_message_id); ?>" data-confirm="Mark this deposit as refunded? This will create a negative refund payment entry." data-reload-page="invoice-view?invoice_id=<?php echo \intval($invoice['Id'] ?? 0); ?>">
                                        <input type="hidden" name="invoice_id" value="<?php echo esc_attr((string) \intval($invoice['Id'] ?? 0)); ?>">
                                        <input type="hidden" name="deposit_outcome" value="refunded">
                                        <button type="submit" class="button button-secondary">Mark Refunded</button>
                                    </form>
                                </div>
                                <p class="myvh-muted" id="<?php echo esc_attr($deposit_settle_message_id); ?>"></p>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <div class="myvh-account-card myvh-invoice-view-section">
                    <div class="myvh-account-card-head">
                        <h3>Payments</h3>
                        <span><?php echo esc_html((string) $invoice_payment_count); ?> payment<?php echo $invoice_payment_count === 1 ? '' : 's'; ?></span>
                    </div>

                    <?php if (empty($invoice_payments)): ?>
                        <div class="myvh-invoices-empty-state myvh-invoice-view-empty-state">
                            <p class="myvh-invoices-empty-state__title">No payments recorded yet.</p>
                            <p>This invoice has not had any payments logged against it.</p>
                        </div>
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
                                        <?php $payment_message_id = 'myvh-portal-payment-delete-message-' . \intval($payment['Id'] ?? 0); ?>
                                        <tr>
                                            <td><?php echo esc_html(date('j M Y', strtotime((string) ($payment['PaymentDate'] ?? 'now')))); ?></td>
                                            <td><?php echo esc_html(ucwords((string) ($payment['PaymentMethod'] ?? 'other'))); ?></td>
                                            <td class="myvh-amount">£<?php echo number_format(floatval($payment['Amount'] ?? 0), 2); ?></td>
                                            <td><?php echo esc_html($payment['TransactionReference'] ?? ''); ?></td>
                                            <td><?php echo esc_html($payment['Notes'] ?? ''); ?></td>
                                            <?php if ($is_client_admin): ?>
                                                <td>
                                                    <form class="myvh-inline-form" data-portal-action="myvh_portal_delete_payment" data-message-target="<?php echo esc_attr($payment_message_id); ?>" data-confirm="Delete this payment?">
                                                        <input type="hidden" name="payment_id" value="<?php echo esc_attr((string) \intval($payment['Id'] ?? 0)); ?>">
                                                        <input type="hidden" name="invoice_id" value="<?php echo esc_attr((string) \intval($invoice['Id'] ?? 0)); ?>">
                                                        <input type="hidden" name="redirect_route" value="invoice-view?invoice_id=<?php echo \intval($invoice['Id'] ?? 0); ?>">
                                                        <button type="submit" class="myvh-action-icon" aria-label="Delete payment" title="Delete payment" style="background:none; border:none; padding:0; margin:0; cursor:pointer;">
                                                            <span class="dashicons dashicons-trash" aria-hidden="true"></span>
                                                        </button>
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
                    <div class="myvh-account-card myvh-invoice-view-section">
                        <div class="myvh-account-card-head">
                            <h3>Add Payment</h3>
                        </div>
                        <?php if ($invoice_status === 'cancelled'): ?>
                            <p>Reopen this invoice before recording payments.</p>
                        <?php elseif ($invoice_due <= 0): ?>
                            <p>This invoice has already been fully paid.</p>
                        <?php else: ?>
                            <form class="myvh-account-form myvh-invoice-payment-form" data-portal-action="myvh_portal_create_payment" data-message-target="myvh-portal-payment-create-message">
                                <input type="hidden" name="invoice_id" value="<?php echo esc_attr((string) \intval($invoice['Id'] ?? 0)); ?>">
                                <input type="hidden" name="redirect_route" value="invoice-view?invoice_id=<?php echo \intval($invoice['Id'] ?? 0); ?>">
                                <div class="myvh-account-grid myvh-invoice-payment-grid">
                                    <div class="myvh-account-field">
                                        <label for="myvh-portal-payment-date"><strong>Payment Date</strong></label>
                                        <input id="myvh-portal-payment-date" type="text" name="payment_date" data-myvh-picker="date" autocomplete="off" value="<?php echo esc_attr(current_time('Y-m-d')); ?>" required>
                                    </div>
                                    <div class="myvh-account-field">
                                        <label for="myvh-portal-payment-amount"><strong>Amount</strong></label>
                                        <input id="myvh-portal-payment-amount" type="number" name="payment_amount" min="0.01" step="0.01" required>
                                    </div>
                                    <div class="myvh-account-field">
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
                                    <div class="myvh-account-field">
                                        <label for="myvh-portal-payment-reference"><strong>Reference</strong></label>
                                        <input id="myvh-portal-payment-reference" type="text" name="payment_reference">
                                    </div>
                                    <div class="myvh-account-field myvh-invoice-payment-field--comment">
                                        <label for="myvh-portal-payment-comment"><strong>Comment</strong></label>
                                        <textarea id="myvh-portal-payment-comment" name="payment_comment" rows="3"></textarea>
                                    </div>
                                </div>
                                <button type="submit" class="button button-primary myvh-portal-add-button">Save Payment</button>
                                <p class="myvh-muted" id="myvh-portal-payment-create-message"></p>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="myvh-account-card myvh-invoice-view-section">
                    <div class="myvh-account-card-head">
                        <h3>Invoice line items</h3>
                        <span><?php echo esc_html((string) $invoice_item_count); ?> line item<?php echo $invoice_item_count === 1 ? '' : 's'; ?></span>
                    </div>

                    <?php if (empty($invoice_items)): ?>
                        <div class="myvh-invoices-empty-state myvh-invoice-view-empty-state">
                            <p class="myvh-invoices-empty-state__title">No invoice line items found.</p>
                            <p>This invoice does not currently have any line items attached to it.</p>
                        </div>
                    <?php else: ?>
                        <div class="myvh-invoices-table-wrap">
                            <table class="myvh-customer-list-table myvh-invoices-table">
                                <thead>
                                    <tr>
                                        <th>Booking ID</th>
                                        <th>Type</th>
                                        <th>Description</th>
                                        <th class="myvh-invoices-table__amount">Qty</th>
                                        <th class="myvh-invoices-table__amount">Unit Price</th>
                                        <th class="myvh-invoices-table__amount">Line Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($invoice_items as $item): ?>
                                        <?php
                                            $booking_id = \intval($item['BookingId'] ?? 0);
                                            $quantity = (float) ($item['Quantity'] ?? 0);
                                            $unit_price = (float) ($item['UnitPrice'] ?? 0);
                                            $line_total = (float) ($item['TotalAmount'] ?? 0);
                                        ?>
                                        <tr>
                                            <td>
                                                <?php if ($booking_id > 0): ?>
                                                    <a href="#booking-view?booking_id=<?php echo $booking_id; ?>">#<?php echo $booking_id; ?></a>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php echo esc_html(ucwords(str_replace('-', ' ', (string) ($item['ItemType'] ?? 'charge')))); ?>
                                            </td>
                                            <td><?php echo esc_html((string) ($item['Description'] ?? '-')); ?></td>
                                            <td class="myvh-amount"><?php echo number_format($quantity, 2); ?></td>
                                            <td class="myvh-amount">£<?php echo number_format($unit_price, 2); ?></td>
                                            <td class="myvh-amount">£<?php echo number_format($line_total, 2); ?></td>
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