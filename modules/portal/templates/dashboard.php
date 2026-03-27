<?php
if (!defined('ABSPATH')) exit;

$current_user = wp_get_current_user();
$customer = $customer ?? null;
$is_client_admin = !empty($is_client_admin);

$groups = array_values($groups ?? []);
$now_ts = wp_timestamp();
$min_notice_hours = max(0, intval(myvh_setting('booking.general.min_notice_hours', 24)));

$can_delete_booking = static function(array $booking) use ($now_ts, $min_notice_hours): bool {
  $status = strtolower((string)($booking['Status'] ?? ''));
  $start_ts = strtotime((string)($booking['StartDate'] ?? '') . ' ' . (string)($booking['StartTime'] ?? ''));

  if (!$start_ts || $start_ts <= $now_ts) {
    return false;
  }

  if ($status === 'pending') {
    return true;
  }

  if ($status === 'confirmed') {
    return $start_ts > ($now_ts + ($min_notice_hours * HOUR_IN_SECONDS));
  }

  return false;
};

usort($groups, function ($a, $b) {
  $next_timestamp = function ($group) {
    $members = $group['bookings'] ?? [];

    usort($members, function ($x, $y) {
      return strcmp(
        ($x['StartDate'] ?? '') . ' ' . ($x['StartTime'] ?? ''),
        ($y['StartDate'] ?? '') . ' ' . ($y['StartTime'] ?? '')
      );
    });

    foreach ($members as $member) {
      if (($member['StartDate'] ?? '') >= date('Y-m-d') && ($member['Status'] ?? '') !== 'cancelled') {
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

<div class="myvh-dashboard">

  <div class="myvh-header">
    <h1 class="greeting">Welcome, <em><?= esc_html($current_user->display_name) ?></em></h1>
    <span class="tagline"><?php echo $is_client_admin ? esc_html(get_bloginfo('name') . ' - Client Admin Portal') : 'My Village Hall - Member Portal'; ?></span>
  </div>

  <div class="myvh-dashboard-columns">

    <div class="dashboard-left myvh-surface-panel">
      <h2 class="section-title"><?php echo $is_client_admin ? 'Client Bookings' : 'Upcoming Bookings'; ?></h2>

      <?php if (!$is_client_admin && empty($customer['Id'])): ?>
        <p class="no-bookings">No customer profile is linked to this account yet.</p>
      <?php elseif (!empty($groups)): ?>
        <div class="myvh-bookings-list dashboard-style">
          <?php $last_group_year = null; ?>
          <?php
            $today = date('Y-m-d');
            foreach ($groups as $group):
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

                $count = count($members);
                $rep = $members[0];
                $upcoming = null;
                foreach ($members as $mb) {
                  if ($mb['StartDate'] >= $today && $mb['Status'] !== 'cancelled') {
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
                $schedule = Recurring_Pattern_Service::describe($pattern);
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
                    <?php foreach ($members as $b): ?>
                      <?php $is_past = $b['StartDate'] < $today; ?>
                      <?php $status_class = 'is-' . sanitize_html_class($b['Status']); ?>
                      <?php $can_delete = $can_delete_booking($b); ?>
                      <?php $child_year = date('Y', strtotime($b['StartDate'])); ?>
                      <?php if ($show_child_year_dividers && $last_child_year !== null && $child_year !== $last_child_year): ?>
                        <div class="myvh-year-divider myvh-year-divider-child"><span><?php echo esc_html($child_year); ?></span></div>
                      <?php endif; ?>
                      <?php $last_child_year = $child_year; ?>
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
                <?php
              else:
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
                <?php
              endif;
            endforeach;
          ?>
        </div>

      <?php else: ?>
        <p class="no-bookings"><?php echo $is_client_admin ? 'No bookings found for this client.' : 'You have no upcoming bookings.'; ?></p>
      <?php endif; ?>
    </div>

    <div class="dashboard-right myvh-surface-panel">
      <h2 class="section-title">Quick Actions</h2>
      <div class="action-cards">

        <div class="dashboard-card">
          <a href="#bookings">
            <span class="card-icon">📅</span>
            <?php echo $is_client_admin ? 'View Bookings' : 'Book a Room'; ?>
          </a>
        </div>

        <div class="dashboard-card">
          <a href="#calendar">
            <span class="card-icon">🗓</span>
            View Calendar
          </a>
        </div>

        <div class="dashboard-card">
          <a href="#bookings">
            <span class="card-icon">📋</span>
            <?php echo $is_client_admin ? 'All Bookings' : 'My Bookings'; ?>
          </a>
        </div>

        <?php if (!empty($customer['Id'])): ?>
          <div class="dashboard-card">
            <a href="#organisations">
              <span class="card-icon">👥</span>
              Organisations
            </a>
          </div>
        <?php endif; ?>

        <?php if ($is_client_admin): ?>
          <div class="dashboard-card">
            <a href="#client-admins">
              <span class="card-icon">🛡</span>
              Client Admins
            </a>
          </div>
          <div class="dashboard-card">
            <a href="#customers">
              <span class="card-icon">👤</span>
              Customers
            </a>
          </div>
          <div class="dashboard-card">
            <a href="#settings">
              <span class="card-icon">⚙</span>
              Settings
            </a>
          </div>
        <?php endif; ?>

      </div>
    </div>

  </div>

  <div class="dashboard-notices myvh-surface-panel">
    <h2 class="section-title">Hall Notices</h2>
    <ul class="notices-list">
      <li>Kitchen refurbishment starting next month.</li>
      <li>AGM scheduled for April 23rd.</li>
    </ul>
  </div>

</div>
