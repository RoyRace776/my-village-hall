<?php
if (!defined('ABSPATH')) exit;

$rate = isset($rate) && is_array($rate) ? $rate : null;
$rooms = isset($rooms) && is_array($rooms) ? $rooms : [];
$organisation_types = isset($organisation_types) && is_array($organisation_types) ? $organisation_types : [];

if (!$rate) {
    echo '<div class="myvh-card myvh-error"><p>Room rate not found.</p></div>';
    return;
}

$current_room_id = (int) ($rate['RoomId'] ?? 0);
$current_org_type_id = (int) ($rate['OrganisationTypeId'] ?? 0);
$current_days_of_week = isset($rate['DaysOfWeek']) && is_array($rate['DaysOfWeek'])
    ? array_values(array_filter(array_map('intval', $rate['DaysOfWeek']), static fn(int $day): bool => $day >= 0 && $day <= 6))
    : [];
if (empty($current_days_of_week) && isset($rate['DayOfWeek']) && $rate['DayOfWeek'] !== '' && $rate['DayOfWeek'] !== null) {
    $legacy_day = (int) $rate['DayOfWeek'];
    if ($legacy_day >= 0 && $legacy_day <= 6) {
        $current_days_of_week = [$legacy_day];
    }
}
$current_days_lookup = array_fill_keys($current_days_of_week, true);
$current_start_time = trim((string) ($rate['StartTime'] ?? ''));
$current_end_time = trim((string) ($rate['EndTime'] ?? ''));
?>

