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

    <div class="myvh-calendar-toolbar">
        <div class="myvh-calendar-nav">
            <button id="myvh-prev" class="button">&lt;</button>
            <button id="myvh-today" class="button button-primary">Today</button>
            <button id="myvh-next" class="button">&gt;</button>
        </div>

        <div class="myvh-calendar-views">
            <button id="myvh-day" class="button myvh-view-btn" data-view="Day" type="button">Day</button>
            <button id="myvh-week" class="button myvh-view-btn active" data-view="Week" type="button">Week</button>
            <button id="myvh-month" class="button myvh-view-btn" data-view="Month" type="button">Month</button>
        </div>
    </div>

    <div id="myvh-calendar" style="height: 700px;"></div>

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
            </table>

            <p>
                <button type="submit" class="button button-primary">Create Booking</button>
                <button type="button" class="button myvh-cancel">Cancel</button>
            </p>
        </form>
    </div>
</div>
