<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('manage_myvh')) {
    wp_die(__('Permission denied', 'my-village-hall'));
}

global $myvh_container;

$edit_id   = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
$room_service  = $myvh_container->get(RoomService::class);
$venue_service = $myvh_container->get(VenueService::class);
$availability_service = $myvh_container->get(AvailabilityService::class);
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
$form_venue_id = isset($form_data['venue_id']) ? intval($form_data['venue_id']) : intval($edit_room['VenueId'] ?? 0);
$form_capacity = $form_data['capacity'] ?? ($edit_room['Capacity'] ?? '');
$form_description = $form_data['description'] ?? ($edit_room['Description'] ?? '');
$form_opening_time = $form_data['opening_time'] ?? ($edit_room['OpeningTime'] ?? '09:00');
$form_closing_time = $form_data['closing_time'] ?? ($edit_room['ClosingTime'] ?? '17:00');
$form_allow_multi_day = isset($form_data['allow-multi-day-bookings']) ? 1 : intval($edit_room['AllowMultiDayBookings'] ?? 0);
$form_calc_closed_hours = isset($form_data['calc-closed-hours']) ? 1 : intval($edit_room['CalcClosedHours'] ?? 0);
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
                            <th><?php _e('Capacity', 'my-village-hall'); ?></th>
                            <th><?php _e('Hours', 'my-village-hall'); ?></th>
                            <th><?php _e('Actions', 'my-village-hall'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rooms)): ?>
                            <tr>
                                <td colspan="5"><?php _e('No rooms found. Please add a room to get started.', 'my-village-hall'); ?></td>
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
                                    <td colspan="5"><strong><?php echo esc_html($room['VenueName']); ?></strong></td>
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
                                            <?php selected($form_venue_id, intval($venue['Id'])); ?>>
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
