<?php
if (!defined('ABSPATH')) exit;

$addons = isset($addons) && is_array($addons) ? $addons : [];

$charge_type_labels = [
    'per_hour' => 'Per Hour',
    'per_day' => 'Per Day',
    'fixed' => 'Fixed',
];
?>

<div class="myvh-dashboard-section myvh-addons-page">
    <div class="myvh-account-header" style="display:flex; align-items:end; justify-content:space-between; gap:24px;">
        <div>
            <h2>Add-ons</h2>
            <p>Manage booking add-ons for this client site.</p>
        </div>
        <a href="#addon-add" class="myvh-portal-add-btn">
            <span class="myvh-portal-add-btn__icon" aria-hidden="true">+</span>
            <span>Add Add-on</span>
        </a>
    </div>

    <div class="myvh-card myvh-account-card">
        <div class="myvh-account-card-head">
            <h3>All Add-ons</h3>
            <span><?php echo count($addons); ?> add-on records</span>
        </div>

        <?php if (empty($addons)): ?>
            <p>No add-ons found for this site.</p>
            <p><a href="#addon-add" class="button">Create First Add-on</a></p>
        <?php else: ?>
            <table class="myvh-customer-list-table">
                <thead>
                    <tr>
                        <th style="padding-right:24px;">Name</th>
                        <th style="padding-right:24px;">Price</th>
                        <th style="padding-right:24px;">Type</th>
                        <th style="padding-right:24px;">Room</th>
                        <th style="padding-right:24px;">Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($addons as $addon): ?>
                    <?php
                    $addon_id = (int) ($addon['Id'] ?? 0);
                    $delete_message_id = 'myvh-addon-message-' . $addon_id;
                    ?>
                    <tr>
                        <td style="padding-right:24px;">
                            <strong><?php echo esc_html($addon['Name'] ?? ''); ?></strong>
                            <?php if (!empty($addon['Description'])): ?>
                                <br><small style="color:#7a7166;"><?php echo esc_html($addon['Description']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td style="padding-right:24px;">GBP <?php echo number_format((float) ($addon['Price'] ?? 0), 2); ?></td>
                        <td style="padding-right:24px;"><?php echo esc_html($charge_type_labels[$addon['ChargeType'] ?? ''] ?? ($addon['ChargeType'] ?? '')); ?></td>
                        <td style="padding-right:24px;"><?php echo !empty($addon['RoomName']) ? esc_html($addon['RoomName']) : 'All Rooms'; ?></td>
                        <td style="padding-right:24px;"><?php echo !empty($addon['IsActive']) ? 'Active' : 'Inactive'; ?></td>
                        <td style="white-space:nowrap;">
                            <a href="#addon-edit?id=<?php echo $addon_id; ?>" class="myvh-action-icon" aria-label="Edit add-on" title="Edit add-on" style="margin-right:10px; vertical-align:middle;">✎</a>
                            <form class="myvh-inline-form" style="display:inline;" data-portal-action="myvh_portal_delete_addon" data-message-target="<?php echo esc_attr($delete_message_id); ?>" data-reload-page="addons" data-confirm="Archive this add-on? Existing booking records will be kept.">
                                <button type="submit" class="myvh-action-icon myvh-action-danger" aria-label="Archive add-on" title="Archive add-on" style="background:none; border:none; padding:0; margin:0; vertical-align:middle; cursor:pointer;">🗑</button>
                                <input type="hidden" name="addon_id" value="<?php echo $addon_id; ?>">
                            </form>
                            <div id="<?php echo esc_attr($delete_message_id); ?>" class="myvh-muted" aria-live="polite"></div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