<div class="myvh-dashboard-section myvh-room-rate-edit-page">
    <div class="myvh-account-header">
        <div>
            <h2>Edit Room Rate</h2>
            <p>Update room pricing and save changes.</p>
        </div>
    </div>

    <div class="myvh-card myvh-account-card">
        <form class="myvh-account-form" data-portal-action="myvh_portal_save_room_rate" data-message-target="myvh-room-rate-edit-message" data-reload-page="room-rates">
            <input type="hidden" name="rate_id" value="<?php echo (int) ($rate['Id'] ?? 0); ?>">

            <label class="myvh-account-field">
                <span>Rate Name</span>
                <input type="text" name="name" required value="<?php echo esc_attr($rate['Name'] ?? ''); ?>">
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
                            <option value="<?php echo (int) ($room['Id'] ?? 0); ?>" <?php selected($current_room_id, (int) ($room['Id'] ?? 0)); ?>>
                                <?php echo esc_html($room['Name'] ?? ''); ?>
                            </option>
                        <?php endforeach; ?>
                        <?php if ($current_venue !== ''): ?></optgroup><?php endif; ?>
                    </select>
                </label>

                <label class="myvh-account-field">
                    <span>Rate Amount</span>
                    <input type="number" name="rate" min="0" step="0.01" required value="<?php echo esc_attr((string) ($rate['Rate'] ?? '0')); ?>">
                </label>
            </div>

            <div class="myvh-account-grid">
                <label class="myvh-account-field">
                    <span>Charge Type</span>
                    <select name="charge_type" required>
                        <option value="per_hour" <?php selected(($rate['ChargeType'] ?? ''), 'per_hour'); ?>>Per Hour</option>
                        <option value="per_day" <?php selected(($rate['ChargeType'] ?? ''), 'per_day'); ?>>Per Day</option>
                        <option value="fixed" <?php selected(($rate['ChargeType'] ?? ''), 'fixed'); ?>>Fixed</option>
                    </select>
                </label>

                <label class="myvh-account-field">
                    <span>Organisation Type</span>
                    <select name="organisation_type_id">
                        <option value="">All Types</option>
                        <?php foreach ($organisation_types as $org_type): ?>
                            <option value="<?php echo (int) ($org_type['Id'] ?? 0); ?>" <?php selected($current_org_type_id, (int) ($org_type['Id'] ?? 0)); ?>><?php echo esc_html($org_type['Name'] ?? ''); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>

            <div class="myvh-account-grid">
                <label class="myvh-account-field">
                    <span>Days of Week</span>
                    <div style="display:flex; flex-direction:column; gap:6px; margin-top:4px;">
                        <label style="display:flex; align-items:center; gap:10px; cursor:pointer;"><input type="checkbox" name="days_of_week[]" value="0" style="flex-shrink:0; width:16px; height:16px;" <?php checked(isset($current_days_lookup[0])); ?>> <span style="min-width:90px;">Sunday</span></label>
                        <label style="display:flex; align-items:center; gap:10px; cursor:pointer;"><input type="checkbox" name="days_of_week[]" value="1" style="flex-shrink:0; width:16px; height:16px;" <?php checked(isset($current_days_lookup[1])); ?>> <span style="min-width:90px;">Monday</span></label>
                        <label style="display:flex; align-items:center; gap:10px; cursor:pointer;"><input type="checkbox" name="days_of_week[]" value="2" style="flex-shrink:0; width:16px; height:16px;" <?php checked(isset($current_days_lookup[2])); ?>> <span style="min-width:90px;">Tuesday</span></label>
                        <label style="display:flex; align-items:center; gap:10px; cursor:pointer;"><input type="checkbox" name="days_of_week[]" value="3" style="flex-shrink:0; width:16px; height:16px;" <?php checked(isset($current_days_lookup[3])); ?>> <span style="min-width:90px;">Wednesday</span></label>
                        <label style="display:flex; align-items:center; gap:10px; cursor:pointer;"><input type="checkbox" name="days_of_week[]" value="4" style="flex-shrink:0; width:16px; height:16px;" <?php checked(isset($current_days_lookup[4])); ?>> <span style="min-width:90px;">Thursday</span></label>
                        <label style="display:flex; align-items:center; gap:10px; cursor:pointer;"><input type="checkbox" name="days_of_week[]" value="5" style="flex-shrink:0; width:16px; height:16px;" <?php checked(isset($current_days_lookup[5])); ?>> <span style="min-width:90px;">Friday</span></label>
                        <label style="display:flex; align-items:center; gap:10px; cursor:pointer;"><input type="checkbox" name="days_of_week[]" value="6" style="flex-shrink:0; width:16px; height:16px;" <?php checked(isset($current_days_lookup[6])); ?>> <span style="min-width:90px;">Saturday</span></label>
                    </div>
                    <small class="myvh-muted">Leave all unchecked to apply this rate every day.</small>
                </label>

                <label class="myvh-account-field">
                    <span>Time Window</span>
                    <div style="display:flex; gap:8px; align-items:center;">
                        <input type="text" name="start_time" data-myvh-picker="time" data-myvh-minute-increment="15" autocomplete="off" placeholder="HH:MM" value="<?php echo esc_attr($current_start_time !== '' ? substr($current_start_time, 0, 5) : ''); ?>" style="flex:1; min-width:0;">
                        <span>to</span>
                        <input type="text" name="end_time" data-myvh-picker="time" data-myvh-minute-increment="15" autocomplete="off" placeholder="HH:MM" value="<?php echo esc_attr($current_end_time !== '' ? substr($current_end_time, 0, 5) : ''); ?>" style="flex:1; min-width:0;">
                    </div>
                    <small class="myvh-muted">Leave both blank to apply all day. Overnight windows are not supported.</small>
                </label>
            </div>

            <label class="myvh-account-field">
                <span>Description</span>
                <textarea name="description" rows="3"><?php echo esc_textarea($rate['Description'] ?? ''); ?></textarea>
            </label>

            <input type="hidden" name="minimum_hours" value="<?php echo esc_attr((string) ($rate['MinimumHours'] ?? '1')); ?>">

            <div class="myvh-account-grid">
                <label class="myvh-account-field">
                    <span>Priority</span>
                    <input type="number" name="priority" min="0" value="<?php echo esc_attr((string) ($rate['Priority'] ?? '0')); ?>">
                </label>
            </div>

            <div class="myvh-account-grid">
                <label class="myvh-account-field">
                    <span>Valid From</span>
                    <input type="text" name="valid_from" data-myvh-picker="date" autocomplete="off" value="<?php echo esc_attr((string) ($rate['ValidFrom'] ?? '')); ?>">
                </label>

                <label class="myvh-account-field">
                    <span>Valid To</span>
                    <input type="text" name="valid_to" data-myvh-picker="date" autocomplete="off" value="<?php echo esc_attr((string) ($rate['ValidTo'] ?? '')); ?>">
                </label>
            </div>

            <label class="myvh-account-field myvh-room-toggle">
                <input type="checkbox" name="is_active" value="1" <?php checked(!empty($rate['IsActive'])); ?>>
                <span class="myvh-room-toggle-copy">Rate is active</span>
            </label>

            <div class="myvh-account-actions">
                <button type="submit" class="myvh-portal-add-btn">
                    <span class="myvh-portal-add-btn__icon" aria-hidden="true">✓</span>
                    <span>Update Room Rate</span>
                </button>
                <a href="#room-rates" class="button">Cancel</a>
                <div id="myvh-room-rate-edit-message" class="myvh-muted" aria-live="polite"></div>
            </div>
        </form>
    </div>
</div>
