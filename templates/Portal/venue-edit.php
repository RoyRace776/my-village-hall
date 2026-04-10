<?php
if (!defined('ABSPATH')) exit;

use MYVH\Availability\AvailabilityService;

$venue = is_array($venue ?? null) ? $venue : null;
$availability_service = $GLOBALS['myvh_container']->get(AvailabilityService::class);

if (!$venue) {
    echo '<div class="myvh-card myvh-error"><p>Venue not found.</p></div>';
    return;
}
?>
<div class="myvh-dashboard-section myvh-venues-page">
    <div class="myvh-account-header">
        <div>
            <h2>Edit Venue</h2>
            <p>Update venue details and save changes.</p>
        </div>
    </div>
    <div class="myvh-card myvh-account-card">
        <form class="myvh-account-form" data-portal-action="myvh_portal_save_venue" data-message-target="myvh-venue-edit-message" data-reload-page="venues">
            <input type="hidden" name="venue_id" value="<?php echo (int) ($venue['Id'] ?? 0); ?>">

            <label class="myvh-account-field">
                <span>Venue Name</span>
                <input type="text" name="name" required value="<?php echo esc_attr($venue['Name'] ?? ''); ?>">
            </label>

            <div class="myvh-account-grid">
                <label class="myvh-account-field">
                    <span>Short Name</span>
                    <input type="text" name="short_name" value="<?php echo esc_attr($venue['ShortName'] ?? ''); ?>">
                </label>

                <label class="myvh-account-field">
                    <span>Post Code</span>
                    <input type="text" name="post_code" value="<?php echo esc_attr($venue['PostCode'] ?? ''); ?>">
                </label>
            </div>

            <label class="myvh-account-field">
                <span>Address</span>
                <input type="text" name="address_line1" value="<?php echo esc_attr($venue['AddressLine1'] ?? ''); ?>">
            </label>

            <div class="myvh-account-grid">
                <label class="myvh-account-field">
                    <span>Opening Time</span>
                    <select name="opening_time">
                        <?php echo $availability_service->get_time_options($venue['OpeningTime'] ?? '09:00', 0, 23, true); ?>
                    </select>
                </label>

                <label class="myvh-account-field">
                    <span>Closing Time</span>
                    <select name="closing_time">
                        <?php echo $availability_service->get_time_options($venue['ClosingTime'] ?? '17:00', 0, 23, true); ?>
                    </select>
                </label>
            </div>

            <div class="myvh-account-actions">
                <button type="submit" class="button button-primary">Update Venue</button>
                <a href="#venues" class="button">Cancel</a>
                <div id="myvh-venue-edit-message" class="myvh-muted" aria-live="polite"></div>
            </div>
        </form>
    </div>
</div>