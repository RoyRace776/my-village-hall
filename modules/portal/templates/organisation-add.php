<?php
if (!defined('ABSPATH')) exit;
?>
<div class="myvh-dashboard-section myvh-orgs-page">
    <div class="myvh-account-header">
        <div>
            <h2>Add Organisation</h2>
            <p>Register a new organisation. You will become the admin for this organisation.</p>
        </div>
    </div>
    <div class="myvh-card myvh-account-card">
        <form class="myvh-account-form" data-portal-action="myvh_portal_add_organisation" data-message-target="myvh-org-add-message" data-reload-page="organisations">
            <label class="myvh-account-field" for="myvh-org-add-name">
                <span>Organisation Name</span>
                <input id="myvh-org-add-name" type="text" name="name" required>
            </label>
            <label class="myvh-account-field" for="myvh-org-add-desc">
                <span>Description (optional)</span>
                <textarea id="myvh-org-add-desc" name="description" rows="3"></textarea>
            </label>
            <div class="myvh-account-actions">
                <button type="submit" class="button button-primary">Create Organisation</button>
                <a href="<?php echo esc_url(remove_query_arg(['action'], get_permalink())); ?>" class="button">Cancel</a>
                <div id="myvh-org-add-message" class="myvh-muted" aria-live="polite"></div>
            </div>
        </form>
    </div>
</div>
