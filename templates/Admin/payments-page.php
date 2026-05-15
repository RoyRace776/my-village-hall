<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('manage_myvh')) {
    wp_die(__('Permission denied', 'my-village-hall'));
}

global $myvh_container;

use MYVH\Invoices\InvoiceService;
use MYVH\Payments\PaymentService;

$invoice_service = $myvh_container->get(InvoiceService::class);
$payment_service = $myvh_container->get(PaymentService::class);

$selected_invoice_id = isset($_GET['invoice_id']) ? \intval($_GET['invoice_id']) : 0;
$payments = $payment_service->get_payments($selected_invoice_id);
$invoices = $invoice_service->get_with_customers() ?: [];
$invoices = array_values(array_filter($invoices, static function (array $invoice): bool {
    return in_array((string) ($invoice['Status'] ?? ''), ['sent', 'part-paid'], true);
}));
$payment_methods = $payment_service->get_valid_methods();
$selected_invoice = $selected_invoice_id > 0 ? $invoice_service->get_detail($selected_invoice_id) : null;
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e('Payments', 'my-village-hall'); ?></h1>
    <?php if ($selected_invoice_id > 0): ?>
        <a href="<?php echo esc_url(admin_url('admin.php?page=myvh-invoices&view=' . $selected_invoice_id)); ?>" class="page-title-action">
            <?php esc_html_e('Back to Invoice', 'my-village-hall'); ?>
        </a>
    <?php endif; ?>
    <hr class="wp-header-end">

    <?php if (isset($_GET['error'])): ?>
        <div class="notice notice-error is-dismissible"><p><?php echo esc_html(wp_unslash($_GET['error'])); ?></p></div>
    <?php endif; ?>
    <?php if (isset($_GET['updated'])): ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Payment saved.', 'my-village-hall'); ?></p></div>
    <?php endif; ?>
    <?php if (isset($_GET['deleted'])): ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Payment deleted.', 'my-village-hall'); ?></p></div>
    <?php endif; ?>

    <div class="myvh-row">
        <div class="myvh-col-40">
            <div class="myvh-card">
                <h2><?php esc_html_e('Add Payment', 'my-village-hall'); ?></h2>
                <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" style="margin-bottom:16px;">
                    <input type="hidden" name="page" value="myvh-payments">
                    <p>
                        <label for="myvh-payment-filter-invoice"><strong><?php esc_html_e('Filter by Invoice', 'my-village-hall'); ?></strong></label>
                    </p>
                    <p>
                        <select id="myvh-payment-filter-invoice" name="invoice_id" class="regular-text">
                            <option value="0"><?php esc_html_e('All invoices', 'my-village-hall'); ?></option>
                            <?php foreach ($invoices as $invoice): ?>
                                <option value="<?php echo esc_attr((string) \intval($invoice['Id'] ?? 0)); ?>" <?php selected($selected_invoice_id === \intval($invoice['Id'] ?? 0)); ?>>
                                    <?php echo esc_html(($invoice['InvoiceNumber'] ?? '') . ' - ' . ($invoice['CustomerName'] ?? __('Unknown', 'my-village-hall'))); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="button"><?php esc_html_e('Apply', 'my-village-hall'); ?></button>
                    </p>
                </form>

                <?php if ($selected_invoice): ?>
                    <p>
                        <strong><?php esc_html_e('Selected invoice:', 'my-village-hall'); ?></strong>
                        <?php echo esc_html($selected_invoice['InvoiceNumber'] ?? ''); ?>
                        <br>
                        <span><?php echo esc_html(sprintf(__('Outstanding balance: %s', 'my-village-hall'), number_format((float) ($selected_invoice['AmountDue'] ?? 0), 2))); ?></span>
                    </p>
                <?php endif; ?>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="myvh_record_payment">
                    <input type="hidden" name="redirect_page" value="myvh-payments">
                    <?php if ($selected_invoice_id > 0): ?>
                        <input type="hidden" name="invoice_id" value="<?php echo esc_attr((string) $selected_invoice_id); ?>">
                    <?php endif; ?>
                    <?php wp_nonce_field('myvh_record_payment'); ?>

                    <?php if ($selected_invoice_id <= 0): ?>
                        <p>
                            <label for="myvh-payment-invoice"><strong><?php esc_html_e('Invoice', 'my-village-hall'); ?></strong></label>
                        </p>
                        <p>
                            <select id="myvh-payment-invoice" name="invoice_id" class="regular-text" required>
                                <option value=""><?php esc_html_e('Select an invoice', 'my-village-hall'); ?></option>
                                <?php foreach ($invoices as $invoice): ?>
                                    <option value="<?php echo esc_attr((string) \intval($invoice['Id'] ?? 0)); ?>">
                                        <?php echo esc_html(($invoice['InvoiceNumber'] ?? '') . ' - ' . ($invoice['CustomerName'] ?? __('Unknown', 'my-village-hall'))); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </p>
                    <?php endif; ?>

                    <p>
                        <label for="myvh-payment-date"><strong><?php esc_html_e('Payment Date', 'my-village-hall'); ?></strong></label>
                    </p>
                    <p>
                        <input id="myvh-payment-date" type="text" name="payment_date" class="regular-text" data-myvh-picker="date" autocomplete="off" value="<?php echo esc_attr(current_time('Y-m-d')); ?>" required>
                    </p>

                    <p>
                        <label for="myvh-payment-amount"><strong><?php esc_html_e('Amount', 'my-village-hall'); ?></strong></label>
                    </p>
                    <p>
                        <input id="myvh-payment-amount" type="number" name="payment_amount" min="0.01" step="0.01" class="regular-text" required>
                    </p>

                    <p>
                        <label for="myvh-payment-method"><strong><?php esc_html_e('Payment Type', 'my-village-hall'); ?></strong></label>
                    </p>
                    <p>
                        <select id="myvh-payment-method" name="payment_method" class="regular-text" required>
                            <option value=""><?php esc_html_e('Select a payment type', 'my-village-hall'); ?></option>
                            <?php foreach ($payment_methods as $method): ?>
                                <option value="<?php echo esc_attr($method); ?>"><?php echo esc_html(ucwords($method)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </p>

                    <p>
                        <label for="myvh-payment-reference"><strong><?php esc_html_e('Reference', 'my-village-hall'); ?></strong></label>
                    </p>
                    <p>
                        <input id="myvh-payment-reference" type="text" name="payment_reference" class="regular-text">
                    </p>

                    <p>
                        <label for="myvh-payment-comment"><strong><?php esc_html_e('Comment', 'my-village-hall'); ?></strong></label>
                    </p>
                    <p>
                        <textarea id="myvh-payment-comment" name="payment_comment" class="large-text" rows="4"></textarea>
                    </p>

                    <p>
                        <button type="submit" class="button button-primary"><?php esc_html_e('Save Payment', 'my-village-hall'); ?></button>
                    </p>
                </form>
            </div>
        </div>

        <div class="myvh-col-60">
            <div class="myvh-card">
                <h2><?php echo $selected_invoice_id > 0 ? esc_html__('Invoice Payments', 'my-village-hall') : esc_html__('All Payments', 'my-village-hall'); ?></h2>

                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Date', 'my-village-hall'); ?></th>
                            <th><?php esc_html_e('Invoice', 'my-village-hall'); ?></th>
                            <th><?php esc_html_e('Customer', 'my-village-hall'); ?></th>
                            <th><?php esc_html_e('Type', 'my-village-hall'); ?></th>
                            <th><?php esc_html_e('Amount', 'my-village-hall'); ?></th>
                            <th><?php esc_html_e('Reference', 'my-village-hall'); ?></th>
                            <th><?php esc_html_e('Comment', 'my-village-hall'); ?></th>
                            <th><?php esc_html_e('Actions', 'my-village-hall'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($payments)): ?>
                            <tr>
                                <td colspan="8"><?php esc_html_e('No payments found.', 'my-village-hall'); ?></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($payments as $payment): ?>
                                <?php $invoice_id = \intval($payment['InvoiceId'] ?? 0); ?>
                                <tr>
                                    <td><?php echo esc_html(date('j M Y', strtotime((string) ($payment['PaymentDate'] ?? 'now')))); ?></td>
                                    <td>
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=myvh-invoices&view=' . $invoice_id)); ?>">
                                            <?php echo esc_html($payment['InvoiceNumber'] ?? ''); ?>
                                        </a>
                                    </td>
                                    <td><?php echo esc_html($payment['CustomerName'] ?? __('Unknown', 'my-village-hall')); ?></td>
                                    <td><?php echo esc_html(ucwords((string) ($payment['PaymentMethod'] ?? 'other'))); ?></td>
                                    <td><?php echo esc_html(number_format((float) ($payment['Amount'] ?? 0), 2)); ?></td>
                                    <td><?php echo esc_html($payment['TransactionReference'] ?? ''); ?></td>
                                    <td><?php echo esc_html($payment['Notes'] ?? ''); ?></td>
                                    <td>
                                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js(__('Delete this payment?', 'my-village-hall')); ?>');">
                                            <input type="hidden" name="action" value="myvh_delete_payment">
                                            <input type="hidden" name="payment_id" value="<?php echo esc_attr((string) \intval($payment['Id'] ?? 0)); ?>">
                                            <input type="hidden" name="invoice_id" value="<?php echo esc_attr((string) $invoice_id); ?>">
                                            <input type="hidden" name="redirect_page" value="myvh-payments">
                                            <?php wp_nonce_field('myvh_delete_payment'); ?>
                                            <button type="submit" class="button button-small"><?php esc_html_e('Delete', 'my-village-hall'); ?></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
