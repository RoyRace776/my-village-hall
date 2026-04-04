<?php
if (!defined('ABSPATH')) exit;
if (!current_user_can('manage_myvh')) wp_die(__('Permission denied', 'my-village-hall'));

global $myvh_container;
use MYVH\Organisations\OrganisationTypeService;

$manage_type  = isset($_GET['manage_type']) ? sanitize_text_field(wp_unslash($_GET['manage_type'])) : '';
if ($manage_type !== '') {
    if ($manage_type === '__add_new__') {
        wp_safe_redirect(admin_url('admin.php?page=myvh-org-types&add=1'));
        exit;
    }

    if (ctype_digit($manage_type)) {
        wp_safe_redirect(admin_url('admin.php?page=myvh-org-types&edit=' . intval($manage_type)));
        exit;
    }
}

$edit_id      = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
$type_service = $myvh_container->get(OrganisationTypeService::class);
$edit_type    = $edit_id ? $type_service->get($edit_id) : null;
$edit_type_is_system = !empty($edit_type['IsSystem']);
$types        = $type_service->get_all();
$invalid_edit = isset($_GET['edit']) && $edit_id > 0 && !$edit_type;
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Organisation Types', 'my-village-hall'); ?></h1>
    <hr class="wp-header-end">

    <?php if (isset($_GET['updated'])): ?>
        <div class="notice notice-success is-dismissible"><p><?php _e('Organisation type saved.', 'my-village-hall'); ?></p></div>
    <?php endif; ?>
    <?php if (isset($_GET['deleted'])): ?>
        <div class="notice notice-success is-dismissible"><p><?php _e('Organisation type deleted.', 'my-village-hall'); ?></p></div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div class="notice notice-error is-dismissible"><p><?php echo esc_html($_GET['error']); ?></p></div>
    <?php endif; ?>
    <?php if ($invalid_edit): ?>
        <div class="notice notice-error is-dismissible"><p><?php _e('The selected organisation type could not be found.', 'my-village-hall'); ?></p></div>
    <?php endif; ?>

    <div class="myvh-row">

        <div class="myvh-col-60">
            <div class="myvh-card">
                <h2><?php _e('Select Organisation Type', 'my-village-hall'); ?></h2>
                <p><?php _e('Choose a type to edit, or choose Add New Organisation Type to create one.', 'my-village-hall'); ?></p>

                <?php if (empty($types)): ?>
                    <p>
                        <a href="<?php echo admin_url('admin.php?page=myvh-org-types&add=1'); ?>" class="button button-primary">
                            <?php _e('Add Organisation Type', 'my-village-hall'); ?>
                        </a>
                    </p>
                <?php else: ?>
                    <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
                        <input type="hidden" name="page" value="myvh-org-types">
                        <p>
                            <select name="manage_type" size="12" style="width: 100%; max-width: 420px;">
                                <option value=""><?php _e('Select an organisation type...', 'my-village-hall'); ?></option>
                                <option value="__add_new__"><?php _e('Add New Organisation Type', 'my-village-hall'); ?></option>
                                <?php foreach ($types as $type): ?>
                                    <?php
                                        $label = $type['Name'];
                                        if (!empty($type['IsDefault'])) {
                                            $label .= ' (' . __('Default', 'my-village-hall') . ')';
                                        }
                                        if (!empty($type['IsSystem'])) {
                                            $label .= ' (' . __('System', 'my-village-hall') . ')';
                                        }
                                    ?>
                                    <option value="<?php echo intval($type['Id']); ?>" <?php selected($edit_id, intval($type['Id'])); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </p>
                        <p class="submit">
                            <button type="submit" class="button button-primary"><?php _e('Go', 'my-village-hall'); ?></button>
                            <a href="<?php echo admin_url('admin.php?page=myvh-org-types&add=1'); ?>" class="button">
                                <?php _e('Add New Type', 'my-village-hall'); ?>
                            </a>
                        </p>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <?php if (isset($_GET['add']) || $edit_type): ?>
        <div class="myvh-col-40">
            <div class="myvh-card">
                <h2><?php echo $edit_type ? __('Edit Organisation Type', 'my-village-hall') : __('Add Organisation Type', 'my-village-hall'); ?></h2>

                <?php if ($edit_type_is_system): ?>
                    <div class="notice notice-warning inline"><p><?php _e('This is a system organisation type and cannot be edited.', 'my-village-hall'); ?></p></div>
                <?php endif; ?>

                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <input type="hidden" name="action" value="myvh_save_org_type">
                    <?php wp_nonce_field('myvh_save_org_type'); ?>
                    <?php if ($edit_type): ?>
                        <input type="hidden" name="org_type_id" value="<?php echo $edit_type['Id']; ?>">
                    <?php endif; ?>

                    <table class="form-table">
                        <tr>
                            <th><?php _e('Name', 'my-village-hall'); ?> *</th>
                            <td>
                                <input type="text" name="name" required class="regular-text"
                                    value="<?php echo $edit_type ? esc_attr($edit_type['Name']) : ''; ?>"
                                    <?php disabled($edit_type_is_system); ?>>
                            </td>
                        </tr>

                        <tr>
                            <th><?php _e('Description', 'my-village-hall'); ?></th>
                            <td>
                                <textarea name="description" class="large-text" rows="3" <?php disabled($edit_type_is_system); ?>><?php echo $edit_type ? esc_textarea($edit_type['Description'] ?? '') : ''; ?></textarea>
                            </td>
                        </tr>

                        <tr>
                            <th><?php _e('Default Type', 'my-village-hall'); ?></th>
                            <td>
                                <?php if ($edit_type_is_system): ?>
                                    <span><?php echo !empty($edit_type['IsDefault']) ? __('Yes', 'my-village-hall') : __('No', 'my-village-hall'); ?></span>
                                <?php else: ?>
                                    <label>
                                        <input type="checkbox" name="is_default" value="1" <?php checked(!empty($edit_type['IsDefault'])); ?>>
                                        <?php _e('Set as default organisation type', 'my-village-hall'); ?>
                                    </label>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <?php if (!$edit_type_is_system): ?>
                            <button class="button button-primary">
                                <?php echo $edit_type ? __('Update Type', 'my-village-hall') : __('Add Type', 'my-village-hall'); ?>
                            </button>
                        <?php endif; ?>
                        <?php if ($edit_type): ?>
                            <a href="<?php echo admin_url('admin.php?page=myvh-org-types'); ?>" class="button">
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
