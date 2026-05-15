<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('manage_myvh')) {
    wp_die(__('Permission denied', 'my-village-hall'));
}

global $myvh_container;

use MYVH\Rooms\RoomService;
use MYVH\Rooms\RoomColour;
use MYVH\Rooms\RoomDepositRepository;
use MYVH\Rooms\RoomHoursRepository;
use MYVH\Venues\VenueService;
use MYVH\Availability\AvailabilityService;

$edit_id   = isset($_GET['edit']) ? \intval($_GET['edit']) : 0;
$room_service  = $myvh_container->get(RoomService::class);
$room_deposit_repository = $myvh_container->get(RoomDepositRepository::class);
$room_hours_repository = $myvh_container->get(RoomHoursRepository::class);
$venue_service = $myvh_container->get(VenueService::class);
$availability_service = $myvh_container->get(AvailabilityService::class);

$edit_room = $edit_id ? $room_service->get($edit_id) : null;
$rooms     = $room_service->get_all_with_venues();
$venues    = $venue_service->get_all();

$form_data = get_transient('myvh_room_form_' . get_current_user_id());
if (!is_array($form_data)) {
    $form_data = [];
} else {
    delete_transient('myvh_room_form_' . get_current_user_id());
}

$form_name = $form_data['name'] ?? ($edit_room['Name'] ?? '');
$form_room_colour = $form_data['room_colour'] ?? RoomColour::resolve($edit_room['Colour'] ?? '', \intval($edit_room['Id'] ?? 0));
$form_venue_id = isset($form_data['venue_id']) ? \intval($form_data['venue_id']) : \intval($edit_room['VenueId'] ?? 0);
$form_capacity = $form_data['capacity'] ?? ($edit_room['Capacity'] ?? '');
$form_description = $form_data['description'] ?? ($edit_room['Description'] ?? '');
$form_opening_time = $form_data['opening_time'] ?? ($edit_room['OpeningTime'] ?? '09:00');
$form_closing_time = $form_data['closing_time'] ?? ($edit_room['ClosingTime'] ?? '17:00');
$form_allow_multi_day = isset($form_data['allow-multi-day-bookings']) ? 1 : \intval($edit_room['AllowMultiDayBookings'] ?? 0);
$form_calc_closed_hours = isset($form_data['calc-closed-hours']) ? 1 : \intval($edit_room['CalcClosedHours'] ?? 0);

$deposit_config = $edit_id > 0
    ? $room_deposit_repository->get($edit_id)
    : [
        'enabled' => false,
        'days' => [],
        'end_after' => null,
        'amount' => 0.0,
        'action' => 'auto_add',
    ];

$form_deposit_enabled = isset($form_data['deposit_enabled'])
    ? !empty($form_data['deposit_enabled'])
    : !empty($deposit_config['enabled']);

$form_deposit_days = isset($form_data['deposit_days']) && is_array($form_data['deposit_days'])
    ? array_map('sanitize_key', $form_data['deposit_days'])
    : (array) ($deposit_config['days'] ?? []);

$form_deposit_end_after = isset($form_data['deposit_end_after'])
    ? sanitize_text_field((string) $form_data['deposit_end_after'])
    : (string) ($deposit_config['end_after'] ?? '');

$form_deposit_amount = isset($form_data['deposit_amount'])
    ? max(0, \floatval($form_data['deposit_amount']))
    : \floatval($deposit_config['amount'] ?? 0);

$form_deposit_action = isset($form_data['deposit_action'])
    ? sanitize_key((string) $form_data['deposit_action'])
    : (string) ($deposit_config['action'] ?? 'auto_add');

if (!in_array($form_deposit_action, ['auto_add', 'require_review'], true)) {
    $form_deposit_action = 'auto_add';
}

$day_labels = [
    0 => __('Sunday', 'my-village-hall'),
    1 => __('Monday', 'my-village-hall'),
    2 => __('Tuesday', 'my-village-hall'),
    3 => __('Wednesday', 'my-village-hall'),
    4 => __('Thursday', 'my-village-hall'),
    5 => __('Friday', 'my-village-hall'),
    6 => __('Saturday', 'my-village-hall'),
];

