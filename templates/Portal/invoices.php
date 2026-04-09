<?php
if (!defined('ABSPATH')) {
    exit;
}

$is_client_admin_view = !empty($is_client_admin);
$invoice_count = is_array($invoices ?? null) ? count($invoices) : 0;
?>

<div class="myvh-dashboard-section myvh-client-settings-page myvh-invoices-page">
    <div class="myvh-account-header">
        <div>
            <h2><?php echo $is_client_admin_view ? 'Generated Invoices' : 'Your Invoices'; ?></h2>
            <p><?php echo $is_client_admin_view
                ? 'Review generated invoices, statuses, and outstanding balances across this site.'
                : 'Review invoice statuses, balances, and due dates for your account.'; ?></p>
        </div>
        <?php if ($is_client_admin_view): ?>
            <a href="#invoice-generate" class="myvh-portal-add-btn">
                <span class="myvh-portal-add-btn__icon" aria-hidden="true">+</span>
                <span>Generate Invoices</span>
            </a>
        <?php endif; ?>
    </div>
    <div class="myvh-card myvh-account-card myvh-invoices-card">
        <div class="myvh-account-card-head">
            <div>
                <h3><?php echo $is_client_admin_view ? 'Invoice List' : 'Your Invoice List'; ?></h3>
                <span><?php echo esc_html((string) $invoice_count); ?> <?php echo 1 === $invoice_count ? 'invoice record' : 'invoice records'; ?></span>
            </div>
        </div>

        <div class="myvh-invoice-filter-section">
            <form id="myvh-invoice-filter-form" class="myvh-invoice-filter-form">
                <div class="myvh-invoice-filter-group">
                    <span class="myvh-invoice-filter-label">Filter by status</span>
                    <div class="myvh-checkbox-group">
                        <?php foreach ($available_statuses as $status): ?>
                            <label class="myvh-checkbox-label">
                                <input type="checkbox"
                                       name="statuses[]"
                                       value="<?php echo esc_attr($status); ?>"
                                       <?php checked(in_array($status, $selected_statuses, true)); ?>>
                                <span><?php echo ucfirst(esc_html($status)); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <button type="submit" class="myvh-invoice-filter-submit">Apply Filter</button>
            </form>
        </div>

        <?php if (!empty($invoices)): ?>
            <div class="myvh-invoices-table-wrap">
                <table class="myvh-customer-list-table myvh-invoices-table">
                    <thead>
                        <tr>
                            <th>Invoice Number</th>
                            <?php if ($is_client_admin_view): ?>
                                <th>Customer</th>
                            <?php endif; ?>
                            <th>Date</th>
                            <th>Billing</th>
                            <th class="myvh-invoices-table__amount">Amount</th>
                            <th class="myvh-invoices-table__amount">Paid</th>
                            <th>Due Date</th>
                            <th>Status</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoices as $invoice): ?>
                            <tr class="myvh-invoice-row">
                                <td class="myvh-invoice-number">
                                    <strong><a href="#invoice-view?invoice_id=<?php echo intval($invoice['Id']); ?>"><?php echo esc_html($invoice['InvoiceNumber']); ?></a></strong>
                                </td>
                                <?php if ($is_client_admin_view): ?>
                                    <td>
                                        <strong><?php echo esc_html($invoice['CustomerName'] ?? 'Unknown'); ?></strong>
                                        <?php if (!empty($invoice['CustomerEmail'])): ?>
                                            <span class="myvh-invoice-meta"><?php echo esc_html($invoice['CustomerEmail']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                                <td>
                                    <?php echo esc_html(date_format(date_create($invoice['InvoiceDate']), 'j M Y')); ?>
                                </td>
                                <td class="myvh-invoice-billing">
                                    <?php
                                        $invoice_type = !empty($invoice['IsPersonalInvoice']) ? 'Personal' : 'Organisation';
                                        if (!empty($invoice['OrganisationName'])) {
                                            echo esc_html($invoice['OrganisationName']);
                                            echo ' <span class="myvh-badge myvh-badge-org">Org</span>';
                                        } else {
                                            echo esc_html($invoice_type);
                                        }

                                        if (!empty($invoice['BillingName'])) {
                                            echo '<span class="myvh-invoice-meta">' . esc_html($invoice['BillingName']) . '</span>';
                                        }

                                        if (!empty($invoice['BillingReference'])) {
                                            echo '<span class="myvh-invoice-meta">Ref: ' . esc_html($invoice['BillingReference']) . '</span>';
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
                                <td>
                                    <a href="#invoice-view?invoice_id=<?php echo intval($invoice['Id']); ?>" class="myvh-button myvh-button-small">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="myvh-empty-state myvh-invoices-empty-state">
                <p class="myvh-invoices-empty-state__title">No invoices found.</p>
                <p><?php echo !empty($selected_statuses)
                    ? 'Try adjusting the selected statuses to see more invoices.'
                    : 'Invoices will appear here once they have been generated for this account.'; ?></p>
            </div>
        <?php endif; ?>
        </div>
    </div>
</div>
