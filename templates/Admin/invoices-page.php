<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('manage_myvh')) {
    wp_die(__('Permission denied', 'my-village-hall'));
}

global $myvh_container;

use MYVH\Invoices\InvoiceService;

$invoice_service = $myvh_container->get(InvoiceService::class);
$invoices = $invoice_service->get_with_customers() ?: [];

$client_name = isset($_GET['client_name']) ? sanitize_text_field(wp_unslash($_GET['client_name'])) : '';
$client_name_match = isset($_GET['client_name_match']) ? sanitize_key(wp_unslash($_GET['client_name_match'])) : 'contains';
$invoice_number = isset($_GET['invoice_number']) ? sanitize_text_field(wp_unslash($_GET['invoice_number'])) : '';
$invoice_number_match = isset($_GET['invoice_number_match']) ? sanitize_key(wp_unslash($_GET['invoice_number_match'])) : 'contains';

$allowed_match_types = ['begins_with', 'contains'];
if (!in_array($client_name_match, $allowed_match_types, true)) {
    $client_name_match = 'contains';
}
if (!in_array($invoice_number_match, $allowed_match_types, true)) {
    $invoice_number_match = 'contains';
}

$matches_filter_value = static function (string $value, string $term, string $match_type): bool {
    $value = trim($value);
    $term = trim($term);

    if ($term === '') {
        return true;
    }

    $value = function_exists('mb_strtolower') ? mb_strtolower($value) : strtolower($value);
    $term = function_exists('mb_strtolower') ? mb_strtolower($term) : strtolower($term);

    if ($match_type === 'begins_with') {
        return strpos($value, $term) === 0;
    }

    return strpos($value, $term) !== false;
};

$invoices = array_values(array_filter($invoices, static function (array $invoice) use ($matches_filter_value, $client_name, $client_name_match, $invoice_number, $invoice_number_match): bool {
    $customer_name = (string) ($invoice['CustomerName'] ?? '');
    $invoice_ref = (string) ($invoice['InvoiceNumber'] ?? '');

    if ($client_name !== '' && !$matches_filter_value($customer_name, $client_name, $client_name_match)) {
        return false;
    }

    if ($invoice_number !== '' && !$matches_filter_value($invoice_ref, $invoice_number, $invoice_number_match)) {
        return false;
    }

    return true;
}));

