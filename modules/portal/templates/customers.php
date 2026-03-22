<?php
if (!defined('ABSPATH')) exit;

$customers = is_array($customers ?? null) ? $customers : [];
?>

<div class="myvh-dashboard-section myvh-customers-page">
    <div class="myvh-account-header">
        <div>
            <h2>Customers</h2>
            <p>Create, update, and remove customers for this client site.</p>
        </div>
    </div>

    <div class="myvh-account-grid">
        <div class="myvh-card myvh-account-card">
            <div class="myvh-account-card-head">
                <h3>Add Customer</h3>
                <span>Creates a customer profile and links/creates a WordPress user as needed.</span>
            </div>

            <form class="myvh-account-form" data-portal-action="myvh_portal_save_customer" data-message-target="myvh-customer-create-message" data-reload-page="customers">
                <label class="myvh-account-field" for="myvh-customer-create-name">
                    <span>Name</span>
                    <input id="myvh-customer-create-name" type="text" name="name" required>
                </label>

                <label class="myvh-account-field" for="myvh-customer-create-email">
                    <span>Email</span>
                    <input id="myvh-customer-create-email" type="email" name="email" required>
                </label>

                <div class="myvh-account-grid">
                    <label class="myvh-account-field" for="myvh-customer-create-phone">
                        <span>Phone</span>
                        <input id="myvh-customer-create-phone" type="text" name="phone_number">
                    </label>

                    <label class="myvh-account-field" for="myvh-customer-create-postcode">
                        <span>Post code</span>
                        <input id="myvh-customer-create-postcode" type="text" name="post_code">
                    </label>
                </div>

                <label class="myvh-account-field" for="myvh-customer-create-address">
                    <span>Address</span>
                    <input id="myvh-customer-create-address" type="text" name="address_line1">
                </label>

                <label class="myvh-account-field">
                    <span>
                        <input type="checkbox" name="email_verified" value="1">
                        Mark email as verified
                    </span>
                </label>

                <div class="myvh-account-actions">
                    <button type="submit" class="button button-primary">Create Customer</button>
                    <div id="myvh-customer-create-message" class="myvh-muted" aria-live="polite"></div>
                </div>
            </form>
        </div>

        <div class="myvh-card myvh-account-card">
            <div class="myvh-account-card-head">
                <h3>Existing Customers</h3>
                <span><?php echo count($customers); ?> customer records</span>
            </div>

            <?php if (empty($customers)): ?>
                <p>No customers found for this site.</p>
            <?php else: ?>
                <div class="myvh-client-admin-list">
                    <?php foreach ($customers as $item): ?>
                        <?php $customer_id = (int) ($item['Id'] ?? 0); ?>
                        <div class="myvh-customer-admin-card">
                            <form class="myvh-account-form" data-portal-action="myvh_portal_save_customer" data-message-target="myvh-customer-message-<?php echo $customer_id; ?>" data-reload-page="customers">
                                <input type="hidden" name="customer_id" value="<?php echo $customer_id; ?>">

                                <div class="myvh-account-grid">
                                    <label class="myvh-account-field">
                                        <span>Name</span>
                                        <input type="text" name="name" required value="<?php echo esc_attr($item['Name'] ?? ''); ?>">
                                    </label>

                                    <label class="myvh-account-field">
                                        <span>Email</span>
                                        <input type="email" name="email" required value="<?php echo esc_attr($item['Email'] ?? ''); ?>">
                                    </label>
                                </div>

                                <div class="myvh-account-grid">
                                    <label class="myvh-account-field">
                                        <span>Phone</span>
                                        <input type="text" name="phone_number" value="<?php echo esc_attr($item['PhoneNumber'] ?? ''); ?>">
                                    </label>

                                    <label class="myvh-account-field">
                                        <span>Post code</span>
                                        <input type="text" name="post_code" value="<?php echo esc_attr($item['PostCode'] ?? ''); ?>">
                                    </label>
                                </div>

                                <label class="myvh-account-field">
                                    <span>Address</span>
                                    <input type="text" name="address_line1" value="<?php echo esc_attr($item['AddressLine1'] ?? ''); ?>">
                                </label>

                                <label class="myvh-account-field">
                                    <span>
                                        <input type="checkbox" name="email_verified" value="1" <?php checked(!empty($item['EmailVerified'])); ?>>
                                        Email verified
                                    </span>
                                </label>

                                <div class="myvh-account-actions">
                                    <button type="submit" class="button button-primary">Save Customer</button>
                                    <div id="myvh-customer-message-<?php echo $customer_id; ?>" class="myvh-muted" aria-live="polite"></div>
                                </div>
                            </form>

                            <form class="myvh-inline-form" data-portal-action="myvh_portal_delete_customer" data-message-target="myvh-customer-message-<?php echo $customer_id; ?>" data-reload-page="customers" data-confirm="Delete this customer? This cannot be undone.">
                                <input type="hidden" name="customer_id" value="<?php echo $customer_id; ?>">
                                <button type="submit" class="button">Delete Customer</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>