<?php
if (!defined('ABSPATH')) exit;

$customers = is_array($customers ?? null) ? $customers : [];
$action = $_GET['action'] ?? '';
$edit_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($action === 'add') {
    // Show add customer form (separate page)
    include __DIR__ . '/customer-add.php';
    return;
}

if ($action === 'edit' && $edit_id) {
    // Show edit customer form (reuse add form, prefill values)
    $customer_service = $GLOBALS['myvh_container']->get(Customer_Service::class);
    $edit_customer = $customer_service->get($edit_id);
    ?>
    <div class="myvh-dashboard-section myvh-customers-page">
        <div class="myvh-account-header">
            <div>
                <h2>Edit Customer</h2>
                <p>Update customer details and save changes.</p>
            </div>
        </div>
        <div class="myvh-card myvh-account-card">
            <form class="myvh-account-form" data-portal-action="myvh_portal_save_customer" data-message-target="myvh-customer-edit-message" data-reload-page="customers">
                <input type="hidden" name="customer_id" value="<?php echo (int)($edit_customer['Id'] ?? 0); ?>">
                <label class="myvh-account-field">
                    <span>Name</span>
                    <input type="text" name="name" required value="<?php echo esc_attr($edit_customer['Name'] ?? ''); ?>">
                </label>
                <label class="myvh-account-field">
                    <span>Email</span>
                    <input type="email" name="email" required value="<?php echo esc_attr($edit_customer['Email'] ?? ''); ?>">
                </label>
                <div class="myvh-account-grid">
                    <label class="myvh-account-field">
                        <span>Phone</span>
                        <input type="text" name="phone_number" value="<?php echo esc_attr($edit_customer['PhoneNumber'] ?? ''); ?>">
                    </label>
                    <label class="myvh-account-field">
                        <span>Post code</span>
                        <input type="text" name="post_code" value="<?php echo esc_attr($edit_customer['PostCode'] ?? ''); ?>">
                    </label>
                </div>
                <label class="myvh-account-field">
                    <span>Address</span>
                    <input type="text" name="address_line1" value="<?php echo esc_attr($edit_customer['AddressLine1'] ?? ''); ?>">
                </label>
                <label class="myvh-account-field">
                    <span>
                        <input type="checkbox" name="email_verified" value="1" <?php checked(!empty($edit_customer['EmailVerified'])); ?>>
                        Email verified
                    </span>
                </label>
                <div class="myvh-account-actions">
                    <button type="submit" class="button button-primary">Update Customer</button>
                    <a href="<?php echo esc_url(remove_query_arg(['action','id'], get_permalink())); ?>" class="button">Cancel</a>
                    <div id="myvh-customer-edit-message" class="myvh-muted" aria-live="polite"></div>
                </div>
            </form>
        </div>
    </div>
    <?php
    return;
}

// Default: show customer list
?>
<div class="myvh-dashboard-section myvh-customers-page">
    <div class="myvh-account-header" style="display:flex; align-items:end; justify-content:space-between; gap:24px;">
        <div>
            <h2>Customers</h2>
            <p>View, edit, or remove customers for this client site.</p>
        </div>
        <button onclick="window.location.href='<?php echo esc_url(add_query_arg('action', 'add', get_permalink())); ?>'" class="button button-primary myvh-add-customer-btn" type="button" style="margin-bottom:4px;">
            <span class="dashicons dashicons-plus-alt" style="vertical-align:middle; margin-right:4px;"></span> Add Customer
        </button>
    </div>
    <div class="myvh-card myvh-account-card">
        <div class="myvh-account-card-head">
            <h3>All Customers</h3>
            <span><?php echo count($customers); ?> customer records</span>
        </div>
        <?php if (empty($customers)): ?>
            <p>No customers found for this site.</p>
        <?php else: ?>
            <table class="myvh-customer-list-table">
                <thead>
                    <tr>
                        <th style="padding-right:32px;">Name</th>
                        <th style="padding-right:32px;">Email</th>
                        <th style="padding-right:32px;">Phone</th>
                        <th style="min-width:90px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($customers as $item): ?>
                    <tr>
                        <td style="padding-right:32px;"><?php echo esc_html($item['Name'] ?? ''); ?></td>
                        <td style="padding-right:32px;"><?php echo esc_html($item['Email'] ?? ''); ?></td>
                        <td style="padding-right:32px;"><?php echo esc_html($item['PhoneNumber'] ?? ''); ?></td>
                        <td style="white-space:nowrap;">
                            <a href="<?php echo esc_url(add_query_arg(['action'=>'edit','id'=>$item['Id']], get_permalink())); ?>" class="myvh-action-icon" aria-label="Edit customer" title="Edit customer" style="margin-right:10px; vertical-align:middle;">✎</a>
                            <form class="myvh-inline-form" style="display:inline;" data-portal-action="myvh_portal_delete_customer" data-message-target="myvh-customer-message-<?php echo (int)($item['Id'] ?? 0); ?>" data-reload-page="customers" data-confirm="Delete this customer? This cannot be undone.">
                                <button type="submit" class="myvh-action-icon myvh-action-danger" aria-label="Delete customer" title="Delete customer" style="background:none; border:none; padding:0; margin:0; vertical-align:middle; cursor:pointer;">🗑</button>
                                <input type="hidden" name="customer_id" value="<?php echo (int)($item['Id'] ?? 0); ?>">
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>