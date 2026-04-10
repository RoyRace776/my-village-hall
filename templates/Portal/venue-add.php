<?php
if (!defined('ABSPATH')) exit;

use MYVH\Availability\AvailabilityService;

$availability_service = $GLOBALS['myvh_container']->get(AvailabilityService::class);
?>
<div class="myvh-dashboard-section myvh-venues-page">
    <div class="myvh-account-header">
        <div>
            <h2>Add Venue</h2>
            <p>Create a new venue and define its default opening hours.</p>
        </div>
    </div>

    <div class="myvh-card myvh-account-card">
        <form class="myvh-account-form" data-portal-action="myvh_portal_save_venue" data-message-target="myvh-venue-create-message" data-reload-page="venues">
            <label class="myvh-account-field">
                <span>Venue Name</span>
                <input type="text" name="name" required>
            </label>

            <div class="myvh-account-grid">
                <label class="myvh-account-field">
                    <span>Short Name</span>
                    <input type="text" name="short_name">
                </label>

                <label class="myvh-account-field">
                    <span>Post Code</span>
                    <input type="text" name="post_code">
                </label>
            </div>

            <label class="myvh-account-field">
                <span>Address</span>
                <input type="text" name="address_line1">
            </label>

            <div class="myvh-account-grid">
                <label class="myvh-account-field">
                    <span>Opening Time</span>
                    <select name="opening_time">
                        <?php echo $availability_service->get_time_options('09:00', 0, 23, true); ?>
                    </select>
                </label>

                <label class="myvh-account-field">
                    <span>Closing Time</span>
                    <select name="closing_time">
                        <?php echo $availability_service->get_time_options('17:00', 0, 23, true); ?>
                    </select>
                </label>
            </div>

            <div class="myvh-account-actions">
                <button type="submit" class="button button-primary">Create Venue</button>
                <a href="#venues" class="button">Cancel</a>
                <div id="myvh-venue-create-message" class="myvh-muted" aria-live="polite"></div>
            </div>
        </form>
    </div>
</div>