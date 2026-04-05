<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('manage_myvh')) {
    wp_die(__('Permission denied', 'my-village-hall'));
}

global $myvh_container;

use MYVH\Bookings\BookingService;

$booking_service = $myvh_container->get(BookingService::class);
$uninvoiced_bookings = $booking_service->get_uninvoiced_bookings([
    'orderby' => 'b.StartDate',
    'order' => 'DESC',
]);
$uninvoiced_by_customer = $booking_service->get_uninvoiced_by_customer();
$uninvoiced_by_organisation = $booking_service->get_uninvoiced_by_organisation();
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e('Generate Invoices', 'my-village-hall'); ?></h1>
    <a href="<?php echo esc_url(admin_url('admin.php?page=myvh-invoices')); ?>" class="page-title-action">
        <?php esc_html_e('View Invoices', 'my-village-hall'); ?>
    </a>
    <hr class="wp-header-end">

    <?php if (isset($_GET['error'])): ?>
        <div class="notice notice-error is-dismissible"><p><?php echo esc_html(wp_unslash($_GET['error'])); ?></p></div>
    <?php endif; ?>

    <div class="myvh-row">
        <div class="myvh-col-60">
            <div class="myvh-card">
                <h2><?php esc_html_e('Manual Invoice Creation', 'my-village-hall'); ?></h2>
                <p><?php esc_html_e('Select uninvoiced bookings and choose how they should be grouped into invoices.', 'my-village-hall'); ?></p>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="myvh_generate_invoices">
                    <input type="hidden" name="redirect_page" value="myvh-invoice-generate">
                    <?php wp_nonce_field('myvh_generate_invoices'); ?>

                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row"><label for="myvh-group-by-admin"><?php esc_html_e('Grouping', 'my-village-hall'); ?></label></th>
                                <td>
                                    <select id="myvh-group-by-admin" name="group_by" class="regular-text">
                                        <option value="per_booking"><?php esc_html_e('One invoice per booking', 'my-village-hall'); ?></option>
                                        <option value="by_customer"><?php esc_html_e('Group by customer', 'my-village-hall'); ?></option>
                                        <option value="by_organisation"><?php esc_html_e('Group by organisation', 'my-village-hall'); ?></option>
                                    </select>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <table class="wp-list-table widefat striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Select', 'my-village-hall'); ?></th>
                                <th><?php esc_html_e('Booking', 'my-village-hall'); ?></th>
                                <th><?php esc_html_e('Customer', 'my-village-hall'); ?></th>
                                <th><?php esc_html_e('Organisation', 'my-village-hall'); ?></th>
                                <th><?php esc_html_e('Description', 'my-village-hall'); ?></th>
                                <th><?php esc_html_e('Date', 'my-village-hall'); ?></th>
                                <th><?php esc_html_e('Room', 'my-village-hall'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($uninvoiced_bookings)): ?>
                                <tr>
                                    <td colspan="7"><?php esc_html_e('No uninvoiced confirmed or completed bookings were found.', 'my-village-hall'); ?></td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($uninvoiced_bookings as $booking): ?>
                                    <tr>
                                        <td><input type="checkbox" name="booking_ids[]" value="<?php echo esc_attr((string) intval($booking['Id'])); ?>"></td>
                                        <td>#<?php echo esc_html((string) intval($booking['Id'])); ?></td>
                                        <td><?php echo esc_html($booking['CustomerName'] ?? 'Unknown'); ?></td>
                                        <td><?php echo esc_html($booking['OrganisationName'] ?? '-'); ?></td>
                                        <td><?php echo esc_html($booking['Description'] ?? '-'); ?></td>
                                        <td><?php echo esc_html(date('j M Y', strtotime((string) ($booking['StartDate'] ?? 'now')))); ?></td>
                                        <td><?php echo esc_html($booking['RoomName'] ?? '-'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <p class="submit">
                        <button type="submit" class="button button-primary"><?php esc_html_e('Generate Invoices', 'my-village-hall'); ?></button>
                    </p>
                </form>
            </div>
        </div>

        <div class="myvh-col-40">
            <div class="myvh-card">
                <h2><?php esc_html_e('Uninvoiced By Customer', 'my-village-hall'); ?></h2>
                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Customer', 'my-village-hall'); ?></th>
                            <th><?php esc_html_e('Count', 'my-village-hall'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($uninvoiced_by_customer)): ?>
                            <tr><td colspan="2"><?php esc_html_e('No customer totals available.', 'my-village-hall'); ?></td></tr>
                        <?php else: ?>
                            <?php foreach ($uninvoiced_by_customer as $customer): ?>
                                <tr>
                                    <td><?php echo esc_html($customer['CustomerName'] ?? 'Unknown'); ?></td>
                                    <td><?php echo esc_html((string) intval($customer['UninvoicedCount'] ?? 0)); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="myvh-card" style="margin-top: 20px;">
                <h2><?php esc_html_e('Uninvoiced By Organisation', 'my-village-hall'); ?></h2>
                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Organisation', 'my-village-hall'); ?></th>
                            <th><?php esc_html_e('Count', 'my-village-hall'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($uninvoiced_by_organisation)): ?>
                            <tr><td colspan="2"><?php esc_html_e('No organisation totals available.', 'my-village-hall'); ?></td></tr>
                        <?php else: ?>
                            <?php foreach ($uninvoiced_by_organisation as $organisation): ?>
                                <tr>
                                    <td><?php echo esc_html($organisation['OrganisationName'] ?? 'Unknown'); ?></td>
                                    <td><?php echo esc_html((string) intval($organisation['UninvoicedCount'] ?? 0)); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>