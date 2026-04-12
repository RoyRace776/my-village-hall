<?php
if (!defined('ABSPATH')) exit;

use MYVH\Bookings\BookingStatus;
use MYVH\Bookings\RecurringPatternService;

$is_client_admin = !empty($is_client_admin);
$customer = $customer ?? null;
$groups = array_values($groups ?? []);
$today  = date('Y-m-d');
$group_count = count($groups);
$portal_bookings_date_format = (string) myvh_setting('general.portal_bookings_date_format', 'd MMM');
$format_booking_date = static function ($date_value) use ($portal_bookings_date_format): string {
    return myvh_format_date_with_pattern($date_value, $portal_bookings_date_format, 'M j');
};
$can_delete_booking = $can_delete_booking ?? static function(array $booking): bool {
    return false;
};

usort($groups, function ($a, $b) use ($today) {
    $next_timestamp = function ($group) use ($today) {
        $members = $group['bookings'] ?? [];

        usort($members, function ($x, $y) {
            return strcmp(
                ($x['StartDate'] ?? '') . ' ' . ($x['StartTime'] ?? ''),
                ($y['StartDate'] ?? '') . ' ' . ($y['StartTime'] ?? '')
            );
        });

        // Find the next upcoming booking (regardless of status) to sort by
        foreach ($members as $member) {
            if (($member['StartDate'] ?? '') >= $today) {
                return strtotime(($member['StartDate'] ?? '') . ' ' . ($member['StartTime'] ?? '00:00:00')) ?: PHP_INT_MAX;
            }
        }

        // If no upcoming bookings, use the first member's date for chronological ordering
        if (!empty($members[0])) {
            return strtotime(($members[0]['StartDate'] ?? '') . ' ' . ($members[0]['StartTime'] ?? '00:00:00')) ?: PHP_INT_MAX;
        }

        return PHP_INT_MAX;
    };

    return $next_timestamp($a) <=> $next_timestamp($b);
});
?>

