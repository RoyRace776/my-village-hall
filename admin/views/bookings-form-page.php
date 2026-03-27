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

$booking_service           = $myvh_container->get(Booking_Service::class);
$customer_service          = $myvh_container->get(Customer_Service::class);
$org_service               = $myvh_container->get(Organisation_Service::class);
$room_service              = $myvh_container->get(Room_Service::class);
$addon_service             = $myvh_container->get(Addon_Service::class);
$recurring_pattern_service = $myvh_container->get(Recurring_Pattern_Service::class);

$form_transient_key = 'myvh_booking_form_' . get_current_user_id();
$form_data = get_transient($form_transient_key) ?: [];
$has_form_data = !empty($form_data);
if (!empty($form_data)) {
    delete_transient($form_transient_key);
}

$edit_booking = $booking_id ? $booking_service->get_by_id($booking_id) : null;
$is_view_mode = $view_id > 0;
$return_to = isset($_GET['return_to']) ? wp_validate_redirect(wp_unslash($_GET['return_to']), '') : '';

$customers      = $customer_service->get_all();
$organisations  = $org_service->get_all(true);
$rooms          = $room_service->get_all_with_venues();
$all_addons = $addon_service->get_all(['orderby' => 'DisplayOrder', 'order' => 'ASC']);

$customer_organisations_map = [];
foreach ($customers as $customer_row) {
    $customer_id = intval($customer_row['Id'] ?? 0);
    if ($customer_id <= 0) {
        continue;
    }

    $customer_orgs = $customer_service->get_organisations_for_customer($customer_id);

    $customer_organisations_map[$customer_id] = array_map(static function ($org) {
        return [
            'Id' => intval($org['Id'] ?? 0),
            'Name' => sanitize_text_field($org['Name'] ?? ''),
        ];
    }, $customer_orgs ?: []);
}

// Active addons only for the form
$available_addons = array_filter($all_addons ?? [], fn($a) => !empty($a['IsActive']));

// Existing booking addons (for edit/view mode)
$booking_addons = [];
if (!empty($form_data['addons']) && is_array($form_data['addons'])) {
    $booking_addons = $form_data['addons'];
} elseif ($booking_id) {
    $booking_addons = $booking_service->get_addons_for_booking($booking_id);
}

