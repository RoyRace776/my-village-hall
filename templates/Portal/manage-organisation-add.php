<?php
if (!defined('ABSPATH')) exit;

$organisation_types = isset($organisation_types) && is_array($organisation_types) ? $organisation_types : [];
$default_organisation_type_id = isset($default_organisation_type_id) ? (int) $default_organisation_type_id : 0;
$single_booking_rule_options = isset($single_booking_rule_options) && is_array($single_booking_rule_options) ? $single_booking_rule_options : [];
$recurring_booking_rule_options = isset($recurring_booking_rule_options) && is_array($recurring_booking_rule_options) ? $recurring_booking_rule_options : [];
?>
<div class="myvh-dashboard-section myvh-orgs-page myvh-manage-org-add-page">
    <div class="myvh-account-header">
        <div>
            <h2>Add Organisation</h2>
            <p>Create a new organisation for this client.</p>
        </div>
    </div>

    <div class="myvh-card myvh-account-card myvh-manage-org-add-card">
        <form class="myvh-account-form" data-portal-action="myvh_portal_admin_add_organisation" data-message-target="myvh-manage-org-add-message" data-reload-page="manage-organisations">
            <label class="myvh-account-field" for="myvh-manage-org-add-name">
                <span>Organisation Name</span>
                <input id="myvh-manage-org-add-name" type="text" name="name" required>
            </label>

            <label class="myvh-account-field" for="myvh-manage-org-add-type">
                <span>Organisation Type</span>
                <select id="myvh-manage-org-add-type" name="organisation_type_id">
                    <option value="">Select an organisation type...</option>
                    <?php foreach ($organisation_types as $organisation_type): ?>
                        <option value="<?php echo esc_attr((int) ($organisation_type['Id'] ?? 0)); ?>" <?php selected($default_organisation_type_id, (int) ($organisation_type['Id'] ?? 0)); ?>>
                            <?php echo esc_html($organisation_type['Name'] ?? ''); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="myvh-muted">Need another type? Manage them from the Organisation Types page.</small>
            </label>

            <label class="myvh-account-field" for="myvh-manage-org-add-auto-invoice-rule">
                <span>Single booking auto-invoice rule</span>
                <select id="myvh-manage-org-add-auto-invoice-rule" name="single_booking_auto_invoice_rule_id">
                    <option value="0">Use default rule</option>
                    <?php foreach ($single_booking_rule_options as $rule_id => $rule_name): ?>
                        <option value="<?php echo esc_attr((int) $rule_id); ?>"><?php echo esc_html($rule_name); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="myvh-account-field" for="myvh-manage-org-add-recurring-auto-invoice-rule">
                <span>Recurring booking auto-invoice rule</span>
                <select id="myvh-manage-org-add-recurring-auto-invoice-rule" name="recurring_booking_auto_invoice_rule_id">
                    <option value="0">Use default rule</option>
                    <?php foreach ($recurring_booking_rule_options as $rule_id => $rule_name): ?>
                        <option value="<?php echo esc_attr((int) $rule_id); ?>"><?php echo esc_html($rule_name); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="myvh-account-field" for="myvh-manage-org-add-email">
                <span>Contact Email</span>
                <input id="myvh-manage-org-add-email" type="email" name="contact_email" required>
            </label>

            <label class="myvh-account-field" for="myvh-manage-org-add-phone">
                <span>Contact Phone</span>
                <input id="myvh-manage-org-add-phone" type="text" name="contact_phone" required>
            </label>

            <div class="myvh-account-actions">
                <button type="submit" class="myvh-portal-add-btn">
                    <span class="myvh-portal-add-btn__icon" aria-hidden="true">+</span>
                    <span>Create Organisation</span>
                </button>
                <a href="#manage-organisations" class="myvh-portal-add-btn myvh-portal-add-btn--secondary">
                    <span>Cancel</span>
                </a>
                <div id="myvh-manage-org-add-message" class="myvh-muted" aria-live="polite"></div>
            </div>
        </form>
    </div>
</div>