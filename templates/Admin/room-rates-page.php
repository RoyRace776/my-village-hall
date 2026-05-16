<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('manage_myvh')) {
    wp_die(__('Permission denied', 'my-village-hall'));
}

use MYVH\Rooms\RoomService;
use MYVH\Pricing\RoomRateService;
use MYVH\Organisations\OrganisationTypeService;

global $myvh_container;

$edit_id           = isset($_GET['edit']) ? \intval($_GET['edit']) : 0;
$room_rate_service = $myvh_container->get(RoomRateService::class);
$room_service      = $myvh_container->get(RoomService::class);
$org_type_service  = $myvh_container->get(OrganisationTypeService::class);
$edit_rate         = $edit_id ? $room_rate_service->get($edit_id) : null;
$rates             = $room_rate_service->get_all();
$rooms             = $room_service->get_all_with_venues();
$org_types         = $org_type_service->get_all();

$requested_room_id = isset($_GET['room_id']) ? \intval($_GET['room_id']) : 0;
$selected_room_id = $edit_rate ? \intval($edit_rate['RoomId'] ?? 0) : $requested_room_id;
$selected_room = null;
$current_days_of_week = [];
$current_start_time = '';
$current_end_time = '';

if ($edit_rate) {
    $current_days_of_week = isset($edit_rate['DaysOfWeek']) && is_array($edit_rate['DaysOfWeek'])
        ? array_values(array_filter(array_map('intval', $edit_rate['DaysOfWeek']), static fn(int $day): bool => $day >= 0 && $day <= 6))
        : [];
    if (empty($current_days_of_week) && isset($edit_rate['DayOfWeek']) && $edit_rate['DayOfWeek'] !== null && $edit_rate['DayOfWeek'] !== '') {
        $legacy_day = \intval($edit_rate['DayOfWeek']);
        if ($legacy_day >= 0 && $legacy_day <= 6) {
            $current_days_of_week = [$legacy_day];
        }
    }
    $current_start_time = isset($edit_rate['StartTime']) && $edit_rate['StartTime'] ? (string) $edit_rate['StartTime'] : '';
    $current_end_time = isset($edit_rate['EndTime']) && $edit_rate['EndTime'] ? (string) $edit_rate['EndTime'] : '';
}

$current_days_lookup = array_fill_keys($current_days_of_week, true);

