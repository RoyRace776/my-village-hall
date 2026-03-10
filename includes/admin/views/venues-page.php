<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('manage_myvh')) {
    wp_die(__('You do not have sufficient permissions to access this page.', 'my-village-hall'));
}

global $myvh_container;

$action   = isset($_GET['action']) ? sanitize_key($_GET['action']) : 'list';
$venue_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$venue_service = $myvh_container->get(MYVH_Venue_Service::class);
$venue  = $venue_id ? $venue_service->get($venue_id) : null;
$venues = $venue_service->get_all();
?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <?php echo ($action === 'edit' || $action === 'add')
            ? ($venue_id ? __('Edit Venue', 'my-village-hall') : __('Add New Venue', 'my-village-hall'))
            : __('Venues', 'my-village-hall'); ?>
    </h1>

    <?php if ($action === 'list'): ?>

        <a href="<?php echo admin_url('admin.php?page=myvh-venues&action=add'); ?>" class="page-title-action">
            <?php _e('Add New', 'my-village-hall'); ?>
        </a>

        <hr class="wp-header-end">

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th><?php _e('Name', 'my-village-hall'); ?></th>
                    <th><?php _e('Short Name', 'my-village-hall'); ?></th>
                    <th><?php _e('Post Code', 'my-village-hall'); ?></th>
                    <th><?php _e('Address', 'my-village-hall'); ?></th>
                    <th><?php _e('Actions', 'my-village-hall'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($venues)): ?>
                    <tr><td colspan="6"><?php _e('No venues found', 'my-village-hall'); ?></td></tr>
                <?php else: ?>
                    <?php foreach ($venues as $venue): ?>
                        <tr>
                            <td><?php echo intval($venue['Id']); ?></td>
                            <td><strong><?php echo esc_html($venue['Name']); ?></strong></td>
                            <td><?php echo esc_html($venue['ShortName']); ?></td>
                            <td><?php echo esc_html($venue['PostCode']); ?></td>
                            <td><?php echo esc_html($venue['AddressLine1']); ?></td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=myvh-venues&action=edit&id=' . $venue['Id']); ?>">
                                    <?php _e('Edit', 'my-village-hall'); ?>
                                </a> |
                                <a href="<?php echo wp_nonce_url(
                                    admin_url('admin-post.php?action=myvh_delete_venue&id=' . $venue['Id']),
                                    'myvh_delete_venue'
                                ); ?>" class="link-delete">
                                    <?php _e('Delete', 'my-village-hall'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

    <?php else: ?>

        <hr class="wp-header-end">

        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="max-width:800px;">
            <input type="hidden" name="action" value="myvh_save_venue">
            <?php wp_nonce_field('myvh_save_venue'); ?>
            <input type="hidden" name="venue_id" value="<?php echo $venue_id; ?>">

            <table class="form-table">
                <tr>
                    <th><label for="name"><?php _e('Venue Name', 'my-village-hall'); ?> *</label></th>
                    <td><input type="text" name="name" required class="regular-text"
                        value="<?php echo $venue ? esc_attr($venue['Name']) : ''; ?>"></td>
                </tr>

                <tr>
                    <th><label><?php _e('Short Name', 'my-village-hall'); ?></label></th>
                    <td><input type="text" name="short_name" class="regular-text"
                        value="<?php echo $venue ? esc_attr($venue['ShortName']) : ''; ?>"></td>
                </tr>

                <tr>
                    <th><label><?php _e('Post Code', 'my-village-hall'); ?></label></th>
                    <td><input type="text" name="post_code" class="regular-text"
                        value="<?php echo $venue ? esc_attr($venue['PostCode']) : ''; ?>"></td>
                </tr>

                <tr>
                    <th><label><?php _e('Address', 'my-village-hall'); ?></label></th>
                    <td><input type="text" name="address_line1" class="large-text"
                        value="<?php echo $venue ? esc_attr($venue['AddressLine1']) : ''; ?>"></td>
                </tr>

                <tr>
                    <th><label><?php _e('Opening Time', 'my-village-hall'); ?></label></th>
                    <td>
                        <select name="opening_time">
                            <?php echo myvh_get_time_options($venue ? $venue['OpeningTime'] : '09:00'); ?>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th><label><?php _e('Closing Time', 'my-village-hall'); ?></label></th>
                    <td>
                        <select name="closing_time">
                            <?php echo myvh_get_time_options($venue ? $venue['ClosingTime'] : '17:00'); ?>
                        </select>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <button class="button button-primary">
                    <?php echo $venue_id ? __('Update Venue', 'my-village-hall') : __('Create Venue', 'my-village-hall'); ?>
                </button>
                <a href="<?php echo admin_url('admin.php?page=myvh-venues'); ?>" class="button">
                    <?php _e('Cancel', 'my-village-hall'); ?>
                </a>
            </p>
        </form>

    <?php endif; ?>
</div>