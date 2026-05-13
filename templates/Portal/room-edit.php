<?php
if (!defined('ABSPATH')) exit;

use MYVH\Availability\AvailabilityService;
use MYVH\Rooms\RoomColour;
use MYVH\Rooms\RoomDepositRepository;
use MYVH\Rooms\RoomHoursRepository;

$room = isset($room) && is_array($room) ? $room : null;
$venues = isset($venues) && is_array($venues) ? $venues : [];
$availability_service = $GLOBALS['myvh_container']->get(AvailabilityService::class);
$room_hours_repository = $GLOBALS['myvh_container']->get(RoomHoursRepository::class);
$room_deposit_repository = $GLOBALS['myvh_container']->get(RoomDepositRepository::class);
$room_colour_palette = RoomColour::palette();

if (!$room) {
    echo '<div class="myvh-card myvh-error"><p>Room not found.</p></div>';
    return;
}

$room_colour = RoomColour::resolve($room['Colour'] ?? '', intval($room['Id'] ?? 0));

$day_labels = [
    0 => 'Sunday',
    1 => 'Monday',
    2 => 'Tuesday',
    3 => 'Wednesday',
    4 => 'Thursday',
    5 => 'Friday',
    6 => 'Saturday',
];

$room_hours_rows = !empty($room['Id']) ? $room_hours_repository->get_by_room((int) $room['Id']) : [];
$room_hours_index = [];
foreach ((array) $room_hours_rows as $row) {
    $room_hours_index[(int) ($row['DayOfWeek'] ?? -1)] = $row;
}

$default_open = substr((string) ($room['OpeningTime'] ?? '09:00'), 0, 5);
$default_close = substr((string) ($room['ClosingTime'] ?? '17:00'), 0, 5);
$deposit_config = $room_deposit_repository->get((int) ($room['Id'] ?? 0));
$deposit_days = (array) ($deposit_config['days'] ?? []);
$deposit_end_after = (string) ($deposit_config['end_after'] ?? '');
$deposit_amount = (float) ($deposit_config['amount'] ?? 0);
$deposit_action = (string) ($deposit_config['action'] ?? 'auto_add');
if (!in_array($deposit_action, ['auto_add', 'require_review'], true)) {
    $deposit_action = 'auto_add';
}
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
                        class="myvh-room-colour-input"
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
                            <?php
                            $row = $room_hours_index[$day] ?? null;
                            $use_venue = !empty($row['UseVenueHours']) ? 1 : 0;
                            $is_closed = !empty($row['IsClosed']) ? 1 : 0;
                            $opening_time = ($use_venue || $is_closed)
                                ? ''
                                : substr((string) ($row['OpeningTime'] ?? $default_open), 0, 5);
                            $closing_time = ($use_venue || $is_closed)
                                ? ''
                                : substr((string) ($row['ClosingTime'] ?? $default_close), 0, 5);
                            ?>
                            <tr>
                                <td>
                                    <?php echo esc_html($label); ?>
                                    <input type="hidden" name="opening_hours_by_day[<?php echo (int) $day; ?>][day_of_week]" value="<?php echo (int) $day; ?>">
                                </td>
                                <td>
                                    <input type="hidden" name="opening_hours_by_day[<?php echo (int) $day; ?>][use_venue_hours]" value="0">
                                    <input type="checkbox" name="opening_hours_by_day[<?php echo (int) $day; ?>][use_venue_hours]" value="1" <?php checked($use_venue, 1); ?>>
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

            <label class="myvh-account-field myvh-room-toggle">
                <input type="checkbox" name="allow-multi-day-bookings" value="1" <?php checked(!empty($room['AllowMultiDayBookings'])); ?>>
                <span class="myvh-room-toggle-copy">Allow multi-day bookings</span>
            </label>

            <label class="myvh-account-field myvh-room-toggle">
                <input type="checkbox" name="calc-closed-hours" value="1" <?php checked(!empty($room['CalcClosedHours'])); ?>>
                <span class="myvh-room-toggle-copy">Include closed hours when calculating booking duration</span>
            </label>

            <label class="myvh-account-field myvh-room-toggle">
                <input type="checkbox" name="is-public" value="1" <?php checked(!empty($room['IsPublic'])); ?>>
                <span class="myvh-room-toggle-copy">Make this room public</span>
            </label>

            <div class="myvh-account-field">
                <span>Deposits</span>

                <label class="myvh-room-toggle myvh-room-toggle--nested">
                    <input type="hidden" name="deposit_enabled" value="0">
                    <input type="checkbox" name="deposit_enabled" value="1" <?php checked(!empty($deposit_config['enabled'])); ?>>
                    <span class="myvh-room-toggle-copy">Enable deposits for this room</span>
                </label>

                <label style="margin-top:10px; display:block;">
                    <span>Days of week</span>
                    <select name="deposit_days[]" multiple size="7">
                        <option value="mon" <?php selected(in_array('mon', $deposit_days, true), true); ?>>Monday</option>
                        <option value="tue" <?php selected(in_array('tue', $deposit_days, true), true); ?>>Tuesday</option>
                        <option value="wed" <?php selected(in_array('wed', $deposit_days, true), true); ?>>Wednesday</option>
                        <option value="thu" <?php selected(in_array('thu', $deposit_days, true), true); ?>>Thursday</option>
                        <option value="fri" <?php selected(in_array('fri', $deposit_days, true), true); ?>>Friday</option>
                        <option value="sat" <?php selected(in_array('sat', $deposit_days, true), true); ?>>Saturday</option>
                        <option value="sun" <?php selected(in_array('sun', $deposit_days, true), true); ?>>Sunday</option>
                    </select>
                    <small class="myvh-muted">Leave empty to apply every day.</small>
                </label>

                <div class="myvh-account-grid" style="margin-top:10px;">
                    <label class="myvh-account-field">
                        <span>Booking ends after</span>
                        <input type="time" name="deposit_end_after" value="<?php echo esc_attr($deposit_end_after); ?>">
                    </label>

                    <label class="myvh-account-field">
                        <span>Deposit amount</span>
                        <input type="number" name="deposit_amount" min="0" step="0.01" value="<?php echo esc_attr((string) $deposit_amount); ?>">
                    </label>
                </div>

                <label style="margin-top:10px; display:block;">
                    <span>Deposit action</span>
                    <select name="deposit_action">
                        <option value="auto_add" <?php selected($deposit_action, 'auto_add'); ?>>Auto add to invoice</option>
                        <option value="require_review" <?php selected($deposit_action, 'require_review'); ?>>Require admin review</option>
                    </select>
                </label>
            </div>

            <div class="myvh-account-actions">
                <button type="submit" class="button button-primary">Update Room</button>
                <a href="#room-rate-add?room_id=<?php echo (int) ($room['Id'] ?? 0); ?>" class="button">Manage Rates</a>
                <a href="#rooms" class="button">Cancel</a>
                <div id="myvh-room-edit-message" class="myvh-muted" aria-live="polite"></div>
            </div>
        </form>
    </div>
</div>
