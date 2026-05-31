<?php
if (!defined('ABSPATH')) exit;

$all_organisations = isset($all_organisations) && is_array($all_organisations) ? $all_organisations : [];
$selected_organisation = isset($selected_organisation) && is_array($selected_organisation) ? $selected_organisation : null;
$organisation_types = isset($organisation_types) && is_array($organisation_types) ? $organisation_types : [];
$single_booking_rule_options = isset($single_booking_rule_options) && is_array($single_booking_rule_options) ? $single_booking_rule_options : [];
$recurring_booking_rule_options = isset($recurring_booking_rule_options) && is_array($recurring_booking_rule_options) ? $recurring_booking_rule_options : [];
$selected_org_id = (int) ($selected_organisation['Id'] ?? 0);
$list_message_id = 'myvh-manage-org-list-message';
$edit_message_id = 'myvh-manage-org-edit-message';
?>

<div class="myvh-dashboard-section myvh-orgs-page myvh-manage-orgs-page">
    <div class="myvh-account-header myvh-manage-orgs-header">
        <div>
            <h2>Manage Organisations</h2>
            <p>Administrators can add organisations, select one to edit, and delete organisations that have no bookings.</p>
        </div>
        <a href="#manage-organisations-add" class="myvh-portal-add-btn myvh-portal-nav-btn">
            <span class="myvh-portal-add-btn__icon" aria-hidden="true">+</span>
            <span>Add Organisation</span>
        </a>
    </div>

    <div class="myvh-manage-orgs-layout">
        <section class="myvh-card myvh-account-card myvh-manage-orgs-list-card">
            <div class="myvh-account-card-head">
                <h3>All Organisations</h3>
                <span><?php echo esc_html(count($all_organisations)); ?> total</span>
            </div>

            <?php if (empty($all_organisations)): ?>
                <p class="myvh-muted">No organisations found yet.</p>
            <?php else: ?>
                <div class="myvh-invoices-table-wrap">
                    <table class="booking-table myvh-orgs-table myvh-manage-org-list">
                        <thead>
                            <tr>
                                <th>Organisation</th>
                                <th>Type</th>
                                <th>Members</th>
                                <th>Status</th>
                                <th>Bookings</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($all_organisations as $organisation): ?>
                            <?php
                                $org_id = (int) ($organisation['Id'] ?? 0);
                                $is_selected = $selected_org_id > 0 && $selected_org_id === $org_id;
                                $is_system = !empty($organisation['IsSystem']);
                                $has_linked_bookings = !empty($organisation['HasLinkedBookings']);
                                $is_active = !empty($organisation['IsActive']);
                            ?>
                            <tr<?php echo $is_selected ? ' class="is-selected"' : ''; ?>>
                                <td>
                                    <strong><?php echo esc_html($organisation['Name'] ?? ''); ?></strong>
                                    <?php if ($is_system): ?>
                                        <div class="myvh-small">System organisation</div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($organisation['OrganisationTypeName'] ?? 'Unassigned'); ?></td>
                                <td><?php echo esc_html((string) (int) ($organisation['MemberCount'] ?? 0)); ?></td>
                                <td>
                                    <?php if ($is_active): ?>
                                        <span class="myvh-badge">Active</span>
                                    <?php else: ?>
                                        <span class="myvh-small">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($has_linked_bookings): ?>
                                        <span class="myvh-small">Has bookings</span>
                                    <?php else: ?>
                                        <span class="myvh-small">No bookings</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="booking-actions">
                                        <a
                                            href="#manage-organisations?org_id=<?php echo esc_attr($org_id); ?>"
                                            class="myvh-action-icon<?php echo $is_selected ? ' is-selected' : ''; ?>"
                                            aria-label="<?php echo esc_attr($is_selected ? 'Selected organisation' : 'Edit organisation'); ?>"
                                            title="<?php echo esc_attr($is_selected ? 'Selected organisation' : 'Edit organisation'); ?>"
                                        >
                                            <span class="dashicons dashicons-edit" aria-hidden="true"></span>
                                            <span class="screen-reader-text"><?php echo esc_html($is_selected ? 'Selected organisation' : 'Edit organisation'); ?></span>
                                        </a>

                                        <?php if ($has_linked_bookings): ?>
                                            <button
                                                type="button"
                                                class="myvh-action-icon myvh-action-icon--disabled"
                                                disabled
                                                aria-label="Delete unavailable because this organisation has bookings"
                                                title="Organisations with bookings cannot be deleted"
                                            >
                                                <span class="dashicons dashicons-trash" aria-hidden="true"></span>
                                                <span class="screen-reader-text">Delete unavailable because this organisation has bookings</span>
                                            </button>
                                        <?php else: ?>
                                            <form
                                                class="myvh-inline-form"
                                                data-portal-action="myvh_portal_delete_organisation"
                                                data-message-target="<?php echo esc_attr($list_message_id); ?>"
                                                data-reload-page="manage-organisations"
                                                data-confirm="Delete this organisation? This cannot be undone."
                                            >
                                                <input type="hidden" name="organisation_id" value="<?php echo esc_attr($org_id); ?>">
                                                <button
                                                    type="submit"
                                                    class="myvh-action-icon myvh-action-icon--danger"
                                                    aria-label="Delete organisation"
                                                    title="Delete organisation"
                                                >
                                                    <span class="dashicons dashicons-trash" aria-hidden="true"></span>
                                                    <span class="screen-reader-text">Delete organisation</span>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <div id="<?php echo esc_attr($list_message_id); ?>" class="myvh-muted" aria-live="polite"></div>
        </section>

        <section class="myvh-card myvh-account-card myvh-manage-orgs-edit-card">
            <div class="myvh-account-card-head">
                <h3><?php echo $selected_org_id > 0 ? 'Edit Organisation' : 'Select an Organisation'; ?></h3>
                <span><?php echo $selected_org_id > 0 ? esc_html($selected_organisation['Name'] ?? '') : 'Choose an organisation from the list.'; ?></span>
            </div>

            <?php if ($selected_org_id <= 0): ?>
                <p class="myvh-muted">Select an organisation from the table to edit it.</p>
            <?php else: ?>
                <form
                    class="myvh-account-form"
                    data-portal-action="myvh_portal_admin_save_organisation"
                    data-message-target="<?php echo esc_attr($edit_message_id); ?>"
                    data-reload-page="manage-organisations?org_id=<?php echo esc_attr($selected_org_id); ?>"
                >
                    <input type="hidden" name="organisation_id" value="<?php echo esc_attr($selected_org_id); ?>">

                    <div class="myvh-field-grid">
                        <label class="myvh-account-field">
                            <span>Organisation name</span>
                            <input type="text" name="name" required value="<?php echo esc_attr($selected_organisation['Name'] ?? ''); ?>" <?php echo !empty($selected_organisation['IsSystem']) ? 'disabled' : ''; ?>>
                        </label>

                        <label class="myvh-account-field">
                            <span>Organisation type</span>
                            <select name="organisation_type_id" <?php echo !empty($selected_organisation['IsSystem']) ? 'disabled' : ''; ?>>
                                <option value="">Select an organisation type...</option>
                                <?php foreach ($organisation_types as $organisation_type): ?>
                                    <option value="<?php echo esc_attr((int) ($organisation_type['Id'] ?? 0)); ?>" <?php selected((int) ($selected_organisation['OrganisationTypeId'] ?? 0), (int) ($organisation_type['Id'] ?? 0)); ?>>
                                        <?php echo esc_html($organisation_type['Name'] ?? ''); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>

                    <div class="myvh-field-grid">
                        <label class="myvh-account-field">
                            <span>Contact email</span>
                            <input type="email" name="contact_email" required value="<?php echo esc_attr($selected_organisation['ContactEmail'] ?? ''); ?>">
                        </label>

                        <label class="myvh-account-field">
                            <span>Contact phone</span>
                            <input type="text" name="contact_phone" required value="<?php echo esc_attr($selected_organisation['ContactPhone'] ?? ''); ?>">
                        </label>
                    </div>

                    <label class="myvh-account-field">
                        <span>Website URL</span>
                        <input type="url" name="website_url" value="<?php echo esc_attr($selected_organisation['WebsiteUrl'] ?? ''); ?>" placeholder="https://example.com">
                    </label>

                    <div class="myvh-field-grid">
                        <label class="myvh-account-field">
                            <span>Single booking auto-invoice rule</span>
                            <select name="single_booking_auto_invoice_rule_id">
                                <option value="0">Use default rule</option>
                                <?php foreach ($single_booking_rule_options as $rule_id => $rule_name): ?>
                                    <option value="<?php echo esc_attr((int) $rule_id); ?>" <?php selected((int) ($selected_organisation['SingleBookingAutoInvoiceRuleId'] ?? 0), (int) $rule_id); ?>>
                                        <?php echo esc_html($rule_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label class="myvh-account-field">
                            <span>Recurring booking auto-invoice rule</span>
                            <select name="recurring_booking_auto_invoice_rule_id">
                                <option value="0">Use default rule</option>
                                <?php foreach ($recurring_booking_rule_options as $rule_id => $rule_name): ?>
                                    <option value="<?php echo esc_attr((int) $rule_id); ?>" <?php selected((int) ($selected_organisation['RecurringBookingAutoInvoiceRuleId'] ?? 0), (int) $rule_id); ?>>
                                        <?php echo esc_html($rule_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>

                    <label class="myvh-toggle-row">
                        <input type="checkbox" name="send_booking_emails_to_organisation" value="1" <?php checked(!empty($selected_organisation['SendBookingEmailsToOrganisation'])); ?>>
                        <span>Send booking emails to organisation</span>
                    </label>

                    <label class="myvh-toggle-row">
                        <input type="checkbox" name="invoice_organisation_bookings" value="1" class="myvh-org-invoice-toggle" <?php checked(!empty($selected_organisation['InvoiceOrganisationBookings'])); ?>>
                        <span>Invoice this organisation for its bookings</span>
                    </label>

                    <div class="myvh-org-billing-fields"<?php echo empty($selected_organisation['InvoiceOrganisationBookings']) ? ' hidden' : ''; ?>>
                        <div class="myvh-field-grid">
                            <label class="myvh-account-field">
                                <span>Billing contact name</span>
                                <input type="text" name="billing_contact_name" value="<?php echo esc_attr($selected_organisation['BillingContactName'] ?? ''); ?>">
                            </label>

                            <label class="myvh-account-field">
                                <span>Billing email</span>
                                <input type="email" name="billing_email" value="<?php echo esc_attr($selected_organisation['BillingEmail'] ?? ''); ?>">
                            </label>
                        </div>

                        <div class="myvh-field-grid">
                            <label class="myvh-account-field">
                                <span>Address line 1</span>
                                <input type="text" name="billing_address_line1" value="<?php echo esc_attr($selected_organisation['BillingAddressLine1'] ?? ''); ?>">
                            </label>

                            <label class="myvh-account-field">
                                <span>Address line 2</span>
                                <input type="text" name="billing_address_line2" value="<?php echo esc_attr($selected_organisation['BillingAddressLine2'] ?? ''); ?>">
                            </label>
                        </div>

                        <div class="myvh-field-grid">
                            <label class="myvh-account-field">
                                <span>Town or city</span>
                                <input type="text" name="billing_town_city" value="<?php echo esc_attr($selected_organisation['BillingTownCity'] ?? ''); ?>">
                            </label>

                            <label class="myvh-account-field">
                                <span>Postcode</span>
                                <input type="text" name="billing_postcode" value="<?php echo esc_attr($selected_organisation['BillingPostcode'] ?? ''); ?>">
                            </label>
                        </div>

                        <label class="myvh-account-field">
                            <span>Billing reference</span>
                            <input type="text" name="billing_reference" value="<?php echo esc_attr($selected_organisation['BillingReference'] ?? ''); ?>">
                        </label>
                    </div>

                    <div class="myvh-field-grid">
                        <label class="myvh-toggle-row">
                            <input type="checkbox" name="is_active" value="1" <?php checked(!empty($selected_organisation['IsActive'])); ?>>
                            <span>Is active</span>
                        </label>

                        <label class="myvh-toggle-row">
                            <input type="checkbox" name="is_default" value="1" <?php checked(!empty($selected_organisation['IsDefault'])); ?>>
                            <span>Default organisation</span>
                        </label>
                    </div>

                    <label class="myvh-toggle-row">
                        <input type="checkbox" name="default_public" value="1" <?php checked(!empty($selected_organisation['DefaultPublic'])); ?>>
                        <span>Default public organisation</span>
                    </label>

                    <div class="myvh-account-actions">
                        <button type="submit" class="myvh-portal-add-btn">
                            <span class="myvh-portal-add-btn__icon" aria-hidden="true">✓</span>
                            <span>Save Organisation</span>
                        </button>
                        <a href="#manage-organisations-members?org_id=<?php echo esc_attr($selected_org_id); ?>" class="myvh-portal-add-btn myvh-portal-add-btn--secondary">
                            <span>Manage Members</span>
                        </a>
                    </div>
                </form>

                <div id="<?php echo esc_attr($edit_message_id); ?>" class="myvh-muted" aria-live="polite"></div>
            <?php endif; ?>
        </section>
    </div>
</div>