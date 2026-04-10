<?php
if (!defined('ABSPATH')) exit;

if (!is_user_logged_in()) {
    echo '<p>Please log in to view a booking.</p>';
    return;
}

// TODO: Refactor to use REST API for addons data
use MYVH\Addons\AddonService;

global $myvh_container;
$addon_service = $myvh_container->get(AddonService::class);
$all_addons = $addon_service->get_all(['orderby' => 'DisplayOrder', 'order' => 'ASC']);
$available_addons = array_values(array_filter($all_addons ?? [], fn($a) => !empty($a['IsActive'])));

// Read-only booking view modal.

?>

<div id="myvh-booking-modal-view" class="myvh-modal hidden">
<div class="myvh-modal-content myvh-booking-modal-shell myvh-booking-modal-view-shell">
    <h2>View Booking</h2>
    <p class="myvh-account-hint">Review the booking details below.</p>

    <p class="myvh-modal-actions" style="margin-bottom: 15px;">
        <button type="button" class="button button-primary myvh-edit-booking" disabled>Edit Booking</button>
        <button type="button" class="button myvh-delete-booking" disabled>Delete Booking</button>
        <button type="button" class="button myvh-cancel">Close</button>
    </p>

    <form id="myvh-booking-form-view">
        <div class="myvh-modal-group myvh-modal-group-main">
            <h3>Booking Details</h3>

            <table class="form-table">
                <tr>
                    <th>Start Date</th>
                    <td><input type="text" id="myvh-modal-start-date" data-myvh-picker="date" data-myvh-allow-input="0" autocomplete="off" readonly></td>
                </tr>
                    <tr>
                        <th>Start Time</th>
                        <td><input type="text" id="myvh-modal-start-time" data-myvh-picker="time" data-myvh-allow-input="0" autocomplete="off" readonly></td>
                    </tr>
                    <tr id="myvh-modal-end-date-row" style="display:none;">
                        <th>End Date</th>
                        <td><input type="text" id="myvh-modal-end-date" data-myvh-picker="date" data-myvh-allow-input="0" autocomplete="off" readonly></td>
                    </tr>
                    <tr>
                        <th>End Time</th>
                        <td><input type="text" id="myvh-modal-end-time" data-myvh-picker="time" data-myvh-allow-input="0" autocomplete="off" readonly></td>
                    </tr>
                    <tr>
                        <th>Room</th>
                        <td><select name="room_id" disabled></select></td>
                    </tr>
                    <tr>
                        <th>Customer</th>
                        <td><select name="customer_id" disabled></select></td>
                    </tr>
                    <tr>
                        <th>Organisation</th>
                        <td><select name="organisation_id" disabled></select></td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td><input type="text" name="status" readonly></td>
                    </tr>
                    <tr>
                        <th>Description</th>
                        <td><input type="text" name="description" placeholder="No description" readonly></td>
                    </tr>
                    <tr>
                        <th>Visibility</th>
                        <td>
                            <label>
                                <input type="checkbox" name="public" value="1" disabled>
                                Show on public calendar
                            </label>
                        </td>
                    </tr>
                    <tr id="myvh-modal-view-no-invoice-row" style="display:none;">
                        <th>Invoice</th>
                        <td>
                            <label>
                                <input type="checkbox" name="no_invoice_required" value="1" disabled>
                                This booking does not need an invoice
                            </label>
                        </td>
                    </tr>
                </table>
            </div>

            <?php if (!empty($available_addons)): ?>
            <div class="myvh-modal-group" style="margin: 15px 0;">
                <h3 style="margin:0 0 8px;">Add-ons</h3>
                <table class="widefat striped" id="myvh-modal-addons-table">
                    <thead>
                        <tr>
                            <th style="width:30px;"></th>
                            <th>Add-on</th>
                            <th style="width:110px;">Unit Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($available_addons as $i => $addon): ?>
                            <tr class="myvh-modal-addon-row">
                                <td>
                                    <input type="checkbox" class="myvh-modal-addon-checkbox" value="1" disabled>
                                    <input type="hidden" name="addons[<?php echo $i; ?>][addon_id]" value="<?php echo intval($addon['Id']); ?>">
                                    <input type="hidden" name="addons[<?php echo $i; ?>][enabled]" class="myvh-modal-addon-enabled" value="0">
                                </td>
                                <td>
                                    <strong><?php echo esc_html($addon['Name']); ?></strong>
                                    <br><small style="color:#999;"><?php echo esc_html(ucfirst(str_replace('_', ' ', $addon['ChargeType']))); ?></small>
                                </td>
                                <td>
                                    <input type="number" step="0.01" min="0" name="addons[<?php echo $i; ?>][unit_price]" class="small-text myvh-modal-addon-price" value="<?php echo esc_attr(number_format((float)$addon['Price'], 2, '.', '')); ?>" disabled>
                                </td>
                                <input type="hidden" name="addons[<?php echo $i; ?>][quantity]" class="myvh-modal-addon-qty" value="1">
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p id="myvh-modal-addons-empty" class="description" style="display:none; margin-top:8px;">No add-ons selected.</p>
            </div>
            <?php endif; ?>

            <p class="myvh-modal-actions">
                <button type="button" class="button button-primary myvh-edit-booking" disabled>Edit Booking</button>
                <button type="button" class="button myvh-delete-booking" disabled>Delete Booking</button>
                <button type="button" class="button myvh-cancel">Close</button>
            </p>
        </form>
    </div>
</div>
