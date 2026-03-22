<?php
if (!defined('ABSPATH')) exit;
?>

<div class="myvh-dashboard-section myvh-booking-new-page">
    <div class="myvh-account-header">
        <div>
            <h2>New Booking</h2>
            <p>Choose a room and time slot from the live calendar.</p>
        </div>
        <a href="#bookings" class="myvh-button">Back to My Bookings</a>
    </div>

    <div class="myvh-surface-panel myvh-bookings-panel">
        <div class="myvh-card myvh-account-card">
            <div class="myvh-account-card-head">
                <h3>Start a New Booking</h3>
                <span>Availability and booking rules are checked automatically.</span>
            </div>

            <p class="myvh-account-hint">
                Use the calendar flow to pick a date, time, and room, then submit your booking.
            </p>

            <div class="myvh-account-actions">
                <a href="#calendar" class="button button-primary">Open Booking Calendar</a>
                <div class="myvh-muted">You can return to My Bookings at any time.</div>
            </div>
        </div>
    </div>
</div>
