<?php
if (!defined('ABSPATH')) {
    exit;
}

$is_client_admin_view = !empty($is_client_admin);
?>

<div class="myvh-dashboard-section myvh-client-settings-page myvh-invoices-page">
    <div class="myvh-account-header myvh-settings-header">
        <div>
            <h2>Invoices</h2>
            <p>Manage invoice creation and review invoice statuses for your customers and organisations.</p>
        </div>
    </div>


        <?php if ($is_client_admin_view): ?>
            <div class="myvh-settings-tabs myvh-invoices-tabs" role="tablist" aria-label="Invoice actions">
                <button type="button" class="myvh-settings-tab myvh-invoices-tab is-active" role="tab" aria-selected="true" data-invoices-tab="create">Create Invoices</button>
                <button type="button" class="myvh-settings-tab myvh-invoices-tab" role="tab" aria-selected="false" data-invoices-tab="by-customer">By Customer</button>
                <button type="button" class="myvh-settings-tab myvh-invoices-tab" role="tab" aria-selected="false" data-invoices-tab="by-organisation">By Organisation</button>
                <button type="button" class="myvh-settings-tab myvh-invoices-tab" role="tab" aria-selected="false" data-invoices-tab="list">Invoice List</button>
            </div>
        <?php endif; ?>
        <?php if ($is_client_admin_view): ?>
            <div class="myvh-card myvh-account-card myvh-settings-group myvh-invoices-panel" data-invoices-panel="by-customer" hidden>
                <div class="myvh-section-header" style="margin-bottom: 12px;">
                    <h3>Uninvoiced Bookings by Customer</h3>
                </div>
                <?php if (!empty($uninvoiced_by_customer)): ?>
                    <table class="myvh-invoices-table">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Email</th>
                                <th>Uninvoiced Bookings</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($uninvoiced_by_customer as $customer): ?>
                                <tr>
                                    <td><?php echo esc_html($customer['CustomerName'] ?? 'Unknown'); ?></td>
                                    <td><?php echo esc_html($customer['CustomerEmail'] ?? '-'); ?></td>
                                    <td><?php echo intval($customer['UninvoicedCount']); ?></td>
                                    <td><button type="button" class="myvh-drilldown-btn" data-customer-id="<?php echo intval($customer['CustomerId']); ?>">View Bookings</button></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No customers with uninvoiced bookings found.</p>
                <?php endif; ?>
                <div id="myvh-customer-drilldown" style="margin-top:20px;"></div>
            </div>
            <div class="myvh-card myvh-account-card myvh-settings-group myvh-invoices-panel" data-invoices-panel="by-organisation" hidden>
                <div class="myvh-section-header" style="margin-bottom: 12px;">
                    <h3>Uninvoiced Bookings by Organisation</h3>
                </div>
                <?php if (!empty($uninvoiced_by_organisation)): ?>
                    <table class="myvh-invoices-table">
                        <thead>
                            <tr>
                                <th>Organisation</th>
                                <th>Uninvoiced Bookings</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($uninvoiced_by_organisation as $org): ?>
                                <tr>
                                    <td><?php echo esc_html($org['OrganisationName'] ?? 'Unknown'); ?></td>
                                    <td><?php echo intval($org['UninvoicedCount']); ?></td>
                                    <td><button type="button" class="myvh-drilldown-btn" data-organisation-id="<?php echo intval($org['OrganisationId']); ?>">View Bookings</button></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No organisations with uninvoiced bookings found.</p>
                <?php endif; ?>
                <div id="myvh-organisation-drilldown" style="margin-top:20px;"></div>
            </div>
        <?php endif; ?>

        <div class="myvh-account-grid myvh-settings-groups myvh-settings-panels">

        <?php if ($is_client_admin_view): ?>
            <div class="myvh-card myvh-account-card myvh-settings-group myvh-invoices-panel is-active" data-invoices-panel="create">
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
            </div>
        <?php endif; ?>

        <div class="myvh-card myvh-account-card myvh-settings-group myvh-invoices-panel" data-invoices-panel="list" hidden>
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

                                        if (!empty($invoice['BillingName'])) {
                                            echo '<br><small>' . esc_html($invoice['BillingName']) . '</small>';
                                        }

                                        if (!empty($invoice['BillingReference'])) {
                                            echo '<br><small>Ref: ' . esc_html($invoice['BillingReference']) . '</small>';
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
