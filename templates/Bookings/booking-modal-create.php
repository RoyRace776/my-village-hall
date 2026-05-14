<?php
if (!defined('ABSPATH')) exit;

if (!is_user_logged_in()) {
    echo '<p>Please log in to create a booking.</p>';
    return;
}

// TODO: Refactor to use REST API for addons data
use MYVH\Addons\AddonService;

global $myvh_container;
$addon_service = $myvh_container->get(AddonService::class);
$available_addons = $addon_service->get_all(['orderby' => 'DisplayOrder', 'order' => 'ASC']);

// TODO: Take out code relating to viewing bookings and make this template just for creating bookings.

?>

<div id="myvh-booking-modal-create" class="myvh-modal hidden">
<div class="myvh-modal-content myvh-booking-modal-shell">
    <h2>Create Booking</h2>
    <p class="myvh-account-hint">Complete the details below to create a booking.</p>

    <p class="myvh-modal-actions" style="margin-bottom: 15px;">
        <button type="submit" class="button button-primary" form="myvh-booking-form-create">Create Booking</button>
        <button type="button" class="button button-link-delete myvh-delete-booking" style="display:none;" disabled>Delete Booking</button>
        <button type="button" class="button myvh-cancel">Cancel</button>
    </p>

    <form id="myvh-booking-form-create">
        <input type="hidden" name="start">
        <input type="hidden" name="end">
        <input type="hidden" name="booking_id">

        <div class="myvh-modal-group myvh-modal-group-main">
            <h3>Booking Details</h3>

            <table class="form-table">
                <tr>
                    <th>Start Date</th>
                    <td><input type="text" id="myvh-modal-start-date" data-myvh-picker="date" autocomplete="off"></td>
                </tr>
                    <tr>
                        <th>Start Time</th>
                        <td><input type="text" id="myvh-modal-start-time" data-myvh-picker="time" autocomplete="off"></td>
                    </tr>
                    <tr id="myvh-modal-end-date-row" style="display:none;">
                        <th>End Date</th>
                        <td><input type="text" id="myvh-modal-end-date" data-myvh-picker="date" autocomplete="off"></td>
                    </tr>
                    <tr>
                        <th>End Time</th>
                        <td><input type="text" id="myvh-modal-end-time" data-myvh-picker="time" autocomplete="off"></td>
                    </tr>
                    <tr>
                        <th>Room</th>
                        <td><select name="room_id" required></select></td>
                    </tr>
                    <tr>
                        <th>Customer</th>
                        <td><select name="customer_id" required></select></td>
                    </tr>
                    <tr>
                        <th>Organisation</th>
                        <td><select name="organisation_id" required></select></td>
                    </tr>
                    <tr>
                        <th>Description</th>
                        <td><input type="text" name="text" placeholder="Optional"></td>
                    </tr>
                    <tr id="myvh-modal-status-row" style="display:none;">
                        <th>Status</th>
                        <td>
                            <select name="status">
                                <option value="pending">Pending</option>
                                <option value="confirmed">Confirmed</option>
                                <option value="cancelled">Cancelled</option>
                                <option value="completed">Completed</option>
                            </select>
                        </td>
                    </tr>
                    <tr id="myvh-modal-edit-scope-row" style="display:none;">
                        <th>Apply changes to</th>
                        <td>
                            <label style="display:block; margin-bottom:8px;">
                                <input type="radio" name="edit_scope" value="this_only" checked>
                                This booking only
                            </label>
                            <label style="display:block; margin-bottom:8px;">
                                <input type="radio" name="edit_scope" value="all_bookings">
                                All bookings in this series
                            </label>
                            <label style="display:block; margin-bottom:8px;">
                                <input type="radio" name="edit_scope" value="this_and_future">
                                This booking and all future bookings
                            </label>
                            <p class="description" style="margin:8px 0 0;">
                                Series-wide updates apply description, status, visibility, and add-ons.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th>Visibility</th>
                        <td>
                            <label>
                                <input type="checkbox" name="public" value="1">
                                Show on public calendar
                            </label>
                        </td>
                    </tr>
                    <tr id="myvh-modal-no-invoice-row" style="display:none;">
                        <th>Invoice</th>
                        <td>
                            <label>
                                <input type="checkbox" name="no_invoice_required" value="1" disabled>
                                This booking does not need an invoice
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th>Recurring</th>
                        <td>
                            <label>
                                <input type="checkbox" name="is_recurring" value="1" id="myvh-modal-is-recurring">
                                Create recurring bookings
                            </label>
                        </td>
                    </tr>
                </table>
            </div>

            <div id="myvh-modal-recurring-options" class="myvh-modal-group" style="display:none; margin: 15px 0; padding: 12px; background: #f6f7f7; border: 1px solid #dcdcde; border-radius: 4px;">
                <h3>Recurring Options</h3>
                <table class="form-table" style="margin-top:0;">
                    <tr>
                        <th>Type</th>
                        <td>
                            <select name="recurrence_type" id="myvh-modal-rec-type">
                                <option value="daily">Daily</option>
                                <option value="weekly" selected>Weekly</option>
                                <option value="monthly">Monthly (same date)</option>
                                <option value="monthly_day">Monthly (specific weekday)</option>
                                <option value="yearly">Yearly</option>
                            </select>
                        </td>
                    </tr>
                    <tr id="myvh-modal-interval-row">
                        <th>Interval</th>
                        <td>
                            Every <input type="number" name="recurrence_interval" value="1" min="1" max="52" class="small-text"> <span id="myvh-modal-interval-label">week(s)</span>
                        </td>
                    </tr>
                    <tr id="myvh-modal-monthly-day-row" style="display:none;">
                        <th>Pattern</th>
                        <td>
                            <select name="recurrence_week">
                                <option value="1">1st</option>
                                <option value="2">2nd</option>
                                <option value="3">3rd</option>
                                <option value="4">4th</option>
                                <option value="last">Last</option>
                            </select>
                            <select name="recurrence_day">
                                <option value="monday">Monday</option>
                                <option value="tuesday">Tuesday</option>
                                <option value="wednesday">Wednesday</option>
                                <option value="thursday">Thursday</option>
                                <option value="friday">Friday</option>
                                <option value="saturday">Saturday</option>
                                <option value="sunday">Sunday</option>
                            </select>
                            Every <input type="number" name="recurrence_interval_md" value="1" min="1" max="24" class="small-text"> month(s)
                        </td>
                    </tr>
                    <tr>
                        <th>Ends</th>
                        <td>
                            <label><input type="radio" name="recurrence_end_type" value="date" checked> On date</label>
                            <input type="text" name="recurrence_end_date" data-myvh-picker="date" autocomplete="off" value="<?php echo esc_attr(date('Y-m-d', strtotime('+3 months'))); ?>">
                            <br>
                            <label><input type="radio" name="recurrence_end_type" value="count"> After occurrences</label>
                            <input type="number" name="max_occurrences" value="10" min="1" max="365" class="small-text" disabled>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="myvh-modal-group" id="myvh-modal-cost-summary" style="margin: 15px 0;">
                <h3 style="margin:0 0 8px;">Cost Estimate</h3>
                <table class="widefat striped" id="myvh-modal-cost-summary-table" style="display:none;">
                    <tbody>
                        <tr>
                            <th style="width:55%;">Room Charge</th>
                            <td id="myvh-modal-quote-room-charge">-</td>
                        </tr>
                        <tr>
                            <th>Add-ons Total</th>
                            <td id="myvh-modal-quote-addon-total">-</td>
                        </tr>
                        <tr id="myvh-modal-quote-deposit-row" style="display:none;">
                            <th>Deposit</th>
                            <td id="myvh-modal-quote-deposit-total">-</td>
                        </tr>
                        <tr>
                            <th><strong>Booking Total</strong></th>
                            <td id="myvh-modal-quote-booking-total"><strong>-</strong></td>
                        </tr>
                    </tbody>
                </table>
                <p id="myvh-modal-quote-empty" class="description" style="margin-top:8px;">Select date/time, room, customer, and organisation to see the booking cost.</p>
                <p id="myvh-modal-quote-note" class="description" style="display:none; margin-top:8px;"></p>
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
                            <tr class="myvh-modal-addon-row" data-room-id="<?php echo esc_attr((string) intval($addon['RoomId'] ?? 0)); ?>">
                                <td>
                                    <input type="checkbox" class="myvh-modal-addon-checkbox" value="1">
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
            </div>
            <?php endif; ?>

            <p class="myvh-modal-actions">
                <button type="submit" class="button button-primary">Create Booking</button>
                <button type="button" class="button button-link-delete myvh-delete-booking" style="display:none;" disabled>Delete Booking</button>
                <button type="button" class="button myvh-cancel">Cancel</button>
            </p>
        </form>
    </div>
</div>
