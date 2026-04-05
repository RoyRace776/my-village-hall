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
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e('View Invoices', 'my-village-hall'); ?></h1>
    <a href="<?php echo esc_url(admin_url('admin.php?page=myvh-invoice-generate')); ?>" class="page-title-action">
        <?php esc_html_e('Generate Invoices', 'my-village-hall'); ?>
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
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html(sprintf(__('Generated %d invoice(s).', 'my-village-hall'), intval($_GET['generated']))); ?></p></div>
    <?php endif; ?>

    <div class="myvh-card">
        <h2><?php esc_html_e('All Invoices', 'my-village-hall'); ?></h2>

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
                        <td colspan="8"><?php esc_html_e('No invoices found yet.', 'my-village-hall'); ?></td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($invoices as $invoice): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($invoice['InvoiceNumber'] ?? ''); ?></strong><br>
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
                                <?php if (($invoice['Status'] ?? '') !== 'sent'): ?>
                                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=myvh_update_invoice_status&id=' . intval($invoice['Id']) . '&status=sent&redirect_page=myvh-invoices'), 'myvh_update_invoice_status')); ?>">
                                        <?php esc_html_e('Mark Sent', 'my-village-hall'); ?>
                                    </a><br>
                                <?php endif; ?>
                                <?php if (($invoice['Status'] ?? '') !== 'cancelled'): ?>
                                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=myvh_update_invoice_status&id=' . intval($invoice['Id']) . '&status=cancelled&redirect_page=myvh-invoices'), 'myvh_update_invoice_status')); ?>">
                                        <?php esc_html_e('Cancel', 'my-village-hall'); ?>
                                    </a><br>
                                <?php endif; ?>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:8px;">
                                    <input type="hidden" name="action" value="myvh_record_payment">
                                    <input type="hidden" name="invoice_id" value="<?php echo esc_attr((string) intval($invoice['Id'])); ?>">
                                    <input type="hidden" name="redirect_page" value="myvh-invoices">
                                    <?php wp_nonce_field('myvh_record_payment'); ?>
                                    <input type="number" name="payment_amount" min="0" step="0.01" class="small-text" placeholder="0.00">
                                    <button type="submit" class="button button-small"><?php esc_html_e('Record Payment', 'my-village-hall'); ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
