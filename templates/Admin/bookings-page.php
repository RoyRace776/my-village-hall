<?php
if (!defined('ABSPATH')) exit;

if (!current_user_can('manage_myvh')) {
    wp_die(__('Permission denied', 'my-village-hall'));
}
global $myvh_container;

use MYVH\Bookings\BookingService;
use MYVH\Bookings\BookingStatus;
use MYVH\Customers\CustomerService;
use MYVH\Organisations\OrganisationService;
use MYVH\Bookings\RecurringPatternService;
use MYVH\Rooms\RoomService;


$status_filter   = isset($_GET['status'])      ? sanitize_text_field($_GET['status'])  : 'all';
$room_filter     = isset($_GET['room_id'])     ? intval($_GET['room_id'])               : 0;
$customer_filter = isset($_GET['customer_id']) ? intval($_GET['customer_id'])           : 0;

$booking_service = $myvh_container->get(BookingService::class);
$org_service     = $myvh_container->get(OrganisationService::class);
$rooms           = $myvh_container->get(RoomService::class)->get_all_with_venues();
$customers       = $myvh_container->get(CustomerService::class)->get_all();
$organisations   = $org_service->get_all();

$result = $booking_service->get_booking_list([
    'status'      => $status_filter !== 'all' ? $status_filter : '',
    'room_id'     => $room_filter,
    'customer_id' => $customer_filter,
]);

$query_args = [
    'orderby'     => 'b.StartDate',
    'order'       => 'DESC',
    'status'      => $status_filter !== 'all' ? $status_filter : '',
    'room_id'     => $room_filter,
    'customer_id' => $customer_filter,
];

$groups = $result['groups'];
$total_shown = $result['total'];
$recurring_group_count = $result['recurring_groups'];
$bookings = $booking_service->get_all_with_details($query_args);

$today = date('Y-m-d');

$status_colors = [
    BookingStatus::PENDING    => '#2271b1',
    BookingStatus::CONFIRMED  => '#46b450',
    BookingStatus::CANCELLED  => '#dc3232',
    BookingStatus::COMPLETED  => '#777',
];

$total_shown = count($bookings);
$recurring_group_count = count(array_filter($groups, fn($g) => $g['type'] === 'recurring'));

