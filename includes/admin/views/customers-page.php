<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('manage_myvh')) {
    wp_die(__('Permission denied', 'my-village-hall'));
}

global $myvh_container;

$edit_id       = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
$customer_service       = $myvh_container->get('customer_service');
$customer_group_service = $myvh_container->get('customer_group_service');
$edit_customer = $edit_id ? $customer_service->get($edit_id) : null;
$customers     = $customer_service->get_with_groups();
$groups        = $customer_group_service->get_all();
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Customers', 'my-village-hall'); ?></h1>
    <a href="<?php echo admin_url('admin.php?page=myvh-customers&add=1'); ?>" class="page-title-action">
        <?php _e('Add New', 'my-village-hall'); ?>
    </a>
    <hr class="wp-header-end">

    <?php if (isset($_GET['updated'])): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e('Customer saved successfully', 'my-village-hall'); ?></p>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e('Customer deleted successfully', 'my-village-hall'); ?></p>
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
                <h2><?php _e('All Customers', 'my-village-hall'); ?></h2>

                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th><?php _e('Name', 'my-village-hall'); ?></th>
                            <th><?php _e('Email', 'my-village-hall'); ?></th>
                            <th><?php _e('Phone', 'my-village-hall'); ?></th>
                            <th><?php _e('Group', 'my-village-hall'); ?></th>
                            <th><?php _e('Actions', 'my-village-hall'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($customers)): ?>
                            <tr>
                                <td colspan="5"><?php _e('No customers found', 'my-village-hall'); ?></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($customers as $customer): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($customer['Name']); ?></strong>
                                        <?php if ($customer['EmailVerified']): ?>
                                            <span class="dashicons dashicons-yes-alt" style="color: #46b450;" title="<?php _e('Email verified', 'my-village-hall'); ?>"></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html($customer['Email']); ?></td>
                                    <td><?php echo esc_html($customer['PhoneNumber'] ?? '-'); ?></td>
                                    <td><?php echo esc_html($customer['CustomerGroupName'] ?? __('None', 'my-village-hall')); ?></td>
                                    <td>
                                        <a href="<?php echo admin_url('admin.php?page=myvh-customers&edit=' . $customer['Id']); ?>">
                                            <?php _e('Edit', 'my-village-hall'); ?>
                                        </a> |
                                        <a href="<?php echo wp_nonce_url(
                                            admin_url('admin-post.php?action=myvh_delete_customer&id=' . $customer['Id']),
                                            'myvh_delete_customer'
                                        ); ?>" class="link-delete" onclick="return confirm('<?php _e('Are you sure you want to delete this customer?', 'my-village-hall'); ?>');">
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

        <?php if (isset($_GET['add']) || $edit_customer): ?>
        <div class="myvh-col-40">
            <div class="myvh-card">
                <h2><?php echo $edit_customer ? __('Edit Customer', 'my-village-hall') : __('Add Customer', 'my-village-hall'); ?></h2>

                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <input type="hidden" name="action" value="myvh_save_customer">
                    <?php wp_nonce_field('myvh_save_customer'); ?>
                    <?php if ($edit_customer): ?>
                        <input type="hidden" name="customer_id" value="<?php echo $edit_customer['Id']; ?>">
                    <?php endif; ?>

                    <table class="form-table">
                        <tr>
                            <th><?php _e('Name', 'my-village-hall'); ?> *</th>
                            <td><input type="text" name="name" required class="regular-text"
                                value="<?php echo $edit_customer ? esc_attr($edit_customer['Name']) : ''; ?>"></td>
                        </tr>

                        <tr>
                            <th><?php _e('Email', 'my-village-hall'); ?> *</th>
                            <td><input type="email" name="email" required class="regular-text"
                                value="<?php echo $edit_customer ? esc_attr($edit_customer['Email']) : ''; ?>"></td>
                        </tr>

                        <tr>
                            <th><?php _e('Phone Number', 'my-village-hall'); ?></th>
                            <td><input type="tel" name="phone_number" class="regular-text"
                                value="<?php echo $edit_customer ? esc_attr($edit_customer['PhoneNumber']) : ''; ?>"></td>
                        </tr>

                        <tr>
                            <th><?php _e('Post Code', 'my-village-hall'); ?></th>
                            <td><input type="text" name="post_code" class="regular-text"
                                value="<?php echo $edit_customer ? esc_attr($edit_customer['PostCode']) : ''; ?>"></td>
                        </tr>

                        <tr>
                            <th><?php _e('Address', 'my-village-hall'); ?></th>
                            <td><input type="text" name="address_line1" class="large-text"
                                value="<?php echo $edit_customer ? esc_attr($edit_customer['AddressLine1']) : ''; ?>"></td>
                        </tr>

                        <tr>
                            <th><?php _e('Customer Group', 'my-village-hall'); ?></th>
                            <td>
                                <select name="customer_group_id" class="regular-text">
                                    <option value=""><?php _e('None', 'my-village-hall'); ?></option>
                                    <?php foreach ($groups as $group): ?>
                                        <option value="<?php echo $group['Id']; ?>"
                                            <?php selected($edit_customer && $edit_customer['CustomerGroupId'] == $group['Id']); ?>>
                                            <?php echo esc_html($group['Name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>

                        <tr>
                            <th><?php _e('Options', 'my-village-hall'); ?></th>
                            <td>
                                <label><input type="checkbox" name="email_verified" value="1"
                                    <?php checked($edit_customer && $edit_customer['EmailVerified']); ?>>
                                    <?php _e('Email verified', 'my-village-hall'); ?></label>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button class="button button-primary">
                            <?php echo $edit_customer ? __('Update Customer', 'my-village-hall') : __('Add Customer', 'my-village-hall'); ?>
                        </button>
                        <?php if ($edit_customer): ?>
                            <a href="<?php echo admin_url('admin.php?page=myvh-customers'); ?>" class="button">
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
