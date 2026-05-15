<?php
if (!defined('ABSPATH')) {
    exit;
}

$is_client_admin_view = !empty($is_client_admin);
$invoice_count = isset($invoices) && is_array($invoices) ? count($invoices) : 0;
$available_statuses = isset($available_statuses) && is_array($available_statuses) ? $available_statuses : [];
$selected_statuses = isset($selected_statuses) && is_array($selected_statuses) ? $selected_statuses : [];
$selected_start_date = isset($selected_start_date) ? (string) $selected_start_date : '';
$selected_end_date = isset($selected_end_date) ? (string) $selected_end_date : '';
$quick_date_ranges = isset($quick_date_ranges) && is_array($quick_date_ranges) ? $quick_date_ranges : [];
$selected_client_name = isset($selected_client_name) ? (string) $selected_client_name : '';
$selected_client_name_match = isset($selected_client_name_match) ? (string) $selected_client_name_match : 'contains';
$selected_invoice_number = isset($selected_invoice_number) ? (string) $selected_invoice_number : '';
$selected_invoice_number_match = isset($selected_invoice_number_match) ? (string) $selected_invoice_number_match : 'contains';
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
            <a href="#invoice-generate" class="button button-primary myvh-portal-add-button">
                <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
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
                <?php if ($is_client_admin_view): ?>
                    <div class="myvh-invoice-filter-group">
                        <span class="myvh-invoice-filter-label">Filter by client and invoice</span>
                        <div class="myvh-invoice-admin-search-grid">
                            <label class="myvh-invoice-text-field">
                                <span>Client name</span>
                                <input type="text"
                                       name="client_name"
                                       value="<?php echo esc_attr($selected_client_name); ?>"
                                       placeholder="Enter client name">
                            </label>
                            <label class="myvh-invoice-select-field">
                                <span>Match</span>
                                <select name="client_name_match">
                                    <option value="begins_with" <?php selected($selected_client_name_match, 'begins_with'); ?>>Begins with</option>
                                    <option value="contains" <?php selected($selected_client_name_match, 'contains'); ?>>Contains</option>
                                </select>
                            </label>
                            <label class="myvh-invoice-text-field">
                                <span>Invoice number</span>
                                <input type="text"
                                       name="invoice_number"
                                       value="<?php echo esc_attr($selected_invoice_number); ?>"
                                       placeholder="Enter invoice number">
                            </label>
                            <label class="myvh-invoice-select-field">
                                <span>Match</span>
                                <select name="invoice_number_match">
                                    <option value="begins_with" <?php selected($selected_invoice_number_match, 'begins_with'); ?>>Begins with</option>
                                    <option value="contains" <?php selected($selected_invoice_number_match, 'contains'); ?>>Contains</option>
                                </select>
                            </label>
                        </div>
                    </div>
                <?php endif; ?>
                <div class="myvh-invoice-filter-group">
                    <span class="myvh-invoice-filter-label">Filter by status</span>
                    <div class="myvh-checkbox-group" style="display: grid; grid-template-columns: 1fr; gap: 8px;">
                        <div style="display: flex; flex-wrap: wrap; gap: 16px;">
                            <?php foreach ($available_statuses as $status): ?>
                                <?php if (in_array($status, ['draft', 'sent', 'part-paid', 'overdue'], true)): ?>
                                    <label class="myvh-checkbox-label">
                                        <input type="checkbox"
                                               name="statuses[]"
                                               value="<?php echo esc_attr($status); ?>"
                                               <?php checked(in_array($status, $selected_statuses, true)); ?>>
                                        <span><?php echo ucfirst(esc_html(str_replace('-', ' ', $status))); ?></span>
                                    </label>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        <div style="display: flex; flex-wrap: wrap; gap: 16px;">
                            <?php foreach ($available_statuses as $status): ?>
                                <?php if (in_array($status, ['paid', 'cancelled'], true)): ?>
                                    <label class="myvh-checkbox-label">
                                        <input type="checkbox"
                                               name="statuses[]"
                                               value="<?php echo esc_attr($status); ?>"
                                               <?php checked(in_array($status, $selected_statuses, true)); ?>>
                                        <span><?php echo ucfirst(esc_html(str_replace('-', ' ', $status))); ?></span>
                                    </label>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="myvh-invoice-filter-group">
                    <span class="myvh-invoice-filter-label">Filter by invoice date</span>
                    <div class="myvh-invoice-date-filter">
                        <label class="myvh-invoice-date-field">
                            <span>Start date</span>
                            <input type="date"
                                   name="start_date"
                                   value="<?php echo esc_attr($selected_start_date); ?>">
                        </label>
                        <label class="myvh-invoice-date-field">
                            <span>End date</span>
                            <input type="date"
                                   name="end_date"
                                   value="<?php echo esc_attr($selected_end_date); ?>">
                        </label>
                    </div>
                    <?php if (!empty($quick_date_ranges)): ?>
                        <div class="myvh-invoice-date-quick-actions" aria-label="Quick date ranges">
                            <?php foreach ($quick_date_ranges as $key => $range): ?>
                                <button type="button"
                                    class="myvh-filter-date-preset myvh-invoice-date-range-quick"
                                        data-invoice-date-range="<?php echo esc_attr($key); ?>"
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
                    <button type="button" class="myvh-invoice-filter-submit myvh-invoice-filter-reset" data-invoice-filter-reset>
                        Reset Filters
                    </button>
                </div>
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
                            <?php
                                $portal_pdf_url = add_query_arg([
                                    'action' => 'myvh_portal_view_invoice_pdf',
                                    'invoice_id' => \intval($invoice['Id']),
                                    'nonce' => wp_create_nonce('myvh_portal'),
                                ], admin_url('admin-ajax.php'));
                            ?>
                            <tr class="myvh-invoice-row">
                                <td class="myvh-invoice-number">
                                    <strong><a href="#invoice-view?invoice_id=<?php echo \intval($invoice['Id']); ?>"><?php echo esc_html($invoice['InvoiceNumber']); ?></a></strong>
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
                                        $is_personal_invoice = !empty($invoice['IsPersonalInvoice']);
                                        $organisation_name = trim((string) ($invoice['OrganisationName'] ?? ''));
                                        $is_personal_organisation_name = strtolower($organisation_name) === 'personal booking';

                                        if ($is_personal_invoice || $is_personal_organisation_name) {
                                            echo 'Personal booking';
                                        } elseif ($organisation_name !== '') {
                                            echo esc_html($organisation_name);
                                            echo ' <span class="myvh-badge myvh-badge-org">Org</span>';
                                        } else {
                                            echo 'Organisation';
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
                                    <div class="myvh-invoice-actions">
                                        <a href="#invoice-view?invoice_id=<?php echo \intval($invoice['Id']); ?>" class="myvh-action-icon" aria-label="View invoice" title="View invoice">👁</a>
                                        <a href="<?php echo esc_url($portal_pdf_url); ?>" class="myvh-action-icon" target="_blank" rel="noopener noreferrer" aria-label="View invoice PDF" title="View invoice PDF">📄</a>
                                        <?php if ($is_client_admin_view && ($invoice['Status'] ?? '') !== 'cancelled'): ?>
                                            <button type="button" class="myvh-action-icon" data-invoice-email="<?php echo \intval($invoice['Id']); ?>" aria-label="Email invoice to customer" title="Email invoice to customer" style="background:none; border:none; padding:0; margin:0; cursor:pointer;">📧</button>
                                            <a href="#payments?invoice_id=<?php echo \intval($invoice['Id']); ?>" class="myvh-action-icon" aria-label="View invoice payments" title="View invoice payments">💳</a>
                                        <?php endif; ?>
                                    </div>
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
                    ? 'Try adjusting the selected statuses or invoice date range to see more invoices.'
                    : 'Invoices will appear here once they have been generated for this account.'; ?></p>
            </div>
        <?php endif; ?>
        </div>
    </div>
</div>
