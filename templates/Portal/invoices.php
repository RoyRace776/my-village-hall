<?php
if (!defined('ABSPATH')) {
    exit;
}

$is_client_admin_view = !empty($is_client_admin);
?>

<div class="myvh-dashboard-section myvh-client-settings-page myvh-invoices-page">
    <div class="myvh-account-header myvh-settings-header">
        <div>
            <h2><?php echo $is_client_admin_view ? 'Generated Invoices' : 'Your Invoices'; ?></h2>
            <p><?php echo $is_client_admin_view
                ? 'Review generated invoices, statuses, and outstanding balances across this site.'
                : 'Review invoice statuses, balances, and due dates for your account.'; ?></p>
        </div>
        <?php if ($is_client_admin_view): ?>
            <div class="myvh-account-actions">
                <a href="#invoice-generate" class="myvh-button myvh-button-primary">Generate Invoices</a>
            </div>
        <?php endif; ?>
    </div>
    <div class="myvh-account-grid myvh-settings-groups myvh-settings-panels">
        <div class="myvh-card myvh-account-card myvh-settings-group is-active">
            <div class="myvh-section-header" style="margin-bottom: 12px;">
                <h3><?php echo $is_client_admin_view ? 'Invoice List' : 'Your Invoice List'; ?></h3>
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
                            <?php if ($is_client_admin_view): ?>
                                <th>Customer</th>
                            <?php endif; ?>
                            <th>Date</th>
                            <th>Billing</th>
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
                                <?php if ($is_client_admin_view): ?>
                                    <td>
                                        <strong><?php echo esc_html($invoice['CustomerName'] ?? 'Unknown'); ?></strong>
                                        <?php if (!empty($invoice['CustomerEmail'])): ?>
                                            <br><small><?php echo esc_html($invoice['CustomerEmail']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                                <td>
                                    <?php echo esc_html(date_format(date_create($invoice['InvoiceDate']), 'j M Y')); ?>
                                </td>
                                <td>
                                    <?php
                                        $invoice_type = !empty($invoice['IsPersonalInvoice']) ? 'Personal' : 'Organisation';
                                        if (!empty($invoice['OrganisationName'])) {
                                            echo esc_html($invoice['OrganisationName']);
                                            echo ' <span class="myvh-badge myvh-badge-org">Org</span>';
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
