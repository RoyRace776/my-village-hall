<?php
if (!defined('ABSPATH')) exit;

use MYVH\Bookings\BookingStatus;
use MYVH\Bookings\RecurringPatternService;

$is_client_admin = !empty($is_client_admin);
$customer = $customer ?? null;
$groups = array_values($groups ?? []);
$today  = date('Y-m-d');
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

        foreach ($members as $member) {
            if (($member['StartDate'] ?? '') >= $today && ($member['Status'] ?? '') !== BookingStatus::CANCELLED) {
                return strtotime(($member['StartDate'] ?? '') . ' ' . ($member['StartTime'] ?? '00:00:00')) ?: PHP_INT_MAX;
            }
        }

        if (!empty($members[0])) {
            return (strtotime(($members[0]['StartDate'] ?? '') . ' ' . ($members[0]['StartTime'] ?? '00:00:00')) ?: PHP_INT_MAX) + 315360000;
        }

        return PHP_INT_MAX;
    };

    return $next_timestamp($a) <=> $next_timestamp($b);
});
?>

<div class="myvh-dashboard-section">

    <div class="myvh-section-header">
        <h2><?php echo $is_client_admin ? 'Client Bookings' : 'My Bookings'; ?></h2>

        <?php if ($is_client_admin || !empty($customer['Id'])): ?>
            <a href="#new-booking" class="myvh-portal-add-btn">
                <span class="myvh-portal-add-btn__icon" aria-hidden="true">+</span>
                <span>New Booking</span>
            </a>
        <?php endif; ?>
    </div>

    <div class="myvh-surface-panel myvh-bookings-panel">

    <?php if (!$is_client_admin && empty($customer['Id'])): ?>

        <div class="myvh-card">
            <p>No customer profile is linked to this account yet.</p>
        </div>

    <?php elseif (empty($groups)): ?>

        <div class="myvh-card">
            <p><?php echo $is_client_admin ? 'No bookings found for this client.' : 'No bookings yet.'; ?></p>
        </div>

    <?php else: ?>


        <!-- Client-side status filter checkboxes -->
        <div class="myvh-bookings-status-checkboxes" style="margin-bottom: 16px;">
            <strong><?php _e('Show statuses:', 'my-village-hall'); ?></strong>
            <label style="margin-right:10px;"><input type="checkbox" class="myvh-status-filter" value="pending" checked> <?php _e('Pending', 'my-village-hall'); ?></label>
            <label style="margin-right:10px;"><input type="checkbox" class="myvh-status-filter" value="confirmed" checked> <?php _e('Confirmed', 'my-village-hall'); ?></label>
            <label style="margin-right:10px;"><input type="checkbox" class="myvh-status-filter" value="cancelled" checked> <?php _e('Cancelled', 'my-village-hall'); ?></label>
            <label style="margin-right:10px;"><input type="checkbox" class="myvh-status-filter" value="completed" checked> <?php _e('Completed', 'my-village-hall'); ?></label>
        </div>

        <div class="myvh-bookings-list">

            <?php $last_group_year = null; ?>

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
                        if ($mb['StartDate'] >= $today && $mb['Status'] !== BookingStatus::CANCELLED) {
                            $upcoming = $mb;
                            break;
                        }
                    }

                    $summary_booking = $upcoming ?: $rep;
                    $group_year = $summary_booking ? date('Y', strtotime($summary_booking['StartDate'])) : null;

                    if ($group_year && $group_year !== $last_group_year):
                        $last_group_year = $group_year;
                        ?>
                        <div class="myvh-year-divider"><span><?php echo esc_html($group_year); ?></span></div>
                    <?php endif; ?>

                    <?php
                    $schedule = RecurringPatternService::describe($pattern);
                    $group_id = 'rg_' . $pattern['Id'];
                    ?>

                    <div class="myvh-booking-group">
                        <div class="myvh-group-header" data-group="<?php echo esc_attr($group_id); ?>">
                            <div class="myvh-group-toggle">▶</div>
                            <div class="myvh-group-main">
                                <strong>
                                    🔄 <?php echo esc_html($schedule); ?>
                                    <?php if ($summary_booking): ?>
                                        · <?php echo date('D d/m', strtotime($summary_booking['StartDate'])); ?>
                                        <?php echo date('H:i', strtotime($summary_booking['StartTime'])); ?>-
                                        <?php echo date('H:i', strtotime($summary_booking['EndTime'])); ?>
                                    <?php endif; ?>
                                    · <?php echo $count; ?> bookings
                                </strong>
                            </div>

                            <div class="myvh-group-room">
                                <?php echo esc_html($rep['RoomName']); ?>
                                <?php if (!empty($summary_booking['Description'])): ?>
                                    - <?php echo esc_html($summary_booking['Description']); ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="myvh-group-children" data-group="<?php echo esc_attr($group_id); ?>">
                            <?php $last_child_year = null; ?>
                            <?php $child_years = array_unique(array_map(fn($m) => date('Y', strtotime($m['StartDate'])), $members)); ?>
                            <?php $show_child_year_dividers = count($child_years) > 1; ?>

                            <?php foreach ($members as $b):
                                $is_past = $b['StartDate'] < $today;
                                $status_class = 'is-' . sanitize_html_class($b['Status']);
                                $child_year = date('Y', strtotime($b['StartDate']));
                                $can_delete = $can_delete_booking($b);

                                if ($show_child_year_dividers && $last_child_year !== null && $child_year !== $last_child_year):
                                    ?>
                                    <div class="myvh-year-divider myvh-year-divider-child"><span><?php echo esc_html($child_year); ?></span></div>
                                <?php endif;

                                $last_child_year = $child_year;
                                ?>

                                <div class="myvh-booking-card myvh-child <?php echo $is_past ? 'is-past' : ''; ?>" data-status="<?php echo esc_attr($b['Status']); ?>">
                                    <div class="myvh-booking-main myvh-booking-main-inline">
                                        <div class="myvh-booking-date">
                                            <strong>
                                                <?php echo date('D d/m', strtotime($b['StartDate'])); ?>
                                                <?php echo date('H:i', strtotime($b['StartTime'])); ?>-
                                                <?php echo date('H:i', strtotime($b['EndTime'])); ?>
                                            </strong>
                                        </div>

                                        <div class="myvh-booking-details">
                                            <strong>
                                                <?php echo esc_html($b['RoomName'] ?? 'Room booking'); ?>
                                                <?php if (!empty($b['Description'])): ?>
                                                    - <?php echo esc_html($b['Description']); ?>
                                                <?php endif; ?>
                                            </strong>
                                            <?php if ($is_client_admin && !empty($b['CustomerName'])): ?>
                                                <small><?php echo esc_html($b['CustomerName']); ?><?php echo !empty($b['OrganisationName']) ? ' · ' . esc_html($b['OrganisationName']) : ''; ?></small>
                                            <?php endif; ?>
                                        </div>

                                        <div class="myvh-booking-status">
                                            <span class="myvh-status-chip <?php echo esc_attr($status_class); ?>">
                                                <?php echo esc_html(ucfirst($b['Status'])); ?>
                                            </span>
                                            <div class="myvh-booking-actions-inline">
                                                <a class="myvh-action-icon" href="#booking-view?booking_id=<?php echo intval($b['Id']); ?>" aria-label="View booking" title="View booking">👁</a>
                                                <a class="myvh-action-icon" href="#booking-edit?booking_id=<?php echo intval($b['Id']); ?>" aria-label="Edit booking" title="Edit booking">✎</a>
                                                <?php if ($can_delete): ?>
                                                    <a class="myvh-action-icon myvh-action-danger" href="#booking-delete?booking_id=<?php echo intval($b['Id']); ?>" aria-label="Delete booking" title="Delete booking">🗑</a>
                                                <?php else: ?>
                                                    <span class="myvh-action-icon myvh-action-danger myvh-action-icon-disabled" aria-disabled="true" title="Delete not available">🗑</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                <?php else:

                    $b = $group['bookings'][0];
                    $is_past = $b['StartDate'] < $today;
                    $status_class = 'is-' . sanitize_html_class($b['Status']);
                    $can_delete = $can_delete_booking($b);
                    $group_year = date('Y', strtotime($b['StartDate']));

                    if ($group_year !== $last_group_year):
                        $last_group_year = $group_year;
                        ?>
                        <div class="myvh-year-divider"><span><?php echo esc_html($group_year); ?></span></div>
                    <?php endif; ?>

                    <div class="myvh-booking-card <?php echo $is_past ? 'is-past' : ''; ?>" data-status="<?php echo esc_attr($b['Status']); ?>">
                        <div class="myvh-booking-main">
                            <div class="myvh-booking-date">
                                <strong>
                                    <?php echo date('D d/m', strtotime($b['StartDate'])); ?>
                                    <?php echo date('H:i', strtotime($b['StartTime'])); ?>-
                                    <?php echo date('H:i', strtotime($b['EndTime'])); ?>
                                </strong>
                            </div>

                            <div class="myvh-booking-details">
                                <strong>
                                    <?php echo esc_html($b['RoomName']); ?>
                                    <?php if (!empty($b['Description'])): ?>
                                        - <?php echo esc_html($b['Description']); ?>
                                    <?php endif; ?>
                                </strong>
                                <?php if ($is_client_admin && !empty($b['CustomerName'])): ?>
                                    <small><?php echo esc_html($b['CustomerName']); ?><?php echo !empty($b['OrganisationName']) ? ' · ' . esc_html($b['OrganisationName']) : ''; ?></small>
                                <?php endif; ?>
                            </div>

                            <div class="myvh-booking-status">
                                <span class="myvh-status-chip <?php echo esc_attr($status_class); ?>">
                                    <?php echo esc_html(ucfirst($b['Status'])); ?>
                                </span>
                                <div class="myvh-booking-actions-inline">
                                    <a class="myvh-action-icon" href="#booking-view?booking_id=<?php echo intval($b['Id']); ?>" aria-label="View booking" title="View booking">👁</a>
                                    <a class="myvh-action-icon" href="#booking-edit?booking_id=<?php echo intval($b['Id']); ?>" aria-label="Edit booking" title="Edit booking">✎</a>
                                    <?php if ($can_delete): ?>
                                        <a class="myvh-action-icon myvh-action-danger" href="#booking-delete?booking_id=<?php echo intval($b['Id']); ?>" aria-label="Delete booking" title="Delete booking">🗑</a>
                                    <?php else: ?>
                                        <span class="myvh-action-icon myvh-action-danger myvh-action-icon-disabled" aria-disabled="true" title="Delete not available">🗑</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php endif; ?>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

    </div>

</div>