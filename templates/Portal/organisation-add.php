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

            <label class="myvh-account-field" for="myvh-org-add-email">
                <span>Contact Email</span>
                <input id="myvh-org-add-email" type="email" name="contact_email" required>
            </label>

            <label class="myvh-account-field" for="myvh-org-add-phone">
                <span>Contact Phone</span>
                <input id="myvh-org-add-phone" type="text" name="contact_phone" required>
            </label>

            <div class="myvh-account-actions">
                <button type="submit" class="button button-primary">Create Organisation</button>
                <a href="#organisations" class="button">Cancel</a>
                <div id="myvh-org-add-message" class="myvh-muted" aria-live="polite"></div>
            </div>
        </form>
    </div>
</div>
