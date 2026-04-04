<?php
if (!defined('ABSPATH')) exit;

use MYVH\Availability\AvailabilityService;
use MYVH\Rooms\RoomColour;

$room = is_array($room ?? null) ? $room : null;
$venues = is_array($venues ?? null) ? $venues : [];
$availability_service = $GLOBALS['myvh_container']->get(AvailabilityService::class);
$room_colour_palette = RoomColour::palette();

if (!$room) {
    echo '<div class="myvh-card myvh-error"><p>Room not found.</p></div>';
    return;
}

$room_colour = RoomColour::resolve($room['Colour'] ?? '', intval($room['Id'] ?? 0));
?>
<div class="myvh-dashboard-section myvh-rooms-page">
    <div class="myvh-account-header">
        <div>
            <h2>Edit Room</h2>
            <p>Update room details and save changes.</p>
        </div>
    </div>
    <div class="myvh-card myvh-account-card">
        <form class="myvh-account-form" data-portal-action="myvh_portal_save_room" data-message-target="myvh-room-edit-message" data-reload-page="rooms">
            <input type="hidden" name="room_id" value="<?php echo (int) ($room['Id'] ?? 0); ?>">

            <label class="myvh-account-field">
                <span>Room Name</span>
                <input type="text" name="name" required value="<?php echo esc_attr($room['Name'] ?? ''); ?>">
            </label>

            <div class="myvh-account-grid">
                <label class="myvh-account-field">
                    <span>Venue</span>
                    <select name="venue_id" required>
                        <option value="">Select venue</option>
                        <?php foreach ($venues as $venue): ?>
                            <option value="<?php echo (int) ($venue['Id'] ?? 0); ?>" <?php selected((int) ($room['VenueId'] ?? 0), (int) ($venue['Id'] ?? 0)); ?>><?php echo esc_html($venue['Name'] ?? ''); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="myvh-account-field">
                    <span>Capacity</span>
                    <input type="number" name="capacity" min="0" value="<?php echo esc_attr((string) ($room['Capacity'] ?? '')); ?>">
                </label>
            </div>

            <label class="myvh-account-field">
                <span>Room Colour</span>
                <div class="myvh-room-colour-row">
                    <input
                        type="color"
                        name="room_colour"
                        value="<?php echo esc_attr($room_colour); ?>"
                        data-room-colour-input
                        required
                    >
                    <span class="myvh-room-colour-preview-box" data-room-colour-preview style="background-color: <?php echo esc_attr($room_colour); ?>;"></span>
                    <span class="myvh-room-colour-code" data-room-colour-code><?php echo esc_html($room_colour); ?></span>
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
                <textarea name="description" rows="3"><?php echo esc_textarea($room['Description'] ?? ''); ?></textarea>
            </label>

            <div class="myvh-account-grid">
                <label class="myvh-account-field">
                    <span>Opening Time</span>
                    <select name="opening_time" required>
                        <?php echo $availability_service->get_time_options($room['OpeningTime'] ?? '09:00', 0, 23, true); ?>
                    </select>
                </label>

                <label class="myvh-account-field">
                    <span>Closing Time</span>
                    <select name="closing_time" required>
                        <?php echo $availability_service->get_time_options($room['ClosingTime'] ?? '17:00', 0, 23, true); ?>
                    </select>
                </label>
            </div>

            <label class="myvh-account-field">
                <span>
                    <input type="checkbox" name="allow-multi-day-bookings" value="1" <?php checked(!empty($room['AllowMultiDayBookings'])); ?>>
                    Allow multi-day bookings
                </span>
            </label>

            <label class="myvh-account-field">
                <span>
                    <input type="checkbox" name="calc-closed-hours" value="1" <?php checked(!empty($room['CalcClosedHours'])); ?>>
                    Include closed hours when calculating booking duration
                </span>
            </label>

            <div class="myvh-account-actions">
                <button type="submit" class="button button-primary">Update Room</button>
                <a href="#room-rate-add?room_id=<?php echo (int) ($room['Id'] ?? 0); ?>" class="button">Manage Rates</a>
                <a href="#rooms" class="button">Cancel</a>
                <div id="myvh-room-edit-message" class="myvh-muted" aria-live="polite"></div>
            </div>
        </form>
    </div>
</div>
