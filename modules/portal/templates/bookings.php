<?php
if (!defined('ABSPATH')) exit;

if (!is_user_logged_in()) {
    echo '<p>Please log in to view bookings.</p>';
    return;
}

global $myvh_container;

$booking_service  = $myvh_container->get(MYVH_Booking_Service::class);
$customer_service = $myvh_container->get(MYVH_Customer_Service::class);

// Map WP user → customer
$current_user_id = get_current_user_id();
$customer = $customer_service->get_by_user_id($current_user_id);

if (!$customer) {
    echo '<p>No customer profile found.</p>';
    return;
}

// 👇 IMPORTANT: reuse your grouping logic
$result = $booking_service->get_booking_list([
    'customer_id' => $customer['Id'],
]);

$groups = $result['groups'];
$today  = date('Y-m-d');

$groups = array_values($groups);

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
        <h2>My Bookings</h2>

        <a href="<?php echo site_url('/dashboard/?tab=new-booking'); ?>"
           class="myvh-button myvh-button-primary">
            ➕ New Booking
        </a>
    </div>

    <div class="myvh-surface-panel myvh-bookings-panel">

    <?php if (empty($groups)): ?>

        <div class="myvh-card">
            <p>No bookings yet.</p>
        </div>

    <?php else: ?>

        <div class="myvh-bookings-list">

            <?php $last_group_year = null; ?>

            <?php foreach ($groups as $group_key => $group):

                $is_recurring = ($group['type'] === 'recurring');

                if ($is_recurring):

                    $pattern   = $group['pattern'];
                    $members   = $group['bookings'];

                    usort($members, function ($a, $b) {
                        return strcmp(
                            ($a['StartDate'] ?? '') . ' ' . ($a['StartTime'] ?? ''),
                            ($b['StartDate'] ?? '') . ' ' . ($b['StartTime'] ?? '')
                        );
                    });

                    $count     = count($members);
                    $rep       = $members[0];

                    // Find next upcoming
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

                    $schedule = MYVH_Recurring_Pattern_Service::describe($pattern);
                    $group_id = 'rg_' . $pattern['Id'];
                    ?>

                    <!-- 🔄 GROUP CARD -->
                    <div class="myvh-booking-group">

                        <div class="myvh-group-header" data-group="<?php echo esc_attr($group_id); ?>">

                            <div class="myvh-group-toggle">
                                ▶
                            </div>

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

                        <!-- CHILD BOOKINGS -->
                        <div class="myvh-group-children" data-group="<?php echo esc_attr($group_id); ?>">

                            <?php $last_child_year = null; ?>
                            <?php $child_years = array_unique(array_map(fn($m) => date('Y', strtotime($m['StartDate'])), $members)); ?>
                            <?php $show_child_year_dividers = count($child_years) > 1; ?>

                            <?php foreach ($members as $b):

                                $is_past = $b['StartDate'] < $today;
                                $status_class = 'is-' . sanitize_html_class($b['Status']);
                                $child_year = date('Y', strtotime($b['StartDate']));

                                if ($show_child_year_dividers && $last_child_year !== null && $child_year !== $last_child_year):
                                    ?>
                                    <div class="myvh-year-divider myvh-year-divider-child"><span><?php echo esc_html($child_year); ?></span></div>
                                <?php endif;

                                $last_child_year = $child_year;
                                ?>

                                <div class="myvh-booking-card myvh-child <?php echo $is_past ? 'is-past' : ''; ?>">

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
                                                <?php if ($b['Description']): ?>
                                                    - <?php echo esc_html($b['Description']); ?>
                                                <?php endif; ?>
                                            </strong>
                                        </div>

                                        <div class="myvh-booking-status">
                                            <span class="myvh-status-chip <?php echo esc_attr($status_class); ?>">
                                                <?php echo esc_html(ucfirst($b['Status'])); ?>
                                            </span>
                                        </div>

                                    </div>

                                </div>

                            <?php endforeach; ?>

                        </div>

                    </div>

                <?php else:

                    $b       = $group['bookings'][0];
                    $is_past = $b['StartDate'] < $today;
                    $status_class = 'is-' . sanitize_html_class($b['Status']);
                    $group_year = date('Y', strtotime($b['StartDate']));

                    if ($group_year !== $last_group_year):
                        $last_group_year = $group_year;
                        ?>
                        <div class="myvh-year-divider"><span><?php echo esc_html($group_year); ?></span></div>
                    <?php endif; ?>

                    <!-- SINGLE BOOKING -->
                    <div class="myvh-booking-card <?php echo $is_past ? 'is-past' : ''; ?>">

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
                                    <?php if ($b['Description']): ?>
                                        - <?php echo esc_html($b['Description']); ?>
                                    <?php endif; ?>
                                </strong>
                            </div>

                            <div class="myvh-booking-status">
                                <span class="myvh-status-chip <?php echo esc_attr($status_class); ?>">
                                    <?php echo esc_html(ucfirst($b['Status'])); ?>
                                </span>
                            </div>

                        </div>

                    </div>

                <?php endif; ?>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

    </div>

</div>