// Index existing addons by AddonId for easy lookup in the form
$selected_addon_map = [];
foreach ($booking_addons as $ba) {
    $addon_id = intval($ba['AddonId'] ?? $ba['addon_id'] ?? 0);
    if ($addon_id <= 0) {
        continue;
    }

    $selected_addon_map[$addon_id] = [
        'AddonId' => $addon_id,
        'Quantity' => $ba['Quantity'] ?? $ba['quantity'] ?? 1,
        'UnitPrice' => $ba['UnitPrice'] ?? $ba['unit_price'] ?? 0,
        'Description' => $ba['Description'] ?? $ba['description'] ?? '',
    ];
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

$form_customer_id = isset($form_data['customer_id']) ? intval($form_data['customer_id']) : intval($edit_booking['CustomerId'] ?? 0);
$form_organisation_id = isset($form_data['organisation_id']) ? intval($form_data['organisation_id']) : intval($edit_booking['OrganisationId'] ?? 0);
$form_room_id = isset($form_data['room_id']) ? intval($form_data['room_id']) : intval($edit_booking['RoomId'] ?? 0);
$form_start_date = isset($form_data['start_date']) ? sanitize_text_field($form_data['start_date']) : ($edit_booking['StartDate'] ?? date('Y-m-d'));
$form_end_date = isset($form_data['end_date']) ? sanitize_text_field($form_data['end_date']) : ($edit_booking['EndDate'] ?? '');
$form_start_time = isset($form_data['start_time']) ? sanitize_text_field($form_data['start_time']) : ($edit_booking['StartTime'] ?? '09:00');
$form_end_time = isset($form_data['end_time']) ? sanitize_text_field($form_data['end_time']) : ($edit_booking['EndTime'] ?? '17:00');
$form_description = isset($form_data['description']) ? sanitize_textarea_field($form_data['description']) : ($edit_booking['Description'] ?? '');
$default_status = myvh_setting('booking.require_approval', true) ? BookingStatus::PENDING : BookingStatus::CONFIRMED;
$form_status = isset($form_data['status']) ? sanitize_text_field($form_data['status']) : ($edit_booking['Status'] ?? $default_status);
$form_public = $has_form_data ? !empty($form_data['public']) : !empty($edit_booking['Public']);
$form_is_recurring = !empty($form_data['is_recurring']);
$form_recurrence_type = sanitize_text_field($form_data['recurrence_type'] ?? 'weekly');
$form_recurrence_interval = max(1, intval($form_data['recurrence_interval'] ?? 1));
$form_recurrence_interval_md = max(1, intval($form_data['recurrence_interval_md'] ?? 1));
$form_recurrence_week = sanitize_text_field($form_data['recurrence_week'] ?? '1');
$form_recurrence_day = sanitize_text_field($form_data['recurrence_day'] ?? strtolower(date('l', strtotime($form_start_date ?: 'today'))));
$form_recurrence_end_type = sanitize_text_field($form_data['recurrence_end_type'] ?? 'date');
$form_recurrence_end_date = sanitize_text_field($form_data['recurrence_end_date'] ?? date('Y-m-d', strtotime('+1 year')));
$form_max_occurrences = max(1, intval($form_data['max_occurrences'] ?? 12));

$selected_customer_organisations = $form_customer_id > 0
    ? ($customer_organisations_map[$form_customer_id] ?? [])
    : [];

$room_multiday_map = [];
foreach ($rooms as $room_row) {
    $room_id = intval($room_row['Id'] ?? 0);
    if ($room_id <= 0) {
        continue;
    }

    $room_multiday_map[$room_id] = !empty($room_row['AllowMultiDayBookings']);
}

$selected_room_allows_multiday = !empty($room_multiday_map[$form_room_id]);

$allowed_organisation_ids = array_map(static function ($org) {
    return intval($org['Id'] ?? 0);
}, $selected_customer_organisations);

if (!in_array($form_organisation_id, $allowed_organisation_ids, true)) {
    $form_organisation_id = 0;
}

if ($form_organisation_id === 0 && count($selected_customer_organisations) === 1) {
    $form_organisation_id = intval($selected_customer_organisations[0]['Id'] ?? 0);
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
                        <?php
                        $booking_org = $edit_booking['OrganisationId']
                            ? $org_service->get($edit_booking['OrganisationId'])
                            : null;
                        ?>
                        <?php if ($booking_org): ?>
                        <tr>
                            <th><?php _e('Organisation', 'my-village-hall'); ?></th>
                            <td><strong><?php echo esc_html($booking_org['Name']); ?></strong></td>
                        </tr>
                        <?php endif; ?>
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
                        <a href="<?php echo esc_url(add_query_arg($return_to ? ['return_to' => $return_to] : [], admin_url('admin.php?page=my-village-hall&edit=' . $booking_id))); ?>" class="button button-primary">
                            <?php _e('Edit Booking', 'my-village-hall'); ?>
                        </a>
                        <a href="<?php echo esc_url($return_to ?: admin_url('admin.php?page=my-village-hall')); ?>" class="button">
                            <?php echo esc_html($return_to ? __('Back to Calendar', 'my-village-hall') : __('Back to All Bookings', 'my-village-hall')); ?>
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
                                    <td>£<?php echo number_format($ba['UnitPrice'], 2); ?></td>
                                    <td><strong>£<?php echo number_format($ba['TotalAmount'], 2); ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="2"><?php _e('Add-ons Total', 'my-village-hall'); ?></th>
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
                <?php if ($return_to): ?>
                    <input type="hidden" name="return_to" value="<?php echo esc_attr($return_to); ?>">
                <?php endif; ?>
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
                                    <select name="customer_id" required class="regular-text" id="customer-select">
                                        <option value=""><?php _e('Select Customer', 'my-village-hall'); ?></option>
                                        <?php foreach ($customers as $customer): ?>
                                            <option value="<?php echo $customer['Id']; ?>"
                                                <?php selected($form_customer_id, $customer['Id']); ?>>
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
                                <th><?php _e('Organisation', 'my-village-hall'); ?></th>
                                <td>
                                    <select name="organisation_id" class="regular-text" id="organisation-select" <?php disabled($form_customer_id <= 0); ?>>
                                        <option value=""><?php _e('— None —', 'my-village-hall'); ?></option>
                                        <?php foreach ($selected_customer_organisations as $org): ?>
                                            <option value="<?php echo intval($org['Id']); ?>"
                                                <?php selected($form_organisation_id, intval($org['Id'])); ?>>
                                                <?php echo esc_html($org['Name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description">
                                        <?php _e('Select a customer first, then choose from organisations they belong to.', 'my-village-hall'); ?>
                                        <a href="<?php echo admin_url('admin.php?page=myvh-organisations'); ?>" target="_blank">
                                            <?php _e('Manage organisations', 'my-village-hall'); ?>
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
                                                data-allow-multiday="<?php echo !empty($room['AllowMultiDayBookings']) ? '1' : '0'; ?>"
                                                <?php selected($form_room_id, $room['Id']); ?>>
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
                                    <input type="date" name="start_date" required class="regular-text" id='start-date'
                                        value="<?php echo esc_attr($form_start_date); ?>"
                                        min="<?php echo date('Y-m-d'); ?>">
                                </td>
                            </tr>

                            <tr id="end-date-row" style="display: <?php echo $selected_room_allows_multiday ? '' : 'none'; ?>;">
                                <th><?php _e('End Date', 'my-village-hall'); ?></th>
                                <td>
                                    <input type="date" name="end_date" class="regular-text" id='end-date'
                                        value="<?php echo esc_attr($form_end_date); ?>"
                                        <?php echo !$selected_room_allows_multiday ? 'disabled' : ''; ?>>
                                    <p class="description"><?php _e('Leave blank for same-day booking', 'my-village-hall'); ?></p>
                                </td>
                            </tr>

                            <tr>
                                <th><?php _e('Start Time', 'my-village-hall'); ?> *</th>
                                <td>
                                    <input type="time" name="start_time" required class="regular-text" id="start-time"
                                        value="<?php echo esc_attr(substr($form_start_time, 0, 5)); ?>">
                                </td>
                            </tr>

                            <tr>
                                <th><?php _e('End Time', 'my-village-hall'); ?> *</th>
                                <td>
                                    <input type="time" name="end_time" required class="regular-text" id="end-time"
                                        value="<?php echo esc_attr(substr($form_end_time, 0, 5)); ?>">
                                    <p class="description" id="duration-display"></p>
                                </td>
                            </tr>

                            <tr>
                                <th><?php _e('Description', 'my-village-hall'); ?></th>
                                <td>
                                    <textarea name="description" class="large-text" rows="3"
                                        placeholder="<?php _e('Purpose of the booking, event details, etc.', 'my-village-hall'); ?>"><?php echo esc_textarea($form_description); ?></textarea>
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
                                        <option value="pending" <?php selected($form_status, BookingStatus::PENDING); ?>>
                                            <?php _e('Pending', 'my-village-hall'); ?>
                                        </option>
                                        <option value="confirmed" <?php selected($form_status, BookingStatus::CONFIRMED); ?>>
                                            <?php _e('Confirmed', 'my-village-hall'); ?>
                                        </option>
                                        <option value="cancelled" <?php selected($form_status, BookingStatus::CANCELLED); ?>>
                                            <?php _e('Cancelled', 'my-village-hall'); ?>
                                        </option>
                                        <option value="completed" <?php selected($form_status, BookingStatus::COMPLETED); ?>>
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
                                            <?php checked($form_public); ?>>
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
                                        <input type="checkbox" name="is_recurring" value="1" id="is-recurring" <?php checked($form_is_recurring); ?>>
                                        <?php _e('Make this a recurring booking', 'my-village-hall'); ?>
                                    </label>
                                </td>
                            </tr>
                            </table>

                            <div id="recurring-options" style="display: <?php echo $form_is_recurring ? 'block' : 'none'; ?>; margin-top: 20px; padding: 15px; background: #f9f9f9; border-radius: 4px;">
                                <h3><?php _e('Recurring Pattern', 'my-village-hall'); ?></h3>

                                <table class="form-table">
                                    <tr>
                                        <th><?php _e('Repeat', 'my-village-hall'); ?></th>
                                        <td>
                                            <select name="recurrence_type" class="regular-text" id="bf-rec-type">
                                                <option value="daily" <?php selected($form_recurrence_type, 'daily'); ?>><?php _e('Daily', 'my-village-hall'); ?></option>
                                                <option value="weekly" <?php selected($form_recurrence_type, 'weekly'); ?>><?php _e('Weekly', 'my-village-hall'); ?></option>
                                                <option value="monthly" <?php selected($form_recurrence_type, 'monthly'); ?>><?php _e('Monthly (same date)', 'my-village-hall'); ?></option>
                                                <option value="monthly_day" <?php selected($form_recurrence_type, 'monthly_day'); ?>><?php _e('Monthly (specific weekday)', 'my-village-hall'); ?></option>
                                                <option value="yearly" <?php selected($form_recurrence_type, 'yearly'); ?>><?php _e('Yearly', 'my-village-hall'); ?></option>
                                            </select>
                                        </td>
                                    </tr>

                                    <tr id="bf-interval-row">
                                        <th><?php _e('Every', 'my-village-hall'); ?></th>
                                        <td>
                                            <input type="number" name="recurrence_interval" value="<?php echo esc_attr($form_recurrence_interval); ?>" min="1" max="52" class="small-text">
                                            <span id="interval-label"><?php _e('week(s)', 'my-village-hall'); ?></span>
                                        </td>
                                    </tr>

                                    <tr id="bf-monthly-day-row" style="display:<?php echo $form_recurrence_type === 'monthly_day' ? '' : 'none'; ?>;">
                                        <th><?php _e('On the…', 'my-village-hall'); ?></th>
                                        <td>
                                            <select name="recurrence_week">
                                                <option value="1" <?php selected($form_recurrence_week, '1'); ?>><?php _e('1st', 'my-village-hall'); ?></option>
                                                <option value="2" <?php selected($form_recurrence_week, '2'); ?>><?php _e('2nd', 'my-village-hall'); ?></option>
                                                <option value="3" <?php selected($form_recurrence_week, '3'); ?>><?php _e('3rd', 'my-village-hall'); ?></option>
                                                <option value="4" <?php selected($form_recurrence_week, '4'); ?>><?php _e('4th', 'my-village-hall'); ?></option>
                                                <option value="last" <?php selected($form_recurrence_week, 'last'); ?>><?php _e('Last', 'my-village-hall'); ?></option>
                                            </select>
                                            <select name="recurrence_day">
                                                <?php
                                                $days = ['monday'=>'Monday','tuesday'=>'Tuesday','wednesday'=>'Wednesday',
                                                         'thursday'=>'Thursday','friday'=>'Friday','saturday'=>'Saturday','sunday'=>'Sunday'];
                                                // Pre-select the weekday matching the booking's start date
                                                $booking_dow = strtolower(date('l', strtotime($edit_booking ? $edit_booking['StartDate'] : 'today')));
                                                foreach ($days as $v => $l): ?>
                                                    <option value="<?php echo $v; ?>" <?php selected($form_recurrence_day, $v); ?>><?php echo esc_html($l); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <span><?php _e('of every', 'my-village-hall'); ?></span>
                                            <input type="number" name="recurrence_interval_md" value="<?php echo esc_attr($form_recurrence_interval_md); ?>" min="1" max="24" class="small-text">
                                            <span><?php _e('month(s)', 'my-village-hall'); ?></span>
                                            <p class="description" id="bf-rec-preview" style="color:#2271b1;font-style:italic;"></p>
                                        </td>
                                    </tr>

                                    <tr>
                                        <th><?php _e('Ends', 'my-village-hall'); ?></th>
                                        <td>
                                            <label>
                                                <input type="radio" name="recurrence_end_type" value="date" <?php checked($form_recurrence_end_type, 'date'); ?>>
                                                <?php _e('On', 'my-village-hall'); ?>
                                                <input type="date" name="recurrence_end_date" class="regular-text"
                                                    value="<?php echo esc_attr($form_recurrence_end_date); ?>">
                                            </label>
                                            <br>
                                            <label>
                                                <input type="radio" name="recurrence_end_type" value="count" <?php checked($form_recurrence_end_type, 'count'); ?>>
                                                <?php _e('After', 'my-village-hall'); ?>
                                                <input type="number" name="max_occurrences" value="<?php echo esc_attr($form_max_occurrences); ?>" min="1" max="365" class="small-text">
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
                                <th style="width:100px;"><?php _e('Total', 'my-village-hall'); ?></th>
                                <th><?php _e('Note', 'my-village-hall'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($available_addons as $i => $addon):
                            $existing   = $selected_addon_map[$addon['Id']] ?? null;
                            $is_checked = !empty($existing);
                            $charge_type = (string)($addon['ChargeType'] ?? 'fixed');
                            $quantity   = $charge_type === 'per_hour' ? ($existing ? floatval($existing['Quantity']) : 0) : 1;
                            $unit_price = $existing ? floatval($existing['UnitPrice']) : floatval($addon['Price']);
                            $note       = $existing ? esc_attr($existing['Description']) : '';
                        ?>
                        <tr class="myvh-addon-row<?php echo $is_checked ? ' addon-selected' : ''; ?>" data-charge-type="<?php echo esc_attr($charge_type); ?>">
                            <td>
                                <input type="checkbox"
                                    class="myvh-addon-checkbox"
                                    value="1"
                                    <?php checked($is_checked); ?>>
                                <input type="hidden" name="addons[<?php echo $i; ?>][addon_id]"   value="<?php echo intval($addon['Id']); ?>">
                                <input type="hidden" name="addons[<?php echo $i; ?>][enabled]"    class="myvh-addon-enabled" value="<?php echo $is_checked ? '1' : '0'; ?>">
                                <input type="hidden" name="addons[<?php echo $i; ?>][quantity]" class="myvh-addon-qty" value="<?php echo esc_attr($quantity); ?>">
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
                                <th colspan="3" style="text-align:right; padding-right:10px;"><?php _e('Add-ons Total:', 'my-village-hall'); ?></th>
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
                    <a href="<?php echo esc_url($return_to ?: admin_url('admin.php?page=my-village-hall')); ?>" class="button button-large">
                        <?php echo esc_html($return_to ? __('Back to Calendar', 'my-village-hall') : __('Cancel', 'my-village-hall')); ?>
                    </a>
                </p>

            <?php endif; ?>
        </div>

        <script>
        jQuery(document).ready(function($) {
            const customerOrganisations = <?php echo wp_json_encode($customer_organisations_map); ?>;
            const initialOrganisationId = '<?php echo esc_js((string) $form_organisation_id); ?>';
            const isNewBooking = <?php echo $edit_booking ? 'false' : 'true'; ?>;

            function roomAllowsMultiday() {
                const selected = $('#room-select').find(':selected');
                return String(selected.data('allow-multiday') || '0') === '1';
            }

            function syncEndDateVisibility() {
                const allowsMultiday = roomAllowsMultiday();
                const $endDateRow = $('#end-date-row');
                const $endDateInput = $('#end-date');

                $endDateRow.toggle(allowsMultiday);
                $endDateInput.prop('disabled', !allowsMultiday);

                if (!allowsMultiday) {
                    $endDateInput.val('');
                }

                updateDuration();
            }

            function refreshOrganisationOptions(preferredOrgId) {
                const customerId = String($('#customer-select').val() || '');
                const organisations = customerOrganisations[customerId] || [];
                const $organisationSelect = $('#organisation-select');

                $organisationSelect.prop('disabled', customerId === '');
                $organisationSelect.empty();
                $organisationSelect.append($('<option>', {
                    value: '',
                    text: '<?php echo esc_js(__('— None —', 'my-village-hall')); ?>'
                }));

                organisations.forEach(function(org) {
                    $organisationSelect.append($('<option>', {
                        value: String(org.Id),
                        text: org.Name
                    }).attr('data-default-public', String(org.DefaultPublic || 0)));
                });

                if (preferredOrgId && organisations.some(function(org) { return String(org.Id) === String(preferredOrgId); })) {
                    $organisationSelect.val(String(preferredOrgId));
                } else if (organisations.length === 1) {
                    $organisationSelect.val(String(organisations[0].Id));
                } else {
                    $organisationSelect.val('');
                }

                applyVisibilityDefaultFromOrganisation();
            }

            function applyVisibilityDefaultFromOrganisation() {
                if (!isNewBooking) {
                    return;
                }

                const $selectedOrganisation = $('#organisation-select').find(':selected');
                const defaultPublic = String($selectedOrganisation.data('default-public') || '0') === '1';

                $('input[name="public"]').prop('checked', defaultPublic);
            }

            $('#customer-select').on('change', function() {
                refreshOrganisationOptions('');
            });

            $('#organisation-select').on('change', applyVisibilityDefaultFromOrganisation);

            refreshOrganisationOptions(initialOrganisationId);

            // ── Add-ons panel ────────────────────────────────────────────
            function getBookingHours() {
                const startDate = $('#start-date').val();
                const startTime = $('#start-time').val();
                const endTime = $('#end-time').val();
                const endDate = $('#end-date').val() || startDate;

                if (!startDate || !startTime || !endDate || !endTime) {
                    return 0;
                }

                const start = new Date(startDate + 'T' + startTime);
                const end = new Date(endDate + 'T' + endTime);
                const diffHours = (end - start) / 3600000;

                return diffHours > 0 ? Math.round(diffHours * 100) / 100 : 0;
            }

            function addonQuantityForRow($row) {
                const chargeType = String($row.data('charge-type') || 'fixed');
                const quantity = chargeType === 'per_hour' ? getBookingHours() : 1;
                $row.find('.myvh-addon-qty').val(quantity);
                return quantity;
            }

            function recalcAddonRow($row) {
                var price = parseFloat($row.find('.myvh-addon-price').val()) || 0;
                var qty   = addonQuantityForRow($row);
                var total = price * qty;
                $row.find('.myvh-addon-total').text(total > 0 ? '£' + total.toFixed(2) : '—');
            }

            function recalcGrandTotal() {
                var grand = 0;
                $('.myvh-addon-row.addon-selected').each(function() {
                    var price = parseFloat($(this).find('.myvh-addon-price').val()) || 0;
                    var qty   = addonQuantityForRow($(this));
                    grand += price * qty;
                });
                $('#myvh-addons-grand-total').text('£' + grand.toFixed(2));
            }

            // Toggle addon row on/off
            $(document).on('change', '.myvh-addon-checkbox', function() {
                var $row     = $(this).closest('.myvh-addon-row');
                var enabled  = this.checked;
                $row.toggleClass('addon-selected', enabled);
                $row.find('.myvh-addon-price, input[type="text"]').prop('disabled', !enabled);
                $row.find('.myvh-addon-enabled').val(enabled ? '1' : '0');
                recalcAddonRow($row);
                recalcGrandTotal();
            });

            // Recalc on price changes
            $(document).on('input change', '.myvh-addon-price', function() {
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

                const start = new Date($('#start-date').val() + 'T' + $('#start-time').val());
                const end   = new Date(($('#end-date').val() || $('#start-date').val()) + 'T' + $('#end-time').val());

                const diffMinutes = (end - start) / 60000;

                if (diffMinutes <= 0) {
                    $('#duration-display')
                        .text('End time must be after start time')
                        .css('color', 'red');
                    return;
                }

                const hours = Math.floor(diffMinutes / 60);
                const minutes = diffMinutes % 60;

                $('#duration-display')
                    .text(`Duration: ${hours}h ${minutes}m`)
                    .css('color', '');
            }

            $('#start-time, #end-time, #start-date, #end-date').on('change', updateDuration);

            $('#start-time, #end-time, #start-date, #end-date').on('change', recalcGrandTotal);

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

                syncEndDateVisibility();
                updateDuration();
            });

            syncEndDateVisibility();

            // Form validation
            $('#booking-form').on('submit', function(e) {
                const startTime = $('#start-time').val();
                const endTime = $('#end-time').val();
                const startDate = $('#start-date').val();
                const endDate = $('#end-date').val();

                if (startTime && endTime) {
                    const start = new Date(startDate + 'T' + startTime);
                    const end = new Date(endDate + 'T' + endTime);

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
