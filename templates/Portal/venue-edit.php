<?php
if (!defined('ABSPATH')) exit;

use MYVH\Availability\AvailabilityService;
use MYVH\Venues\VenueHoursRepository;

$venue = isset($venue) && is_array($venue) ? $venue : null;
$availability_service = $GLOBALS['myvh_container']->get(AvailabilityService::class);
$venue_hours_repository = $GLOBALS['myvh_container']->get(VenueHoursRepository::class);

if (!$venue) {
    echo '<div class="myvh-card myvh-error"><p>Venue not found.</p></div>';
    return;
}

$day_labels = [
    0 => 'Sunday',
    1 => 'Monday',
    2 => 'Tuesday',
    3 => 'Wednesday',
    4 => 'Thursday',
    5 => 'Friday',
    6 => 'Saturday',
];

$venue_hours_rows = !empty($venue['Id']) ? $venue_hours_repository->get_by_venue((int) $venue['Id']) : [];
$venue_hours_index = [];
foreach ((array) $venue_hours_rows as $row) {
    $venue_hours_index[(int) ($row['DayOfWeek'] ?? -1)] = $row;
}

$default_open = substr((string) ($venue['OpeningTime'] ?? '09:00'), 0, 5);
$default_close = substr((string) ($venue['ClosingTime'] ?? '17:00'), 0, 5);
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

            <div class="myvh-account-field">
                <span>Daily Opening Hours</span>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>Day</th>
                            <th>Closed</th>
                            <th>Opens</th>
                            <th>Closes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($day_labels as $day => $label): ?>
                            <?php
                            $row = $venue_hours_index[$day] ?? null;
                            $is_closed = !empty($row['IsClosed']) ? 1 : 0;
                            $opening_time = $is_closed ? '' : substr((string) ($row['OpeningTime'] ?? $default_open), 0, 5);
                            $closing_time = $is_closed ? '' : substr((string) ($row['ClosingTime'] ?? $default_close), 0, 5);
                            ?>
                            <tr>
                                <td>
                                    <?php echo esc_html($label); ?>
                                    <input type="hidden" name="opening_hours_by_day[<?php echo (int) $day; ?>][day_of_week]" value="<?php echo (int) $day; ?>">
                                </td>
                                <td>
                                    <input type="hidden" name="opening_hours_by_day[<?php echo (int) $day; ?>][is_closed]" value="0">
                                    <input type="checkbox" name="opening_hours_by_day[<?php echo (int) $day; ?>][is_closed]" value="1" <?php checked($is_closed, 1); ?>>
                                </td>
                                <td>
                                    <select name="opening_hours_by_day[<?php echo (int) $day; ?>][opening_time]">
                                        <?php echo $availability_service->get_time_options($opening_time ?: $default_open, 0, 23, true); ?>
                                    </select>
                                </td>
                                <td>
                                    <select name="opening_hours_by_day[<?php echo (int) $day; ?>][closing_time]">
                                        <?php echo $availability_service->get_time_options($closing_time ?: $default_close, 0, 23, true); ?>
                                    </select>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="myvh-account-actions">
                <button type="submit" class="button button-primary">Update Venue</button>
                <a href="#venues" class="button">Cancel</a>
                <div id="myvh-venue-edit-message" class="myvh-muted" aria-live="polite"></div>
            </div>
        </form>
    </div>
</div>