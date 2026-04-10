<?php
if (!defined('ABSPATH')) exit;

use MYVH\Availability\AvailabilityService;
use MYVH\Rooms\RoomColour;

$venues = is_array($venues ?? null) ? $venues : [];
$availability_service = $GLOBALS['myvh_container']->get(AvailabilityService::class);
$default_colour = RoomColour::fallback(count($venues) + 1);
$room_colour_palette = RoomColour::palette();
$day_labels = [
    0 => 'Sunday',
    1 => 'Monday',
    2 => 'Tuesday',
    3 => 'Wednesday',
    4 => 'Thursday',
    5 => 'Friday',
    6 => 'Saturday',
];
?>
<div class="myvh-dashboard-section myvh-rooms-page">
    <div class="myvh-account-header">
        <div>
            <h2>Add Room</h2>
            <p>Create a new room and choose the room colour used in calendars.</p>
        </div>
    </div>

    <div class="myvh-card myvh-account-card">
        <?php if (empty($venues)): ?>
            <p>You need to create at least one venue before adding rooms.</p>
        <?php else: ?>
            <form class="myvh-account-form" data-portal-action="myvh_portal_save_room" data-message-target="myvh-room-create-message" data-reload-page="rooms">
                <label class="myvh-account-field">
                    <span>Room Name</span>
                    <input type="text" name="name" required>
                </label>

                <div class="myvh-account-grid">
                    <label class="myvh-account-field">
                        <span>Venue</span>
                        <select name="venue_id" required>
                            <option value="">Select venue</option>
                            <?php foreach ($venues as $venue): ?>
                                <option value="<?php echo (int) ($venue['Id'] ?? 0); ?>"><?php echo esc_html($venue['Name'] ?? ''); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="myvh-account-field">
                        <span>Capacity</span>
                        <input type="number" name="capacity" min="0">
                    </label>
                </div>

                <label class="myvh-account-field">
                    <span>Room Colour</span>
                    <div class="myvh-room-colour-row">
                        <input
                            type="color"
                            name="room_colour"
                            value="<?php echo esc_attr($default_colour); ?>"
                            data-room-colour-input
                            required
                        >
                        <span class="myvh-room-colour-preview-box" data-room-colour-preview style="background-color: <?php echo esc_attr($default_colour); ?>;"></span>
                        <span class="myvh-room-colour-code" data-room-colour-code><?php echo esc_html($default_colour); ?></span>
                    </div>
                    <div class="myvh-room-colour-swatches" aria-label="Suggested colours">
                        <?php foreach ($room_colour_palette as $swatch): ?>
                            <button
                                type="button"
                                class="myvh-room-colour-swatch"
                                data-room-colour-choice="<?php echo esc_attr($swatch); ?>"
                                title="Use <?php echo esc_attr($swatch); ?>"
                                style="--swatch-colour: <?php echo esc_attr($swatch); ?>;"
                            ></button>
                        <?php endforeach; ?>
                    </div>
                </label>

                <label class="myvh-account-field">
                    <span>Description</span>
                    <textarea name="description" rows="3"></textarea>
                </label>

                <div class="myvh-account-grid">
                    <label class="myvh-account-field">
                        <span>Opening Time</span>
                        <select name="opening_time" required>
                            <?php echo $availability_service->get_time_options('09:00', 0, 23, true); ?>
                        </select>
                    </label>

                    <label class="myvh-account-field">
                        <span>Closing Time</span>
                        <select name="closing_time" required>
                            <?php echo $availability_service->get_time_options('17:00', 0, 23, true); ?>
                        </select>
                    </label>
                </div>

                <div class="myvh-account-field">
                    <span>Daily Opening Hours</span>
                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th>Day</th>
                                <th>Use Venue Hours</th>
                                <th>Closed</th>
                                <th>Opens</th>
                                <th>Closes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($day_labels as $day => $label): ?>
                                <tr>
                                    <td>
                                        <?php echo esc_html($label); ?>
                                        <input type="hidden" name="opening_hours_by_day[<?php echo (int) $day; ?>][day_of_week]" value="<?php echo (int) $day; ?>">
                                    </td>
                                    <td>
                                        <input type="hidden" name="opening_hours_by_day[<?php echo (int) $day; ?>][use_venue_hours]" value="0">
                                        <input type="checkbox" name="opening_hours_by_day[<?php echo (int) $day; ?>][use_venue_hours]" value="1" checked>
                                    </td>
                                    <td>
                                        <input type="hidden" name="opening_hours_by_day[<?php echo (int) $day; ?>][is_closed]" value="0">
                                        <input type="checkbox" name="opening_hours_by_day[<?php echo (int) $day; ?>][is_closed]" value="1">
                                    </td>
                                    <td>
                                        <select name="opening_hours_by_day[<?php echo (int) $day; ?>][opening_time]">
                                            <?php echo $availability_service->get_time_options('09:00', 0, 23, true); ?>
                                        </select>
                                    </td>
                                    <td>
                                        <select name="opening_hours_by_day[<?php echo (int) $day; ?>][closing_time]">
                                            <?php echo $availability_service->get_time_options('17:00', 0, 23, true); ?>
                                        </select>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <label class="myvh-account-field">
                    <span>
                        <input type="checkbox" name="allow-multi-day-bookings" value="1">
                        Allow multi-day bookings
                    </span>
                </label>

                <label class="myvh-account-field">
                    <span>
                        <input type="checkbox" name="calc-closed-hours" value="1">
                        Include closed hours when calculating booking duration
                    </span>
                </label>

                <label class="myvh-account-field">
                    <span>
                        <input type="checkbox" name="is-public" value="1" checked>
                        Make this room public
                    </span>
                </label>

                <div class="myvh-account-actions">
                    <button type="submit" class="button button-primary">Create Room</button>
                    <a href="#rooms" class="button">Cancel</a>
                    <div id="myvh-room-create-message" class="myvh-muted" aria-live="polite"></div>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>
