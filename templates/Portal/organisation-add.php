<?php
if (!defined('ABSPATH')) exit;

$organisation_types = isset($organisation_types) && is_array($organisation_types) ? $organisation_types : [];
$default_organisation_type_id = isset($default_organisation_type_id) ? (int) $default_organisation_type_id : 0;
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

            <?php if ($is_client_admin && !empty($organisation_types)): ?>
                <label class="myvh-account-field" for="myvh-org-add-type">
                    <span>Organisation Type</span>
                    <select id="myvh-org-add-type" name="organisation_type_id">
                        <option value="">Select an organisation type...</option>
                        <?php foreach ($organisation_types as $organisation_type): ?>
                            <option value="<?php echo esc_attr((int) $organisation_type['Id']); ?>" <?php selected($default_organisation_type_id, (int) $organisation_type['Id']); ?>>
                                <?php echo esc_html($organisation_type['Name'] ?? ''); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="myvh-muted">Need another type? Manage them from the Organisation Types page.</small>
                </label>

                <label class="myvh-account-field" for="myvh-org-add-auto-invoice-rule">
                    <span>Single booking auto-invoice rule</span>
                    <select id="myvh-org-add-auto-invoice-rule" name="single_booking_auto_invoice_rule_id">
                        <option value="0">Use default rule</option>
                        <?php foreach (($single_booking_rule_options ?? []) as $rule_id => $rule_name): ?>
                            <option value="<?php echo esc_attr((int) $rule_id); ?>"><?php echo esc_html($rule_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php endif; ?>

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
