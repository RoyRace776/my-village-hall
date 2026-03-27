<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('manage_myvh')) {
    wp_die(__('Permission denied', 'my-village-hall'));
}

global $myvh_container;

$edit_id      = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
$addon_service = $myvh_container->get(AddonService::class);
$room_service  = $myvh_container->get(RoomService::class);
$edit_addon    = $edit_id ? $addon_service->get($edit_id) : null;
$addons        = $addon_service->get_with_relations();
$rooms         = $room_service->get_all();
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Add-ons', 'my-village-hall'); ?></h1>
    <a href="<?php echo admin_url('admin.php?page=myvh-addons&add=1'); ?>" class="page-title-action">
        <?php _e('Add New', 'my-village-hall'); ?>
    </a>
    <hr class="wp-header-end">

    <?php if (isset($_GET['updated'])): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e('Add-on saved successfully', 'my-village-hall'); ?></p>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e('Add-on deleted successfully', 'my-village-hall'); ?></p>
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
                <h2><?php _e('All Add-ons', 'my-village-hall'); ?></h2>

                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th><?php _e('Name', 'my-village-hall'); ?></th>
                            <th><?php _e('Price', 'my-village-hall'); ?></th>
                            <th><?php _e('Type', 'my-village-hall'); ?></th>
                            <th><?php _e('Room', 'my-village-hall'); ?></th>
                            <th><?php _e('Status', 'my-village-hall'); ?></th>
                            <th><?php _e('Actions', 'my-village-hall'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($addons)): ?>
                            <tr>
                                <td colspan="6"><?php _e('No add-ons found', 'my-village-hall'); ?></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($addons as $addon): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($addon['Name']); ?></strong>
                                        <?php if ($addon['Description']): ?>
                                            <br><small style="color:#666;"><?php echo esc_html($addon['Description']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>£<?php echo number_format($addon['Price'], 2); ?></td>
                                    <td><?php echo esc_html(ucfirst(str_replace('_', ' ', $addon['ChargeType']))); ?></td>
                                    <td>
                                        <?php if (!empty($addon['RoomName'])): ?>
                                            <?php echo esc_html($addon['RoomName']); ?>
                                        <?php else: ?>
                                            <span style="color:#999;"><?php _e('All Rooms', 'my-village-hall'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo $addon['IsActive']
                                            ? '<span style="color: #46b450;">●</span> ' . __('Active',   'my-village-hall')
                                            : '<span style="color: #dc3232;">●</span> ' . __('Inactive', 'my-village-hall'); ?>
                                    </td>
                                    <td>
                                        <a href="<?php echo admin_url('admin.php?page=myvh-addons&edit=' . $addon['Id']); ?>">
                                            <?php _e('Edit', 'my-village-hall'); ?>
                                        </a> |
                                        <a href="<?php echo wp_nonce_url(
                                            admin_url('admin-post.php?action=myvh_delete_addon&id=' . $addon['Id']),
                                            'myvh_delete_addon'
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

        <?php if (isset($_GET['add']) || $edit_addon): ?>
        <div class="myvh-col-40">
            <div class="myvh-card">
                <h2><?php echo $edit_addon ? __('Edit Add-on', 'my-village-hall') : __('Add Add-on', 'my-village-hall'); ?></h2>

                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <input type="hidden" name="action" value="myvh_save_addon">
                    <?php wp_nonce_field('myvh_save_addon'); ?>
                    <?php if ($edit_addon): ?>
                        <input type="hidden" name="addon_id" value="<?php echo $edit_addon['Id']; ?>">
                    <?php endif; ?>

                    <table class="form-table">
                        <tr>
                            <th><?php _e('Name', 'my-village-hall'); ?> *</th>
                            <td>
                                <input type="text" name="name" required class="regular-text"
                                    value="<?php echo $edit_addon ? esc_attr($edit_addon['Name']) : ''; ?>">
                            </td>
                        </tr>

                        <tr>
                            <th><?php _e('Description', 'my-village-hall'); ?></th>
                            <td>
                                <textarea name="description" class="large-text" rows="3"><?php echo $edit_addon ? esc_textarea($edit_addon['Description']) : ''; ?></textarea>
                            </td>
                        </tr>

                        <tr>
                            <th><?php _e('Price', 'my-village-hall'); ?> *</th>
                            <td>
                                <input type="number" name="price" required step="0.01" min="0"
                                    value="<?php echo $edit_addon ? esc_attr($edit_addon['Price']) : '0.00'; ?>">
                            </td>
                        </tr>

                        <tr>
                            <th><?php _e('Charge Type', 'my-village-hall'); ?> *</th>
                            <td>
                                <select name="charge_type" required class="regular-text">
                                    <option value="fixed" <?php selected($edit_addon && $edit_addon['ChargeType'] == 'fixed'); ?>>
                                        <?php _e('Fixed', 'my-village-hall'); ?>
                                    </option>
                                    <option value="per_hour" <?php selected($edit_addon && $edit_addon['ChargeType'] == 'per_hour'); ?>>
                                        <?php _e('Per Hour', 'my-village-hall'); ?>
                                    </option>
                                    <option value="per_day" <?php selected($edit_addon && $edit_addon['ChargeType'] == 'per_day'); ?>>
                                        <?php _e('Per Day', 'my-village-hall'); ?>
                                    </option>
                                </select>
                            </td>
                        </tr>

                        <tr>
                            <th><?php _e('Room', 'my-village-hall'); ?></th>
                            <td>
                                <select name="room_id" class="regular-text">
                                    <option value=""><?php _e('All Rooms', 'my-village-hall'); ?></option>
                                    <?php foreach ($rooms as $room): ?>
                                        <option value="<?php echo $room['Id']; ?>"
                                            <?php selected($edit_addon && $edit_addon['RoomId'] == $room['Id']); ?>>
                                            <?php echo esc_html($room['Name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description"><?php _e('Leave blank to make available for all rooms.', 'my-village-hall'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th><?php _e('Display Order', 'my-village-hall'); ?></th>
                            <td>
                                <input type="number" name="display_order" min="0"
                                    value="<?php echo $edit_addon ? esc_attr($edit_addon['DisplayOrder']) : '0'; ?>">
                            </td>
                        </tr>

                        <tr>
                            <th><?php _e('Options', 'my-village-hall'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="is_active" value="1"
                                        <?php checked(!$edit_addon || $edit_addon['IsActive']); ?>>
                                    <?php _e('Active', 'my-village-hall'); ?>
                                </label>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button class="button button-primary">
                            <?php echo $edit_addon ? __('Update Add-on', 'my-village-hall') : __('Add Add-on', 'my-village-hall'); ?>
                        </button>
                        <?php if ($edit_addon): ?>
                            <a href="<?php echo admin_url('admin.php?page=myvh-addons'); ?>" class="button">
                                <?php _e('Cancel', 'my-village-hall'); ?>
                            </a>
                        <?php endif; ?>
                    </p>
                </form>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>