<div class="myvh-dashboard-section myvh-portal-bookings-page">

    <div class="myvh-account-header">
        <div>
            <h2><?php echo $is_client_admin ? 'Client Bookings' : 'My Bookings'; ?></h2>
            <p><?php echo $is_client_admin ? 'Review, filter, and manage bookings across this client site.' : 'Review, filter, and manage your bookings in one place.'; ?></p>
        </div>

        <?php if ($is_client_admin || !empty($customer['Id'])): ?>
            <a href="#new-booking" class="myvh-portal-add-btn">
                <span class="myvh-portal-add-btn__icon" aria-hidden="true">+</span>
                <span>New Booking</span>
            </a>
        <?php endif; ?>
    </div>

    <div class="myvh-card myvh-account-card myvh-bookings-panel myvh-portal-bookings-card">
        <div class="myvh-account-card-head">
            <div>
                <h3><?php echo $is_client_admin ? 'Booking Timeline' : 'Your Booking Timeline'; ?></h3>
                <span><?php echo esc_html((string) $group_count); ?> <?php echo 1 === $group_count ? 'booking group' : 'booking groups'; ?></span>
            </div>
        </div>

    <?php if (!$is_client_admin && empty($customer['Id'])): ?>

        <div class="myvh-empty-state myvh-portal-bookings-empty-state">
            <p class="myvh-portal-bookings-empty-state__title">No customer profile is linked to this account yet.</p>
            <p>Your bookings will appear here once your account is linked to a customer record.</p>
        </div>

    <?php elseif (empty($groups)): ?>

        <div class="myvh-empty-state myvh-portal-bookings-empty-state">
            <p class="myvh-portal-bookings-empty-state__title"><?php echo $is_client_admin ? 'No bookings found for this client.' : 'No bookings yet.'; ?></p>
            <p><?php echo $is_client_admin ? 'Bookings will appear here once this site has upcoming or historic reservations.' : 'Create a booking and it will appear here with its status and actions.'; ?></p>
        </div>

    <?php else: ?>


        <!-- Client-side status filter checkboxes -->
        <div class="myvh-bookings-filter-panel">
            <div class="myvh-bookings-filter-panel__head">
                <strong><?php _e('Show statuses', 'my-village-hall'); ?></strong>
                <span>Use the filters below to focus on the booking states you want to review.</span>
            </div>
            <div class="myvh-bookings-status-checkboxes">
                <label class="myvh-checkbox-label"><input type="checkbox" class="myvh-status-filter" value="pending" checked> <span><?php _e('Pending', 'my-village-hall'); ?></span></label>
                <label class="myvh-checkbox-label"><input type="checkbox" class="myvh-status-filter" value="confirmed" checked> <span><?php _e('Confirmed', 'my-village-hall'); ?></span></label>
                <label class="myvh-checkbox-label"><input type="checkbox" class="myvh-status-filter" value="cancelled" checked> <span><?php _e('Cancelled', 'my-village-hall'); ?></span></label>
                <label class="myvh-checkbox-label"><input type="checkbox" class="myvh-status-filter" value="completed" checked> <span><?php _e('Completed', 'my-village-hall'); ?></span></label>
            </div>
        </div>

        <div class="myvh-bookings-list">
            <table id="myvh-bookings-table" class="myvh-customer-list-table myvh-portal-bookings-table">
                <thead>
                    <tr>
                        <th>Date &amp; Time</th>
                        <th>Booking</th>
                        <?php if ($is_client_admin): ?>
                            <th>Booked By</th>
                        <?php endif; ?>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($groups as $group):
                        $is_recurring = ($group['type'] === 'recurring');

                        if ($is_recurring):
                            $pattern = $group['pattern'];
                            $members = $group['bookings'];

                            usort($members, function ($a, $b) {
                                return strcmp(
                                    ($a['StartDate'] ?? '') . ' ' . ($a['StartTime'] ?? ''),
                                    ($b['StartDate'] ?? '') . ' ' . ($b['StartTime'] ?? '')
                                );
                            });

                            $count = count($members);
                            $rep = $members[0];
                            $upcoming = null;

                            foreach ($members as $mb) {
                                if (($mb['StartDate'] ?? '') >= $today && ($mb['Status'] ?? '') !== BookingStatus::CANCELLED) {
                                    $upcoming = $mb;
                                    break;
                                }
                            }

                            $summary_booking = $upcoming ?: $rep;
                            $schedule = RecurringPatternService::describe($pattern);
                            $group_id = 'rg_' . $pattern['Id'];
                            $colspan = $is_client_admin ? 5 : 4;
                            ?>

                            <tr class="myvh-booking-group-header" data-group="<?php echo esc_attr($group_id); ?>">
                                <td colspan="<?php echo esc_attr((string) $colspan); ?>">
                                    <div class="myvh-group-header-cell">
                                        <button type="button" class="myvh-group-toggle" data-group="<?php echo esc_attr($group_id); ?>" aria-expanded="false">▶</button>
                                        <div class="myvh-group-main">
                                            <strong>🔄 <?php echo esc_html($schedule); ?></strong>
                                            <small>
                                                <?php if ($summary_booking): ?>
                                                    Next: <?php echo esc_html($format_booking_date($summary_booking['StartDate'] ?? '')); ?>
                                                    <?php echo esc_html(date('H:i', strtotime($summary_booking['StartTime'] ?? '00:00:00'))); ?>-
                                                    <?php echo esc_html(date('H:i', strtotime($summary_booking['EndTime'] ?? '00:00:00'))); ?>
                                                    ·
                                                <?php endif; ?>
                                                <?php echo esc_html((string) $count); ?> bookings
                                                · <?php echo esc_html($rep['RoomName'] ?? 'Room booking'); ?>
                                                <?php if (!empty($rep['Description'])): ?>
                                                    - <?php echo esc_html($rep['Description']); ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    </div>
                                </td>
                            </tr>

                            <?php foreach ($members as $b):
                                $is_past = ($b['StartDate'] ?? '') < $today;
                                $status_class = 'is-' . sanitize_html_class($b['Status'] ?? '');
                                $can_delete = $can_delete_booking($b);
                                ?>
                                <tr class="myvh-bookings-table-row myvh-recurring-child <?php echo $is_past ? 'is-past' : ''; ?>" data-group="<?php echo esc_attr($group_id); ?>" data-status="<?php echo esc_attr($b['Status'] ?? ''); ?>">
                                    <td>
                                        <strong>
                                            <?php echo esc_html($format_booking_date($b['StartDate'] ?? '')); ?>
                                            <?php echo esc_html(date('H:i', strtotime($b['StartTime'] ?? '00:00:00'))); ?>-
                                            <?php echo esc_html(date('H:i', strtotime($b['EndTime'] ?? '00:00:00'))); ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <strong>
                                            <?php echo esc_html($b['RoomName'] ?? 'Room booking'); ?>
                                            <?php if (!empty($b['Description'])): ?>
                                                - <?php echo esc_html($b['Description']); ?>
                                            <?php endif; ?>
                                        </strong>
                                    </td>
                                    <?php if ($is_client_admin): ?>
                                        <td>
                                            <?php if (!empty($b['CustomerName'])): ?>
                                                <?php echo esc_html($b['CustomerName']); ?>
                                                <?php if (!empty($b['OrganisationName'])): ?>
                                                    <br><small><?php echo esc_html($b['OrganisationName']); ?></small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="myvh-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                    <td>
                                        <span class="myvh-status-chip <?php echo esc_attr($status_class); ?>">
                                            <?php echo esc_html(ucfirst((string) ($b['Status'] ?? ''))); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="myvh-booking-actions-inline">
                                            <a class="myvh-action-icon" href="#booking-view?booking_id=<?php echo intval($b['Id'] ?? 0); ?>" aria-label="View booking" title="View booking">👁</a>
                                            <a class="myvh-action-icon" href="#booking-edit?booking_id=<?php echo intval($b['Id'] ?? 0); ?>" aria-label="Edit booking" title="Edit booking">✎</a>
                                            <?php if ($can_delete): ?>
                                                <a class="myvh-action-icon myvh-action-danger" href="#booking-delete?booking_id=<?php echo intval($b['Id'] ?? 0); ?>" aria-label="Delete booking" title="Delete booking">🗑</a>
                                            <?php else: ?>
                                                <span class="myvh-action-icon myvh-action-danger myvh-action-icon-disabled" aria-disabled="true" title="Delete not available">🗑</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                        <?php else:
                            $b = $group['bookings'][0];
                            $is_past = ($b['StartDate'] ?? '') < $today;
                            $status_class = 'is-' . sanitize_html_class($b['Status'] ?? '');
                            $can_delete = $can_delete_booking($b);
                            ?>
                            <tr class="myvh-bookings-table-row <?php echo $is_past ? 'is-past' : ''; ?>" data-status="<?php echo esc_attr($b['Status'] ?? ''); ?>">
                                <td>
                                    <strong>
                                        <?php echo esc_html($format_booking_date($b['StartDate'] ?? '')); ?>
                                        <?php echo esc_html(date('H:i', strtotime($b['StartTime'] ?? '00:00:00'))); ?>-
                                        <?php echo esc_html(date('H:i', strtotime($b['EndTime'] ?? '00:00:00'))); ?>
                                    </strong>
                                </td>
                                <td>
                                    <strong>
                                        <?php echo esc_html($b['RoomName'] ?? 'Room booking'); ?>
                                        <?php if (!empty($b['Description'])): ?>
                                            - <?php echo esc_html($b['Description']); ?>
                                        <?php endif; ?>
                                    </strong>
                                </td>
                                <?php if ($is_client_admin): ?>
                                    <td>
                                        <?php if (!empty($b['CustomerName'])): ?>
                                            <?php echo esc_html($b['CustomerName']); ?>
                                            <?php if (!empty($b['OrganisationName'])): ?>
                                                <br><small><?php echo esc_html($b['OrganisationName']); ?></small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="myvh-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                                <td>
                                    <span class="myvh-status-chip <?php echo esc_attr($status_class); ?>">
                                        <?php echo esc_html(ucfirst((string) ($b['Status'] ?? ''))); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="myvh-booking-actions-inline">
                                        <a class="myvh-action-icon" href="#booking-view?booking_id=<?php echo intval($b['Id'] ?? 0); ?>" aria-label="View booking" title="View booking">👁</a>
                                        <a class="myvh-action-icon" href="#booking-edit?booking_id=<?php echo intval($b['Id'] ?? 0); ?>" aria-label="Edit booking" title="Edit booking">✎</a>
                                        <?php if ($can_delete): ?>
                                            <a class="myvh-action-icon myvh-action-danger" href="#booking-delete?booking_id=<?php echo intval($b['Id'] ?? 0); ?>" aria-label="Delete booking" title="Delete booking">🗑</a>
                                        <?php else: ?>
                                            <span class="myvh-action-icon myvh-action-danger myvh-action-icon-disabled" aria-disabled="true" title="Delete not available">🗑</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>

    </div>

</div>