<?php
if (!defined('ABSPATH')) exit;

?>
<div class="myvh-dashboard-section myvh-customers-page">
    <div class="myvh-account-header">
        <div>
            <h2>Add Customer</h2>
            <p>Create a new customer profile and link/create a WordPress user as needed.</p>
        </div>
    </div>

    <div class="myvh-card myvh-account-card">
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
</div>
