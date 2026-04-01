<?php
if (!defined('ABSPATH')) exit;
?>

<div class="myvh-dashboard-section">
    <div class="myvh-account-header">
        <div>
            <h2>View My Booking</h2>
            <p>Review booking details.</p>
        </div>
        <a href="#bookings" class="myvh-button">Back to <?php echo !empty($is_client_admin) ? 'Bookings' : 'My Bookings'; ?></a>
    </div>

    <div class="myvh-surface-panel myvh-bookings-panel">
        <?php if (empty($booking)): ?>
            <div class="myvh-card">
                <p>Booking not found or you do not have permission to view it.</p>
            </div>
        <?php else: ?>
            <?php $can_delete = !empty($delete_rules['can_delete']); ?>
            <div class="myvh-card myvh-account-card">
                <div class="myvh-account-card-head">
                    <h3><?php echo esc_html($booking['RoomName'] ?? 'Booking'); ?></h3>
                    <span>Booking reference #<?php echo intval($booking['Id']); ?></span>
                </div>

                <div class="myvh-account-grid">
                    <div class="myvh-account-card">
                        <div class="myvh-account-card-head">
                            <h3>Date &amp; Time</h3>
                        </div>
                        <p><strong><?php echo date('D d/m/Y', strtotime($booking['StartDate'])); ?></strong></p>
                        <p><?php echo date('H:i', strtotime($booking['StartTime'])); ?> - <?php echo date('H:i', strtotime($booking['EndTime'])); ?></p>
                    </div>

                    <div class="myvh-account-card">
                        <div class="myvh-account-card-head">
                            <h3>Details</h3>
                        </div>
                        <p><strong>Status:</strong> <?php echo esc_html(ucfirst($booking['Status'] ?? '')); ?></p>
                        <?php if (!empty($is_client_admin)): ?>
                            <p><strong>Customer:</strong> <?php echo esc_html($booking['CustomerName'] ?? '-'); ?></p>
                        <?php endif; ?>
                        <p><strong>Venue:</strong> <?php echo esc_html($booking['VenueName'] ?? '-'); ?></p>
                        <p><strong>Organisation:</strong> <?php echo esc_html($booking['OrganisationName'] ?? '-'); ?></p>
                        <p><strong>Description:</strong> <?php echo esc_html($booking['Description'] ?? '-'); ?></p>
                    </div>
                </div>

                <div class="myvh-account-actions" style="margin-top:12px;">
                    <a href="#booking-edit?booking_id=<?php echo intval($booking['Id']); ?>" class="myvh-button myvh-button-primary">Edit Booking</a>
                    <?php if ($can_delete): ?>
                        <a href="#booking-delete?booking_id=<?php echo intval($booking['Id']); ?>" class="myvh-button">Delete Booking</a>
                    <?php else: ?>
                        <span class="myvh-muted"><?php echo esc_html($delete_rules['reason'] ?? 'This booking cannot be deleted.'); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