if ($selected_room_id > 0) {
    foreach ($rooms as $candidate_room) {
        if (intval($candidate_room['Id'] ?? 0) === $selected_room_id) {
            $selected_room = $candidate_room;
            break;
        }
    }

    if (!$selected_room) {
        $selected_room_id = 0;
    }
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Room Rates', 'my-village-hall'); ?></h1>
    <a href="<?php echo admin_url('admin.php?page=myvh-room-rates&add=1'); ?>" class="page-title-action">
        <?php _e('Add New', 'my-village-hall'); ?>
    </a>
    <hr class="wp-header-end">

    <?php if (isset($_GET['updated'])): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e('Rate saved successfully', 'my-village-hall'); ?></p>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['room_created']) && $selected_room): ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <?php printf(
                    esc_html__('Room "%s" was created. Add at least one rate before taking bookings.', 'my-village-hall'),
                    esc_html($selected_room['Name'])
                ); ?>
            </p>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e('Rate deleted successfully', 'my-village-hall'); ?></p>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html($_GET['error']); ?></p>
        </div>
    <?php endif; ?>

    <div class="myvh-row">

        <div class="myvh-col-60">
            <div class="myvh-card">
                <h2><?php _e('All Rates', 'my-village-hall'); ?></h2>

                <div class="notice notice-info inline">
                    <p>
                        <strong><?php _e('How rates work:', 'my-village-hall'); ?></strong><br>
                        <?php _e('Room rates define how much to charge for bookings. You can set different rates based on room, organisation type, charge type, and date validity.', 'my-village-hall'); ?>
                    </p>
                </div>

                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th><?php _e('Name', 'my-village-hall'); ?></th>
                            <th><?php _e('Room', 'my-village-hall'); ?></th>
                            <th><?php _e('Rate', 'my-village-hall'); ?></th>
                            <th><?php _e('Priority', 'my-village-hall'); ?></th>
                            <th><?php _e('Type', 'my-village-hall'); ?></th>
                            <th><?php _e('Org Type', 'my-village-hall'); ?></th>
                            <th><?php _e('Schedule', 'my-village-hall'); ?></th>
                            <th><?php _e('Status', 'my-village-hall'); ?></th>
                            <th><?php _e('Actions', 'my-village-hall'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rates)): ?>
                            <tr>
                                <td colspan="9"><?php _e('No rates found. Add a rate to get started.', 'my-village-hall'); ?></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($rates as $rate): ?>
                                <?php
                                // Resolve room name
                                $room = null;
                                foreach ($rooms as $r) {
                                    if ($r['Id'] == $rate['RoomId']) { $room = $r; break; }
                                }

                                // Resolve organisation type name
                                $org_type_name = __('All Types', 'my-village-hall');
                                if (!empty($rate['OrganisationTypeId'])) {
                                    foreach ($org_types as $ot) {
                                        if ($ot['Id'] == $rate['OrganisationTypeId']) {
                                            $org_type_name = $ot['Name'];
                                            break;
                                        }
                                    }
                                }
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($rate['Name']); ?></strong>
                                        <?php if ($rate['Description']): ?>
                                            <br><small style="color: #666;"><?php echo esc_html($rate['Description']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $room ? esc_html($room['Name']) : '<em>' . __('Deleted', 'my-village-hall') . '</em>'; ?></td>
                                    <td><strong>£<?php echo number_format($rate['Rate'], 2); ?></strong></td>
                                    <td><?php echo esc_html((string) ($rate['Priority'] ?? '0')); ?></td>
                                    <td>
                                        <?php
                                        $charge_types = [
                                            'per_hour' => __('Per Hour', 'my-village-hall'),
                                            'per_day'  => __('Per Day',  'my-village-hall'),
                                            'fixed'    => __('Fixed',    'my-village-hall'),
                                        ];
                                        echo $charge_types[$rate['ChargeType']] ?? esc_html($rate['ChargeType']);
                                        ?>
                                    </td>
                                    <td><?php echo esc_html($org_type_name); ?></td>
                                    <td>
                                        <?php
                                        $day_labels = [
                                            '0' => __('Sun', 'my-village-hall'),
                                            '1' => __('Mon', 'my-village-hall'),
                                            '2' => __('Tue', 'my-village-hall'),
                                            '3' => __('Wed', 'my-village-hall'),
                                            '4' => __('Thu', 'my-village-hall'),
                                            '5' => __('Fri', 'my-village-hall'),
                                            '6' => __('Sat', 'my-village-hall'),
                                        ];

                                        $schedule_days = isset($rate['DaysOfWeek']) && is_array($rate['DaysOfWeek'])
                                            ? array_values(array_filter(array_map('intval', $rate['DaysOfWeek']), static fn(int $day): bool => $day >= 0 && $day <= 6))
                                            : [];
                                        if (empty($schedule_days) && isset($rate['DayOfWeek']) && $rate['DayOfWeek'] !== null && $rate['DayOfWeek'] !== '') {
                                            $legacy_schedule_day = intval($rate['DayOfWeek']);
                                            if ($legacy_schedule_day >= 0 && $legacy_schedule_day <= 6) {
                                                $schedule_days = [$legacy_schedule_day];
                                            }
                                        }
                                        $schedule_start = isset($rate['StartTime']) && $rate['StartTime'] ? substr((string) $rate['StartTime'], 0, 5) : '';
                                        $schedule_end = isset($rate['EndTime']) && $rate['EndTime'] ? substr((string) $rate['EndTime'], 0, 5) : '';

                                        if (empty($schedule_days) && $schedule_start === '' && $schedule_end === '') {
                                            echo '<small>' . esc_html__('All days, all day', 'my-village-hall') . '</small>';
                                        } else {
                                            $parts = [];
                                            if (empty($schedule_days)) {
                                                $parts[] = __('All days', 'my-village-hall');
                                            } else {
                                                $mapped_days = [];
                                                foreach ($schedule_days as $schedule_day) {
                                                    $key = (string) $schedule_day;
                                                    if (isset($day_labels[$key])) {
                                                        $mapped_days[] = $day_labels[$key];
                                                    }
                                                }
                                                $parts[] = empty($mapped_days) ? __('All days', 'my-village-hall') : implode(', ', $mapped_days);
                                            }

                                            if ($schedule_start !== '' && $schedule_end !== '') {
                                                $parts[] = $schedule_start . ' - ' . $schedule_end;
                                            }

                                            echo '<small>' . esc_html(implode(', ', $parts)) . '</small>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($rate['IsActive']): ?>
                                            <span style="color: #46b450;">●</span> <?php _e('Active', 'my-village-hall'); ?>
                                        <?php else: ?>
                                            <span style="color: #dc3232;">●</span> <?php _e('Inactive', 'my-village-hall'); ?>
                                        <?php endif; ?>

                                        <?php if ($rate['ValidFrom'] || $rate['ValidTo']): ?>
                                            <br><small style="color: #666;">
                                                <?php if ($rate['ValidFrom']): ?>
                                                    <?php echo date('M j, Y', strtotime($rate['ValidFrom'])); ?>
                                                <?php endif; ?>
                                                <?php if ($rate['ValidFrom'] && $rate['ValidTo']): ?> - <?php endif; ?>
                                                <?php if ($rate['ValidTo']): ?>
                                                    <?php echo date('M j, Y', strtotime($rate['ValidTo'])); ?>
                                                <?php endif; ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="<?php echo admin_url('admin.php?page=myvh-room-rates&edit=' . $rate['Id']); ?>">
                                            <?php _e('Edit', 'my-village-hall'); ?>
                                        </a> |
                                        <a href="<?php echo wp_nonce_url(
                                            admin_url('admin-post.php?action=myvh_delete_rate&id=' . $rate['Id']),
                                            'myvh_delete_rate'
                                        ); ?>" class="link-delete" onclick="return confirm('<?php _e('Are you sure?', 'my-village-hall'); ?>');">
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

        <?php if (isset($_GET['add']) || $edit_rate): ?>
        <div class="myvh-col-40">
            <div class="myvh-card">
                <h2><?php echo $edit_rate ? __('Edit Rate', 'my-village-hall') : __('Add Rate', 'my-village-hall'); ?></h2>

                <?php if (!$edit_rate && $selected_room): ?>
                    <p class="description">
                        <?php printf(
                            esc_html__('Adding a rate for %s.', 'my-village-hall'),
                            esc_html($selected_room['Name'])
                        ); ?>
                    </p>
                <?php endif; ?>

                <?php if (empty($rooms)): ?>
                    <div class="notice notice-warning inline">
                        <p>
                            <?php _e('You need to create at least one room before adding rates.', 'my-village-hall'); ?>
                            <a href="<?php echo admin_url('admin.php?page=myvh-rooms'); ?>">
                                <?php _e('Add Room', 'my-village-hall'); ?>
                            </a>
                        </p>
                    </div>
                <?php else: ?>

                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <input type="hidden" name="action" value="myvh_save_rate">
                    <?php wp_nonce_field('myvh_save_rate'); ?>
                    <?php if ($edit_rate): ?>
                        <input type="hidden" name="rate_id" value="<?php echo $edit_rate['Id']; ?>">
                    <?php endif; ?>

                    <table class="form-table">
                        <tr>
                            <th><?php _e('Rate Name', 'my-village-hall'); ?> *</th>
                            <td>
                                <input type="text" name="name" required class="regular-text"
                                    value="<?php echo $edit_rate ? esc_attr($edit_rate['Name']) : ''; ?>"
                                    placeholder="<?php _e('e.g., Standard Hourly Rate', 'my-village-hall'); ?>">
                            </td>
                        </tr>

                        <tr>
                            <th><?php _e('Room', 'my-village-hall'); ?> *</th>
                            <td>
                                <select name="room_id" required class="regular-text">
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
                                            <?php selected($selected_room_id, \intval($room['Id'])); ?>>
                                            <?php echo esc_html($room['Name']); ?>
                                        </option>
                                    <?php
                                    endforeach;
                                    if ($current_venue !== '') echo '</optgroup>';
                                    ?>
                                </select>
                            </td>
                        </tr>

                        <tr>
                            <th><?php _e('Rate Amount', 'my-village-hall'); ?> *</th>
                            <td>
                                <input type="number" name="rate" required step="0.01" min="0" class="regular-text"
                                    value="<?php echo $edit_rate ? esc_attr($edit_rate['Rate']) : ''; ?>">
                                <span class="description">£ (GBP)</span>
                            </td>
                        </tr>

                        <tr>
                            <th><?php _e('Charge Type', 'my-village-hall'); ?> *</th>
                            <td>
                                <select name="charge_type" required class="regular-text">
                                    <option value="per_hour" <?php selected($edit_rate && $edit_rate['ChargeType'] == 'per_hour'); ?>>
                                        <?php _e('Per Hour', 'my-village-hall'); ?>
                                    </option>
                                    <option value="per_day" <?php selected($edit_rate && $edit_rate['ChargeType'] == 'per_day'); ?>>
                                        <?php _e('Per Day', 'my-village-hall'); ?>
                                    </option>
                                    <option value="fixed" <?php selected($edit_rate && $edit_rate['ChargeType'] == 'fixed'); ?>>
                                        <?php _e('Fixed Rate', 'my-village-hall'); ?>
                                    </option>
                                </select>
                            </td>
                        </tr>

                        <tr>
                            <th><?php _e('Organisation Type', 'my-village-hall'); ?></th>
                            <td>
                                <select name="organisation_type_id" class="regular-text">
                                    <option value=""><?php _e('All Types', 'my-village-hall'); ?></option>
                                    <?php foreach ($org_types as $ot): ?>
                                        <option value="<?php echo $ot['Id']; ?>"
                                            <?php selected($edit_rate && $edit_rate['OrganisationTypeId'] == $ot['Id']); ?>>
                                            <?php echo esc_html($ot['Name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description"><?php _e('Leave blank to apply to all organisation types.', 'my-village-hall'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th><?php _e('Days', 'my-village-hall'); ?></th>
                            <td>
                                <div style="display:flex; flex-direction:column; gap:6px;">
                                    <label style="display:flex; align-items:center; gap:10px; cursor:pointer;"><input type="checkbox" name="days_of_week[]" value="0" style="flex-shrink:0; width:16px; height:16px;" <?php checked(isset($current_days_lookup[0])); ?>> <span style="min-width:90px;"><?php _e('Sunday', 'my-village-hall'); ?></span></label>
                                    <label style="display:flex; align-items:center; gap:10px; cursor:pointer;"><input type="checkbox" name="days_of_week[]" value="1" style="flex-shrink:0; width:16px; height:16px;" <?php checked(isset($current_days_lookup[1])); ?>> <span style="min-width:90px;"><?php _e('Monday', 'my-village-hall'); ?></span></label>
                                    <label style="display:flex; align-items:center; gap:10px; cursor:pointer;"><input type="checkbox" name="days_of_week[]" value="2" style="flex-shrink:0; width:16px; height:16px;" <?php checked(isset($current_days_lookup[2])); ?>> <span style="min-width:90px;"><?php _e('Tuesday', 'my-village-hall'); ?></span></label>
                                    <label style="display:flex; align-items:center; gap:10px; cursor:pointer;"><input type="checkbox" name="days_of_week[]" value="3" style="flex-shrink:0; width:16px; height:16px;" <?php checked(isset($current_days_lookup[3])); ?>> <span style="min-width:90px;"><?php _e('Wednesday', 'my-village-hall'); ?></span></label>
                                    <label style="display:flex; align-items:center; gap:10px; cursor:pointer;"><input type="checkbox" name="days_of_week[]" value="4" style="flex-shrink:0; width:16px; height:16px;" <?php checked(isset($current_days_lookup[4])); ?>> <span style="min-width:90px;"><?php _e('Thursday', 'my-village-hall'); ?></span></label>
                                    <label style="display:flex; align-items:center; gap:10px; cursor:pointer;"><input type="checkbox" name="days_of_week[]" value="5" style="flex-shrink:0; width:16px; height:16px;" <?php checked(isset($current_days_lookup[5])); ?>> <span style="min-width:90px;"><?php _e('Friday', 'my-village-hall'); ?></span></label>
                                    <label style="display:flex; align-items:center; gap:10px; cursor:pointer;"><input type="checkbox" name="days_of_week[]" value="6" style="flex-shrink:0; width:16px; height:16px;" <?php checked(isset($current_days_lookup[6])); ?>> <span style="min-width:90px;"><?php _e('Saturday', 'my-village-hall'); ?></span></label>
                                </div>
                                <p class="description"><?php _e('Leave all unchecked to apply this rate every day.', 'my-village-hall'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th><?php _e('Time Window', 'my-village-hall'); ?></th>
                            <td>
                                <div style="display:flex; align-items:center; gap:8px; max-width:420px;">
                                    <input type="text" name="start_time" class="regular-text myvh-rate-time-input" data-myvh-picker="time" data-myvh-minute-increment="15" autocomplete="off" placeholder="HH:MM" value="<?php echo esc_attr($current_start_time !== '' ? substr($current_start_time, 0, 5) : ''); ?>">
                                    <span><?php _e('to', 'my-village-hall'); ?></span>
                                    <input type="text" name="end_time" class="regular-text myvh-rate-time-input" data-myvh-picker="time" data-myvh-minute-increment="15" autocomplete="off" placeholder="HH:MM" value="<?php echo esc_attr($current_end_time !== '' ? substr($current_end_time, 0, 5) : ''); ?>">
                                </div>
                                <p class="description"><?php _e('Leave both blank to apply all day. Times must be in 15-minute steps.', 'my-village-hall'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th><?php _e('Description', 'my-village-hall'); ?></th>
                            <td>
                                <textarea name="description" class="large-text" rows="2"><?php echo $edit_rate ? esc_textarea($edit_rate['Description']) : ''; ?></textarea>
                            </td>
                        </tr>

                        <input type="hidden" name="minimum_hours" value="<?php echo $edit_rate ? esc_attr($edit_rate['MinimumHours']) : '1'; ?>">

                        <tr>
                            <th><?php _e('Valid From', 'my-village-hall'); ?></th>
                            <td>
                                <input type="text" name="valid_from" class="regular-text" data-myvh-picker="date" autocomplete="off"
                                    value="<?php echo $edit_rate ? esc_attr($edit_rate['ValidFrom']) : ''; ?>">
                                <p class="description"><?php _e('Leave blank for no start date restriction', 'my-village-hall'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th><?php _e('Valid To', 'my-village-hall'); ?></th>
                            <td>
                                <input type="text" name="valid_to" class="regular-text" data-myvh-picker="date" autocomplete="off"
                                    value="<?php echo $edit_rate ? esc_attr($edit_rate['ValidTo']) : ''; ?>">
                                <p class="description"><?php _e('Leave blank for no end date restriction', 'my-village-hall'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th><?php _e('Priority', 'my-village-hall'); ?></th>
                            <td>
                                <input type="number" name="priority" min="0" class="small-text"
                                    value="<?php echo $edit_rate ? esc_attr($edit_rate['Priority']) : '0'; ?>">
                                <p class="description"><?php _e('Higher number = higher priority when multiple rates match', 'my-village-hall'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th><?php _e('Status', 'my-village-hall'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="is_active" value="1"
                                        <?php checked(!$edit_rate || $edit_rate['IsActive']); ?>>
                                    <?php _e('Active', 'my-village-hall'); ?>
                                </label>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button class="button button-primary">
                            <?php echo $edit_rate ? __('Update Rate', 'my-village-hall') : __('Add Rate', 'my-village-hall'); ?>
                        </button>
                        <?php if ($edit_rate): ?>
                            <a href="<?php echo admin_url('admin.php?page=myvh-room-rates'); ?>" class="button">
                                <?php _e('Cancel', 'my-village-hall'); ?>
                            </a>
                        <?php endif; ?>
                    </p>
                </form>

                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>
