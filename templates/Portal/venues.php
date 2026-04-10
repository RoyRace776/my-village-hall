<?php
if (!defined('ABSPATH')) exit;

$venues = is_array($venues ?? null) ? $venues : [];
$venue_room_counts = is_array($venue_room_counts ?? null) ? $venue_room_counts : [];
?>
<div class="myvh-dashboard-section myvh-venues-page">
    <div class="myvh-account-header" style="display:flex; align-items:end; justify-content:space-between; gap:24px;">
        <div>
            <h2>Venues</h2>
            <p>Manage venue details and opening hours for this client site.</p>
        </div>
        <a href="#venue-add" class="myvh-portal-add-btn">
            <span class="myvh-portal-add-btn__icon" aria-hidden="true">+</span>
            <span>Add Venue</span>
        </a>
    </div>
    <div class="myvh-card myvh-account-card">
        <div class="myvh-account-card-head">
            <h3>All Venues</h3>
            <span><?php echo count($venues); ?> venue records</span>
        </div>
        <?php if (empty($venues)): ?>
            <p>No venues found for this site.</p>
        <?php else: ?>
            <table class="myvh-customer-list-table">
                <thead>
                    <tr>
                        <th style="padding-right:24px;">Venue</th>
                        <th style="padding-right:24px;">Short Name</th>
                        <th style="padding-right:24px;">Rooms</th>
                        <th style="padding-right:24px;">Post Code</th>
                        <th style="padding-right:24px;">Hours</th>
                        <th style="min-width:90px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($venues as $item): ?>
                    <?php
                    $venue_id = (int) ($item['Id'] ?? 0);
                    $room_count = (int) ($venue_room_counts[$venue_id] ?? 0);
                    $can_delete = $room_count === 0;
                    ?>
                    <tr>
                        <td style="padding-right:24px;">
                            <strong><?php echo esc_html($item['Name'] ?? ''); ?></strong>
                            <?php if (!empty($item['AddressLine1'])): ?>
                                <br><small style="color:#7a7166;"><?php echo esc_html($item['AddressLine1']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td style="padding-right:24px;"><?php echo esc_html($item['ShortName'] ?? ''); ?></td>
                        <td style="padding-right:24px;"><?php echo esc_html((string) $room_count); ?></td>
                        <td style="padding-right:24px;"><?php echo esc_html($item['PostCode'] ?? ''); ?></td>
                        <td style="padding-right:24px;">
                            <?php echo esc_html(substr((string)($item['OpeningTime'] ?? ''), 0, 5)); ?> - <?php echo esc_html(substr((string)($item['ClosingTime'] ?? ''), 0, 5)); ?>
                        </td>
                        <td style="white-space:nowrap;">
                            <a href="#venue-edit?id=<?php echo (int)($item['Id'] ?? 0); ?>" class="myvh-action-icon" aria-label="Edit venue" title="Edit venue" style="margin-right:10px; vertical-align:middle;">✎</a>
                            <?php if ($can_delete): ?>
                                <form class="myvh-inline-form" style="display:inline;" data-portal-action="myvh_portal_delete_venue" data-message-target="myvh-venue-message-<?php echo (int)($item['Id'] ?? 0); ?>" data-reload-page="venues" data-confirm="Delete this venue? This cannot be undone.">
                                    <button type="submit" class="myvh-action-icon myvh-action-danger" aria-label="Delete venue" title="Delete venue" style="background:none; border:none; padding:0; margin:0; vertical-align:middle; cursor:pointer;">🗑</button>
                                    <input type="hidden" name="venue_id" value="<?php echo (int)($item['Id'] ?? 0); ?>">
                                </form>
                            <?php else: ?>
                                <span class="myvh-action-icon" aria-label="Delete unavailable while venue has rooms" title="Delete unavailable while venue has rooms" style="margin-right:0; vertical-align:middle; opacity:0.4; cursor:not-allowed;">🗑</span>
                            <?php endif; ?>
                            <div id="myvh-venue-message-<?php echo (int)($item['Id'] ?? 0); ?>" class="myvh-muted" aria-live="polite"></div>
                            <?php if (!$can_delete): ?>
                                <div class="myvh-muted">Delete rooms first.</div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>