<?php
if (!defined('ABSPATH')) exit;

$rates = isset($rates) && is_array($rates) ? $rates : [];
$rooms = isset($rooms) && is_array($rooms) ? $rooms : [];
$organisation_types = isset($organisation_types) && is_array($organisation_types) ? $organisation_types : [];

$room_names = [];
foreach ($rooms as $room) {
    $room_names[(int) ($room['Id'] ?? 0)] = $room['Name'] ?? '';
}

$org_type_names = [];
foreach ($organisation_types as $org_type) {
    $org_type_names[(int) ($org_type['Id'] ?? 0)] = $org_type['Name'] ?? '';
}

$charge_type_labels = [
    'per_hour' => 'Per Hour',
    'per_day' => 'Per Day',
    'fixed' => 'Fixed',
];

$day_labels = [
    0 => 'Sun',
    1 => 'Mon',
    2 => 'Tue',
    3 => 'Wed',
    4 => 'Thu',
    5 => 'Fri',
    6 => 'Sat',
];
?>

<div class="myvh-dashboard-section myvh-room-rates-page">
    <div class="myvh-account-header" style="display:flex; align-items:end; justify-content:space-between; gap:24px;">
        <div>
            <h2>Room Rates</h2>
            <p>Manage room pricing for this client site.</p>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap; justify-content:flex-end;">
            <a href="#room-rate-tester" class="myvh-portal-add-btn">
                <span>Test Rate Schedule</span>
            </a>
            <a href="#room-rate-add" class="myvh-portal-add-btn">
                <span class="myvh-portal-add-btn__icon" aria-hidden="true">+</span>
                <span>Add Room Rate</span>
            </a>
        </div>
    </div>

    <div class="myvh-card myvh-account-card">
        <div class="myvh-account-card-head">
            <h3>All Room Rates</h3>
            <span><?php echo count($rates); ?> rate records</span>
        </div>

        <?php if (empty($rates)): ?>
            <p>No room rates found for this site.</p>
            <p>
                <a href="#room-rate-add" class="myvh-portal-add-btn">
                    <span class="myvh-portal-add-btn__icon" aria-hidden="true">+</span>
                    <span>Create First Room Rate</span>
                </a>
            </p>
        <?php else: ?>
            <table class="myvh-customer-list-table">
                <thead>
                    <tr>
                        <th style="padding-right:24px;">Rate Name</th>
                        <th style="padding-right:24px;">Room</th>
                        <th style="padding-right:24px;">Amount</th>
                        <th style="padding-right:24px;">Schedule</th>
                        <th style="padding-right:24px;">Priority</th>
                        <th style="padding-right:24px;">Type</th>
                        <th style="padding-right:24px;">Organisation Type</th>
                        <th style="padding-right:24px;">Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rates as $rate): ?>
                    <?php
                    $rate_id = (int) ($rate['Id'] ?? 0);
                    $room_id = (int) ($rate['RoomId'] ?? 0);
                    $org_type_id = (int) ($rate['OrganisationTypeId'] ?? 0);
                    $day_of_week = isset($rate['DayOfWeek']) && $rate['DayOfWeek'] !== '' ? (int) $rate['DayOfWeek'] : null;
                    $window_start = trim((string) ($rate['StartTime'] ?? ''));
                    $window_end = trim((string) ($rate['EndTime'] ?? ''));
                    $day_label = $day_of_week !== null ? ($day_labels[$day_of_week] ?? 'Unknown day') : 'All days';
                    $window_label = ($window_start !== '' && $window_end !== '')
                        ? (substr($window_start, 0, 5) . ' - ' . substr($window_end, 0, 5))
                        : 'All times';
                    $delete_message_id = 'myvh-room-rate-message-' . $rate_id;
                    ?>
                    <tr>
                        <td style="padding-right:24px;">
                            <strong><?php echo esc_html($rate['Name'] ?? ''); ?></strong>
                            <?php if (!empty($rate['Description'])): ?>
                                <br><small style="color:#7a7166;"><?php echo esc_html($rate['Description']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td style="padding-right:24px;"><?php echo esc_html($room_names[$room_id] ?? 'Unknown room'); ?></td>
                        <td style="padding-right:24px;">£<?php echo number_format((float) ($rate['Rate'] ?? 0), 2); ?></td>
                        <td style="padding-right:24px;"><?php echo esc_html($day_label . ', ' . $window_label); ?></td>
                        <td style="padding-right:24px;"><?php echo esc_html((string) ($rate['Priority'] ?? '0')); ?></td>
                        <td style="padding-right:24px;"><?php echo esc_html($charge_type_labels[$rate['ChargeType'] ?? ''] ?? ($rate['ChargeType'] ?? '')); ?></td>
                        <td style="padding-right:24px;"><?php echo esc_html($org_type_id > 0 ? ($org_type_names[$org_type_id] ?? 'Unknown type') : 'All Types'); ?></td>
                        <td style="padding-right:24px;"><?php echo !empty($rate['IsActive']) ? 'Active' : 'Inactive'; ?></td>
                        <td style="white-space:nowrap;">
                            <a href="#room-rate-edit?id=<?php echo $rate_id; ?>" class="myvh-action-icon" aria-label="Edit room rate" title="Edit room rate" style="margin-right:10px; vertical-align:middle;">✎</a>
                            <form class="myvh-inline-form" style="display:inline;" data-portal-action="myvh_portal_delete_room_rate" data-message-target="<?php echo esc_attr($delete_message_id); ?>" data-reload-page="room-rates" data-confirm="Delete this room rate? This cannot be undone.">
                                <button type="submit" class="myvh-action-icon myvh-action-danger" aria-label="Delete room rate" title="Delete room rate" style="background:none; border:none; padding:0; margin:0; vertical-align:middle; cursor:pointer;">🗑</button>
                                <input type="hidden" name="rate_id" value="<?php echo $rate_id; ?>">
                            </form>
                            <div id="<?php echo esc_attr($delete_message_id); ?>" class="myvh-muted" aria-live="polite"></div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
