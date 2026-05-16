<?php
if (!defined('ABSPATH')) exit;

$rooms = isset($rooms) && is_array($rooms) ? $rooms : [];
$organisation_types = isset($organisation_types) && is_array($organisation_types) ? $organisation_types : [];
$selected_room_id = (int) ($selected_room_id ?? 0);

$selected_room = null;
if ($selected_room_id > 0) {
    foreach ($rooms as $candidate_room) {
        if ((int) ($candidate_room['Id'] ?? 0) === $selected_room_id) {
            $selected_room = $candidate_room;
            break;
        }
    }
}

if (!$selected_room) {
    $selected_room_id = 0;
}
?>

<div class="myvh-dashboard-section myvh-room-rate-add-page">
    <div class="myvh-account-header">
        <div>
            <h2>Add Room Rate</h2>
            <p>Create a pricing rule for room bookings.</p>
        </div>
    </div>

    <div class="myvh-card myvh-account-card">
        <?php if (empty($rooms)): ?>
            <p>You need to create at least one room before adding room rates.</p>
            <p><a href="#room-add" class="myvh-portal-add-btn">
                <span class="myvh-portal-add-btn__icon" aria-hidden="true">+</span>
                <span>Add Room</span>
            </a></p>
        <?php else: ?>
            <?php if ($selected_room): ?>
                <p class="myvh-muted">Adding a rate for <strong><?php echo esc_html($selected_room['Name'] ?? ''); ?></strong>.</p>
            <?php endif; ?>

            <form class="myvh-account-form" data-portal-action="myvh_portal_save_room_rate" data-message-target="myvh-room-rate-create-message" data-reload-page="room-rates">
                <label class="myvh-account-field">
                    <span>Rate Name</span>
                    <input type="text" name="name" required placeholder="e.g., Standard Hourly Rate">
                </label>

                <div class="myvh-account-grid">
                    <label class="myvh-account-field">
                        <span>Room</span>
                        <select name="room_id" required>
                            <option value="">Select room</option>
                            <?php $current_venue = ''; ?>
                            <?php foreach ($rooms as $room): ?>
                                <?php $venue_name = $room['VenueName'] ?? 'Venue'; ?>
                                <?php if ($current_venue !== $venue_name): ?>
                                    <?php if ($current_venue !== ''): ?></optgroup><?php endif; ?>
                                    <optgroup label="<?php echo esc_attr($venue_name); ?>">
                                    <?php $current_venue = $venue_name; ?>
                                <?php endif; ?>
                                <option value="<?php echo (int) ($room['Id'] ?? 0); ?>" <?php selected($selected_room_id, (int) ($room['Id'] ?? 0)); ?>>
                                    <?php echo esc_html($room['Name'] ?? ''); ?>
                                </option>
                            <?php endforeach; ?>
                            <?php if ($current_venue !== ''): ?></optgroup><?php endif; ?>
                        </select>
                    </label>

                    <label class="myvh-account-field">
                        <span>Rate Amount</span>
                        <input type="number" name="rate" min="0" step="0.01" required>
                    </label>
                </div>

                <div class="myvh-account-grid">
                    <label class="myvh-account-field">
                        <span>Charge Type</span>
                        <select name="charge_type" required>
                            <option value="per_hour">Per Hour</option>
                            <option value="per_day">Per Day</option>
                            <option value="fixed">Fixed</option>
                        </select>
                    </label>

                    <label class="myvh-account-field">
                        <span>Organisation Type</span>
                        <select name="organisation_type_id">
                            <option value="">All Types</option>
                            <?php foreach ($organisation_types as $org_type): ?>
                                <option value="<?php echo (int) ($org_type['Id'] ?? 0); ?>"><?php echo esc_html($org_type['Name'] ?? ''); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>

                <div class="myvh-account-grid">
                    <label class="myvh-account-field">
                        <span>Days of Week</span>
                        <div style="display:flex; flex-direction:column; gap:6px; margin-top:4px;">
                            <label style="display:flex; align-items:center; gap:10px; cursor:pointer;"><input type="checkbox" name="days_of_week[]" value="0" style="flex-shrink:0; width:16px; height:16px;"> <span style="min-width:90px;">Sunday</span></label>
                            <label style="display:flex; align-items:center; gap:10px; cursor:pointer;"><input type="checkbox" name="days_of_week[]" value="1" style="flex-shrink:0; width:16px; height:16px;"> <span style="min-width:90px;">Monday</span></label>
                            <label style="display:flex; align-items:center; gap:10px; cursor:pointer;"><input type="checkbox" name="days_of_week[]" value="2" style="flex-shrink:0; width:16px; height:16px;"> <span style="min-width:90px;">Tuesday</span></label>
                            <label style="display:flex; align-items:center; gap:10px; cursor:pointer;"><input type="checkbox" name="days_of_week[]" value="3" style="flex-shrink:0; width:16px; height:16px;"> <span style="min-width:90px;">Wednesday</span></label>
                            <label style="display:flex; align-items:center; gap:10px; cursor:pointer;"><input type="checkbox" name="days_of_week[]" value="4" style="flex-shrink:0; width:16px; height:16px;"> <span style="min-width:90px;">Thursday</span></label>
                            <label style="display:flex; align-items:center; gap:10px; cursor:pointer;"><input type="checkbox" name="days_of_week[]" value="5" style="flex-shrink:0; width:16px; height:16px;"> <span style="min-width:90px;">Friday</span></label>
                            <label style="display:flex; align-items:center; gap:10px; cursor:pointer;"><input type="checkbox" name="days_of_week[]" value="6" style="flex-shrink:0; width:16px; height:16px;"> <span style="min-width:90px;">Saturday</span></label>
                        </div>
                        <small class="myvh-muted">Leave all unchecked to apply this rate every day.</small>
                    </label>

                    <label class="myvh-account-field">
                        <span>Time Window</span>
                        <div style="display:flex; gap:8px; align-items:center;">
                                <input type="text" name="start_time" data-myvh-picker="time" data-myvh-minute-increment="15" autocomplete="off" placeholder="HH:MM" style="flex:1; min-width:0;">
                            <span>to</span>
                                <input type="text" name="end_time" data-myvh-picker="time" data-myvh-minute-increment="15" autocomplete="off" placeholder="HH:MM" style="flex:1; min-width:0;">
                        </div>
                        <small class="myvh-muted">Leave both blank to apply all day. Overnight windows are not supported.</small>
                    </label>
                </div>

                <label class="myvh-account-field">
                    <span>Description</span>
                    <textarea name="description" rows="3"></textarea>
                </label>

                <input type="hidden" name="minimum_hours" value="1">

                <div class="myvh-account-grid">
                    <label class="myvh-account-field">
                        <span>Priority</span>
                        <input type="number" name="priority" min="0" value="0">
                    </label>
                </div>

                <div class="myvh-account-grid">
                    <label class="myvh-account-field">
                        <span>Valid From</span>
                        <input type="text" name="valid_from" data-myvh-picker="date" autocomplete="off">
                    </label>

                    <label class="myvh-account-field">
                        <span>Valid To</span>
                        <input type="text" name="valid_to" data-myvh-picker="date" autocomplete="off">
                    </label>
                </div>

                <label class="myvh-account-field myvh-room-toggle">
                    <input type="checkbox" name="is_active" value="1" checked>
                    <span class="myvh-room-toggle-copy">Rate is active</span>
                </label>

                <div class="myvh-account-actions">
                    <button type="submit" class="myvh-portal-add-btn">
                        <span class="myvh-portal-add-btn__icon" aria-hidden="true">✓</span>
                        <span>Create Room Rate</span>
                    </button>
                    <a href="#room-rates" class="button">Cancel</a>
                    <div id="myvh-room-rate-create-message" class="myvh-muted" aria-live="polite"></div>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>
