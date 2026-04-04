<?php
if (!defined('ABSPATH')) exit;

$organisation_type = is_array($organisation_type ?? null) ? $organisation_type : null;
if (!$organisation_type) {
    echo '<div class="myvh-card myvh-error"><p>Organisation type not found.</p></div>';
    return;
}

$is_system = !empty($organisation_type['IsSystem']);
?>
<div class="myvh-dashboard-section myvh-organisation-types-page">
    <div class="myvh-account-header">
        <div>
            <h2>Edit Organisation Type</h2>
            <p>Update organisation type details and save changes.</p>
        </div>
    </div>

    <div class="myvh-card myvh-account-card">
        <?php if ($is_system): ?>
            <div class="myvh-account-card-head">
                <h3><?php echo esc_html($organisation_type['Name'] ?? ''); ?></h3>
                <span><?php echo !empty($organisation_type['IsDefault']) ? 'System default type' : 'System type'; ?></span>
            </div>
            <p><?php echo esc_html($organisation_type['Description'] ?? ''); ?></p>
            <p class="myvh-muted">Locked system type. This type cannot be edited or deleted.</p>
            <div class="myvh-account-actions">
                <a href="#organisation-types" class="button">Back</a>
            </div>
        <?php else: ?>
            <form class="myvh-account-form" data-portal-action="myvh_portal_save_org_type" data-message-target="myvh-org-type-edit-message" data-reload-page="organisation-types">
                <input type="hidden" name="org_type_id" value="<?php echo (int) ($organisation_type['Id'] ?? 0); ?>">

                <label class="myvh-account-field" for="myvh-org-type-name-<?php echo (int) ($organisation_type['Id'] ?? 0); ?>">
                    <span>Name</span>
                    <input id="myvh-org-type-name-<?php echo (int) ($organisation_type['Id'] ?? 0); ?>" type="text" name="name" required value="<?php echo esc_attr($organisation_type['Name'] ?? ''); ?>">
                </label>

                <label class="myvh-account-field" for="myvh-org-type-description-<?php echo (int) ($organisation_type['Id'] ?? 0); ?>">
                    <span>Description</span>
                    <textarea id="myvh-org-type-description-<?php echo (int) ($organisation_type['Id'] ?? 0); ?>" name="description" rows="3"><?php echo esc_textarea($organisation_type['Description'] ?? ''); ?></textarea>
                </label>

                <label class="myvh-account-field" style="display:flex; align-items:center; gap:8px;">
                    <input type="checkbox" name="is_default" value="1" <?php checked(!empty($organisation_type['IsDefault'])); ?>>
                    <span>Default organisation type</span>
                </label>

                <div class="myvh-account-actions">
                    <button type="submit" class="button button-primary">Update Organisation Type</button>
                    <a href="#organisation-types" class="button">Cancel</a>
                    <div id="myvh-org-type-edit-message" class="myvh-muted" aria-live="polite"></div>
                </div>
            </form>

            <form class="myvh-inline-form" data-portal-action="myvh_portal_delete_org_type" data-message-target="myvh-org-type-delete-message" data-reload-page="organisation-types" data-confirm="Delete this organisation type? This cannot be undone.">
                <input type="hidden" name="org_type_id" value="<?php echo (int) ($organisation_type['Id'] ?? 0); ?>">
                <button type="submit" class="button">Delete</button>
            </form>
            <div id="myvh-org-type-delete-message" class="myvh-muted" aria-live="polite"></div>
        <?php endif; ?>
    </div>
</div>
