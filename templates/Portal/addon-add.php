<?php
if (!defined('ABSPATH')) exit;

$rooms = isset($rooms) && is_array($rooms) ? $rooms : [];
?>
<div class="myvh-dashboard-section myvh-addons-page">
    <div class="myvh-account-header">
        <div>
            <h2>Add Add-on</h2>
            <p>Create an add-on that can be selected when making bookings.</p>
        </div>
    </div>

    <div class="myvh-card myvh-account-card">
        <form class="myvh-account-form" data-portal-action="myvh_portal_save_addon" data-message-target="myvh-addon-create-message" data-reload-page="addons">
            <label class="myvh-account-field">
                <span>Name</span>
                <input type="text" name="name" required>
            </label>

            <label class="myvh-account-field">
                <span>Description</span>
                <textarea name="description" rows="3"></textarea>
            </label>

            <div class="myvh-account-grid">
                <label class="myvh-account-field">
                    <span>Price</span>
                    <input type="number" name="price" min="0" step="0.01" required value="0.00">
                </label>

                <label class="myvh-account-field">
                    <span>Charge Type</span>
                    <select name="charge_type" required>
                        <option value="fixed">Fixed</option>
                        <option value="per_hour">Per Hour</option>
                        <option value="per_day">Per Day</option>
                    </select>
                </label>
            </div>

            <div class="myvh-account-grid">
                <label class="myvh-account-field">
                    <span>Room</span>
                    <select name="room_id">
                        <option value="">All Rooms</option>
                        <?php foreach ($rooms as $room): ?>
                            <option value="<?php echo (int) ($room['Id'] ?? 0); ?>"><?php echo esc_html($room['Name'] ?? ''); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="myvh-account-field">
                    <span>Display Order</span>
                    <input type="number" name="display_order" min="0" value="0">
                </label>
            </div>

            <label class="myvh-account-field">
                <span>
                    <input type="checkbox" name="is_active" value="1" checked>
                    Active
                </span>
            </label>

            <div class="myvh-account-actions">
                <button type="submit" class="button button-primary">Create Add-on</button>
                <a href="#addons" class="button">Cancel</a>
                <div id="myvh-addon-create-message" class="myvh-muted" aria-live="polite"></div>
            </div>
        </form>
    </div>
</div>
