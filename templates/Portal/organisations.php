
<?php
if (!defined('ABSPATH')) exit;
$action = $_GET['action'] ?? '';
if ($action === 'add') {
    include __DIR__ . '/organisation-add.php';
    return;
}
?>

<div class="myvh-dashboard-section myvh-orgs-page">
    <div class="myvh-account-header" style="display:flex; align-items:end; justify-content:space-between; gap:24px;">
        <div>
            <h2>Organisations</h2>
            <p>View your memberships, request access, and manage organisations you administer.</p>
        </div>
        <button onclick="window.location.href='<?php echo esc_url(add_query_arg('action', 'add', get_permalink())); ?>'" class="button button-primary myvh-add-org-btn" type="button" style="margin-bottom:4px;">
            <span class="dashicons dashicons-plus-alt" style="vertical-align:middle; margin-right:4px;"></span> Add Organisation
        </button>
    </div>

    <div class="myvh-account-grid">
        <div class="myvh-card myvh-account-card">
            <div class="myvh-account-card-head">
                <h3>My Memberships</h3>
                <span>Organisations you currently belong to.</span>
            </div>

            <?php if (empty($my_memberships)): ?>
                <p class="myvh-muted">You are not a member of any organisations yet.</p>
            <?php else: ?>
                <table class="booking-table myvh-orgs-table">
                    <thead>
                        <tr>
                            <th>Organisation</th>
                            <th>Role</th>
                            <th>Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($my_memberships as $membership): ?>
                        <tr>
                            <td><?php echo esc_html($membership['OrganisationName'] ?? ''); ?></td>
                            <td>
                                <?php if (!empty($membership['IsOrganisationAdmin'])): ?>
                                    <span class="myvh-badge">Admin</span>
                                <?php else: ?>
                                    <span class="myvh-small">Member</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo !empty($membership['Created']) ? esc_html(date('d M Y', strtotime($membership['Created']))) : '—'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="myvh-card myvh-account-card">
            <div class="myvh-account-card-head">
                <h3>Request Membership</h3>
                <span>Ask to be added to another organisation.</span>
            </div>

            <?php if (empty($requestable_organisations)): ?>
                <p class="myvh-muted">No organisations are currently available to request.</p>
            <?php else: ?>
                <form id="myvh-org-request-form" class="myvh-account-form" data-portal-action="myvh_portal_request_org_membership" data-message-target="myvh-org-request-message" data-reload-page="organisations">
                    <label class="myvh-account-field" for="myvh-request-organisation-id">
                        <span>Organisation</span>
                        <select id="myvh-request-organisation-id" name="organisation_id" required>
                            <option value="">Select an organisation...</option>
                            <?php foreach ($requestable_organisations as $org): ?>
                                <option value="<?php echo esc_attr((int) $org['Id']); ?>"><?php echo esc_html($org['Name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="myvh-account-field" for="myvh-request-message">
                        <span>Message (optional)</span>
                        <textarea id="myvh-request-message" name="message" rows="3" placeholder="Add context for the organisation admin"></textarea>
                    </label>

                    <div class="myvh-account-actions">
                        <button type="submit" class="button myvh-cal-btn">Send Request</button>
                        <div id="myvh-org-request-message" class="myvh-muted" aria-live="polite"></div>
                    </div>
                </form>
            <?php endif; ?>

            <div class="myvh-orgs-subsection">
                <h4>Pending Requests</h4>
                <?php if (empty($pending_requests)): ?>
                    <p class="myvh-muted">You have no pending membership requests.</p>
                <?php else: ?>
                    <ul class="myvh-orgs-pending-list">
                        <?php foreach ($pending_requests as $request): ?>
                            <li>
                                <strong><?php echo esc_html($request['OrganisationName'] ?? ''); ?></strong>
                                <span class="myvh-small">Requested <?php echo !empty($request['Created']) ? esc_html(date('d M Y', strtotime($request['Created']))) : ''; ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (!empty($manageable_organisations)): ?>
        <div class="myvh-org-admin-section">
            <h3 class="section-title">Organisations You Manage</h3>

            <?php foreach ($manageable_organisations as $org): ?>
                <?php
                    $org_id = (int) $org['Id'];
                    $members = $organisation_members[$org_id] ?? [];
                    $requests = $organisation_pending_requests[$org_id] ?? [];
                    $message_id = 'myvh-org-admin-message-' . $org_id;
                ?>
                <div class="myvh-card myvh-account-card">
                    <div class="myvh-account-card-head">
                        <h3><?php echo esc_html($org['Name']); ?></h3>
                        <span>Approve requests, add and remove members, and manage admin status.</span>
                    </div>

                    <div class="myvh-orgs-subsection">
                        <h4>Invoicing Details</h4>
                        <p class="myvh-muted">Enable organisation invoicing before entering billing contact details.</p>

                        <form class="myvh-account-form" data-portal-action="myvh_portal_save_org_billing" data-message-target="<?php echo esc_attr($message_id); ?>" data-reload-page="organisations">
                            <input type="hidden" name="organisation_id" value="<?php echo esc_attr($org_id); ?>">

                            <label class="myvh-toggle-row">
                                <input type="checkbox" name="invoice_organisation_bookings" value="1" class="myvh-org-invoice-toggle" <?php checked(!empty($org['InvoiceOrganisationBookings'])); ?>>
                                <span>Invoice this organisation for its bookings</span>
                            </label>

                            <div class="myvh-org-billing-fields"<?php echo empty($org['InvoiceOrganisationBookings']) ? ' hidden' : ''; ?> >
                                <div class="myvh-field-grid">
                                    <label class="myvh-account-field">
                                        <span>Billing contact name</span>
                                        <input type="text" name="billing_contact_name" value="<?php echo esc_attr($org['BillingContactName'] ?? ''); ?>" placeholder="Accounts contact">
                                    </label>

                                    <label class="myvh-account-field">
                                        <span>Billing email</span>
                                        <input type="email" name="billing_email" value="<?php echo esc_attr($org['BillingEmail'] ?? ''); ?>" placeholder="accounts@example.com">
                                    </label>
                                </div>

                                <div class="myvh-field-grid">
                                    <label class="myvh-account-field">
                                        <span>Address line 1</span>
                                        <input type="text" name="billing_address_line1" value="<?php echo esc_attr($org['BillingAddressLine1'] ?? ''); ?>">
                                    </label>

                                    <label class="myvh-account-field">
                                        <span>Address line 2</span>
                                        <input type="text" name="billing_address_line2" value="<?php echo esc_attr($org['BillingAddressLine2'] ?? ''); ?>">
                                    </label>
                                </div>

                                <div class="myvh-field-grid">
                                    <label class="myvh-account-field">
                                        <span>Town or city</span>
                                        <input type="text" name="billing_town_city" value="<?php echo esc_attr($org['BillingTownCity'] ?? ''); ?>">
                                    </label>

                                    <label class="myvh-account-field">
                                        <span>Postcode</span>
                                        <input type="text" name="billing_postcode" value="<?php echo esc_attr($org['BillingPostcode'] ?? ''); ?>">
                                    </label>
                                </div>

                                <label class="myvh-account-field">
                                    <span>Billing reference</span>
                                    <input type="text" name="billing_reference" value="<?php echo esc_attr($org['BillingReference'] ?? ''); ?>" placeholder="PO number or internal reference">
                                </label>
                            </div>

                            <div class="myvh-account-actions">
                                <button type="submit" class="button myvh-cal-btn">Save Invoicing Details</button>
                            </div>
                        </form>
                    </div>

                    <div class="myvh-org-admin-grid">
                        <div>
                            <h4>Pending Requests</h4>
                            <?php if (empty($requests)): ?>
                                <p class="myvh-muted">No pending requests.</p>
                            <?php else: ?>
                                <table class="booking-table myvh-orgs-table">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Message</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($requests as $request): ?>
                                        <tr>
                                            <td><?php echo esc_html($request['CustomerName'] ?? ''); ?></td>
                                            <td><?php echo esc_html($request['CustomerEmail'] ?? ''); ?></td>
                                            <td><?php echo esc_html($request['RequestMessage'] ?? ''); ?></td>
                                            <td>
                                                <div class="booking-actions">
                                                    <form class="myvh-inline-form" data-portal-action="myvh_portal_approve_org_request" data-message-target="<?php echo esc_attr($message_id); ?>" data-reload-page="organisations">
                                                        <input type="hidden" name="request_id" value="<?php echo esc_attr((int) $request['Id']); ?>">
                                                        <button type="submit" class="myvh-button">Approve</button>
                                                    </form>
                                                    <form class="myvh-inline-form" data-portal-action="myvh_portal_reject_org_request" data-message-target="<?php echo esc_attr($message_id); ?>" data-reload-page="organisations">
                                                        <input type="hidden" name="request_id" value="<?php echo esc_attr((int) $request['Id']); ?>">
                                                        <button type="submit" class="myvh-button">Reject</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>

                        <div>
                            <h4>Add Member</h4>
                            <form class="myvh-account-form" data-portal-action="myvh_portal_org_add_member" data-message-target="<?php echo esc_attr($message_id); ?>" data-reload-page="organisations">
                                <input type="hidden" name="organisation_id" value="<?php echo esc_attr($org_id); ?>">

                                <label class="myvh-account-field">
                                    <span>Member email</span>
                                    <input type="email" name="email" required placeholder="member@example.com">
                                </label>

                                <label class="myvh-account-field myvh-account-checkbox">
                                    <span>
                                        <input type="checkbox" name="is_admin" value="1">
                                        Add as admin
                                    </span>
                                </label>

                                <button type="submit" class="button myvh-cal-btn">Add Member</button>
                            </form>
                        </div>
                    </div>

                    <div class="myvh-orgs-subsection">
                        <h4>Members</h4>
                        <?php if (empty($members)): ?>
                            <p class="myvh-muted">No members yet.</p>
                        <?php else: ?>
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
                                <?php foreach ($members as $member): ?>
                                    <?php
                                        $is_admin = !empty($member['IsOrganisationAdmin']);
                                        $toggle_action_text = $is_admin ? 'Set as Member' : 'Set as Admin';
                                    ?>
                                    <tr>
                                        <td><?php echo esc_html($member['Name'] ?? ''); ?></td>
                                        <td><?php echo esc_html($member['Email'] ?? ''); ?></td>
                                        <td><?php echo $is_admin ? '<span class="myvh-badge">Admin</span>' : '<span class="myvh-small">Member</span>'; ?></td>
                                        <td>
                                            <div class="booking-actions">
                                                <form class="myvh-inline-form" data-portal-action="myvh_portal_org_set_admin" data-message-target="<?php echo esc_attr($message_id); ?>" data-reload-page="organisations">
                                                    <input type="hidden" name="member_id" value="<?php echo esc_attr((int) $member['Id']); ?>">
                                                    <input type="hidden" name="is_admin" value="<?php echo $is_admin ? '0' : '1'; ?>">
                                                    <button type="submit" class="myvh-button"><?php echo esc_html($toggle_action_text); ?></button>
                                                </form>
                                                <form class="myvh-inline-form" data-portal-action="myvh_portal_org_remove_member" data-message-target="<?php echo esc_attr($message_id); ?>" data-reload-page="organisations" data-confirm="Remove this member from the organisation?">
                                                    <input type="hidden" name="member_id" value="<?php echo esc_attr((int) $member['Id']); ?>">
                                                    <button type="submit" class="myvh-button myvh-link-danger">Remove</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>

                    <div id="<?php echo esc_attr($message_id); ?>" class="myvh-muted" aria-live="polite"></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
