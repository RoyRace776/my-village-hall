<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('manage_myvh')) {
    wp_die(__('Permission denied', 'my-village-hall'));
}

global $myvh_container;

$edit_id = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
$view_id = isset($_GET['view']) ? intval($_GET['view']) : 0;
$booking_id = $edit_id ?: $view_id;

$booking_service           = $myvh_container->get(MYVH_Booking_Service::class);
$customer_service          = $myvh_container->get(MYVH_Customer_Service::class);
$room_service              = $myvh_container->get(MYVH_Room_Service::class);
$addon_service             = $myvh_container->get(MYVH_Addon_Service::class);
$recurring_pattern_service = $myvh_container->get(MYVH_Recurring_Pattern_Service::class);

$edit_booking = $booking_id ? $booking_service->get_by_id($booking_id) : null;
$is_view_mode = $view_id > 0;

$customers  = $customer_service->get_all();
$rooms      = $room_service->get_all_with_venues();
$all_addons = $addon_service->get_all(['orderby' => 'DisplayOrder', 'order' => 'ASC']);

// Active addons only for the form
$available_addons = array_filter($all_addons ?? [], fn($a) => !empty($a['IsActive']));

// Existing booking addons (for edit/view mode)
$booking_addons = $booking_id
    ? $booking_service->get_addons_for_booking($booking_id)
    : [];

// Index existing addons by AddonId for easy lookup in the form
$selected_addon_map = [];
foreach ($booking_addons as $ba) {
    $selected_addon_map[$ba['AddonId']] = $ba;
}

// Get recurring pattern if exists
$recurring_pattern = null;
if ($edit_booking && $edit_booking['RecurringPatternId']) {
    $recurring_pattern = $recurring_pattern_service->get($edit_booking['RecurringPatternId']);
}

if ($edit_booking) {
    $customer = $customer_service->get($edit_booking['CustomerId']);
    $room = $room_service->get($edit_booking['RoomId']);
}

$status_colors = [
    BookingStatus::PENDING => '#2271b1',
    BookingStatus::CONFIRMED => '#46b450',
    BookingStatus::CANCELLED => '#dc3232',
    BookingStatus::COMPLETED => '#999'
];
?>

