<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="myvh-dashboard-section">
    <div class="myvh-section-header">
        <h2>Invoices</h2>
    </div>

    <div class="myvh-surface-panel myvh-bookings-panel">
        <div class="myvh-card">
        <?php if (!empty($is_client_admin)): ?>
            <div class="myvh-section-header" style="margin-bottom: 12px;">
                <h3>Manual Invoice Creation</h3>
            </div>

            <form class="myvh-account-form"
                  data-portal-action="myvh_portal_create_invoice"
                  data-message-target="myvh-invoice-create-message"
                  data-reload-page="invoices">
                <p>Select uninvoiced bookings and choose how to group them into invoices.</p>

                <div class="myvh-field-grid" style="margin-bottom: 12px;">
                    <div>
                        <label for="myvh-group-by"><strong>Grouping</strong></label>
                        <select id="myvh-group-by" name="group_by" class="myvh-input">
                            <option value="per_booking">One invoice per booking</option>
                            <option value="by_customer">Group by customer</option>
                            <option value="by_organisation">Group by organisation</option>
                        </select>
                    </div>
                </div>

                <?php if (!empty($uninvoiced_bookings)): ?>
                    <div style="margin-bottom: 8px;">
                        <button type="button" class="button" id="myvh-select-all-uninvoiced">Select all</button>
                        <button type="button" class="button" id="myvh-clear-all-uninvoiced">Clear</button>
                    </div>

                    <div style="max-height: 260px; overflow: auto; border: 1px solid #d0d3d8; border-radius: 6px; padding: 8px; margin-bottom: 12px;">
                        <table class="myvh-invoices-table">
                            <thead>
                                <tr>
                                    <th>Select</th>
                                    <th>Booking</th>
                                    <th>Customer</th>
                                    <th>Organisation</th>
                                    <th>Description</th>
                                    <th>Date</th>
                                    <th>Room</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($uninvoiced_bookings as $booking): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="booking_ids[]" value="<?php echo intval($booking['Id']); ?>" class="myvh-uninvoiced-checkbox">
                                        </td>
                                        <td>#<?php echo intval($booking['Id']); ?></td>
                                        <td><?php echo esc_html($booking['CustomerName'] ?? 'Unknown'); ?></td>
                                        <td><?php echo esc_html($booking['OrganisationName'] ?? '-'); ?></td>
                                        <td><?php echo esc_html($booking['Description'] ?? '-'); ?></td>
                                        <td><?php echo esc_html(date('j M Y', strtotime((string) ($booking['StartDate'] ?? '')))); ?></td>
                                        <td><?php echo esc_html($booking['RoomName'] ?? '-'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="myvh-account-actions">
                        <button type="submit" class="myvh-button myvh-button-primary">Create Invoice(s)</button>
                    </div>
                <?php else: ?>
                    <p>No uninvoiced confirmed/completed bookings found.</p>
                <?php endif; ?>

                <p id="myvh-invoice-create-message" class="myvh-form-message" role="status" aria-live="polite"></p>
            </form>

            <hr style="margin: 16px 0;" />
        <?php endif; ?>

        <div class="myvh-section-header" style="margin-bottom: 12px;">
            <h3>Your Invoice List</h3>
        </div>

        <div class="myvh-filter-section">
            <form id="myvh-invoice-filter-form" class="myvh-filter-form">
                <div class="myvh-filter-group">
                    <label>Filter by Status:</label>
                    <div class="myvh-checkbox-group">
                        <?php foreach ($available_statuses as $status): ?>
                            <label class="myvh-checkbox-label">
                                <input type="checkbox"
                                       name="statuses[]"
                                       value="<?php echo esc_attr($status); ?>"
                                       <?php checked(in_array($status, $selected_statuses)); ?>>
                                <?php echo ucfirst(esc_html($status)); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <button type="submit" class="button">Apply Filter</button>
            </form>
        </div>

        <!-- Invoice List -->
        <?php if (!empty($invoices)): ?>
            <table class="myvh-invoices-table">
                <thead>
                    <tr>
                        <th>Invoice Number</th>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Paid</th>
                        <th>Due Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoices as $invoice): ?>
                        <tr class="myvh-invoice-row">
                            <td class="myvh-invoice-number">
                                <strong><?php echo esc_html($invoice['InvoiceNumber']); ?></strong>
                            </td>
                            <td>
                                <?php echo esc_html(date_format(date_create($invoice['InvoiceDate']), 'j M Y')); ?>
                            </td>
                            <td>
                                <?php
                                    $invoice_type = !empty($invoice['IsPersonalInvoice']) ? 'Personal' : 'Organisation';
                                    if (!empty($invoice['OrganisationName'])) {
                                        echo esc_html($invoice['OrganisationName']);
                                        echo ' <span class="myvh-badge myvh-badge-org">Org Admin</span>';
                                    } else {
                                        echo esc_html($invoice_type);
                                    }
                                ?>
                            </td>
                            <td class="myvh-amount">
                                £<?php echo number_format(floatval($invoice['TotalAmount']), 2); ?>
                            </td>
                            <td class="myvh-amount">
                                £<?php echo number_format(floatval($invoice['AmountPaid']), 2); ?>
                            </td>
                            <td>
                                <?php
                                    $due_date = date_create($invoice['DueDate']);
                                    $today = date_create('today');
                                    $due_class = '';

                                    if ($due_date < $today && $invoice['Status'] !== 'paid') {
                                        $due_class = ' class="myvh-text-danger"';
                                    }
                                ?>
                                <span<?php echo $due_class; ?>>
                                    <?php echo esc_html(date_format($due_date, 'j M Y')); ?>
                                </span>
                            </td>
                            <td>
                                <span class="myvh-status-badge myvh-status-<?php echo esc_attr($invoice['Status']); ?>">
                                    <?php echo ucfirst(esc_html($invoice['Status'])); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="myvh-empty-state">
                <p>No invoices found.</p>
                <?php if (!empty($selected_statuses)): ?>
                    <p>Try adjusting your filters to see more invoices.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        </div>
    </div>
</div>
