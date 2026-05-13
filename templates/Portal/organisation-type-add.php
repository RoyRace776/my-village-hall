<?php
if (!defined('ABSPATH')) exit;
?>
<div class="myvh-dashboard-section myvh-organisation-types-page">
    <div class="myvh-account-header">
        <div>
            <h2>Add Organisation Type</h2>
            <p>Create a new organisation type for this client site.</p>
        </div>
    </div>

    <div class="myvh-card myvh-account-card">
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
                <button type="submit" class="myvh-portal-add-btn">
                    <span class="myvh-portal-add-btn__icon" aria-hidden="true">+</span>
                    <span>Create Organisation Type</span>
                </button>
                <a href="#organisation-types" class="button">Cancel</a>
                <div id="myvh-org-type-create-message" class="myvh-muted" aria-live="polite"></div>
            </div>
        </form>
    </div>
</div>
