<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('manage_myvh')) {
    wp_die(__('Permission denied', 'my-village-hall'));
}

global $myvh_container;

$edit_id           = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
$room_rate_service = $myvh_container->get(Room_Rate_Service::class);
$room_service      = $myvh_container->get(Room_Service::class);
$org_type_service  = $myvh_container->get(Organisation_Type_Service::class);
$edit_rate         = $edit_id ? $room_rate_service->get($edit_id) : null;
$rates             = $room_rate_service->get_all();
$rooms             = $room_service->get_all_with_venues();
$org_types         = $org_type_service->get_all();
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
                            <th><?php _e('Type', 'my-village-hall'); ?></th>
                            <th><?php _e('Org Type', 'my-village-hall'); ?></th>
                            <th><?php _e('Status', 'my-village-hall'); ?></th>
                            <th><?php _e('Actions', 'my-village-hall'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rates)): ?>
                            <tr>
                                <td colspan="7"><?php _e('No rates found. Add a rate to get started.', 'my-village-hall'); ?></td>
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
                                            <?php selected($edit_rate && $edit_rate['RoomId'] == $room['Id']); ?>>
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
                            <th><?php _e('Description', 'my-village-hall'); ?></th>
                            <td>
                                <textarea name="description" class="large-text" rows="2"><?php echo $edit_rate ? esc_textarea($edit_rate['Description']) : ''; ?></textarea>
                            </td>
                        </tr>

                        <tr>
                            <th><?php _e('Minimum Hours', 'my-village-hall'); ?></th>
                            <td>
                                <input type="number" name="minimum_hours" step="0.5" min="0" class="small-text"
                                    value="<?php echo $edit_rate ? esc_attr($edit_rate['MinimumHours']) : ''; ?>">
                                <span class="description"><?php _e('Optional minimum booking duration', 'my-village-hall'); ?></span>
                            </td>
                        </tr>

                        <tr>
                            <th><?php _e('Valid From', 'my-village-hall'); ?></th>
                            <td>
                                <input type="date" name="valid_from" class="regular-text"
                                    value="<?php echo $edit_rate ? esc_attr($edit_rate['ValidFrom']) : ''; ?>">
                                <p class="description"><?php _e('Leave blank for no start date restriction', 'my-village-hall'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th><?php _e('Valid To', 'my-village-hall'); ?></th>
                            <td>
                                <input type="date" name="valid_to" class="regular-text"
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
