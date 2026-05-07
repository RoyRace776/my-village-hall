<?php
if (!defined('ABSPATH')) exit;

use MYVH\Rooms\RoomColour;

$rooms = isset($rooms) && is_array($rooms) ? $rooms : [];
?>
<div class="myvh-dashboard-section myvh-rooms-page">
    <div class="myvh-account-header" style="display:flex; align-items:end; justify-content:space-between; gap:24px;">
        <div>
            <h2>Rooms</h2>
            <p>Manage room details and room colours for this client site.</p>
        </div>
        <a href="#room-add" class="myvh-portal-add-btn">
            <span class="myvh-portal-add-btn__icon" aria-hidden="true">+</span>
            <span>Add Room</span>
        </a>
    </div>
    <div class="myvh-card myvh-account-card">
        <div class="myvh-account-card-head">
            <h3>All Rooms</h3>
            <span><?php echo count($rooms); ?> room records</span>
        </div>
        <?php if (empty($rooms)): ?>
            <p>No rooms found for this site.</p>
        <?php else: ?>
            <table class="myvh-customer-list-table">
                <thead>
                    <tr>
                        <th style="padding-right:24px;">Room</th>
                        <th style="padding-right:24px;">Venue</th>
                        <th style="padding-right:24px;">Colour</th>
                        <th style="padding-right:24px;">Hours</th>
                        <th style="min-width:90px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rooms as $item): ?>
                    <?php $room_colour = RoomColour::resolve($item['Colour'] ?? '', intval($item['Id'] ?? 0)); ?>
                    <tr>
                        <td style="padding-right:24px;">
                            <strong><?php echo esc_html($item['Name'] ?? ''); ?></strong>
                            <?php if (empty($item['IsPublic'])): ?>
                                <span style="display:inline-block; margin-left:8px; padding:2px 6px; background:#f5e6d3; color:#7a5c3a; border-radius:3px; font-size:11px; font-weight:500;">🔒 PRIVATE</span>
                            <?php endif; ?>
                            <?php if (!empty($item['Description'])): ?>
                                <br><small style="color:#7a7166;"><?php echo esc_html($item['Description']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td style="padding-right:24px;"><?php echo esc_html($item['VenueName'] ?? ''); ?></td>
                        <td style="padding-right:24px; white-space:nowrap;">
                            <span style="display:inline-block; width:18px; height:18px; border-radius:4px; border:1px solid #cfc4b6; background:<?php echo esc_attr($room_colour); ?>; vertical-align:middle; margin-right:8px;"></span>
                            <?php echo esc_html($room_colour); ?>
                        </td>
                        <td style="padding-right:24px;">
                            <?php echo esc_html(substr((string)($item['OpeningTime'] ?? ''), 0, 5)); ?> - <?php echo esc_html(substr((string)($item['ClosingTime'] ?? ''), 0, 5)); ?>
                        </td>
                        <td style="white-space:nowrap;">
                            <a href="#room-edit?id=<?php echo (int)($item['Id'] ?? 0); ?>" class="myvh-action-icon" aria-label="Edit room" title="Edit room" style="margin-right:10px; vertical-align:middle;">✎</a>
                            <a href="#room-rate-add?room_id=<?php echo (int)($item['Id'] ?? 0); ?>" class="myvh-action-icon" aria-label="Manage rates" title="Manage rates" style="margin-right:10px; vertical-align:middle;">£</a>
                            <form class="myvh-inline-form" style="display:inline;" data-portal-action="myvh_portal_delete_room" data-message-target="myvh-room-message-<?php echo (int)($item['Id'] ?? 0); ?>" data-reload-page="rooms" data-confirm="Delete this room? This cannot be undone.">
                                <button type="submit" class="myvh-action-icon myvh-action-danger" aria-label="Delete room" title="Delete room" style="background:none; border:none; padding:0; margin:0; vertical-align:middle; cursor:pointer;">🗑</button>
                                <input type="hidden" name="room_id" value="<?php echo (int)($item['Id'] ?? 0); ?>">
                            </form>
                            <div id="myvh-room-message-<?php echo (int)($item['Id'] ?? 0); ?>" class="myvh-muted" aria-live="polite"></div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