$is_filtered = $client_name !== '' || $invoice_number !== '';
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e('View Invoices', 'my-village-hall'); ?></h1>
    <a href="<?php echo esc_url(admin_url('admin.php?page=myvh-invoice-generate')); ?>" class="page-title-action">
        <?php esc_html_e('Generate Invoices', 'my-village-hall'); ?>
    </a>
    <a href="<?php echo esc_url(admin_url('admin.php?page=myvh-payments')); ?>" class="page-title-action">
        <?php esc_html_e('View Payments', 'my-village-hall'); ?>
    </a>
    <hr class="wp-header-end">

    <?php if (isset($_GET['error'])): ?>
        <div class="notice notice-error is-dismissible"><p><?php echo esc_html(wp_unslash($_GET['error'])); ?></p></div>
    <?php endif; ?>
    <?php if (isset($_GET['updated'])): ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Invoice updated.', 'my-village-hall'); ?></p></div>
    <?php endif; ?>
    <?php if (isset($_GET['deleted'])): ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Invoice deleted.', 'my-village-hall'); ?></p></div>
    <?php endif; ?>
    <?php if (isset($_GET['generated'])): ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html(sprintf(__('Generated %d invoice(s).', 'my-village-hall'), \intval($_GET['generated']))); ?></p></div>
    <?php endif; ?>
    <?php if (isset($_GET['emailed'])): ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Invoice emailed and marked as sent.', 'my-village-hall'); ?></p></div>
    <?php endif; ?>

    <div class="myvh-card">
        <h2><?php esc_html_e('All Invoices', 'my-village-hall'); ?></h2>

        <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="myvh-admin-invoice-filter-form" style="margin: 16px 0 20px;">
            <input type="hidden" name="page" value="myvh-invoices">
            <div class="myvh-filter-row" style="display:flex; gap:12px; flex-wrap:wrap; align-items:end;">
                <div>
                    <label for="myvh-admin-filter-client-name"><strong><?php esc_html_e('Client name', 'my-village-hall'); ?></strong></label><br>
                    <input type="text"
                           id="myvh-admin-filter-client-name"
                           name="client_name"
                           value="<?php echo esc_attr($client_name); ?>"
                           placeholder="<?php esc_attr_e('Enter client name', 'my-village-hall'); ?>">
                </div>
                <div>
                    <label for="myvh-admin-filter-client-name-match"><strong><?php esc_html_e('Match', 'my-village-hall'); ?></strong></label><br>
                    <select id="myvh-admin-filter-client-name-match" name="client_name_match">
                        <option value="begins_with" <?php selected($client_name_match, 'begins_with'); ?>><?php esc_html_e('Begins with', 'my-village-hall'); ?></option>
                        <option value="contains" <?php selected($client_name_match, 'contains'); ?>><?php esc_html_e('Contains', 'my-village-hall'); ?></option>
                    </select>
                </div>
                <div>
                    <label for="myvh-admin-filter-invoice-number"><strong><?php esc_html_e('Invoice number', 'my-village-hall'); ?></strong></label><br>
                    <input type="text"
                           id="myvh-admin-filter-invoice-number"
                           name="invoice_number"
                           value="<?php echo esc_attr($invoice_number); ?>"
                           placeholder="<?php esc_attr_e('Enter invoice number', 'my-village-hall'); ?>">
                </div>
                <div>
                    <label for="myvh-admin-filter-invoice-number-match"><strong><?php esc_html_e('Match', 'my-village-hall'); ?></strong></label><br>
                    <select id="myvh-admin-filter-invoice-number-match" name="invoice_number_match">
                        <option value="begins_with" <?php selected($invoice_number_match, 'begins_with'); ?>><?php esc_html_e('Begins with', 'my-village-hall'); ?></option>
                        <option value="contains" <?php selected($invoice_number_match, 'contains'); ?>><?php esc_html_e('Contains', 'my-village-hall'); ?></option>
                    </select>
                </div>
                <div style="display:flex; gap:8px; align-items:center;">
                    <button type="submit" class="button button-primary"><?php esc_html_e('Apply Filter', 'my-village-hall'); ?></button>
                    <?php if ($is_filtered): ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=myvh-invoices')); ?>" class="button"><?php esc_html_e('Reset', 'my-village-hall'); ?></a>
                    <?php endif; ?>
                </div>
            </div>
        </form>

        <!-- Client-side status filter checkboxes -->
        <div class="myvh-invoices-status-checkboxes" style="margin-top: 16px;">
            <strong><?php esc_html_e('Filter by status:', 'my-village-hall'); ?></strong>
            <div style="margin-top: 8px;">
                <div>
                    <label style="margin-right:12px;"><input type="checkbox" class="myvh-invoice-status-filter" value="draft" checked> <?php esc_html_e('Draft', 'my-village-hall'); ?></label>
                    <label style="margin-right:12px;"><input type="checkbox" class="myvh-invoice-status-filter" value="sent" checked> <?php esc_html_e('Sent', 'my-village-hall'); ?></label>
                    <label style="margin-right:12px;"><input type="checkbox" class="myvh-invoice-status-filter" value="part_paid" checked> <?php esc_html_e('Part Paid', 'my-village-hall'); ?></label>
                    <label style="margin-right:12px;"><input type="checkbox" class="myvh-invoice-status-filter" value="overdue" checked> <?php esc_html_e('Overdue', 'my-village-hall'); ?></label>
                </div>
                <div style="margin-top: 8px;">
                    <label style="margin-right:12px;"><input type="checkbox" class="myvh-invoice-status-filter" value="paid"> <?php esc_html_e('Paid', 'my-village-hall'); ?></label>
                    <label style="margin-right:12px;"><input type="checkbox" class="myvh-invoice-status-filter" value="cancelled"> <?php esc_html_e('Cancelled', 'my-village-hall'); ?></label>
                </div>
            </div>
        </div>

        <?php if ($is_filtered): ?>
            <p class="description" style="margin-top:0; margin-bottom:16px;"><?php esc_html_e('Showing invoices matching the selected filters.', 'my-village-hall'); ?></p>
        <?php endif; ?>

        <table class="wp-list-table widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Invoice', 'my-village-hall'); ?></th>
                    <th><?php esc_html_e('Customer', 'my-village-hall'); ?></th>
                    <th><?php esc_html_e('Billing', 'my-village-hall'); ?></th>
                    <th><?php esc_html_e('Amount', 'my-village-hall'); ?></th>
                    <th><?php esc_html_e('Paid', 'my-village-hall'); ?></th>
                    <th><?php esc_html_e('Due', 'my-village-hall'); ?></th>
                    <th><?php esc_html_e('Status', 'my-village-hall'); ?></th>
                    <th><?php esc_html_e('Actions', 'my-village-hall'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($invoices)): ?>
                    <tr>
                        <td colspan="8"><?php echo $is_filtered ? esc_html__('No invoices match the current filters.', 'my-village-hall') : esc_html__('No invoices found yet.', 'my-village-hall'); ?></td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($invoices as $invoice): ?>
                        <?php $view_url = admin_url('admin.php?page=myvh-invoices&view=' . \intval($invoice['Id'])); ?>
                        <tr>
                            <td>
                                <strong><a href="<?php echo esc_url($view_url); ?>"><?php echo esc_html($invoice['InvoiceNumber'] ?? ''); ?></a></strong><br>
                                <small><?php echo esc_html(date('j M Y', strtotime((string) ($invoice['InvoiceDate'] ?? 'now')))); ?></small>
                            </td>
                            <td>
                                <strong><?php echo esc_html($invoice['CustomerName'] ?? 'Unknown'); ?></strong>
                                <?php if (!empty($invoice['CustomerEmail'])): ?>
                                    <br><small><?php echo esc_html($invoice['CustomerEmail']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo esc_html($invoice['BillingOrganisationName'] ?: ($invoice['BillingName'] ?: 'Unassigned')); ?>
                                <?php if (!empty($invoice['BillingReference'])): ?>
                                    <br><small><?php echo esc_html__('Ref:', 'my-village-hall'); ?> <?php echo esc_html($invoice['BillingReference']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html(number_format((float) ($invoice['TotalAmount'] ?? 0), 2)); ?></td>
                            <td><?php echo esc_html(number_format((float) ($invoice['AmountPaid'] ?? 0), 2)); ?></td>
                            <td><?php echo esc_html(date('j M Y', strtotime((string) ($invoice['DueDate'] ?? 'now')))); ?></td>
                            <td>
                                <span class="myvh-status-badge myvh-status-<?php echo esc_attr($invoice['Status'] ?? 'draft'); ?>">
                                    <?php echo esc_html(ucfirst($invoice['Status'] ?? 'draft')); ?>
                                </span>
                            </td>
                            <td>
                                <a href="<?php echo esc_url($view_url); ?>"><?php esc_html_e('View', 'my-village-hall'); ?></a><br>
                                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=myvh_view_invoice_pdf&id=' . \intval($invoice['Id']) . '&redirect_page=myvh-invoices'), 'myvh_view_invoice_pdf')); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('View PDF', 'my-village-hall'); ?></a><br>
                                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=myvh_email_invoice&id=' . \intval($invoice['Id']) . '&redirect_page=myvh-invoices'), 'myvh_email_invoice')); ?>"><?php esc_html_e('Email Invoice', 'my-village-hall'); ?></a><br>
                                <?php if (($invoice['Status'] ?? '') !== 'cancelled'): ?>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=myvh-payments&invoice_id=' . \intval($invoice['Id']))); ?>"><?php esc_html_e('Payments', 'my-village-hall'); ?></a><br>
                                <?php endif; ?>
                                <?php if (($invoice['Status'] ?? '') === 'draft'): ?>
                                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=myvh_update_invoice_status&id=' . \intval($invoice['Id']) . '&status=sent&redirect_page=myvh-invoices'), 'myvh_update_invoice_status')); ?>">
                                        <?php esc_html_e('Mark Sent', 'my-village-hall'); ?>
                                    </a><br>
                                <?php endif; ?>
                                <?php if (($invoice['Status'] ?? '') !== 'cancelled' && (float) ($invoice['AmountPaid'] ?? 0) <= 0): ?>
                                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=myvh_update_invoice_status&id=' . \intval($invoice['Id']) . '&status=cancelled&redirect_page=myvh-invoices'), 'myvh_update_invoice_status')); ?>">
                                        <?php esc_html_e('Cancel', 'my-village-hall'); ?>
                                    </a>
                                <?php elseif ((float) ($invoice['AmountPaid'] ?? 0) > 0): ?>
                                    <span><?php esc_html_e('Cannot cancel after payment', 'my-village-hall'); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <script>
        (function() {
            function filterInvoicesByStatus() {
                const checkedStatuses = Array.from(document.querySelectorAll('.myvh-invoice-status-filter:checked')).map(cb => cb.value);
                const rows = document.querySelectorAll('table.wp-list-table tbody tr');

                rows.forEach(row => {
                    // Skip the "no invoices" row
                    if (row.textContent.includes('No invoices')) {
                        return;
                    }

                    // Get the status from the status cell (7th column)
                    const statusCell = row.querySelector('td:nth-child(7)');
                    if (!statusCell) return;

                    const statusText = statusCell.textContent.trim().toLowerCase();
                    const isVisible = checkedStatuses.some(status => {
                        // Map display statuses to actual statuses
                        const statusMap = {
                            'draft': 'draft',
                            'sent': 'sent',
                            'part_paid': 'part paid',
                            'overdue': 'overdue',
                            'paid': 'paid',
                            'cancelled': 'cancelled'
                        };
                        return statusText === statusMap[status];
                    });

                    row.style.display = isVisible ? '' : 'none';
                });
            }

            // Add event listeners to checkboxes
            document.querySelectorAll('.myvh-invoice-status-filter').forEach(checkbox => {
                checkbox.addEventListener('change', filterInvoicesByStatus);
            });

            // Apply filter on page load
            filterInvoicesByStatus();
        })();
        </script>
    </div>
</div>
