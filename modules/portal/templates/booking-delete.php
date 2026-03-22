<?php
if (!defined('ABSPATH')) exit;
?>

<div class="myvh-dashboard-section myvh-booking-delete-page">
    <div class="myvh-account-header">
        <div>
            <h2>Delete Booking</h2>
            <p>Deletion rules will be added next.</p>
        </div>
        <a href="#bookings" class="myvh-button">Back to My Bookings</a>
    </div>

    <div class="myvh-surface-panel myvh-bookings-panel">
        <?php if (empty($booking)): ?>
            <div class="myvh-card">
                <p>Booking not found or you do not have permission to delete it.</p>
            </div>
        <?php else: ?>
            <?php $can_delete = !empty($delete_rules['can_delete']); ?>
            <div class="myvh-card myvh-account-card">
                <div class="myvh-account-card-head">
                    <h3><?php echo esc_html($booking['RoomName'] ?? 'Booking'); ?></h3>
                    <span>Booking reference #<?php echo intval($booking['Id']); ?></span>
                </div>

                <p>
                    <strong>Description:</strong>
                    <?php echo esc_html(!empty($booking['Description']) ? $booking['Description'] : 'No description provided.'); ?>
                </p>
                <?php if (!$can_delete): ?>
                    <p><?php echo esc_html($delete_rules['reason'] ?? 'This booking cannot be deleted.'); ?></p>
                <?php endif; ?>
                <p><strong>Date:</strong> <?php echo date('D d/m/Y', strtotime($booking['StartDate'])); ?>, <?php echo date('H:i', strtotime($booking['StartTime'])); ?> - <?php echo date('H:i', strtotime($booking['EndTime'])); ?></p>
                <p><strong>Status:</strong> <?php echo esc_html(ucfirst($booking['Status'] ?? '')); ?></p>

                <div class="myvh-account-actions" style="margin-top:12px;">
                    <?php if ($can_delete): ?>
                        <form
                            data-portal-action="myvh_portal_delete_booking"
                            data-message-target="myvh-booking-delete-message"
                            data-reload-page="bookings"
                            data-confirm="Delete this booking? This action cannot be undone."
                        >
                            <input type="hidden" name="booking_id" value="<?php echo intval($booking['Id']); ?>">
                            <button type="submit" class="button button-primary myvh-delete-booking-button">Delete Booking</button>
                        </form>
                    <?php else: ?>
                        <button type="button" class="button" disabled>Delete Booking</button>
                    <?php endif; ?>

                    <a href="#booking-view?booking_id=<?php echo intval($booking['Id']); ?>" class="myvh-button">View Booking</a>
                    <a href="#bookings" class="myvh-button myvh-button-primary">Back to List</a>
                </div>
                <div id="myvh-booking-delete-message" class="myvh-muted" aria-live="polite"></div>
            </div>
        <?php endif; ?>
    </div>
</div>
