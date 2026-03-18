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

$status_colors = [
    BookingStatus::PENDING   => '#2271b1',
    BookingStatus::CONFIRMED => '#46b450',
    BookingStatus::CANCELLED => '#dc3232',
    BookingStatus::COMPLETED => '#777',
];
?>

<div class="myvh-dashboard-section">

    <div class="myvh-section-header">
        <h2>My Bookings</h2>

        <a href="<?php echo site_url('/dashboard/?tab=new-booking'); ?>"
           class="myvh-button myvh-button-primary">
            ➕ New Booking
        </a>
    </div>

    <?php if (empty($groups)): ?>

        <div class="myvh-card">
            <p>No bookings yet.</p>
        </div>

    <?php else: ?>

        <div class="myvh-bookings-list">

            <?php foreach ($groups as $group_key => $group):

                $is_recurring = ($group['type'] === 'recurring');

                if ($is_recurring):

                    $pattern   = $group['pattern'];
                    $members   = $group['bookings'];
                    $count     = count($members);
                    $rep       = $members[0];

                    // Find next upcoming
                    $upcoming = null;
                    foreach (array_reverse($members) as $mb) {
                        if ($mb['StartDate'] >= $today && $mb['Status'] !== BookingStatus::CANCELLED) {
                            $upcoming = $mb;
                            break;
                        }
                    }

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

                                <strong>🔄 <?php echo esc_html($schedule); ?></strong>

                                <div class="myvh-group-meta">
                                    <?php echo $count; ?> bookings

                                    <?php if ($upcoming): ?>
                                        · next: <?php echo date('j M', strtotime($upcoming['StartDate'])); ?>
                                    <?php endif; ?>
                                </div>

                            </div>

                            <div class="myvh-group-room">
                                <?php echo esc_html($rep['RoomName']); ?>
                            </div>

                        </div>

                        <!-- CHILD BOOKINGS -->
                        <div class="myvh-group-children" data-group="<?php echo esc_attr($group_id); ?>">

                            <?php foreach ($members as $b):

                                $is_past = $b['StartDate'] < $today;
                                $sc      = $status_colors[$b['Status']] ?? '#777';
                            ?>

                                <div class="myvh-booking-card myvh-child <?php echo $is_past ? 'is-past' : ''; ?>">

                                    <div class="myvh-booking-main">

                                        <div class="myvh-booking-date">
                                            <?php echo date('D j M Y', strtotime($b['StartDate'])); ?><br>
                                            <small>
                                                <?php echo date('g:i A', strtotime($b['StartTime'])); ?> –
                                                <?php echo date('g:i A', strtotime($b['EndTime'])); ?>
                                            </small>
                                        </div>

                                        <div class="myvh-booking-details">
                                            <?php if ($b['Description']): ?>
                                                <?php echo esc_html($b['Description']); ?>
                                            <?php endif; ?>
                                        </div>

                                        <div class="myvh-booking-status">
                                            <span style="color:<?php echo $sc; ?>;">●</span>
                                            <?php echo esc_html(ucfirst($b['Status'])); ?>
                                        </div>

                                    </div>

                                </div>

                            <?php endforeach; ?>

                        </div>

                    </div>

                <?php else:

                    $b       = $group['bookings'][0];
                    $is_past = $b['StartDate'] < $today;
                    $sc      = $status_colors[$b['Status']] ?? '#777';
                ?>

                    <!-- SINGLE BOOKING -->
                    <div class="myvh-booking-card <?php echo $is_past ? 'is-past' : ''; ?>">

                        <div class="myvh-booking-main">

                            <div class="myvh-booking-date">
                                <strong><?php echo date('D j M Y', strtotime($b['StartDate'])); ?></strong><br>
                                <small>
                                    <?php echo date('g:i A', strtotime($b['StartTime'])); ?> –
                                    <?php echo date('g:i A', strtotime($b['EndTime'])); ?>
                                </small>
                            </div>

                            <div class="myvh-booking-details">
                                <strong><?php echo esc_html($b['RoomName']); ?></strong>
                            </div>

                            <div class="myvh-booking-status">
                                <span style="color:<?php echo $sc; ?>;">●</span>
                                <?php echo esc_html(ucfirst($b['Status'])); ?>
                            </div>

                        </div>

                    </div>

                <?php endif; ?>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</div>