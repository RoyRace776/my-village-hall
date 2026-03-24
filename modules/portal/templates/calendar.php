<?php
if (!defined('ABSPATH')) exit;

if (!is_user_logged_in()) {
    echo '<p>Please log in to view the calendar.</p>';
    return;
}

global $myvh_container;
$addon_service = $myvh_container->get(MYVH_Addon_Service::class);
$all_addons = $addon_service->get_all(['orderby' => 'DisplayOrder', 'order' => 'ASC']);
$available_addons = array_values(array_filter($all_addons ?? [], fn($a) => !empty($a['IsActive'])));
?>

<div class="myvh-dashboard-section myvh-portal-calendar">

    <div class="myvh-section-header">
        <h2>Calendar</h2>
    </div>

    <?php if (empty($can_create_booking)): ?>
        <div class="myvh-surface-panel myvh-bookings-panel">
            <div class="myvh-card">
                <p>A customer profile is required before this account can create portal bookings.</p>
            </div>
        </div>
        <?php return; ?>
    <?php endif; ?>

    <div class="myvh-calendar-shell">
        <div class="myvh-calendar-main">
            <aside class="myvh-calendar-sidebar">
                <div id="myvh-calendar-nav-picker" class="myvh-cal-nav-picker"></div>
            </aside>

            <div class="myvh-calendar-content">
                <div class="myvh-calendar-toolbar">
            <div class="myvh-calendar-nav myvh-pill-group">
                <button id="myvh-prev" class="myvh-cal-btn myvh-cal-nav-btn" type="button" aria-label="Previous">&lt;</button>
                <button id="myvh-today" class="myvh-cal-btn myvh-cal-nav-btn" type="button">Today</button>
                <button id="myvh-next" class="myvh-cal-btn myvh-cal-nav-btn" type="button" aria-label="Next">&gt;</button>
            </div>

            <div class="myvh-calendar-views myvh-pill-group">
                <button id="myvh-mode-calendar" class="myvh-cal-btn myvh-view-btn myvh-mode-btn active" data-mode="Calendar" type="button">Calendar</button>
                <button id="myvh-mode-scheduler" class="myvh-cal-btn myvh-view-btn myvh-mode-btn" data-mode="Scheduler" type="button">Scheduler</button>
                <button id="myvh-day" class="myvh-cal-btn myvh-view-btn myvh-detail-btn" data-view="Day" type="button">Day</button>
                <button id="myvh-week" class="myvh-cal-btn myvh-view-btn myvh-detail-btn active" data-view="Week" type="button">Week</button>
                <button id="myvh-month" class="myvh-cal-btn myvh-view-btn myvh-detail-btn" data-view="Month" type="button">Month</button>
            </div>
                </div>

                <div id="myvh-calendar" class="myvh-daypilot-frame myvh-portal-calendar-frame"></div>
            </div>
        </div>
    </div>

</div>

<!-- Booking modal for viewing bookings from calendar event click removed -->
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
                    <tr>
                        <th>Visibility</th>
                        <td>
                            <label>
                                <input type="checkbox" name="public" value="1">
                                Show on public calendar
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
                            <input type="date" name="recurrence_end_date" value="<?php echo esc_attr(date('Y-m-d', strtotime('+3 months'))); ?>">
                            <br>
                            <label><input type="radio" name="recurrence_end_type" value="count"> After occurrences</label>
                            <input type="number" name="max_occurrences" value="10" min="1" max="365" class="small-text" disabled>
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
                                <input type="hidden" name="addons[<?php echo $i; ?>][quantity]" value="1">
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <p class="myvh-modal-actions">
                <button type="submit" class="button button-primary">Create Booking</button>
                <button type="button" class="button myvh-cancel">Cancel</button>
            </p>
        </form>
    </div>
</div>
