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
                    <td><input type="date" id="myvh-modal-start-date" readonly></td>
                </tr>
                    <tr>
                        <th>Start Time</th>
                        <td><input type="time" id="myvh-modal-start-time" readonly></td>
                    </tr>
                    <tr id="myvh-modal-end-date-row" style="display:none;">
                        <th>End Date</th>
                        <td><input type="date" id="myvh-modal-end-date" readonly></td>
                    </tr>
                    <tr>
                        <th>End Time</th>
                        <td><input type="time" id="myvh-modal-end-time" readonly></td>
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

            <p class="myvh-modal-actions">
                <button type="button" class="button button-primary myvh-edit-booking" disabled>Edit Booking</button>
                <button type="button" class="button myvh-delete-booking" disabled>Delete Booking</button>
                <button type="button" class="button myvh-cancel">Close</button>
            </p>
        </form>
    </div>
</div>
