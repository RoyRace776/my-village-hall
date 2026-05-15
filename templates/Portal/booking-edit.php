<?php
if (!defined('ABSPATH')) exit;
?>

<div class="myvh-dashboard-section myvh-booking-edit-page">
    <div class="myvh-account-header">
        <div>
            <h2>Edit Booking</h2>
            <p>Update your booking details.</p>
        </div>
        <a href="#bookings" class="myvh-button">Back to <?php echo !empty($is_client_admin) ? 'Bookings' : 'My Bookings'; ?></a>
    </div>

    <div class="myvh-surface-panel myvh-bookings-panel">
        <?php if (empty($booking)): ?>
            <div class="myvh-card">
                <p>Booking not found or you do not have permission to edit it.</p>
            </div>
        <?php else: ?>
            <div class="myvh-card myvh-account-card">
                <div class="myvh-account-card-head">
                    <h3><?php echo esc_html($booking['RoomName'] ?? 'Booking'); ?></h3>
                    <span>Booking reference #<?php echo \intval($booking['Id']); ?></span>
                </div>

                <form id="myvh-booking-edit-form"
                      class="myvh-account-form"
                      data-portal-action="myvh_portal_update_booking"
                      data-message-target="myvh-booking-edit-message"
                      data-reload-page="bookings">

                    <input type="hidden" name="booking_id" value="<?php echo \intval($booking['Id']); ?>">

                    <label class="myvh-account-field" for="myvh-booking-start-date">
                        <span>Date</span>
                        <input id="myvh-booking-start-date" type="text" name="start_date" data-myvh-picker="date" autocomplete="off" required value="<?php echo esc_attr($booking['StartDate']); ?>">
                    </label>

                    <div class="myvh-account-grid">
                        <label class="myvh-account-field" for="myvh-booking-start-time">
                            <span>Start time</span>
                            <input id="myvh-booking-start-time" type="text" name="start_time" data-myvh-picker="time" autocomplete="off" required value="<?php echo esc_attr(substr((string) $booking['StartTime'], 0, 5)); ?>">
                        </label>

                        <label class="myvh-account-field" for="myvh-booking-end-time">
                            <span>End time</span>
                            <input id="myvh-booking-end-time" type="text" name="end_time" data-myvh-picker="time" autocomplete="off" required value="<?php echo esc_attr(substr((string) $booking['EndTime'], 0, 5)); ?>">
                        </label>
                    </div>

                    <label class="myvh-account-field" for="myvh-booking-description">
                        <span>Description</span>
                        <textarea id="myvh-booking-description" name="description" rows="3"><?php echo esc_textarea($booking['Description'] ?? ''); ?></textarea>
                    </label>

                    <?php if (!empty($booking['RecurringPatternId'])): ?>
                        <fieldset class="myvh-account-field" data-recurring-scope-fieldset>
                            <span>Apply changes to</span>
                            <label>
                                <input type="radio" name="edit_scope" value="this_only" checked>
                                This booking only
                            </label>
                            <label>
                                <input type="radio" name="edit_scope" value="all_bookings">
                                All bookings in this series
                            </label>
                            <label>
                                <input type="radio" name="edit_scope" value="this_and_future">
                                This booking and all future bookings
                            </label>
                            <p class="myvh-account-hint" data-recurring-scope-hint>
                                Series-wide updates apply description only in this portal form. If you change the date or time, broader options will be disabled.
                            </p>
                        </fieldset>
                    <?php endif; ?>

                    <p class="myvh-account-hint">Room and organisation are fixed for this edit flow.</p>
                    <?php if (!empty($is_client_admin) && !empty($booking['CustomerName'])): ?>
                        <p class="myvh-account-hint">Customer: <?php echo esc_html($booking['CustomerName']); ?></p>
                    <?php endif; ?>

                    <div class="myvh-account-actions">
                        <button type="submit" class="button button-primary">Save Booking</button>
                        <div id="myvh-booking-edit-message" class="myvh-muted" aria-live="polite"></div>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>