$room_hours_rows = $edit_id ? $room_hours_repository->get_by_room($edit_id) : [];
$room_hours_index = [];
foreach ((array) $room_hours_rows as $row) {
    $room_hours_index[(int) ($row['DayOfWeek'] ?? -1)] = $row;
}

$posted_hours = isset($form_data['opening_hours_by_day']) && is_array($form_data['opening_hours_by_day'])
    ? $form_data['opening_hours_by_day']
    : [];

$default_open = substr((string) $form_opening_time, 0, 5) ?: '09:00';
$default_close = substr((string) $form_closing_time, 0, 5) ?: '17:00';

$room_hours_by_day = [];
for ($day = 0; $day <= 6; $day++) {
    $posted_row = isset($posted_hours[$day]) && is_array($posted_hours[$day]) ? $posted_hours[$day] : null;
    $db_row = $room_hours_index[$day] ?? null;

    $use_venue = $posted_row !== null
        ? (!empty($posted_row['use_venue_hours']) ? 1 : 0)
        : (!empty($db_row['UseVenueHours']) ? 1 : 0);

    $is_closed = $posted_row !== null
        ? (!empty($posted_row['is_closed']) ? 1 : 0)
        : (!empty($db_row['IsClosed']) ? 1 : 0);

    $opening_time = $posted_row !== null
        ? sanitize_text_field($posted_row['opening_time'] ?? '')
        : substr((string) ($db_row['OpeningTime'] ?? $default_open), 0, 5);

    $closing_time = $posted_row !== null
        ? sanitize_text_field($posted_row['closing_time'] ?? '')
        : substr((string) ($db_row['ClosingTime'] ?? $default_close), 0, 5);

    if ($use_venue || $is_closed) {
        $opening_time = '';
        $closing_time = '';
    }

    $room_hours_by_day[$day] = [
        'use_venue_hours' => $use_venue,
        'is_closed' => $is_closed,
        'opening_time' => $opening_time,
        'closing_time' => $closing_time,
    ];
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Rooms', 'my-village-hall'); ?></h1>
    <a href="<?php echo admin_url('admin.php?page=myvh-rooms&add=1'); ?>" class="page-title-action">
        <?php _e('Add New', 'my-village-hall'); ?>
    </a>
    <hr class="wp-header-end">

    <?php if (isset($_GET['error'])): ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html($_GET['error']); ?></p>
        </div>
    <?php endif; ?>

    <div class="myvh-row">

        <div class="myvh-col-60">
            <div class="myvh-card">
                <h2><?php _e('All Rooms', 'my-village-hall'); ?></h2>

                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th><?php _e('Room Name', 'my-village-hall'); ?></th>
                            <th><?php _e('Venue', 'my-village-hall'); ?></th>
                            <th><?php _e('Colour', 'my-village-hall'); ?></th>
                            <th><?php _e('Capacity', 'my-village-hall'); ?></th>
                            <th><?php _e('Hours', 'my-village-hall'); ?></th>
                            <th><?php _e('Actions', 'my-village-hall'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rooms)): ?>
                            <tr>
                                <td colspan="6"><?php _e('No rooms found. Please add a room to get started.', 'my-village-hall'); ?></td>
                            </tr>
                        <?php else: ?>
                            <?php
                            $current_venue = '';
                            foreach ($rooms as $room):
                                // Add venue separator row
                                if ($current_venue !== $room['VenueName']):
                                    $current_venue = $room['VenueName'];
                            ?>
                                <tr style="background-color: #f0f0f0;">
                                    <td colspan="6"><strong><?php echo esc_html($room['VenueName']); ?></strong></td>
                                </tr>
                            <?php endif; ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($room['Name']); ?></strong>
                                        <?php if ($room['Description']): ?>
                                            <br><small style="color: #666;"><?php echo esc_html($room['Description']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html($room['VenueName']); ?></td>
                                    <td>
                                        <span style="display:inline-block;width:18px;height:18px;border-radius:4px;border:1px solid #c3c4c7;background:<?php echo esc_attr(RoomColour::resolve($room['Colour'] ?? '', \intval($room['Id'] ?? 0))); ?>;"></span>
                                    </td>
                                    <td>
                                        <?php if ($room['Capacity']): ?>
                                            <?php echo esc_html($room['Capacity']); ?> people
                                        <?php else: ?>
                                            <span style="color: #999;">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($room['OpeningTime'] && $room['ClosingTime']): ?>
                                            <?php echo date('g:i A', strtotime($room['OpeningTime'])); ?> -
                                            <?php echo date('g:i A', strtotime($room['ClosingTime'])); ?>
                                        <?php else: ?>
                                            <span style="color: #999;">Not set</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="<?php echo admin_url('admin.php?page=myvh-rooms&edit=' . $room['Id']); ?>">
                                            <?php _e('Edit', 'my-village-hall'); ?>
                                        </a> |
                                        <a href="<?php echo admin_url('admin.php?page=myvh-room-rates&add=1&room_id=' . \intval($room['Id'])); ?>">
                                            <?php _e('Manage Rates', 'my-village-hall'); ?>
                                        </a> |
                                        <a href="<?php echo wp_nonce_url(
                                            admin_url('admin-post.php?action=myvh_delete_room&id=' . $room['Id']),
                                            'myvh_delete_room'
                                        ); ?>" class="link-delete" onclick="return confirm('<?php _e('Are you sure you want to delete this room? This cannot be undone.', 'my-village-hall'); ?>');">
                                            <?php _e('Delete', 'my-village-hall'); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if (isset($_GET['add']) || $edit_room): ?>
        <div class="myvh-col-40">
            <div class="myvh-card">
                <h2><?php echo $edit_room ? __('Edit Room', 'my-village-hall') : __('Add Room', 'my-village-hall'); ?></h2>

                <?php if (empty($venues)): ?>
                    <div class="notice notice-warning inline">
                        <p>
                            <?php _e('You need to create at least one venue before adding rooms.', 'my-village-hall'); ?>
                            <a href="<?php echo admin_url('admin.php?page=myvh-venues'); ?>">
                                <?php _e('Add Venue', 'my-village-hall'); ?>
                            </a>
                        </p>
                    </div>
                <?php else: ?>

                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <input type="hidden" name="action" value="myvh_save_room">
                    <?php wp_nonce_field('myvh_save_room'); ?>
                    <?php if ($edit_room): ?>
                        <input type="hidden" name="room_id" value="<?php echo $edit_room['Id']; ?>">
                    <?php endif; ?>

                    <table class="form-table">
                        <tr>
                            <th><?php _e('Room Name', 'my-village-hall'); ?> *</th>
                            <td>
                                <input type="text" name="name" required class="regular-text"
                                    value="<?php echo esc_attr($form_name); ?>"
                                    placeholder="<?php _e('e.g., Main Hall, Meeting Room 1', 'my-village-hall'); ?>">
                            </td>
                        </tr>

                        <tr>
                            <th><?php _e('Venue', 'my-village-hall'); ?> *</th>
                            <td>
                                <select name="venue_id" required class="regular-text">
                                    <option value=""><?php _e('Select Venue', 'my-village-hall'); ?></option>
                                    <?php foreach ($venues as $venue): ?>
                                        <option value="<?php echo $venue['Id']; ?>"
                                            <?php selected($form_venue_id, \intval($venue['Id'])); ?>>
                                            <?php echo esc_html($venue['Name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>

                        <tr>
                            <th><?php _e('Capacity', 'my-village-hall'); ?></th>
                            <td>
                                <input type="number" name="capacity" min="0" class="small-text"
                                    value="<?php echo esc_attr($form_capacity); ?>">
                                <span class="description"><?php _e('people', 'my-village-hall'); ?></span>
                            </td>
                        </tr>

                        <tr>
                            <th><?php _e('Room Colour', 'my-village-hall'); ?> *</th>
                            <td>
                                <div class="myvh-room-colour-row">
                                    <input class="myvh-room-colour-input" type="color" name="room_colour" value="<?php echo esc_attr($form_room_colour); ?>" required>
                                    <span class="myvh-room-colour-code"><?php echo esc_html($form_room_colour); ?></span>
                                </div>
                            </td>
                        </tr>

                        <tr>
                            <th><?php _e('Description', 'my-village-hall'); ?></th>
                            <td>
                                <textarea name="description" class="large-text" rows="3"
                                    placeholder="<?php _e('Optional description or notes about this room', 'my-village-hall'); ?>"><?php echo esc_textarea($form_description); ?></textarea>
                            </td>
                        </tr>

                        <tr>
                            <th><label><?php _e('Opening Time', 'my-village-hall'); ?></label></th>
                            <td>
                                <select name="opening_time">
                                    <?php echo $availability_service->get_time_options($form_opening_time, 0, 23,true); ?>
                                </select>
                            </td>
                        </tr>

                        <tr>
                            <th><label><?php _e('Closing Time', 'my-village-hall'); ?></label></th>
                            <td>
                                <select name="closing_time">
                                    <?php echo $availability_service->get_time_options($form_closing_time, 0, 23,true); ?>
                                    </select>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Options', 'my-village-hall'); ?></th>
                            <td>
                                <label><input type="checkbox" name="allow-multi-day-bookings" value="1"
                                    <?php checked($form_allow_multi_day, 1); ?>>
                                    <?php _e('Allow multi-day bookings', 'my-village-hall'); ?></label><br>

                                <label><input type="checkbox" name="calc-closed-hours" value="1"
                                    <?php checked($form_calc_closed_hours, 1); ?>>
                                    <?php _e('Include closed hours when calculating booking duration', 'my-village-hall'); ?></label>
                            </td>
                        </tr>

                        <tr>
                            <th><?php _e('Daily Opening Hours', 'my-village-hall'); ?></th>
                            <td>
                                <table class="widefat striped" style="max-width:680px;">
                                    <thead>
                                        <tr>
                                            <th><?php _e('Day', 'my-village-hall'); ?></th>
                                            <th><?php _e('Use Venue Hours', 'my-village-hall'); ?></th>
                                            <th><?php _e('Closed', 'my-village-hall'); ?></th>
                                            <th><?php _e('Opens', 'my-village-hall'); ?></th>
                                            <th><?php _e('Closes', 'my-village-hall'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($day_labels as $day => $label): ?>
                                            <?php $day_row = $room_hours_by_day[$day]; ?>
                                            <tr>
                                                <td>
                                                    <?php echo esc_html($label); ?>
                                                    <input type="hidden" name="opening_hours_by_day[<?php echo (int) $day; ?>][day_of_week]" value="<?php echo (int) $day; ?>">
                                                </td>
                                                <td>
                                                    <input type="hidden" name="opening_hours_by_day[<?php echo (int) $day; ?>][use_venue_hours]" value="0">
                                                    <label>
                                                        <input type="checkbox" name="opening_hours_by_day[<?php echo (int) $day; ?>][use_venue_hours]" value="1" <?php checked((int) $day_row['use_venue_hours'], 1); ?>>
                                                        <?php _e('Use venue', 'my-village-hall'); ?>
                                                    </label>
                                                </td>
                                                <td>
                                                    <input type="hidden" name="opening_hours_by_day[<?php echo (int) $day; ?>][is_closed]" value="0">
                                                    <label>
                                                        <input type="checkbox" name="opening_hours_by_day[<?php echo (int) $day; ?>][is_closed]" value="1" <?php checked((int) $day_row['is_closed'], 1); ?>>
                                                        <?php _e('Closed', 'my-village-hall'); ?>
                                                    </label>
                                                </td>
                                                <td>
                                                    <select name="opening_hours_by_day[<?php echo (int) $day; ?>][opening_time]">
                                                        <?php echo $availability_service->get_time_options($day_row['opening_time'] ?: $default_open, 0, 23, true); ?>
                                                    </select>
                                                </td>
                                                <td>
                                                    <select name="opening_hours_by_day[<?php echo (int) $day; ?>][closing_time]">
                                                        <?php echo $availability_service->get_time_options($day_row['closing_time'] ?: $default_close, 0, 23, true); ?>
                                                    </select>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <p class="description"><?php _e('Enable "Use venue" to inherit venue day hours. If disabled, you can mark the day closed or set custom room hours.', 'my-village-hall'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th><?php _e('Deposits', 'my-village-hall'); ?></th>
                            <td>
                                <p>
                                    <label>
                                        <input type="hidden" name="deposit_enabled" value="0">
                                        <input type="checkbox" name="deposit_enabled" value="1" <?php checked($form_deposit_enabled, true); ?>>
                                        <?php _e('Enable deposits for this room', 'my-village-hall'); ?>
                                    </label>
                                </p>

                                <p>
                                    <label for="myvh_deposit_days"><?php _e('Days of week', 'my-village-hall'); ?></label><br>
                                    <select id="myvh_deposit_days" name="deposit_days[]" multiple size="7" style="min-width:180px;">
                                        <?php
                                        $deposit_day_options = [
                                            'mon' => __('Monday', 'my-village-hall'),
                                            'tue' => __('Tuesday', 'my-village-hall'),
                                            'wed' => __('Wednesday', 'my-village-hall'),
                                            'thu' => __('Thursday', 'my-village-hall'),
                                            'fri' => __('Friday', 'my-village-hall'),
                                            'sat' => __('Saturday', 'my-village-hall'),
                                            'sun' => __('Sunday', 'my-village-hall'),
                                        ];
                                        foreach ($deposit_day_options as $value => $label):
                                        ?>
                                            <option value="<?php echo esc_attr($value); ?>" <?php selected(in_array($value, $form_deposit_days, true), true); ?>>
                                                <?php echo esc_html($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <br><span class="description"><?php _e('Leave empty to apply every day.', 'my-village-hall'); ?></span>
                                </p>

                                <p>
                                    <label for="myvh_deposit_end_after"><?php _e('Booking ends after', 'my-village-hall'); ?></label><br>
                                    <input id="myvh_deposit_end_after" type="time" name="deposit_end_after" value="<?php echo esc_attr($form_deposit_end_after); ?>">
                                    <br><span class="description"><?php _e('Leave blank for no time restriction.', 'my-village-hall'); ?></span>
                                </p>

                                <p>
                                    <label for="myvh_deposit_amount"><?php _e('Deposit amount', 'my-village-hall'); ?></label><br>
                                    <input id="myvh_deposit_amount" type="number" name="deposit_amount" min="0" step="0.01" value="<?php echo esc_attr((string) $form_deposit_amount); ?>">
                                </p>

                                <p>
                                    <label for="myvh_deposit_action"><?php _e('Deposit action', 'my-village-hall'); ?></label><br>
                                    <select id="myvh_deposit_action" name="deposit_action">
                                        <option value="auto_add" <?php selected($form_deposit_action, 'auto_add'); ?>><?php _e('Auto add to invoice', 'my-village-hall'); ?></option>
                                        <option value="require_review" <?php selected($form_deposit_action, 'require_review'); ?>><?php _e('Require admin review', 'my-village-hall'); ?></option>
                                    </select>
                                </p>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button class="button button-primary">
                            <?php echo $edit_room ? __('Update Room', 'my-village-hall') : __('Add Room', 'my-village-hall'); ?>
                        </button>
                        <?php if ($edit_room): ?>
                            <a href="<?php echo admin_url('admin.php?page=myvh-rooms'); ?>" class="button">
                                <?php _e('Cancel', 'my-village-hall'); ?>
                            </a>
                        <?php endif; ?>
                    </p>
                </form>

                <?php endif; ?>
            </div>

            <?php if ($edit_room): ?>
            <div class="myvh-card" style="margin-top: 20px;">
                <h3><?php _e('Room Information', 'my-village-hall'); ?></h3>
                <p>
                    <a class="button" href="<?php echo admin_url('admin.php?page=myvh-room-rates&add=1&room_id=' . \intval($edit_room['Id'])); ?>">
                        <?php _e('Manage Rates for This Room', 'my-village-hall'); ?>
                    </a>
                </p>
                <table class="form-table">
                    <tr>
                        <th><?php _e('Room ID', 'my-village-hall'); ?></th>
                        <td><?php echo $edit_room['Id']; ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Created', 'my-village-hall'); ?></th>
                        <td><?php echo isset($edit_room['Created']) ? date('F j, Y g:i A', strtotime($edit_room['Created'])) : '—'; ?></td>
                    </tr>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div>
</div>
