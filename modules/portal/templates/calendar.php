<?php
if (!defined('ABSPATH')) exit;

if (!is_user_logged_in()) {
    echo '<p>Please log in to view the calendar.</p>';
    return;
}
?>

<div class="myvh-dashboard-section myvh-portal-calendar">

    <div class="myvh-section-header">
        <h2>Calendar</h2>
    </div>

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

                <div id="myvh-calendar" class="myvh-daypilot-frame" style="height: 700px;"></div>
            </div>
        </div>
    </div>

</div>

<div id="myvh-booking-modal" class="myvh-modal hidden">
    <div class="myvh-modal-content">
        <h2>Create Booking</h2>

        <form id="myvh-booking-form">
            <input type="hidden" name="start">
            <input type="hidden" name="end">

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
            </table>

            <p>
                <button type="submit" class="button button-primary">Create Booking</button>
                <button type="button" class="button myvh-cancel">Cancel</button>
            </p>
        </form>
    </div>
</div>