<div class="wrap">
    <?php if ($is_view_mode): ?>
        <h1><?php _e('View Booking', 'my-village-hall'); ?></h1>
    <?php elseif ($edit_booking): ?>
        <h1><?php _e('Edit Booking', 'my-village-hall'); ?></h1>
    <?php else: ?>
        <h1><?php _e('Add New Booking', 'my-village-hall'); ?></h1>
    <?php endif; ?>

    <hr class="wp-header-end">

    <?php if (isset($_GET['error'])): ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html($_GET['error']); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($is_view_mode): ?>
        <!-- VIEW MODE -->
        <div class="myvh-row">
            <div class="myvh-col-60">
                <div class="myvh-card">
                    <h2><?php _e('Booking Details', 'my-village-hall'); ?></h2>

                    <?php

                    $status_color = $status_colors[$edit_booking['Status']] ?? '#999';
                    ?>

                    <table class="form-table">
                        <tr>
                            <th><?php _e('Booking ID', 'my-village-hall'); ?></th>
                            <td>#<?php echo $edit_booking['Id']; ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Status', 'my-village-hall'); ?></th>
                            <td>
                                <span style="color: <?php echo $status_color; ?>; font-weight: bold;">
                                    ● <?php echo esc_html(ucfirst($edit_booking['Status'])); ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Customer', 'my-village-hall'); ?></th>
                            <td>
                                <strong><?php echo $customer ? esc_html($customer['Name']) : __('Unknown', 'my-village-hall'); ?></strong>
                                <?php if ($customer): ?>
                                    <br><?php echo esc_html($customer['Email']); ?>
                                    <?php if ($customer['PhoneNumber']): ?>
                                        <br><?php echo esc_html($customer['PhoneNumber']); ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Room', 'my-village-hall'); ?></th>
                            <td>
                                <strong><?php echo $room ? esc_html($room['Name']) : __('Unknown', 'my-village-hall'); ?></strong>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Date', 'my-village-hall'); ?></th>
                            <td>
                                <strong><?php echo date('l, F j, Y', strtotime($edit_booking['StartDate'])); ?></strong>
                                <?php if ($edit_booking['StartDate'] !== $edit_booking['EndDate']): ?>
                                    to <?php echo date('l, F j, Y', strtotime($edit_booking['EndDate'])); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Time', 'my-village-hall'); ?></th>
                            <td>
                                <?php echo date('g:i A', strtotime($edit_booking['StartTime'])); ?> -
                                <?php echo date('g:i A', strtotime($edit_booking['EndTime'])); ?>

                                <?php
                                $duration_start = new DateTime($edit_booking['StartTime']);
                                $duration_end = new DateTime($edit_booking['EndTime']);
                                $duration = $duration_start->diff($duration_end);
                                $hours = $duration->h + ($duration->days * 24);
                                ?>
                                <br><small style="color: #666;">
                                    (<?php echo $hours; ?> <?php echo $hours == 1 ? __('hour', 'my-village-hall') : __('hours', 'my-village-hall'); ?>
                                    <?php if ($duration->i > 0): ?>
                                        <?php echo $duration->i; ?> <?php _e('minutes', 'my-village-hall'); ?>
                                    <?php endif; ?>)
                                </small>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Description', 'my-village-hall'); ?></th>
                            <td><?php echo $edit_booking['Description'] ? esc_html($edit_booking['Description']) : '<em>' . __('None', 'my-village-hall') . '</em>'; ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Visibility', 'my-village-hall'); ?></th>
                            <td>
                                <?php if ($edit_booking['Public']): ?>
                                    🌐 <?php _e('Public (visible on calendar)', 'my-village-hall'); ?>
                                <?php else: ?>
                                    🔒 <?php _e('Private (hidden from public)', 'my-village-hall'); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if ($recurring_pattern): ?>
                        <tr>
                            <th><?php _e('Recurring Pattern', 'my-village-hall'); ?></th>
                            <td>
                                🔄 <?php echo esc_html(ucfirst($recurring_pattern['RecurrenceType'])); ?>
                                <?php if ($recurring_pattern['RecurrenceInterval'] > 1): ?>
                                    (every <?php echo $recurring_pattern['RecurrenceInterval']; ?>)
                                <?php endif; ?>
                                <br>
                                <a href="<?php echo admin_url('admin.php?page=myvh-recurring'); ?>">
                                    <?php _e('View Recurring Patterns', 'my-village-hall'); ?>
                                </a>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <th><?php _e('Created', 'my-village-hall'); ?></th>
                            <td><?php echo date('F j, Y g:i A', strtotime($edit_booking['Created'])); ?></td>
                        </tr>
                    </table>

                    <p>
                        <a href="<?php echo admin_url('admin.php?page=my-village-hall&edit=' . $booking_id); ?>" class="button button-primary">
                            <?php _e('Edit Booking', 'my-village-hall'); ?>
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=my-village-hall'); ?>" class="button">
                            <?php _e('Back to All Bookings', 'my-village-hall'); ?>
                        </a>
                        <?php if ($edit_booking['Status'] !== BookingStatus::CANCELLED): ?>
                            <a href="<?php echo wp_nonce_url(
                                admin_url('admin-post.php?action=myvh_cancel_booking&id=' . $booking_id),
                                'myvh_cancel_booking'
                            ); ?>" class="button" style="color: #dc3232;" onclick="return confirm('<?php _e('Cancel this booking?', 'my-village-hall'); ?>');">
                                <?php _e('Cancel Booking', 'my-village-hall'); ?>
                            </a>
                        <?php endif; ?>
                    </p>
                </div>
            </div>

            <?php if (!empty($booking_addons)): ?>
            <div class="myvh-col-40">
                <div class="myvh-card">
                    <h2><?php _e('Add-ons', 'my-village-hall'); ?></h2>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th><?php _e('Add-on', 'my-village-hall'); ?></th>
                                <th><?php _e('Qty', 'my-village-hall'); ?></th>
                                <th><?php _e('Unit Price', 'my-village-hall'); ?></th>
                                <th><?php _e('Total', 'my-village-hall'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $addons_total = 0; ?>
                            <?php foreach ($booking_addons as $ba): ?>
                                <?php $addons_total += floatval($ba['TotalAmount']); ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($ba['AddonName']); ?></strong>
                                        <?php if ($ba['Description']): ?>
                                            <br><small style="color:#666;"><?php echo esc_html($ba['Description']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html($ba['Quantity']); ?></td>
                                    <td>£<?php echo number_format($ba['UnitPrice'], 2); ?></td>
                                    <td><strong>£<?php echo number_format($ba['TotalAmount'], 2); ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="3"><?php _e('Add-ons Total', 'my-village-hall'); ?></th>
                                <th>£<?php echo number_format($addons_total, 2); ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>

    <?php else: ?>
        <!-- EDIT/ADD MODE -->
        <div class="myvh-card">
            <?php if (empty($customers)): ?>
                <div class="notice notice-warning">
                    <p>
                        <?php _e('You need to create at least one customer before making bookings.', 'my-village-hall'); ?>
                        <a href="<?php echo admin_url('admin.php?page=myvh-customers'); ?>" class="button button-small">
                            <?php _e('Add Customer', 'my-village-hall'); ?>
                        </a>
                    </p>
                </div>
            <?php elseif (empty($rooms)): ?>
                <div class="notice notice-warning">
                    <p>
                        <?php _e('You need to create at least one room before making bookings.', 'my-village-hall'); ?>
                        <a href="<?php echo admin_url('admin.php?page=myvh-rooms'); ?>" class="button button-small">
                            <?php _e('Add Room', 'my-village-hall'); ?>
                        </a>
                    </p>
                </div>
            <?php else: ?>

            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" id="booking-form">
                <input type="hidden" name="action" value="myvh_save_booking">
                <?php wp_nonce_field('myvh_save_booking'); ?>
                <?php if ($edit_booking): ?>
                    <input type="hidden" name="booking_id" value="<?php echo $edit_booking['Id']; ?>">
                <?php endif; ?>

                <div class="myvh-row">
                    <!-- Left Column -->
                    <div class="myvh-col-60">
                        <h2><?php _e('Booking Information', 'my-village-hall'); ?></h2>

                        <table class="form-table">
                            <tr>
                                <th><?php _e('Customer', 'my-village-hall'); ?> *</th>
                                <td>
                                    <select name="customer_id" required class="regular-text">
                                        <option value=""><?php _e('Select Customer', 'my-village-hall'); ?></option>
                                        <?php foreach ($customers as $customer): ?>
                                            <option value="<?php echo $customer['Id']; ?>"
                                                <?php selected($edit_booking && $edit_booking['CustomerId'] == $customer['Id']); ?>>
                                                <?php echo esc_html($customer['Name'] . ' (' . $customer['Email'] . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description">
                                        <?php _e('Or', 'my-village-hall'); ?>
                                        <a href="<?php echo admin_url('admin.php?page=myvh-customers&add=1'); ?>" target="_blank">
                                            <?php _e('add a new customer', 'my-village-hall'); ?>
                                        </a>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th><?php _e('Room', 'my-village-hall'); ?> *</th>
                                <td>
                                    <select name="room_id" required class="regular-text" id="room-select">
                                        <option value=""><?php _e('Select Room', 'my-village-hall'); ?></option>
                                        <?php
                                        $current_venue = '';
                                        foreach ($rooms as $room):
                                            if ($current_venue !== $room['VenueName']):
                                                if ($current_venue !== '') echo '</optgroup>';
                                                echo '<optgroup label="' . esc_attr($room['VenueName']) . '">';
                                                $current_venue = $room['VenueName'];
                                            endif;
                                        ?>
                                            <option value="<?php echo $room['Id']; ?>"
                                                data-opening="<?php echo esc_attr($room['OpeningTime']); ?>"
                                                data-closing="<?php echo esc_attr($room['ClosingTime']); ?>"
                                                <?php selected($edit_booking && $edit_booking['RoomId'] == $room['Id']); ?>>
                                                <?php echo esc_html($room['Name']); ?>
                                                <?php if ($room['Capacity']): ?>
                                                    (<?php echo $room['Capacity']; ?> people)
                                                <?php endif; ?>
                                            </option>
                                        <?php
                                        endforeach;
                                        if ($current_venue !== '') echo '</optgroup>';
                                        ?>
                                    </select>
                                </td>
                            </tr>

                            <tr>
                                <th><?php _e('Start Date', 'my-village-hall'); ?> *</th>
                                <td>
                                    <input type="date" name="start_date" required class="regular-text"
                                        value="<?php echo $edit_booking ? esc_attr($edit_booking['StartDate']) : date('Y-m-d'); ?>"
                                        min="<?php echo date('Y-m-d'); ?>">
                                </td>
                            </tr>

                            <tr>
                                <th><?php _e('End Date', 'my-village-hall'); ?></th>
                                <td>
                                    <input type="date" name="end_date" class="regular-text"
                                        value="<?php echo $edit_booking ? esc_attr($edit_booking['EndDate']) : ''; ?>">
                                    <p class="description"><?php _e('Leave blank for same-day booking', 'my-village-hall'); ?></p>
                                </td>
                            </tr>

                            <tr>
                                <th><?php _e('Start Time', 'my-village-hall'); ?> *</th>
                                <td>
                                    <input type="time" name="start_time" required class="regular-text" id="start-time"
                                        value="<?php echo $edit_booking ? esc_attr($edit_booking['StartTime']) : '09:00'; ?>">
                                </td>
                            </tr>

                            <tr>
                                <th><?php _e('End Time', 'my-village-hall'); ?> *</th>
                                <td>
                                    <input type="time" name="end_time" required class="regular-text" id="end-time"
                                        value="<?php echo $edit_booking ? esc_attr($edit_booking['EndTime']) : '17:00'; ?>">
                                    <p class="description" id="duration-display"></p>
                                </td>
                            </tr>

                            <tr>
                                <th><?php _e('Description', 'my-village-hall'); ?></th>
                                <td>
                                    <textarea name="description" class="large-text" rows="3"
                                        placeholder="<?php _e('Purpose of the booking, event details, etc.', 'my-village-hall'); ?>"><?php echo $edit_booking ? esc_textarea($edit_booking['Description']) : ''; ?></textarea>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Right Column -->
                    <div class="myvh-col-40">
                        <h2><?php _e('Booking Options', 'my-village-hall'); ?></h2>

                        <table class="form-table">
                            <tr>
                                <th><?php _e('Status', 'my-village-hall'); ?></th>
                                <td>
                                    <select name="status" class="regular-text">
                                        <option value="pending" <?php selected($edit_booking && $edit_booking['Status'] == BookingStatus::PENDING); ?>>
                                            <?php _e('Pending', 'my-village-hall'); ?>
                                        </option>
                                        <option value="confirmed" <?php selected(!$edit_booking || $edit_booking['Status'] == BookingStatus::CONFIRMED); ?>>
                                            <?php _e('Confirmed', 'my-village-hall'); ?>
                                        </option>
                                        <option value="cancelled" <?php selected($edit_booking && $edit_booking['Status'] == BookingStatus::CANCELLED); ?>>
                                            <?php _e('Cancelled', 'my-village-hall'); ?>
                                        </option>
                                        <option value="completed" <?php selected($edit_booking && $edit_booking['Status'] == BookingStatus::COMPLETED); ?>>
                                            <?php _e('Completed', 'my-village-hall'); ?>
                                        </option>
                                    </select>
                                </td>
                            </tr>

                            <tr>
                                <th><?php _e('Visibility', 'my-village-hall'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="public" value="1"
                                            <?php checked(!$edit_booking || $edit_booking['Public']); ?>>
                                        <?php _e('Show on public calendar', 'my-village-hall'); ?>
                                    </label>
                                    <p class="description"><?php _e('Uncheck to hide from public view', 'my-village-hall'); ?></p>
                                </td>
                            </tr>

                            <?php if (!$edit_booking): ?>
                            <tr>
                                <th><?php _e('Recurring', 'my-village-hall'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="is_recurring" value="1" id="is-recurring">
                                        <?php _e('Make this a recurring booking', 'my-village-hall'); ?>
                                    </label>
                                </td>
                            </tr>
                            </table>

                            <div id="recurring-options" style="display: none; margin-top: 20px; padding: 15px; background: #f9f9f9; border-radius: 4px;">
                                <h3><?php _e('Recurring Pattern', 'my-village-hall'); ?></h3>

                                <table class="form-table">
                                    <tr>
                                        <th><?php _e('Repeat', 'my-village-hall'); ?></th>
                                        <td>
                                            <select name="recurrence_type" class="regular-text" id="bf-rec-type">
                                                <option value="daily"><?php _e('Daily', 'my-village-hall'); ?></option>
                                                <option value="weekly" selected><?php _e('Weekly', 'my-village-hall'); ?></option>
                                                <option value="monthly"><?php _e('Monthly (same date)', 'my-village-hall'); ?></option>
                                                <option value="monthly_day"><?php _e('Monthly (specific weekday)', 'my-village-hall'); ?></option>
                                                <option value="yearly"><?php _e('Yearly', 'my-village-hall'); ?></option>
                                            </select>
                                        </td>
                                    </tr>

                                    <tr id="bf-interval-row">
                                        <th><?php _e('Every', 'my-village-hall'); ?></th>
                                        <td>
                                            <input type="number" name="recurrence_interval" value="1" min="1" max="52" class="small-text">
                                            <span id="interval-label"><?php _e('week(s)', 'my-village-hall'); ?></span>
                                        </td>
                                    </tr>

                                    <tr id="bf-monthly-day-row" style="display:none;">
                                        <th><?php _e('On the…', 'my-village-hall'); ?></th>
                                        <td>
                                            <select name="recurrence_week">
                                                <option value="1"><?php _e('1st', 'my-village-hall'); ?></option>
                                                <option value="2"><?php _e('2nd', 'my-village-hall'); ?></option>
                                                <option value="3"><?php _e('3rd', 'my-village-hall'); ?></option>
                                                <option value="4"><?php _e('4th', 'my-village-hall'); ?></option>
                                                <option value="last"><?php _e('Last', 'my-village-hall'); ?></option>
                                            </select>
                                            <select name="recurrence_day">
                                                <?php
                                                $days = ['monday'=>'Monday','tuesday'=>'Tuesday','wednesday'=>'Wednesday',
                                                         'thursday'=>'Thursday','friday'=>'Friday','saturday'=>'Saturday','sunday'=>'Sunday'];
                                                // Pre-select the weekday matching the booking's start date
                                                $booking_dow = strtolower(date('l', strtotime($edit_booking ? $edit_booking['StartDate'] : 'today')));
                                                foreach ($days as $v => $l): ?>
                                                    <option value="<?php echo $v; ?>" <?php selected($booking_dow, $v); ?>><?php echo esc_html($l); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <span><?php _e('of every', 'my-village-hall'); ?></span>
                                            <input type="number" name="recurrence_interval_md" value="1" min="1" max="24" class="small-text">
                                            <span><?php _e('month(s)', 'my-village-hall'); ?></span>
                                            <p class="description" id="bf-rec-preview" style="color:#2271b1;font-style:italic;"></p>
                                        </td>
                                    </tr>

                                    <tr>
                                        <th><?php _e('Ends', 'my-village-hall'); ?></th>
                                        <td>
                                            <label>
                                                <input type="radio" name="recurrence_end_type" value="date" checked>
                                                <?php _e('On', 'my-village-hall'); ?>
                                                <input type="date" name="recurrence_end_date" class="regular-text"
                                                    value="<?php echo date('Y-m-d', strtotime('+1 year')); ?>">
                                            </label>
                                            <br>
                                            <label>
                                                <input type="radio" name="recurrence_end_type" value="count">
                                                <?php _e('After', 'my-village-hall'); ?>
                                                <input type="number" name="max_occurrences" value="12" min="1" max="365" class="small-text">
                                                <?php _e('occurrences', 'my-village-hall'); ?>
                                            </label>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <table class="form-table">
                            <?php endif; ?>
                        </table>

                        <?php if ($edit_booking && $recurring_pattern): ?>
                        <div style="padding: 15px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; margin-top: 20px;">
                            <p style="margin: 0;">
                                <strong>⚠️ <?php _e('Note:', 'my-village-hall'); ?></strong>
                                <?php _e('This is part of a recurring pattern. Changes will only affect this booking.', 'my-village-hall'); ?>
                                <a href="<?php echo admin_url('admin.php?page=myvh-recurring'); ?>">
                                    <?php _e('Manage pattern', 'my-village-hall'); ?>
                                </a>
                            </p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ADD-ONS PANEL -->
                <?php if (!empty($available_addons)): ?>
                <div class="myvh-card" style="margin-top: 20px;">
                    <h2><?php _e('Add-ons', 'my-village-hall'); ?></h2>
                    <p class="description" style="margin-bottom:15px;">
                        <?php _e('Select any add-ons required for this booking.', 'my-village-hall'); ?>
                    </p>
                    <table class="widefat" id="myvh-addons-table">
                        <thead>
                            <tr>
                                <th style="width:30px;"></th>
                                <th><?php _e('Add-on', 'my-village-hall'); ?></th>
                                <th style="width:110px;"><?php _e('Unit Price (£)', 'my-village-hall'); ?></th>
                                <th style="width:90px;"><?php _e('Quantity', 'my-village-hall'); ?></th>
                                <th style="width:100px;"><?php _e('Total', 'my-village-hall'); ?></th>
                                <th><?php _e('Note', 'my-village-hall'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($available_addons as $i => $addon):
                            $existing   = $selected_addon_map[$addon['Id']] ?? null;
                            $is_checked = !empty($existing);
                            $quantity   = $existing ? floatval($existing['Quantity'])  : 1;
                            $unit_price = $existing ? floatval($existing['UnitPrice']) : floatval($addon['Price']);
                            $note       = $existing ? esc_attr($existing['Description']) : '';
                        ?>
                        <tr class="myvh-addon-row<?php echo $is_checked ? ' addon-selected' : ''; ?>">
                            <td>
                                <input type="checkbox"
                                    class="myvh-addon-checkbox"
                                    value="1"
                                    <?php checked($is_checked); ?>>
                                <input type="hidden" name="addons[<?php echo $i; ?>][addon_id]"   value="<?php echo intval($addon['Id']); ?>">
                                <input type="hidden" name="addons[<?php echo $i; ?>][enabled]"    class="myvh-addon-enabled" value="<?php echo $is_checked ? '1' : '0'; ?>">
                            </td>
                            <td>
                                <strong><?php echo esc_html($addon['Name']); ?></strong>
                                <?php if ($addon['Description']): ?>
                                    <br><small style="color:#777;"><?php echo esc_html($addon['Description']); ?></small>
                                <?php endif; ?>
                                <br><small style="color:#999;"><?php echo esc_html(ucfirst(str_replace('_', ' ', $addon['ChargeType']))); ?></small>
                            </td>
                            <td>
                                <input type="number" step="0.01" min="0"
                                    name="addons[<?php echo $i; ?>][unit_price]"
                                    class="small-text myvh-addon-price"
                                    value="<?php echo number_format($unit_price, 2, '.', ''); ?>"
                                    <?php echo !$is_checked ? 'disabled' : ''; ?>>
                            </td>
                            <td>
                                <input type="number" step="0.5" min="0.5"
                                    name="addons[<?php echo $i; ?>][quantity]"
                                    class="small-text myvh-addon-qty"
                                    value="<?php echo esc_attr($quantity); ?>"
                                    <?php echo !$is_checked ? 'disabled' : ''; ?>>
                            </td>
                            <td class="myvh-addon-total" style="font-weight:bold; white-space:nowrap;">
                                <?php echo $is_checked ? '£' . number_format($unit_price * $quantity, 2) : '—'; ?>
                            </td>
                            <td>
                                <input type="text"
                                    name="addons[<?php echo $i; ?>][description]"
                                    class="regular-text"
                                    placeholder="<?php _e('Optional note…', 'my-village-hall'); ?>"
                                    value="<?php echo $note; ?>"
                                    <?php echo !$is_checked ? 'disabled' : ''; ?>>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="4" style="text-align:right; padding-right:10px;"><?php _e('Add-ons Total:', 'my-village-hall'); ?></th>
                                <th id="myvh-addons-grand-total" style="font-weight:bold; white-space:nowrap;">£0.00</th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php endif; ?>

                <p class="submit">
                    <button type="submit" class="button button-primary button-large">
                        <?php echo $edit_booking ? __('Update Booking', 'my-village-hall') : __('Create Booking', 'my-village-hall'); ?>
                    </button>
                    <a href="<?php echo admin_url('admin.php?page=my-village-hall'); ?>" class="button button-large">
                        <?php _e('Cancel', 'my-village-hall'); ?>
                    </a>
                </p>

            <?php endif; ?>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // ── Add-ons panel ────────────────────────────────────────────
            function recalcAddonRow($row) {
                var price = parseFloat($row.find('.myvh-addon-price').val()) || 0;
                var qty   = parseFloat($row.find('.myvh-addon-qty').val())   || 0;
                var total = price * qty;
                $row.find('.myvh-addon-total').text(total > 0 ? '£' + total.toFixed(2) : '—');
            }

            function recalcGrandTotal() {
                var grand = 0;
                $('.myvh-addon-row.addon-selected').each(function() {
                    var price = parseFloat($(this).find('.myvh-addon-price').val()) || 0;
                    var qty   = parseFloat($(this).find('.myvh-addon-qty').val())   || 0;
                    grand += price * qty;
                });
                $('#myvh-addons-grand-total').text('£' + grand.toFixed(2));
            }

            // Toggle addon row on/off
            $(document).on('change', '.myvh-addon-checkbox', function() {
                var $row     = $(this).closest('.myvh-addon-row');
                var enabled  = this.checked;
                $row.toggleClass('addon-selected', enabled);
                $row.find('.myvh-addon-price, .myvh-addon-qty, input[type="text"]').prop('disabled', !enabled);
                $row.find('.myvh-addon-enabled').val(enabled ? '1' : '0');
                recalcAddonRow($row);
                recalcGrandTotal();
            });

            // Recalc on price / qty changes
            $(document).on('input change', '.myvh-addon-price, .myvh-addon-qty', function() {
                var $row = $(this).closest('.myvh-addon-row');
                recalcAddonRow($row);
                recalcGrandTotal();
            });

            // Initial totals
            $('.myvh-addon-row.addon-selected').each(function() { recalcAddonRow($(this)); });
            recalcGrandTotal();
            // ────────────────────────────────────────────────────────────
            // Show/hide recurring options
            $('#is-recurring').on('change', function() {
                $('#recurring-options').toggle(this.checked);
            });

            // Update interval label / show monthly_day row
            var intervalLabels = {
                'daily':       '<?php _e('day(s)', 'my-village-hall'); ?>',
                'weekly':      '<?php _e('week(s)', 'my-village-hall'); ?>',
                'monthly':     '<?php _e('month(s)', 'my-village-hall'); ?>',
                'monthly_day': '',
                'yearly':      '<?php _e('year(s)', 'my-village-hall'); ?>'
            };
            var ordinalLabels = {'1':'1st','2':'2nd','3':'3rd','4':'4th','last':'last'};

            function syncBfRecType() {
                var t = $('#bf-rec-type').val();
                var isMD = (t === 'monthly_day');
                $('#bf-interval-row').toggle(!isMD);
                $('#bf-monthly-day-row').toggle(isMD);
                $('#interval-label').text(intervalLabels[t] || '');
                if (isMD) $('input[name="recurrence_interval"]').val($('input[name="recurrence_interval_md"]').val());
                updateBfPreview();
            }

            function updateBfPreview() {
                if ($('#bf-rec-type').val() !== 'monthly_day') { $('#bf-rec-preview').text(''); return; }
                var week = ordinalLabels[$('select[name="recurrence_week"]').val()] || '';
                var day  = $('select[name="recurrence_day"] option:selected').text();
                var n    = parseInt($('input[name="recurrence_interval_md"]').val()) || 1;
                var suffix = n > 1 ? ', every ' + n + ' months' : '';
                $('#bf-rec-preview').text('e.g. ' + week + ' ' + day + ' of the month' + suffix);
            }

            $('input[name="recurrence_interval_md"]').on('input', function() {
                $('input[name="recurrence_interval"]').val($(this).val());
                updateBfPreview();
            });
            $('select[name="recurrence_week"], select[name="recurrence_day"]').on('change', updateBfPreview);
            $('#bf-rec-type').on('change', syncBfRecType);

            // Calculate and display duration
            function updateDuration() {
                const startTime = $('#start-time').val();
                const endTime = $('#end-time').val();

                if (startTime && endTime) {
                    const start = new Date('2000-01-01 ' + startTime);
                    const end = new Date('2000-01-01 ' + endTime);
                    const diff = (end - start) / 1000 / 60; // minutes

                    if (diff > 0) {
                        const hours = Math.floor(diff / 60);
                        const minutes = diff % 60;
                        let durationText = '';

                        if (hours > 0) {
                            durationText += hours + ' <?php _e('hour(s)', 'my-village-hall'); ?> ';
                        }
                        if (minutes > 0) {
                            durationText += minutes + ' <?php _e('minutes', 'my-village-hall'); ?>';
                        }

                        $('#duration-display').text('<?php _e('Duration:', 'my-village-hall'); ?> ' + durationText.trim());
                    } else {
                        $('#duration-display').text('<?php _e('End time must be after start time', 'my-village-hall'); ?>').css('color', 'red');
                    }
                }
            }

            $('#start-time, #end-time').on('change', updateDuration);
            updateDuration(); // Initial calculation

            // Auto-fill times based on room selection
            $('#room-select').on('change', function() {
                const selected = $(this).find(':selected');
                const opening = selected.data('opening');
                const closing = selected.data('closing');

                if (opening && !$('#start-time').val()) {
                    $('#start-time').val(opening);
                }
                if (closing && !$('#end-time').val()) {
                    $('#end-time').val(closing);
                }
                updateDuration();
            });

            // Auto-set end date to start date if empty
            $('input[name="start_date"]').on('change', function() {
                if (!$('input[name="end_date"]').val()) {
                    $('input[name="end_date"]').val($(this).val());
                }
            });

            // Form validation
            $('#booking-form').on('submit', function(e) {
                const startTime = $('#start-time').val();
                const endTime = $('#end-time').val();

                if (startTime && endTime) {
                    const start = new Date('2000-01-01 ' + startTime);
                    const end = new Date('2000-01-01 ' + endTime);

                    if (end <= start) {
                        e.preventDefault();
                        alert('<?php _e('End time must be after start time', 'my-village-hall'); ?>');
                        return false;
                    }
                }
            });
        });
        </script>
    <?php endif; ?>
</div>
