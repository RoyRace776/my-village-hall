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
$single_uninvoiced_bookings = array_values(array_filter(
    $uninvoiced_bookings,
    static function ($booking): bool {
        return empty($booking['RecurringPatternId']);
    }
));
$recurring_uninvoiced_bookings = array_values(array_filter(
    $uninvoiced_bookings,
    static function ($booking): bool {
        return !empty($booking['RecurringPatternId']);
    }
));
$admin_recurring_booking_groups = [];
foreach ($recurring_uninvoiced_bookings as $booking) {
    $pattern_id = intval($booking['RecurringPatternId'] ?? 0);
    if ($pattern_id <= 0) {
        $pattern_id = intval($booking['Id'] ?? 0);
    }

    if (!isset($admin_recurring_booking_groups[$pattern_id])) {
        $admin_recurring_booking_groups[$pattern_id] = [
            'pattern_id' => $pattern_id,
            'bookings' => [],
        ];
    }

    $admin_recurring_booking_groups[$pattern_id]['bookings'][] = $booking;
}
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

                    <h2 class="nav-tab-wrapper" style="margin-bottom: 12px;">
                        <a href="#" class="nav-tab nav-tab-active myvh-admin-booking-type-tab" data-booking-type-tab="single">
                            <?php echo esc_html(sprintf(__('Single Bookings (%d)', 'my-village-hall'), intval(count($single_uninvoiced_bookings)))); ?>
                        </a>
                        <a href="#" class="nav-tab myvh-admin-booking-type-tab" data-booking-type-tab="recurring">
                            <?php echo esc_html(sprintf(__('Recurring Bookings (%d)', 'my-village-hall'), intval(count($recurring_uninvoiced_bookings)))); ?>
                        </a>
                    </h2>

                    <div class="myvh-admin-booking-type-panel" data-booking-type-panel="single">
                        <p>
                            <button type="button" class="button myvh-admin-select-all" data-booking-type="single"><?php esc_html_e('Select all', 'my-village-hall'); ?></button>
                            <button type="button" class="button myvh-admin-clear-all" data-booking-type="single"><?php esc_html_e('Clear', 'my-village-hall'); ?></button>
                        </p>

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
                                <?php if (empty($single_uninvoiced_bookings)): ?>
                                    <tr>
                                        <td colspan="7"><?php esc_html_e('No uninvoiced single bookings were found.', 'my-village-hall'); ?></td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($single_uninvoiced_bookings as $booking): ?>
                                        <tr>
                                            <td><input type="checkbox" name="booking_ids[]" value="<?php echo esc_attr((string) intval($booking['Id'])); ?>" class="myvh-admin-uninvoiced-checkbox" data-booking-type="single"></td>
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
                    </div>

                    <div class="myvh-admin-booking-type-panel" data-booking-type-panel="recurring" hidden>
                        <p>
                            <button type="button" class="button myvh-admin-select-all" data-booking-type="recurring"><?php esc_html_e('Select all', 'my-village-hall'); ?></button>
                            <button type="button" class="button myvh-admin-clear-all" data-booking-type="recurring"><?php esc_html_e('Clear', 'my-village-hall'); ?></button>
                        </p>

                        <table class="wp-list-table widefat striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Select', 'my-village-hall'); ?></th>
                                    <th><?php esc_html_e('Booking', 'my-village-hall'); ?></th>
                                    <th><?php esc_html_e('Pattern', 'my-village-hall'); ?></th>
                                    <th><?php esc_html_e('Customer', 'my-village-hall'); ?></th>
                                    <th><?php esc_html_e('Organisation', 'my-village-hall'); ?></th>
                                    <th><?php esc_html_e('Description', 'my-village-hall'); ?></th>
                                    <th><?php esc_html_e('Date', 'my-village-hall'); ?></th>
                                    <th><?php esc_html_e('Room', 'my-village-hall'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($admin_recurring_booking_groups)): ?>
                                    <tr>
                                        <td colspan="8"><?php esc_html_e('No uninvoiced recurring bookings were found.', 'my-village-hall'); ?></td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($admin_recurring_booking_groups as $group): ?>
                                        <?php $group_id = intval($group['pattern_id']); ?>
                                        <tr>
                                            <td colspan="8" style="background:#f6f7f7;">
                                                <button
                                                    type="button"
                                                    class="button-link myvh-admin-recurring-group-toggle"
                                                    data-recurring-group="<?php echo esc_attr((string) $group_id); ?>"
                                                    aria-expanded="false"
                                                    style="font-weight:600; text-decoration:none;"
                                                >
                                                    <?php
                                                    echo esc_html(sprintf(
                                                        'Pattern #%d (%d booking%s)',
                                                        $group_id,
                                                        count($group['bookings']),
                                                        count($group['bookings']) === 1 ? '' : 's'
                                                    ));
                                                    ?>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php foreach ($group['bookings'] as $booking): ?>
                                            <tr data-recurring-group-child="<?php echo esc_attr((string) $group_id); ?>" hidden>
                                                <td><input type="checkbox" name="booking_ids[]" value="<?php echo esc_attr((string) intval($booking['Id'])); ?>" class="myvh-admin-uninvoiced-checkbox" data-booking-type="recurring" disabled></td>
                                                <td>#<?php echo esc_html((string) intval($booking['Id'])); ?></td>
                                                <td>#<?php echo esc_html((string) intval($booking['RecurringPatternId'] ?? 0)); ?></td>
                                                <td><?php echo esc_html($booking['CustomerName'] ?? 'Unknown'); ?></td>
                                                <td><?php echo esc_html($booking['OrganisationName'] ?? '-'); ?></td>
                                                <td><?php echo esc_html($booking['Description'] ?? '-'); ?></td>
                                                <td><?php echo esc_html(date('j M Y', strtotime((string) ($booking['StartDate'] ?? 'now')))); ?></td>
                                                <td><?php echo esc_html($booking['RoomName'] ?? '-'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

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

<script>
document.addEventListener('DOMContentLoaded', function () {
    const tabs = Array.from(document.querySelectorAll('.myvh-admin-booking-type-tab'));
    const panels = Array.from(document.querySelectorAll('.myvh-admin-booking-type-panel'));
    if (!tabs.length || !panels.length) {
        return;
    }

    function activateTab(tabKey) {
        tabs.forEach(function (tab) {
            const isActive = tab.getAttribute('data-booking-type-tab') === tabKey;
            tab.classList.toggle('nav-tab-active', isActive);
        });

        panels.forEach(function (panel) {
            const isActive = panel.getAttribute('data-booking-type-panel') === tabKey;
            panel.hidden = !isActive;

            const checkboxes = Array.from(panel.querySelectorAll('.myvh-admin-uninvoiced-checkbox'));
            checkboxes.forEach(function (checkbox) {
                checkbox.disabled = !isActive;
            });
        });
    }

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function (event) {
            event.preventDefault();
            activateTab(tab.getAttribute('data-booking-type-tab') || 'single');
        });
    });

    document.addEventListener('click', function (event) {
        const recurringGroupToggle = event.target.closest('.myvh-admin-recurring-group-toggle');
        if (recurringGroupToggle) {
            event.preventDefault();

            const groupId = recurringGroupToggle.getAttribute('data-recurring-group') || '';
            if (groupId) {
                const isExpanded = recurringGroupToggle.getAttribute('aria-expanded') === 'true';
                recurringGroupToggle.setAttribute('aria-expanded', isExpanded ? 'false' : 'true');

                const groupedRows = Array.from(document.querySelectorAll('[data-recurring-group-child="' + groupId + '"]'));
                groupedRows.forEach(function (row) {
                    row.hidden = isExpanded;
                });
            }

            return;
        }

        const selectAllButton = event.target.closest('.myvh-admin-select-all');
        const clearAllButton = event.target.closest('.myvh-admin-clear-all');
        if (!selectAllButton && !clearAllButton) {
            return;
        }

        const bookingType = (selectAllButton || clearAllButton).getAttribute('data-booking-type');
        const checkboxes = Array.from(document.querySelectorAll('.myvh-admin-uninvoiced-checkbox[data-booking-type="' + bookingType + '"]:not(:disabled)'));

        if (selectAllButton) {
            checkboxes.forEach(function (checkbox) {
                checkbox.checked = true;
            });
        }

        if (clearAllButton) {
            checkboxes.forEach(function (checkbox) {
                checkbox.checked = false;
            });
        }
    });

    activateTab('single');
});
</script>