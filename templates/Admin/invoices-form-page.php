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

$view_id = isset($_GET['view']) ? intval($_GET['view']) : 0;
$invoice_service = $myvh_container->get(InvoiceService::class);
$payment_service = $myvh_container->get(PaymentService::class);
$invoice = $view_id > 0 ? $invoice_service->get_detail($view_id) : null;
$invoice_bookings = $invoice['BookingsSummary'] ?? [];
$invoice_payments = $invoice['Payments'] ?? [];
$valid_statuses = $invoice_service->get_valid_statuses();
$payment_methods = $payment_service->get_valid_methods();
$has_payments = !empty($invoice_payments);
?>

<div class="wrap">
    <h1><?php esc_html_e('View Invoice', 'my-village-hall'); ?></h1>
    <a href="<?php echo esc_url(admin_url('admin.php?page=myvh-invoices')); ?>" class="page-title-action"><?php esc_html_e('Back to Invoices', 'my-village-hall'); ?></a>
    <?php if (($invoice['Status'] ?? '') !== 'cancelled'): ?>
    <a href="<?php echo esc_url(admin_url('admin.php?page=myvh-payments&invoice_id=' . $view_id)); ?>" class="page-title-action"><?php esc_html_e('Open Payments Page', 'my-village-hall'); ?></a>
    <?php endif; ?>
    <hr class="wp-header-end">

    <?php if (isset($_GET['error'])): ?>
        <div class="notice notice-error is-dismissible"><p><?php echo esc_html(wp_unslash($_GET['error'])); ?></p></div>
    <?php endif; ?>
    <?php if (isset($_GET['updated'])): ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Invoice updated.', 'my-village-hall'); ?></p></div>
    <?php endif; ?>
    <?php if (isset($_GET['deleted'])): ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Payment deleted.', 'my-village-hall'); ?></p></div>
    <?php endif; ?>

    <?php if (empty($invoice)): ?>
        <div class="notice notice-warning"><p><?php esc_html_e('Invoice not found.', 'my-village-hall'); ?></p></div>
    <?php else: ?>
        <div class="myvh-row">
            <div class="myvh-col-60">
                <div class="myvh-card">
                    <h2><?php esc_html_e('Invoice Details', 'my-village-hall'); ?></h2>

                    <table class="form-table">
                        <tr>
                            <th><?php esc_html_e('Invoice Number', 'my-village-hall'); ?></th>
                            <td><strong><?php echo esc_html($invoice['InvoiceNumber'] ?? ''); ?></strong></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Status', 'my-village-hall'); ?></th>
                            <td>
                                <span class="myvh-status-badge myvh-status-<?php echo esc_attr($invoice['Status'] ?? 'draft'); ?>">
                                    <?php echo esc_html($invoice_service->get_status_label((string) ($invoice['Status'] ?? 'draft'))); ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Customer', 'my-village-hall'); ?></th>
                            <td>
                                <strong><?php echo esc_html($invoice['CustomerName'] ?? __('Unknown', 'my-village-hall')); ?></strong>
                                <?php if (!empty($invoice['CustomerEmail'])): ?>
                                    <br><?php echo esc_html($invoice['CustomerEmail']); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Billing', 'my-village-hall'); ?></th>
                            <td>
                                <?php echo esc_html($invoice['BillingOrganisationName'] ?: ($invoice['BillingName'] ?: __('Unassigned', 'my-village-hall'))); ?>
                                <?php if (!empty($invoice['BillingEmail'])): ?>
                                    <br><?php echo esc_html($invoice['BillingEmail']); ?>
                                <?php endif; ?>
                                <?php if (!empty($invoice['BillingAddressLine1']) || !empty($invoice['BillingTownCity']) || !empty($invoice['BillingPostcode'])): ?>
                                    <br><?php echo esc_html(trim(implode(', ', array_filter([
                                        $invoice['BillingAddressLine1'] ?? '',
                                        $invoice['BillingAddressLine2'] ?? '',
                                        $invoice['BillingTownCity'] ?? '',
                                        $invoice['BillingPostcode'] ?? '',
                                    ])), ', ')); ?>
                                <?php endif; ?>
                                <?php if (!empty($invoice['BillingReference'])): ?>
                                    <br><small><?php esc_html_e('Reference:', 'my-village-hall'); ?> <?php echo esc_html($invoice['BillingReference']); ?></small>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Invoice Date', 'my-village-hall'); ?></th>
                            <td><?php echo esc_html(date('j F Y', strtotime((string) ($invoice['InvoiceDate'] ?? 'now')))); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Due Date', 'my-village-hall'); ?></th>
                            <td><?php echo esc_html(date('j F Y', strtotime((string) ($invoice['DueDate'] ?? 'now')))); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Amounts', 'my-village-hall'); ?></th>
                            <td>
                                <?php printf(
                                    esc_html__('Total %1$s | Paid %2$s | Due %3$s', 'my-village-hall'),
                                    number_format((float) ($invoice['TotalAmount'] ?? 0), 2),
                                    number_format((float) ($invoice['AmountPaid'] ?? 0), 2),
                                    number_format((float) ($invoice['AmountDue'] ?? 0), 2)
                                ); ?>
                            </td>
                        </tr>
                        <?php if (!empty($invoice['Notes'])): ?>
                            <tr>
                                <th><?php esc_html_e('Notes', 'my-village-hall'); ?></th>
                                <td><?php echo nl2br(esc_html($invoice['Notes'])); ?></td>
                            </tr>
                        <?php endif; ?>
                    </table>
                </div>

                <div class="myvh-card">
                    <h2><?php esc_html_e('Payments', 'my-village-hall'); ?></h2>

                    <?php if (empty($invoice_payments)): ?>
                        <p><?php esc_html_e('No payments have been recorded for this invoice yet.', 'my-village-hall'); ?></p>
                    <?php else: ?>
                        <table class="wp-list-table widefat striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Date', 'my-village-hall'); ?></th>
                                    <th><?php esc_html_e('Type', 'my-village-hall'); ?></th>
                                    <th><?php esc_html_e('Amount', 'my-village-hall'); ?></th>
                                    <th><?php esc_html_e('Reference', 'my-village-hall'); ?></th>
                                    <th><?php esc_html_e('Comment', 'my-village-hall'); ?></th>
                                    <th><?php esc_html_e('Actions', 'my-village-hall'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($invoice_payments as $payment): ?>
                                    <tr>
                                        <td><?php echo esc_html(date('j M Y', strtotime((string) ($payment['PaymentDate'] ?? 'now')))); ?></td>
                                        <td><?php echo esc_html(ucwords((string) ($payment['PaymentMethod'] ?? 'other'))); ?></td>
                                        <td><?php echo esc_html(number_format((float) ($payment['Amount'] ?? 0), 2)); ?></td>
                                        <td><?php echo esc_html($payment['TransactionReference'] ?? ''); ?></td>
                                        <td><?php echo esc_html($payment['Notes'] ?? ''); ?></td>
                                        <td>
                                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js(__('Delete this payment?', 'my-village-hall')); ?>');">
                                                <input type="hidden" name="action" value="myvh_delete_payment">
                                                <input type="hidden" name="payment_id" value="<?php echo esc_attr((string) intval($payment['Id'] ?? 0)); ?>">
                                                <input type="hidden" name="invoice_id" value="<?php echo esc_attr((string) intval($invoice['Id'] ?? 0)); ?>">
                                                <input type="hidden" name="redirect_page" value="myvh-invoices">
                                                <input type="hidden" name="redirect_view" value="<?php echo esc_attr((string) intval($invoice['Id'] ?? 0)); ?>">
                                                <?php wp_nonce_field('myvh_delete_payment'); ?>
                                                <button type="submit" class="button button-small"><?php esc_html_e('Delete', 'my-village-hall'); ?></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <div class="myvh-card">
                    <h2><?php esc_html_e('Related Bookings', 'my-village-hall'); ?></h2>

                    <?php if (empty($invoice_bookings)): ?>
                        <p><?php esc_html_e('No bookings were found for this invoice.', 'my-village-hall'); ?></p>
                    <?php else: ?>
                        <table class="wp-list-table widefat striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Booking', 'my-village-hall'); ?></th>
                                    <th><?php esc_html_e('Description', 'my-village-hall'); ?></th>
                                    <th><?php esc_html_e('Date', 'my-village-hall'); ?></th>
                                    <th><?php esc_html_e('Room', 'my-village-hall'); ?></th>
                                    <th><?php esc_html_e('Organisation', 'my-village-hall'); ?></th>
                                    <th><?php esc_html_e('Line Total', 'my-village-hall'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($invoice_bookings as $booking): ?>
                                    <tr>
                                        <td>
                                            <a href="<?php echo esc_url(admin_url('admin.php?page=my-village-hall&view=' . intval($booking['BookingId']))); ?>">#<?php echo intval($booking['BookingId']); ?></a>
                                        </td>
                                        <td>
                                            <strong><?php echo esc_html($booking['Description'] ?? ''); ?></strong>
                                        </td>
                                        <td>
                                            <?php if (!empty($booking['StartDate'])): ?>
                                                <?php echo esc_html(date('j M Y', strtotime((string) $booking['StartDate']))); ?>
                                                <?php if (!empty($booking['StartTime']) && !empty($booking['EndTime'])): ?>
                                                    <br><small><?php echo esc_html(date('H:i', strtotime((string) $booking['StartTime'])) . ' - ' . date('H:i', strtotime((string) $booking['EndTime']))); ?></small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo esc_html($booking['RoomName'] ?? '-'); ?></td>
                                        <td><?php echo esc_html($booking['OrganisationName'] ?? '-'); ?></td>
                                        <td><?php echo esc_html(number_format((float) ($booking['TotalAmount'] ?? 0), 2)); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <div class="myvh-col-40">
                <div class="myvh-card">
                    <h2><?php esc_html_e('Amend Status', 'my-village-hall'); ?></h2>
                    <?php if ($has_payments): ?>
                        <p><?php esc_html_e('Status is managed by recorded payments for this invoice.', 'my-village-hall'); ?></p>
                    <?php else: ?>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <input type="hidden" name="action" value="myvh_update_invoice_status">
                            <input type="hidden" name="invoice_id" value="<?php echo esc_attr((string) intval($invoice['Id'])); ?>">
                            <input type="hidden" name="redirect_page" value="myvh-invoices">
                            <input type="hidden" name="redirect_view" value="<?php echo esc_attr((string) intval($invoice['Id'])); ?>">
                            <?php wp_nonce_field('myvh_update_invoice_status'); ?>

                            <p>
                                <label for="myvh-invoice-status"><strong><?php esc_html_e('Status', 'my-village-hall'); ?></strong></label>
                            </p>
                            <p>
                                <select id="myvh-invoice-status" name="status">
                                    <?php foreach ($valid_statuses as $status): ?>
                                        <?php if (in_array($status, ['draft', 'sent', 'overdue', 'cancelled'], true)): ?>
                                            <option value="<?php echo esc_attr($status); ?>" <?php selected(($invoice['Status'] ?? '') === $status); ?>>
                                                <?php echo esc_html($invoice_service->get_status_label($status)); ?>
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </p>
                            <p>
                                <button type="submit" class="button button-primary"><?php esc_html_e('Update Status', 'my-village-hall'); ?></button>
                            </p>
                        </form>
                    <?php endif; ?>
                </div>

                <div class="myvh-card">
                    <h2><?php esc_html_e('Add Payment', 'my-village-hall'); ?></h2>
                    <?php if (($invoice['Status'] ?? '') === 'cancelled'): ?>
                        <p><?php esc_html_e('Reopen this invoice before recording payments.', 'my-village-hall'); ?></p>
                    <?php elseif ((float) ($invoice['AmountDue'] ?? 0) <= 0): ?>
                        <p><?php esc_html_e('This invoice has already been fully paid.', 'my-village-hall'); ?></p>
                    <?php else: ?>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <input type="hidden" name="action" value="myvh_record_payment">
                            <input type="hidden" name="invoice_id" value="<?php echo esc_attr((string) intval($invoice['Id'])); ?>">
                            <input type="hidden" name="redirect_page" value="myvh-invoices">
                            <input type="hidden" name="redirect_view" value="<?php echo esc_attr((string) intval($invoice['Id'])); ?>">
                            <?php wp_nonce_field('myvh_record_payment'); ?>

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
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>