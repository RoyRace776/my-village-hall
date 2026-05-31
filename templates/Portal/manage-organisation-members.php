<?php
if (!defined('ABSPATH')) exit;

$selected_organisation = isset($selected_organisation) && is_array($selected_organisation) ? $selected_organisation : null;
$selected_org_id = (int) ($selected_organisation['Id'] ?? 0);
$selected_organisation_members = isset($selected_organisation_members) && is_array($selected_organisation_members) ? $selected_organisation_members : [];
$members_message_id = 'myvh-manage-org-members-message';
?>

<div class="myvh-dashboard-section myvh-orgs-page myvh-manage-org-members-page">
    <div class="myvh-account-header">
        <div>
            <h2>Manage Members</h2>
            <p><?php echo $selected_org_id > 0 ? esc_html($selected_organisation['Name'] ?? '') : 'Select an organisation to manage its members.'; ?></p>
        </div>
        <?php if ($selected_org_id > 0): ?>
            <a href="#manage-organisations?org_id=<?php echo esc_attr($selected_org_id); ?>" class="myvh-portal-add-btn myvh-portal-add-btn--secondary">
                <span>Back to Organisation</span>
            </a>
        <?php endif; ?>
    </div>

    <?php if ($selected_org_id <= 0): ?>
        <div class="myvh-card myvh-account-card">
            <p class="myvh-muted">No organisation selected.</p>
        </div>
    <?php else: ?>
        <section class="myvh-card myvh-account-card myvh-manage-orgs-members-card">
            <div class="myvh-account-card-head">
                <h3>Members</h3>
                <span>Manage the members for this organisation.</span>
            </div>

            <div class="myvh-org-admin-grid myvh-manage-orgs-members-grid">
                <div>
                    <h4>Add Member</h4>
                    <form
                        class="myvh-account-form"
                        data-portal-action="myvh_portal_admin_org_add_member"
                        data-message-target="<?php echo esc_attr($members_message_id); ?>"
                        data-reload-page="manage-organisations-members?org_id=<?php echo esc_attr($selected_org_id); ?>"
                    >
                        <input type="hidden" name="organisation_id" value="<?php echo esc_attr($selected_org_id); ?>">

                        <label class="myvh-account-field">
                            <span>Member email</span>
                            <input type="email" name="email" required placeholder="member@example.com">
                        </label>

                        <label class="myvh-toggle-row">
                            <input type="checkbox" name="is_admin" value="1">
                            <span>Add as admin</span>
                        </label>

                        <div class="myvh-account-actions">
                            <button type="submit" class="myvh-portal-add-btn">
                                <span class="myvh-portal-add-btn__icon" aria-hidden="true">+</span>
                                <span>Add Member</span>
                            </button>
                        </div>
                    </form>
                </div>

                <div>
                    <h4>Member List</h4>
                    <?php if (empty($selected_organisation_members)): ?>
                        <p class="myvh-muted">No members yet.</p>
                    <?php else: ?>
                        <div class="myvh-invoices-table-wrap myvh-manage-orgs-members-table-wrap">
                            <table class="booking-table myvh-orgs-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($selected_organisation_members as $member): ?>
                                    <?php $is_admin = !empty($member['IsOrganisationAdmin']); ?>
                                    <tr>
                                        <td><?php echo esc_html($member['Name'] ?? ''); ?></td>
                                        <td><?php echo esc_html($member['Email'] ?? ''); ?></td>
                                        <td><?php echo $is_admin ? '<span class="myvh-badge">Admin</span>' : '<span class="myvh-small">Member</span>'; ?></td>
                                        <td>
                                            <div class="booking-actions">
                                                <form
                                                    class="myvh-inline-form"
                                                    data-portal-action="myvh_portal_admin_org_set_admin"
                                                    data-message-target="<?php echo esc_attr($members_message_id); ?>"
                                                    data-reload-page="manage-organisations-members?org_id=<?php echo esc_attr($selected_org_id); ?>"
                                                >
                                                    <input type="hidden" name="organisation_id" value="<?php echo esc_attr($selected_org_id); ?>">
                                                    <input type="hidden" name="member_id" value="<?php echo esc_attr((int) ($member['Id'] ?? 0)); ?>">
                                                    <input type="hidden" name="is_admin" value="<?php echo $is_admin ? '0' : '1'; ?>">
                                                    <button type="submit" class="myvh-portal-add-btn<?php echo $is_admin ? ' myvh-portal-add-btn--secondary' : ''; ?>">
                                                        <span><?php echo esc_html($is_admin ? 'Set as Member' : 'Set as Admin'); ?></span>
                                                    </button>
                                                </form>
                                                <form
                                                    class="myvh-inline-form"
                                                    data-portal-action="myvh_portal_admin_org_remove_member"
                                                    data-message-target="<?php echo esc_attr($members_message_id); ?>"
                                                    data-reload-page="manage-organisations-members?org_id=<?php echo esc_attr($selected_org_id); ?>"
                                                    data-confirm="Remove this member from the organisation?"
                                                >
                                                    <input type="hidden" name="organisation_id" value="<?php echo esc_attr($selected_org_id); ?>">
                                                    <input type="hidden" name="member_id" value="<?php echo esc_attr((int) ($member['Id'] ?? 0)); ?>">
                                                    <button type="submit" class="myvh-client-admin-remove-btn">Remove</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div id="<?php echo esc_attr($members_message_id); ?>" class="myvh-muted" aria-live="polite"></div>
        </section>
    <?php endif; ?>
</div>