?>
<div class="wrap myvh-bookings-admin">
    <h1 class="wp-heading-inline"><?php _e('All Bookings', 'my-village-hall'); ?></h1>
    <a href="<?php echo admin_url('admin.php?page=myvh-calendar'); ?>" class="page-title-action">
        <?php _e('Calendar View', 'my-village-hall'); ?>
    </a>
    <a href="<?php echo admin_url('admin.php?page=my-village-hall&add=1'); ?>" class="page-title-action">
        <?php _e('Add New Booking', 'my-village-hall'); ?>
    </a>
    <hr class="wp-header-end">

    <?php if (isset($_GET['updated'])): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e('Booking saved successfully.', 'my-village-hall'); ?></p>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['deleted'])): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e('Booking deleted successfully.', 'my-village-hall'); ?></p>
        </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="myvh-card myvh-bookings-filters">
        <form method="get" action="<?php echo admin_url('admin.php'); ?>">
            <input type="hidden" name="page" value="my-village-hall">
            <div class="myvh-bookings-filters-row">
                <label>
                    <strong><?php _e('Status:', 'my-village-hall'); ?></strong>
                    <select name="status" class="myvh-bookings-filter-select">
                        <option value="all"       <?php selected($status_filter,'all');       ?>><?php _e('All Statuses','my-village-hall'); ?></option>
                        <option value="pending"   <?php selected($status_filter,BookingStatus::PENDING);   ?>><?php _e('Pending',     'my-village-hall'); ?></option>
                        <option value="confirmed" <?php selected($status_filter,BookingStatus::CONFIRMED); ?>><?php _e('Confirmed',   'my-village-hall'); ?></option>
                        <option value="cancelled" <?php selected($status_filter,BookingStatus::CANCELLED); ?>><?php _e('Cancelled',   'my-village-hall'); ?></option>
                        <option value="completed" <?php selected($status_filter,BookingStatus::COMPLETED); ?>><?php _e('Completed',   'my-village-hall'); ?></option>
                    </select>
                </label>
                <label>
                    <strong><?php _e('Room:', 'my-village-hall'); ?></strong>
                    <select name="room_id" class="myvh-bookings-filter-select">
                        <option value="0"><?php _e('All Rooms', 'my-village-hall'); ?></option>
                        <?php foreach ($rooms as $room): ?>
                            <option value="<?php echo $room['Id']; ?>" <?php selected($room_filter, $room['Id']); ?>>
                                <?php echo esc_html($room['Name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <strong><?php _e('Customer:', 'my-village-hall'); ?></strong>
                    <select name="customer_id" class="myvh-bookings-filter-select">
                        <option value="0"><?php _e('All Customers', 'my-village-hall'); ?></option>
                        <?php foreach ($customers as $customer): ?>
                            <option value="<?php echo $customer['Id']; ?>" <?php selected($customer_filter, $customer['Id']); ?>>
                                <?php echo esc_html($customer['Name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="submit" class="button"><?php _e('Filter', 'my-village-hall'); ?></button>
                <?php if ($status_filter !== 'all' || $room_filter || $customer_filter): ?>
                    <a href="<?php echo admin_url('admin.php?page=my-village-hall'); ?>" class="button">
                        <?php _e('Clear Filters', 'my-village-hall'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </form>

        <!-- Client-side status filter checkboxes -->
        <div class="myvh-bookings-status-checkboxes" style="margin-top: 10px;">
            <strong><?php _e('Show statuses:', 'my-village-hall'); ?></strong>
            <label style="margin-right:10px;"><input type="checkbox" class="myvh-status-filter" value="pending" checked> <?php _e('Pending', 'my-village-hall'); ?></label>
            <label style="margin-right:10px;"><input type="checkbox" class="myvh-status-filter" value="confirmed" checked> <?php _e('Confirmed', 'my-village-hall'); ?></label>
            <label style="margin-right:10px;"><input type="checkbox" class="myvh-status-filter" value="cancelled" checked> <?php _e('Cancelled', 'my-village-hall'); ?></label>
            <label style="margin-right:10px;"><input type="checkbox" class="myvh-status-filter" value="completed" checked> <?php _e('Completed', 'my-village-hall'); ?></label>
        </div>
    </div>

    <div class="myvh-card">

        <?php if (empty($bookings)): ?>
            <p>
                <?php _e('No bookings found.', 'my-village-hall'); ?>
                <a href="<?php echo admin_url('admin.php?page=my-village-hall&add=1'); ?>">
                    <?php _e('Create your first booking', 'my-village-hall'); ?>
                </a>
            </p>
        <?php else: ?>

        <!-- Expand / collapse controls – only shown when there are recurring groups -->
        <?php if ($recurring_group_count > 0): ?>
        <div class="myvh-expand-all-bar">
            <button type="button" class="button button-small" id="myvh-expand-all">
                <?php _e('Expand all recurring', 'my-village-hall'); ?>
            </button>
            <button type="button" class="button button-small" id="myvh-collapse-all">
                <?php _e('Collapse all recurring', 'my-village-hall'); ?>
            </button>
            <span style="color:#666; font-size:12px;">
                <?php printf(
                    _n('%d recurring group', '%d recurring groups', $recurring_group_count, 'my-village-hall'),
                    $recurring_group_count
                ); ?>
            </span>
        </div>
        <?php endif; ?>

        <table class="wp-list-table widefat myvh-bookings-table" id="myvh-bookings-table">
            <thead>
                <tr>
                    <th style="width:175px;"><?php _e('Date & Time', 'my-village-hall'); ?></th>
                    <th><?php _e('Customer', 'my-village-hall'); ?></th>
                    <th><?php _e('Organisation', 'my-village-hall'); ?></th>
                    <th><?php _e('Room', 'my-village-hall'); ?></th>
                    <th><?php _e('Description', 'my-village-hall'); ?></th>
                    <th style="width:105px;"><?php _e('Status', 'my-village-hall'); ?></th>
                    <th style="width:130px;"><?php _e('Actions', 'my-village-hall'); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($groups as $group_key => $group):
                $is_recurring = ($group['type'] === 'recurring');

                if ($is_recurring):
                    // ── Recurring group ───────────────────────────────────
                    $pattern   = $group['pattern'];
                    $members   = $group['bookings']; // sorted DESC (most recent first)
                    $count     = count($members);

                    // Representative booking for summary cells (first = most recent)
                    $rep = $members[0];

                    // Find next upcoming booking
                    $upcoming = null;
                    foreach (array_reverse($members) as $mb) {
                        if ($mb['StartDate'] >= $today && $mb['Status'] !== BookingStatus::CANCELLED) {
                            $upcoming = $mb;
                            break;
                        }
                    }

                    $schedule = RecurringPatternService::describe($pattern);
                    $group_id = 'rg_' . $pattern['Id'];

                    // Aggregate status: if any confirmed/pending, show that; otherwise cancelled/completed
                    $active_statuses = array_filter(array_column($members, 'Status'), fn($s) => in_array($s, [BookingStatus::CONFIRMED,BookingStatus::PENDING]));
                    $group_status    = !empty($active_statuses) ? reset($active_statuses) : ($members[0]['Status'] ?? BookingStatus::COMPLETED);
                    $group_sc        = $status_colors[$group_status] ?? '#777';
                    ?>

                    <!-- GROUP HEADER ROW -->
                    <tr class="myvh-booking-group-header" data-group="<?php echo esc_attr($group_id); ?>" data-status="<?php echo esc_attr($group_status); ?>">
                        <td>
                            <button type="button" class="myvh-group-toggle" data-group="<?php echo esc_attr($group_id); ?>" aria-expanded="false">
                                <i class="toggle-icon">▶</i>
                                <span>
                                    <?php if ($upcoming): ?>
                                        <strong><?php echo date('D j M Y', strtotime($upcoming['StartDate'])); ?></strong>
                                        <br><small style="color:#666; font-weight:normal;">
                                            <?php echo date('g:i A', strtotime($upcoming['StartTime'])); ?> –
                                            <?php echo date('g:i A', strtotime($upcoming['EndTime'])); ?>
                                        </small>
                                    <?php else: ?>
                                        <span style="color:#999;"><?php _e('No upcoming', 'my-village-hall'); ?></span>
                                    <?php endif; ?>
                                </span>
                            </button>
                        </td>
                        <td>
                            <strong><?php echo esc_html($rep['CustomerName'] ?? '—'); ?></strong>
                        </td>
                        <td><?php echo esc_html($rep['OrganisationName'] ?? '—'); ?></td>
                        <td>
                            <?php echo esc_html($rep['RoomName'] ?? '—'); ?>
                            <?php if (!empty($rep['VenueName'])): ?>
                                <br><small style="color:#666;"><?php echo esc_html($rep['VenueName']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span style="color:#2271b1;">🔄 <?php echo esc_html($schedule); ?></span>
                            <br>
                            <small style="color:#666;">
                                <?php printf(
                                    _n('%d booking', '%d bookings', $count, 'my-village-hall'),
                                    $count
                                ); ?>
                                <?php if ($upcoming): ?>
                                    &middot; <?php _e('next:', 'my-village-hall'); ?>
                                    <?php echo date('j M', strtotime($upcoming['StartDate'])); ?>
                                <?php endif; ?>
                            </small>
                            <?php if ($rep['Description']): ?>
                                <br><small style="color:#888;"><?php echo esc_html($rep['Description']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span style="color:<?php echo $group_sc; ?>;">●</span>
                            <?php echo esc_html(ucfirst($group_status)); ?>
                            <?php if (!$pattern['IsActive']): ?>
                                <br><small style="color:#999;"><?php _e('(inactive)', 'my-village-hall'); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=myvh-recurring&view=' . $pattern['Id']); ?>">
                                <?php _e('Pattern', 'my-village-hall'); ?>
                            </a> |
                            <a href="<?php echo admin_url('admin.php?page=myvh-recurring&edit=' . $pattern['Id']); ?>">
                                <?php _e('Edit', 'my-village-hall'); ?>
                            </a>
                        </td>
                    </tr>

                    <!-- CHILD ROWS -->
                    <?php foreach ($members as $b):
                        $is_past = $b['StartDate'] < $today;
                        $sc      = $status_colors[$b['Status']] ?? '#777';
                    ?>
                    <tr class="myvh-recurring-child <?php echo $is_past ? 'myvh-child-past' : ''; ?>"
                        data-group="<?php echo esc_attr($group_id); ?>" data-status="<?php echo esc_attr($b['Status']); ?>">
                        <td>
                            <?php echo date('D j M Y', strtotime($b['StartDate'])); ?>
                            <?php if ($b['StartDate'] === $today): ?>
                                <span style="background:#46b450;color:#fff;padding:1px 5px;border-radius:3px;font-size:10px;margin-left:4px;">TODAY</span>
                            <?php endif; ?>
                            <br>
                            <small style="color:#666;">
                                <?php echo date('g:i A', strtotime($b['StartTime'])); ?> –
                                <?php echo date('g:i A', strtotime($b['EndTime'])); ?>
                            </small>
                        </td>
                        <td><?php echo esc_html($b['CustomerName'] ?? '—'); ?></td>
                        <td><?php echo esc_html($b['OrganisationName'] ?? '—'); ?></td>
                        <td>
                            <?php echo esc_html($b['RoomName'] ?? '—'); ?>
                            <?php if (!empty($b['VenueName'])): ?>
                                <br><small style="color:#666;"><?php echo esc_html($b['VenueName']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($b['Description']): ?>
                                <?php echo esc_html($b['Description']); ?>
                            <?php else: ?>
                                <span style="color:#bbb;">—</span>
                            <?php endif; ?>
                            <?php if (!$b['Public']): ?>
                                <br><small style="color:#999;">🔒 <?php _e('Private', 'my-village-hall'); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span style="color:<?php echo $sc; ?>;">●</span>
                            <?php echo esc_html(ucfirst($b['Status'])); ?>
                        </td>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=my-village-hall&edit=' . $b['Id']); ?>">
                                <?php _e('Edit', 'my-village-hall'); ?>
                            </a> |
                            <a href="<?php echo admin_url('admin.php?page=my-village-hall&view=' . $b['Id']); ?>">
                                <?php _e('View', 'my-village-hall'); ?>
                            </a>
                            <?php if ($b['Status'] !== BookingStatus::CANCELLED): ?>
                                |
                                <a href="<?php echo wp_nonce_url(
                                    admin_url('admin-post.php?action=myvh_cancel_booking&id=' . $b['Id']),
                                    'myvh_cancel_booking'
                                ); ?>" style="color:#dc3232;"
                                   onclick="return confirm('<?php _e('Cancel this booking?', 'my-village-hall'); ?>');">
                                    <?php _e('Cancel', 'my-village-hall'); ?>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; // child rows ?>

                <?php else:
                    // ── Standalone booking ────────────────────────────────
                    $b       = $group['bookings'][0];
                    $is_past = $b['StartDate'] < $today;
                    $sc      = $status_colors[$b['Status']] ?? '#777';
                ?>
                    <tr <?php if ($is_past) echo 'style="opacity:0.6;"'; ?> data-status="<?php echo esc_attr($b['Status']); ?>">
                        <td>
                            <strong><?php echo date('D j M Y', strtotime($b['StartDate'])); ?></strong>
                            <?php if ($b['StartDate'] === $today): ?>
                                <span style="background:#46b450;color:#fff;padding:1px 5px;border-radius:3px;font-size:10px;margin-left:4px;">TODAY</span>
                            <?php endif; ?>
                            <br>
                            <small style="color:#666;">
                                <?php echo date('g:i A', strtotime($b['StartTime'])); ?> –
                                <?php echo date('g:i A', strtotime($b['EndTime'])); ?>
                            </small>
                        </td>
                        <td>
                            <strong><?php echo esc_html($b['CustomerName'] ?? '—'); ?></strong>
                            <?php if (!empty($b['CustomerEmail'])): ?>
                                <br><small><?php echo esc_html($b['CustomerEmail']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($b['OrganisationName'] ?? '—'); ?></td>
                        <td>
                            <?php echo esc_html($b['RoomName'] ?? '—'); ?>
                            <?php if (!empty($b['VenueName'])): ?>
                                <br><small style="color:#666;"><?php echo esc_html($b['VenueName']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($b['Description']): ?>
                                <?php echo esc_html($b['Description']); ?>
                            <?php else: ?>
                                <span style="color:#999;">—</span>
                            <?php endif; ?>
                            <?php if (!$b['Public']): ?>
                                <br><small style="color:#999;">🔒 <?php _e('Private', 'my-village-hall'); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span style="color:<?php echo $sc; ?>;">●</span>
                            <?php echo esc_html(ucfirst($b['Status'])); ?>
                        </td>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=my-village-hall&edit=' . $b['Id']); ?>">
                                <?php _e('Edit', 'my-village-hall'); ?>
                            </a> |
                            <a href="<?php echo admin_url('admin.php?page=my-village-hall&view=' . $b['Id']); ?>">
                                <?php _e('View', 'my-village-hall'); ?>
                            </a>
                            <?php if ($b['Status'] !== BookingStatus::CANCELLED): ?>
                                |
                                <a href="<?php echo wp_nonce_url(
                                    admin_url('admin-post.php?action=myvh_cancel_booking&id=' . $b['Id']),
                                    'myvh_cancel_booking'
                                ); ?>" style="color:#dc3232;"
                                   onclick="return confirm('<?php _e('Cancel this booking?', 'my-village-hall'); ?>');">
                                    <?php _e('Cancel', 'my-village-hall'); ?>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endif; ?>
            <?php endforeach; // groups ?>
            </tbody>
        </table>

        <div class="myvh-bookings-summary">
            <strong><?php _e('Total:', 'my-village-hall'); ?></strong>
            <?php echo $total_shown; ?> <?php _e('booking(s)', 'my-village-hall'); ?>
            <?php if ($recurring_group_count > 0): ?>
                &middot;
                <?php printf(
                    _n('shown as %d recurring group + standalone', 'shown as %d recurring groups + standalone', $recurring_group_count, 'my-village-hall'),
                    $recurring_group_count
                ); ?>
            <?php endif; ?>
            <?php if ($status_filter !== 'all' || $room_filter || $customer_filter): ?>
                <span style="color:#666;">(<?php _e('filtered', 'my-village-hall'); ?>)</span>
            <?php endif; ?>
        </div>

        <?php endif; // not empty bookings ?>
    </div>

    <div class="myvh-card myvh-bookings-actions">
        <h3><?php _e('Quick Actions', 'my-village-hall'); ?></h3>
        <p>
            <a href="<?php echo admin_url('admin.php?page=myvh-calendar'); ?>" class="button">
                📅 <?php _e('View Calendar', 'my-village-hall'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=my-village-hall&add=1'); ?>" class="button button-primary">
                ➕ <?php _e('New Booking', 'my-village-hall'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=myvh-recurring'); ?>" class="button">
                🔄 <?php _e('Recurring Patterns', 'my-village-hall'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=myvh-customers'); ?>" class="button">
                👥 <?php _e('Manage Customers', 'my-village-hall'); ?>
            </a>
        </p>
    </div>
</div>

<script>
jQuery(document).ready(function($) {

    // ── Toggle a single group ────────────────────────────────────────────────
    function openGroup($btn) {
        var group = $btn.data('group');
        $btn.addClass('is-open').attr('aria-expanded', 'true');
        $('[data-group="' + group + '"].myvh-recurring-child').addClass('is-visible');
    }

    function closeGroup($btn) {
        var group = $btn.data('group');
        $btn.removeClass('is-open').attr('aria-expanded', 'false');
        $('[data-group="' + group + '"].myvh-recurring-child').removeClass('is-visible');
    }

    // Click on the toggle button
    $(document).on('click', '.myvh-group-toggle', function(e) {
        e.stopPropagation();
        var $btn = $(this);
        $btn.hasClass('is-open') ? closeGroup($btn) : openGroup($btn);
    });

    // Also allow clicking the whole header row
    $(document).on('click', '.myvh-booking-group-header', function(e) {
        if ($(e.target).is('a')) return; // don't intercept link clicks
        var $btn = $(this).find('.myvh-group-toggle');
        $btn.hasClass('is-open') ? closeGroup($btn) : openGroup($btn);
    });

    // ── Expand / collapse all ────────────────────────────────────────────────
    $('#myvh-expand-all').on('click', function() {
        $('.myvh-group-toggle').each(function() { openGroup($(this)); });
    });

    $('#myvh-collapse-all').on('click', function() {
        $('.myvh-group-toggle').each(function() { closeGroup($(this)); });
    });
});
</script>
