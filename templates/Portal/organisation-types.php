<?php
if (!defined('ABSPATH')) exit;

$organisation_types = is_array($organisation_types ?? null) ? $organisation_types : [];
?>

<div class="myvh-dashboard-section myvh-organisation-types-page">
    <div class="myvh-account-header" style="display:flex; align-items:end; justify-content:space-between; gap:24px;">
        <div>
            <h2>Organisation Types</h2>
            <p>Add, update, and remove organisation types for this client site.</p>
        </div>
    </div>

    <div class="myvh-account-grid">
        <div class="myvh-card myvh-account-card">
            <div class="myvh-account-card-head">
                <h3>Add Organisation Type</h3>
                <span>Create a type for organisation records</span>
            </div>

            <form class="myvh-account-form" data-portal-action="myvh_portal_save_org_type" data-message-target="myvh-org-type-create-message" data-reload-page="organisation-types">
                <label class="myvh-account-field" for="myvh-org-type-name">
                    <span>Name</span>
                    <input id="myvh-org-type-name" type="text" name="name" required>
                </label>

                <label class="myvh-account-field" for="myvh-org-type-description">
                    <span>Description</span>
                    <textarea id="myvh-org-type-description" name="description" rows="3"></textarea>
                </label>

                <label class="myvh-account-field" style="display:flex; align-items:center; gap:8px;">
                    <input type="checkbox" name="is_default" value="1">
                    <span>Set as default organisation type</span>
                </label>

                <div class="myvh-account-actions">
                    <button type="submit" class="button button-primary">Add Organisation Type</button>
                    <div id="myvh-org-type-create-message" class="myvh-muted" aria-live="polite"></div>
                </div>
            </form>
        </div>

        <div class="myvh-card myvh-account-card">
            <div class="myvh-account-card-head">
                <h3>All Organisation Types</h3>
                <span><?php echo count($organisation_types); ?> type records</span>
            </div>

            <?php if (empty($organisation_types)): ?>
                <p>No organisation types found for this site.</p>
            <?php else: ?>
                <div class="myvh-client-admin-list">
                    <?php foreach ($organisation_types as $type): ?>
                        <?php
                        $type_id = (int) ($type['Id'] ?? 0);
                        $is_system = !empty($type['IsSystem']);
                        $is_default = !empty($type['IsDefault']);
                        $save_message_id = 'myvh-org-type-save-message-' . $type_id;
                        $delete_message_id = 'myvh-org-type-delete-message-' . $type_id;
                        ?>

                        <div class="myvh-card myvh-account-card" style="margin-bottom:12px;">
                            <?php if ($is_system): ?>
                                <div class="myvh-account-card-head">
                                    <h3><?php echo esc_html($type['Name'] ?? ''); ?></h3>
                                    <span><?php echo $is_default ? 'System default type' : 'System type'; ?></span>
                                </div>
                                <p><?php echo esc_html($type['Description'] ?? ''); ?></p>
                                <p class="myvh-muted">Locked system type</p>
                            <?php else: ?>
                                <form class="myvh-account-form" data-portal-action="myvh_portal_save_org_type" data-message-target="<?php echo esc_attr($save_message_id); ?>" data-reload-page="organisation-types">
                                    <input type="hidden" name="org_type_id" value="<?php echo $type_id; ?>">

                                    <label class="myvh-account-field" for="myvh-org-type-name-<?php echo $type_id; ?>">
                                        <span>Name</span>
                                        <input id="myvh-org-type-name-<?php echo $type_id; ?>" type="text" name="name" value="<?php echo esc_attr($type['Name'] ?? ''); ?>" required>
                                    </label>

                                    <label class="myvh-account-field" for="myvh-org-type-description-<?php echo $type_id; ?>">
                                        <span>Description</span>
                                        <textarea id="myvh-org-type-description-<?php echo $type_id; ?>" name="description" rows="2"><?php echo esc_textarea($type['Description'] ?? ''); ?></textarea>
                                    </label>

                                    <label class="myvh-account-field" style="display:flex; align-items:center; gap:8px;">
                                        <input type="checkbox" name="is_default" value="1" <?php checked($is_default); ?>>
                                        <span>Default organisation type</span>
                                    </label>

                                    <div class="myvh-account-actions">
                                        <button type="submit" class="button button-secondary">Save</button>
                                    </div>
                                </form>

                                <form class="myvh-inline-form" data-portal-action="myvh_portal_delete_org_type" data-message-target="<?php echo esc_attr($delete_message_id); ?>" data-reload-page="organisation-types" data-confirm="Delete this organisation type? This cannot be undone.">
                                    <input type="hidden" name="org_type_id" value="<?php echo $type_id; ?>">
                                    <button type="submit" class="button">Delete</button>
                                </form>
                            <?php endif; ?>

                            <div id="<?php echo esc_attr($save_message_id); ?>" class="myvh-muted" aria-live="polite"></div>
                            <div id="<?php echo esc_attr($delete_message_id); ?>" class="myvh-muted" aria-live="polite"></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
