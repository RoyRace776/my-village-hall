<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('manage_myvh')) {
    wp_die(__('Permission denied', 'my-village-hall'));
}


$edit_id    = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
$customer_group_service = MYVH_Registry::get('customer_group_service');
$edit_group = $edit_id ? $customer_group_service->get($edit_id) : null;
$groups     = $customer_group_service->get_all(false);
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Customer Groups', 'my-village-hall'); ?></h1>
    <a href="<?php echo admin_url('admin.php?page=myvh-customer-groups&add=1'); ?>" class="page-title-action">
        <?php _e('Add New', 'my-village-hall'); ?>
    </a>
    <hr class="wp-header-end">

    <div class="myvh-row">

        <div class="myvh-col-60">
            <div class="myvh-card">
                <h2><?php _e('Customer Groups', 'my-village-hall'); ?></h2>

                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th><?php _e('Name', 'my-village-hall'); ?></th>
                            <th><?php _e('Discount', 'my-village-hall'); ?></th>
                            <th><?php _e('Status', 'my-village-hall'); ?></th>
                            <th><?php _e('Actions', 'my-village-hall'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($groups as $group): ?>
                            <tr>
                                <td><strong><?php echo esc_html($group['Name']); ?></strong></td>
                                <td><?php echo number_format($group['DiscountPercentage'], 1); ?>%</td>
                                <td><?php echo $group['IsActive'] ? __('Active', 'my-village-hall') : __('Inactive', 'my-village-hall'); ?></td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=myvh-customer-groups&edit=' . $group['Id']); ?>">
                                        <?php _e('Edit', 'my-village-hall'); ?>
                                    </a> |
                                    <a href="<?php echo wp_nonce_url(
                                        admin_url('admin-post.php?action=myvh_delete_customer_group&id=' . $group['Id']),
                                        'myvh_delete_customer_group'
                                    ); ?>" class="link-delete">
                                        <?php _e('Delete', 'my-village-hall'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="myvh-col-40">
            <div class="myvh-card">
                <h2><?php echo $edit_group ? __('Edit Customer Group', 'my-village-hall') : __('Add Customer Group', 'my-village-hall'); ?></h2>

                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <input type="hidden" name="action" value="myvh_save_customer_group">
                    <?php wp_nonce_field('myvh_save_customer_group'); ?>
                    <?php if ($edit_group): ?>
                        <input type="hidden" name="group_id" value="<?php echo $edit_group['Id']; ?>">
                    <?php endif; ?>

                    <table class="form-table">
                        <tr>
                            <th><?php _e('Name', 'my-village-hall'); ?></th>
                            <td><input type="text" name="name" required class="regular-text"
                                value="<?php echo $edit_group ? esc_attr($edit_group['Name']) : ''; ?>"></td>
                        </tr>

                        <tr>
                            <th><?php _e('Description', 'my-village-hall'); ?></th>
                            <td><textarea name="description" class="large-text"><?php echo $edit_group ? esc_textarea($edit_group['Description']) : ''; ?></textarea></td>
                        </tr>

                        <tr>
                            <th><?php _e('Discount %', 'my-village-hall'); ?></th>
                            <td><input type="number" name="discount_percentage" step="0.01"
                                value="<?php echo $edit_group ? esc_attr($edit_group['DiscountPercentage']) : '0'; ?>"></td>
                        </tr>

                        <tr>
                            <th><?php _e('Display Order', 'my-village-hall'); ?></th>
                            <td><input type="number" name="display_order"
                                value="<?php echo $edit_group ? esc_attr($edit_group['DisplayOrder']) : '0'; ?>"></td>
                        </tr>

                        <tr>
                            <th><?php _e('Options', 'my-village-hall'); ?></th>
                            <td>
                                <label><input type="checkbox" name="is_default" value="1"
                                    <?php checked($edit_group && $edit_group['IsDefault']); ?>>
                                    <?php _e('Default group', 'my-village-hall'); ?></label><br>

                                <label><input type="checkbox" name="is_active" value="1"
                                    <?php checked(!$edit_group || $edit_group['IsActive']); ?>>
                                    <?php _e('Active', 'my-village-hall'); ?></label>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button class="button button-primary">
                            <?php echo $edit_group ? __('Update Customer Group', 'my-village-hall') : __('Add Customer Group', 'my-village-hall'); ?>
                        </button>
                    </p>
                </form>
            </div>
        </div>

    </div>
</div>