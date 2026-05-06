<?php
if (!defined('ABSPATH')) exit;

$client_admins = isset($client_admins) && is_array($client_admins) ? $client_admins : [];
$accessible_sites = isset($accessible_sites) && is_array($accessible_sites) ? $accessible_sites : [];
?>

<div class="myvh-dashboard-section myvh-client-admin-page">
    <div class="myvh-account-header">
        <div>
            <h2>Client Administrators</h2>
            <p>Manage portal users who can administer this client site.</p>
        </div>
        <div class="myvh-account-chip"><?php echo esc_html(get_bloginfo('name')); ?></div>
    </div>

    <div class="myvh-account-grid">
        <div class="myvh-card myvh-account-card">
            <div class="myvh-account-card-head">
                <h3>Add Client Administrator</h3>
                <span>Use an existing WordPress email address or username</span>
            </div>

            <form class="myvh-account-form" data-portal-action="myvh_portal_add_client_admin" data-message-target="myvh-client-admin-message" data-reload-page="client-admins">
                <label class="myvh-account-field" for="myvh-client-admin-identifier">
                    <span>Email or username</span>
                    <input id="myvh-client-admin-identifier" type="text" name="user_identifier" required>
                </label>

                <p class="myvh-account-hint">Site administrators and network super admins already have access even if they are not listed here.</p>

                <div class="myvh-account-actions">
                    <button type="submit" class="button button-primary">Add Client Admin</button>
                    <div id="myvh-client-admin-message" class="myvh-muted" aria-live="polite"></div>
                </div>
            </form>
        </div>

        <div class="myvh-card myvh-account-card">
            <div class="myvh-account-card-head">
                <h3>Assigned Administrators</h3>
                <span><?php echo count($client_admins); ?> explicitly assigned</span>
            </div>

            <?php if (empty($client_admins)): ?>
                <p>No client administrators have been explicitly assigned to this site.</p>
            <?php else: ?>
                <div class="myvh-client-admin-list">
                    <?php foreach ($client_admins as $assigned_admin): ?>
                        <div class="myvh-client-admin-item">
                            <div>
                                <strong><?php echo esc_html($assigned_admin['display_name'] ?: $assigned_admin['user_login']); ?></strong>
                                <div class="myvh-muted"><?php echo esc_html($assigned_admin['user_email']); ?></div>
                            </div>

                            <form class="myvh-inline-form" data-portal-action="myvh_portal_remove_client_admin" data-message-target="myvh-client-admin-remove-message-<?php echo intval($assigned_admin['ID']); ?>" data-reload-page="client-admins" data-confirm="Remove this client administrator from the current site?">
                                <input type="hidden" name="user_id" value="<?php echo intval($assigned_admin['ID']); ?>">
                                <button type="submit" class="button">Remove</button>
                            </form>
                        </div>
                        <div id="myvh-client-admin-remove-message-<?php echo intval($assigned_admin['ID']); ?>" class="myvh-muted" aria-live="polite"></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (count($accessible_sites) > 1): ?>
        <div class="myvh-card myvh-account-card">
            <div class="myvh-account-card-head">
                <h3>Your Client Sites</h3>
                <span>Switch between the clients you can administer</span>
            </div>

            <div class="myvh-portal-sites-list">
                <?php foreach ($accessible_sites as $site): ?>
                    <a class="myvh-portal-site-link<?php echo !empty($site['is_current']) ? ' is-current' : ''; ?>" href="<?php echo esc_url($site['url']); ?>">
                        <?php echo esc_html($site['name']); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>