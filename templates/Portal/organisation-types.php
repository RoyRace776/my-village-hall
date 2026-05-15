<?php
if (!defined('ABSPATH')) exit;

$organisation_types = isset($organisation_types) && is_array($organisation_types) ? $organisation_types : [];
?>

<div class="myvh-dashboard-section myvh-organisation-types-page">
    <div class="myvh-account-header" style="display:flex; align-items:end; justify-content:space-between; gap:24px;">
        <div>
            <h2>Organisation Types</h2>
            <p>Select a type to edit, or add a new organisation type.</p>
        </div>
        <a href="#organisation-type-add" class="myvh-portal-add-btn myvh-portal-nav-btn">
            <span class="myvh-portal-add-btn__icon" aria-hidden="true">+</span>
            <span>Add Organisation Type</span>
        </a>
    </div>

    <div class="myvh-card myvh-account-card">
        <div class="myvh-account-card-head">
            <h3>All Organisation Types</h3>
            <span><?php echo count($organisation_types); ?> type records</span>
        </div>

        <?php if (empty($organisation_types)): ?>
            <p>No organisation types found for this site.</p>
            <p>
                <a href="#organisation-type-add" class="myvh-portal-add-btn myvh-portal-nav-btn">
                    <span class="myvh-portal-add-btn__icon" aria-hidden="true">+</span>
                    <span>Create First Organisation Type</span>
                </a>
            </p>
        <?php else: ?>
            <table class="myvh-customer-list-table">
                <thead>
                    <tr>
                        <th style="padding-right:24px;">Type</th>
                        <th style="padding-right:24px;">Description</th>
                        <th style="padding-right:24px;">Default</th>
                        <th style="padding-right:24px;">System</th>
                        <th style="min-width:90px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($organisation_types as $type): ?>
                    <?php
                    $type_id = (int) ($type['Id'] ?? 0);
                    $is_system = !empty($type['IsSystem']);
                    $delete_message_id = 'myvh-org-type-message-' . $type_id;
                    ?>
                    <tr>
                        <td style="padding-right:24px;"><strong><?php echo esc_html($type['Name'] ?? ''); ?></strong></td>
                        <td style="padding-right:24px;"><?php echo !empty($type['Description']) ? esc_html($type['Description']) : '<span style="color:#7a7166;">-</span>'; ?></td>
                        <td style="padding-right:24px;"><?php echo !empty($type['IsDefault']) ? 'Yes' : '-'; ?></td>
                        <td style="padding-right:24px;"><?php echo $is_system ? 'Yes' : '-'; ?></td>
                        <td style="white-space:nowrap;">
                            <a href="#organisation-type-edit?id=<?php echo $type_id; ?>" class="myvh-action-icon" aria-label="Edit organisation type" title="Edit organisation type" style="margin-right:10px; vertical-align:middle;">✎</a>
                            <?php if ($is_system): ?>
                                <span class="myvh-action-icon myvh-action-danger myvh-action-icon-disabled" aria-disabled="true" title="Delete not available">🗑</span>
                            <?php else: ?>
                                <form class="myvh-inline-form" style="display:inline;" data-portal-action="myvh_portal_delete_org_type" data-message-target="<?php echo esc_attr($delete_message_id); ?>" data-reload-page="organisation-types" data-confirm="Delete this organisation type? This cannot be undone.">
                                    <button type="submit" class="myvh-action-icon myvh-action-danger" aria-label="Delete organisation type" title="Delete organisation type" style="background:none; border:none; padding:0; margin:0; vertical-align:middle; cursor:pointer;">🗑</button>
                                    <input type="hidden" name="org_type_id" value="<?php echo $type_id; ?>">
                                </form>
                            <?php endif; ?>
                            <div id="<?php echo esc_attr($delete_message_id); ?>" class="myvh-muted" aria-live="polite"></div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
