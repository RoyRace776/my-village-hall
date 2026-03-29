<?php
if (!defined('ABSPATH')) exit;
if (!current_user_can('manage_myvh')) wp_die(__('Permission denied', 'my-village-hall'));

global $myvh_container;

$edit_id      = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
$type_service = $myvh_container->get(OrganisationTypeService::class);
$edit_type    = $edit_id ? $type_service->get($edit_id) : null;
$types        = $type_service->get_all();
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Organisation Types', 'my-village-hall'); ?></h1>
    <a href="<?php echo admin_url('admin.php?page=myvh-org-types&add=1'); ?>" class="page-title-action">
        <?php _e('Add New', 'my-village-hall'); ?>
    </a>
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

    <div class="myvh-row">

        <!-- ── List ────────────────────────────────────────────────────────── -->
        <div class="myvh-col-60">
            <div class="myvh-card">
                <h2><?php _e('All Organisation Types', 'my-village-hall'); ?></h2>

                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th><?php _e('Name', 'my-village-hall'); ?></th>
                            <th><?php _e('Description', 'my-village-hall'); ?></th>
                            <th><?php _e('Actions', 'my-village-hall'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($types)): ?>
                            <tr><td colspan="3"><?php _e('No organisation types found.', 'my-village-hall'); ?></td></tr>
                        <?php else: ?>
                            <?php foreach ($types as $type): ?>
                                <tr>
                                    <td><strong><?php echo esc_html($type['Name']); ?></strong></td>
                                    <td><?php echo esc_html($type['Description'] ?? ''); ?></td>
                                    <td>
                                        <a href="<?php echo admin_url('admin.php?page=myvh-org-types&edit=' . $type['Id']); ?>">
                                            <?php _e('Edit', 'my-village-hall'); ?>
                                        </a> |
                                        <a href="<?php echo wp_nonce_url(
                                            admin_url('admin-post.php?action=myvh_delete_org_type&id=' . $type['Id']),
                                            'myvh_delete_org_type'
                                        ); ?>" class="link-delete"
                                           onclick="return confirm('<?php _e('Delete this type?', 'my-village-hall'); ?>');">
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

        <!-- ── Form ─────────────────────────────────────────────────────────── -->
        <?php if (isset($_GET['add']) || $edit_type): ?>
        <div class="myvh-col-40">
            <div class="myvh-card">
                <h2><?php echo $edit_type ? __('Edit Organisation Type', 'my-village-hall') : __('Add Organisation Type', 'my-village-hall'); ?></h2>

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
                                    value="<?php echo $edit_type ? esc_attr($edit_type['Name']) : ''; ?>">
                            </td>
                        </tr>

                        <tr>
                            <th><?php _e('Description', 'my-village-hall'); ?></th>
                            <td>
                                <textarea name="description" class="large-text" rows="3"><?php echo $edit_type ? esc_textarea($edit_type['Description'] ?? '') : ''; ?></textarea>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button class="button button-primary">
                            <?php echo $edit_type ? __('Update Type', 'my-village-hall') : __('Add Type', 'my-village-hall'); ?>
                        </button>
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
