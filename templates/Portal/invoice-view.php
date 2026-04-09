<?php
if (!defined('ABSPATH')) {
    exit;
}

$invoice_bookings = $invoice['BookingsSummary'] ?? [];
$available_statuses = $available_statuses ?? [];
?>

<div class="myvh-dashboard-section">
    <div class="myvh-account-header">
        <div>
            <h2>View Invoice</h2>
            <p>Review invoice details, related bookings, and current status.</p>
        </div>
        <a href="#invoices" class="myvh-button">Back to Invoices</a>
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
                        <p><strong>Status:</strong> <span class="myvh-status-badge myvh-status-<?php echo esc_attr($invoice['Status'] ?? 'draft'); ?>" data-invoice-status-badge><?php echo esc_html(ucfirst($invoice['Status'] ?? 'draft')); ?></span></p>
                        <p><strong>Current status:</strong> <span data-current-invoice-status><?php echo esc_html(ucfirst($invoice['Status'] ?? 'draft')); ?></span></p>
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
                        <form id="myvh-portal-invoice-status-form">
                            <input type="hidden" name="invoice_id" value="<?php echo esc_attr((string) intval($invoice['Id'])); ?>">
                            <div class="myvh-account-grid">
                                <div>
                                    <label for="myvh-portal-invoice-status"><strong>Status</strong></label>
                                    <select id="myvh-portal-invoice-status" name="status">
                                        <?php foreach ($available_statuses as $status): ?>
                                            <option value="<?php echo esc_attr($status); ?>" <?php selected(($invoice['Status'] ?? '') === $status); ?>><?php echo esc_html(ucfirst($status)); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div style="align-self: end;">
                                    <button type="submit" class="myvh-button myvh-button-primary">Update Status</button>
                                </div>
                            </div>
                            <p class="myvh-muted" data-invoice-status-message></p>
                        </form>
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