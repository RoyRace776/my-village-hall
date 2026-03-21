<?php
if (!defined('ABSPATH')) exit;
if (!current_user_can('manage_myvh')) wp_die(__('Permission denied', 'my-village-hall'));

global $myvh_container;

$edit_id      = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
$org_service  = $myvh_container->get(MYVH_Organisation_Service::class);
$type_service = $myvh_container->get(MYVH_Organisation_Type_Service::class);

$edit_org  = $edit_id ? $org_service->get($edit_id) : null;
$orgs      = $org_service->get_all_with_type();
$org_types = $type_service->get_all();
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Organisations', 'my-village-hall'); ?></h1>
    <a href="<?php echo admin_url('admin.php?page=myvh-organisations&add=1'); ?>" class="page-title-action">
        <?php _e('Add New', 'my-village-hall'); ?>
    </a>
    <hr class="wp-header-end">

    <?php if (isset($_GET['updated'])): ?>
        <div class="notice notice-success is-dismissible"><p><?php _e('Organisation saved.', 'my-village-hall'); ?></p></div>
    <?php endif; ?>
    <?php if (isset($_GET['deleted'])): ?>
        <div class="notice notice-success is-dismissible"><p><?php _e('Organisation deleted.', 'my-village-hall'); ?></p></div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div class="notice notice-error is-dismissible"><p><?php echo esc_html($_GET['error']); ?></p></div>
    <?php endif; ?>

    <div class="myvh-row">

        <!-- ── List ────────────────────────────────────────────────────────── -->
        <div class="myvh-col-60">
            <div class="myvh-card">
                <h2><?php _e('All Organisations', 'my-village-hall'); ?></h2>

                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th><?php _e('Name', 'my-village-hall'); ?></th>
                            <th><?php _e('Default', 'my-village-hall'); ?></th>
                            <th><?php _e('Default Visibility', 'my-village-hall'); ?></th>
                            <th><?php _e('Type', 'my-village-hall'); ?></th>
                            <th><?php _e('Email', 'my-village-hall'); ?></th>
                            <th><?php _e('Phone', 'my-village-hall'); ?></th>
                            <th><?php _e('Website', 'my-village-hall'); ?></th>
                            <th><?php _e('Status', 'my-village-hall'); ?></th>
                            <th><?php _e('Actions', 'my-village-hall'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orgs)): ?>
                            <tr><td colspan="9"><?php _e('No organisations found.', 'my-village-hall'); ?></td></tr>
                        <?php else: ?>
                            <?php foreach ($orgs as $org): ?>
                                <tr>
                                    <td><strong><?php echo esc_html($org['Name']); ?></strong></td>
                                    <td><?php echo !empty($org['IsDefault']) ? __('Yes', 'my-village-hall') : '—'; ?></td>
                                    <td><?php echo !empty($org['DefaultPublic']) ? __('Public', 'my-village-hall') : __('Private', 'my-village-hall'); ?></td>
                                    <td><?php echo esc_html($org['OrganisationTypeName'] ?? '—'); ?></td>
                                    <td><?php echo esc_html($org['ContactEmail'] ?? '—'); ?></td>
                                    <td><?php echo esc_html($org['ContactPhone'] ?? '—'); ?></td>
                                    <td>
                                        <?php if (!empty($org['WebsiteUrl'])): ?>
                                            <a href="<?php echo esc_url($org['WebsiteUrl']); ?>" target="_blank" rel="noopener noreferrer">
                                                <?php echo esc_html($org['WebsiteUrl']); ?>
                                            </a>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $org['IsActive'] ? __('Active', 'my-village-hall') : __('Inactive', 'my-village-hall'); ?></td>
                                    <td>
                                        <a href="<?php echo admin_url('admin.php?page=myvh-organisations&edit=' . $org['Id']); ?>">
                                            <?php _e('Edit', 'my-village-hall'); ?>
                                        </a> |
                                        <a href="<?php echo admin_url('admin.php?page=myvh-org-members&organisation_id=' . $org['Id']); ?>">
                                            <?php _e('Members', 'my-village-hall'); ?>
                                        </a> |
                                        <a href="<?php echo wp_nonce_url(
                                            admin_url('admin-post.php?action=myvh_delete_organisation&id=' . $org['Id']),
                                            'myvh_delete_organisation'
                                        ); ?>" class="link-delete"
                                           onclick="return confirm('<?php _e('Delete this organisation?', 'my-village-hall'); ?>');">
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
        <?php if (isset($_GET['add']) || $edit_org): ?>
        <div class="myvh-col-40">
            <div class="myvh-card">
                <h2><?php echo $edit_org ? __('Edit Organisation', 'my-village-hall') : __('Add Organisation', 'my-village-hall'); ?></h2>

                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <input type="hidden" name="action" value="myvh_save_organisation">
                    <?php wp_nonce_field('myvh_save_organisation'); ?>
                    <?php if ($edit_org): ?>
                        <input type="hidden" name="organisation_id" value="<?php echo $edit_org['Id']; ?>">
                    <?php endif; ?>

                    <table class="form-table">
                        <tr>
                            <th><?php _e('Name', 'my-village-hall'); ?> *</th>
                            <td>
                                <input type="text" name="name" required class="regular-text"
                                    value="<?php echo $edit_org ? esc_attr($edit_org['Name']) : ''; ?>">
                            </td>
                        </tr>

                        <tr>
                            <th><?php _e('Organisation Type', 'my-village-hall'); ?></th>
                            <td>
                                <select name="organisation_type_id" class="regular-text">
                                    <option value=""><?php _e('— Select Type —', 'my-village-hall'); ?></option>
                                    <?php foreach ($org_types as $type): ?>
                                        <option value="<?php echo $type['Id']; ?>"
                                            <?php selected($edit_org && $edit_org['OrganisationTypeId'] == $type['Id']); ?>>
                                            <?php echo esc_html($type['Name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">
                                    <a href="<?php echo admin_url('admin.php?page=myvh-org-types'); ?>">
                                        <?php _e('Manage organisation types', 'my-village-hall'); ?>
                                    </a>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th><?php _e('Contact Email', 'my-village-hall'); ?> *</th>
                            <td>
                                <input type="email" name="contact_email" required class="regular-text"
                                    value="<?php echo $edit_org ? esc_attr($edit_org['ContactEmail'] ?? '') : ''; ?>">
                            </td>
                        </tr>

                        <tr>
                            <th><?php _e('Contact Phone', 'my-village-hall'); ?> *</th>
                            <td>
                                <input type="tel" name="contact_phone" required class="regular-text"
                                    value="<?php echo $edit_org ? esc_attr($edit_org['ContactPhone'] ?? '') : ''; ?>">
                            </td>
                        </tr>

                        <tr>
                            <th><?php _e('Website URL', 'my-village-hall'); ?></th>
                            <td>
                                <input type="url" name="website_url" class="regular-text"
                                    value="<?php echo $edit_org ? esc_attr($edit_org['WebsiteUrl'] ?? '') : ''; ?>"
                                    placeholder="https://example.org">
                                <p class="description"><?php _e('Optional website for this organisation.', 'my-village-hall'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th><?php _e('Status', 'my-village-hall'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="is_active" value="1"
                                        <?php checked(!$edit_org || $edit_org['IsActive']); ?>>
                                    <?php _e('Active', 'my-village-hall'); ?>
                                </label>
                            </td>
                        </tr>

                        <tr>
                            <th><?php _e('Default Organisation', 'my-village-hall'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="is_default" value="1"
                                        <?php checked(!empty($edit_org['IsDefault'])); ?>>
                                    <?php _e('Use as the default organisation', 'my-village-hall'); ?>
                                </label>
                                <p class="description"><?php _e('Only one organisation can be default at a time.', 'my-village-hall'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th><?php _e('Default Booking Visibility', 'my-village-hall'); ?></th>
                            <td>
                                <select name="default_public" class="regular-text">
                                    <option value="0" <?php selected(!empty($edit_org['DefaultPublic']) ? 1 : 0, 0); ?>><?php _e('Private by default', 'my-village-hall'); ?></option>
                                    <option value="1" <?php selected(!empty($edit_org['DefaultPublic']) ? 1 : 0, 1); ?>><?php _e('Public by default', 'my-village-hall'); ?></option>
                                </select>
                                <p class="description"><?php _e('Used when creating bookings without an explicit visibility choice.', 'my-village-hall'); ?></p>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button class="button button-primary">
                            <?php echo $edit_org ? __('Update Organisation', 'my-village-hall') : __('Add Organisation', 'my-village-hall'); ?>
                        </button>
                        <?php if ($edit_org): ?>
                            <a href="<?php echo admin_url('admin.php?page=myvh-organisations'); ?>" class="button">
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
