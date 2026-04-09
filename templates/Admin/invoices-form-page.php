<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('manage_myvh')) {
    wp_die(__('Permission denied', 'my-village-hall'));
}

global $myvh_container;

use MYVH\Invoices\InvoiceService;

$view_id = isset($_GET['view']) ? intval($_GET['view']) : 0;
$invoice_service = $myvh_container->get(InvoiceService::class);
$invoice = $view_id > 0 ? $invoice_service->get_detail($view_id) : null;
$invoice_bookings = $invoice['BookingsSummary'] ?? [];
$valid_statuses = $invoice_service->get_valid_statuses();
?>

<div class="wrap">
    <h1><?php esc_html_e('View Invoice', 'my-village-hall'); ?></h1>
    <a href="<?php echo esc_url(admin_url('admin.php?page=myvh-invoices')); ?>" class="page-title-action"><?php esc_html_e('Back to Invoices', 'my-village-hall'); ?></a>
    <hr class="wp-header-end">

    <?php if (isset($_GET['error'])): ?>
        <div class="notice notice-error is-dismissible"><p><?php echo esc_html(wp_unslash($_GET['error'])); ?></p></div>
    <?php endif; ?>
    <?php if (isset($_GET['updated'])): ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Invoice updated.', 'my-village-hall'); ?></p></div>
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
                                    <?php echo esc_html(ucfirst($invoice['Status'] ?? 'draft')); ?>
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
                                    <option value="<?php echo esc_attr($status); ?>" <?php selected(($invoice['Status'] ?? '') === $status); ?>>
                                        <?php echo esc_html(ucfirst($status)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </p>
                        <p>
                            <button type="submit" class="button button-primary"><?php esc_html_e('Update Status', 'my-village-hall'); ?></button>
                        </p>
                    </form>
                </div>

                <div class="myvh-card">
                    <h2><?php esc_html_e('Record Payment', 'my-village-hall'); ?></h2>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="myvh_record_payment">
                        <input type="hidden" name="invoice_id" value="<?php echo esc_attr((string) intval($invoice['Id'])); ?>">
                        <input type="hidden" name="redirect_page" value="myvh-invoices">
                        <input type="hidden" name="redirect_view" value="<?php echo esc_attr((string) intval($invoice['Id'])); ?>">
                        <?php wp_nonce_field('myvh_record_payment'); ?>

                        <p>
                            <label for="myvh-payment-amount"><strong><?php esc_html_e('Amount', 'my-village-hall'); ?></strong></label>
                        </p>
                        <p>
                            <input id="myvh-payment-amount" type="number" name="payment_amount" min="0" step="0.01" class="regular-text" placeholder="0.00">
                        </p>
                        <p>
                            <button type="submit" class="button"><?php esc_html_e('Record Payment', 'my-village-hall'); ?></button>
                        </p>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>