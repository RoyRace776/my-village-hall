<?php
if (!defined('ABSPATH')) exit;

$rooms = isset($rooms) && is_array($rooms) ? $rooms : [];
$organisation_types = isset($organisation_types) && is_array($organisation_types) ? $organisation_types : [];
?>

<div class="myvh-dashboard-section myvh-room-rate-tester-page">
    <div class="myvh-account-header" style="display:flex; align-items:end; justify-content:space-between; gap:24px;">
        <div>
            <h2>Rate Tester</h2>
            <p>Test the room-rate schedule for a room and organisation type.</p>
        </div>
        <a href="#room-rates" class="myvh-portal-add-btn">Back to Room Rates</a>
    </div>

    <div class="myvh-card myvh-account-card">
        <?php if (empty($rooms)): ?>
            <p>You need at least one room to test pricing.</p>
            <p><a href="#room-add" class="myvh-portal-add-btn"><span class="myvh-portal-add-btn__icon" aria-hidden="true">+</span><span>Add Room</span></a></p>
        <?php else: ?>
            <form id="myvh-room-rate-tester-form" class="myvh-account-form" data-portal-action="myvh_portal_test_room_rate" data-message-target="myvh-room-rate-tester-message">
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
                                <option value="<?php echo (int) ($room['Id'] ?? 0); ?>"><?php echo esc_html($room['Name'] ?? ''); ?></option>
                            <?php endforeach; ?>
                            <?php if ($current_venue !== ''): ?></optgroup><?php endif; ?>
                        </select>
                    </label>

                    <label class="myvh-account-field">
                        <span>Organisation Type</span>
                        <select name="organisation_type_id">
                            <option value="0">All Types</option>
                            <?php foreach ($organisation_types as $org_type): ?>
                                <option value="<?php echo (int) ($org_type['Id'] ?? 0); ?>"><?php echo esc_html($org_type['Name'] ?? ''); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>

                <div class="myvh-account-grid">
                    <label class="myvh-account-field">
                        <span>Start Date</span>
                        <input type="text" name="start_date" data-myvh-picker="date" autocomplete="off" required>
                    </label>

                    <label class="myvh-account-field">
                        <span>Start Time</span>
                        <input type="text" name="start_time" data-myvh-picker="time" data-myvh-minute-increment="15" autocomplete="off" placeholder="HH:MM" required>
                    </label>
                </div>

                <div class="myvh-account-grid">
                    <label class="myvh-account-field">
                        <span>End Date</span>
                        <input type="text" name="end_date" data-myvh-picker="date" autocomplete="off" required>
                    </label>

                    <label class="myvh-account-field">
                        <span>End Time</span>
                        <input type="text" name="end_time" data-myvh-picker="time" data-myvh-minute-increment="15" autocomplete="off" placeholder="HH:MM" required>
                    </label>
                </div>

                <div class="myvh-account-actions">
                    <button type="submit" class="myvh-portal-add-btn">
                        <span class="myvh-portal-add-btn__icon" aria-hidden="true">✓</span>
                        <span>Run Test</span>
                    </button>
                    <div id="myvh-room-rate-tester-message" class="myvh-muted" aria-live="polite"></div>
                </div>
            </form>

            <div id="myvh-room-rate-tester-output" style="margin-top:16px; display:none;"></div>
        <?php endif; ?>
    </div>
</div>
