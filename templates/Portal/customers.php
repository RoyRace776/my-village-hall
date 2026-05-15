
<?php
if (!defined('ABSPATH')) exit;
if (!isset($is_client_admin)) $is_client_admin = false;

use MYVH\Customers\CustomerService;

$customers = (isset($customers) && is_array($customers)) ? $customers : [];
$action = $_GET['action'] ?? '';
$edit_id = isset($_GET['id']) ? \intval($_GET['id']) : 0;

// Add/edit forms are now loaded via hash navigation and AJAX (see portal-app.js router)
// This file only renders the customer list table and action links

// Default: show customer list
?>
<div class="myvh-dashboard-section myvh-customers-page">
    <div class="myvh-account-header" style="display:flex; align-items:end; justify-content:space-between; gap:24px;">
        <div>
            <h2>Customers</h2>
            <p>View, edit, or remove customers for this client site.</p>
        </div>
        <a href="#customer-add" class="button button-primary myvh-portal-add-button">
            <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
            <span>Add Customer</span>
        </a>
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
                            <a href="#customer-edit?id=<?php echo (int)($item['Id'] ?? 0); ?>" class="myvh-action-icon" aria-label="Edit customer" title="Edit customer" style="margin-right:10px; vertical-align:middle;">✎</a>
                            <a href="#" class="myvh-send-password-reset" data-customer-id="<?php echo (int)($item['Id'] ?? 0); ?>" aria-label="Send password reset email" title="Send password reset email" style="margin-right:10px; vertical-align:middle;">📧</a>
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