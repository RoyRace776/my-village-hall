<?php
if (!defined('ABSPATH')) exit;

use MYVH\Bookings\RecurringPatternService;

$current_user = wp_get_current_user();
$customer = $customer ?? null;
$is_client_admin = !empty($is_client_admin);

$groups = array_values($groups ?? []);
$group_count = count($groups);
$can_delete_booking = $can_delete_booking ?? static function(array $booking): bool {
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

<div class="myvh-dashboard-section myvh-portal-dashboard-page">

  <div class="myvh-account-header">
    <div>
      <h2>Welcome, <?php echo esc_html($current_user->display_name); ?></h2>
      <p><?php echo $is_client_admin ? esc_html(get_bloginfo('name') . ' - Client Admin Portal') : 'My Village Hall - Member Portal'; ?></p>
    </div>
    <a href="#bookings" class="myvh-portal-add-btn myvh-portal-nav-btn">
      <span class="myvh-portal-add-btn__icon" aria-hidden="true">→</span>
      <span><?php echo $is_client_admin ? 'View Bookings' : 'My Bookings'; ?></span>
    </a>
  </div>

  <div class="myvh-portal-dashboard-grid">

    <div class="myvh-card myvh-account-card myvh-portal-dashboard-main-card">
      <div class="myvh-account-card-head">
        <div>
          <h3><?php echo $is_client_admin ? 'Client Bookings' : 'Upcoming Bookings'; ?></h3>
          <span><?php echo esc_html((string) $group_count); ?> <?php echo 1 === $group_count ? 'booking group' : 'booking groups'; ?></span>
        </div>
      </div>

      <?php if (!$is_client_admin && empty($customer['Id'])): ?>
        <div class="myvh-empty-state myvh-portal-dashboard-empty-state">
          <p class="myvh-portal-dashboard-empty-state__title">No customer profile is linked to this account yet.</p>
          <p>Your dashboard will start showing bookings as soon as this account is linked to a customer record.</p>
        </div>
      <?php elseif (!empty($groups)): ?>
        <div class="myvh-bookings-list myvh-portal-dashboard-bookings-list">
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
                      <?php if (!empty($rep['Description'])): ?>
                        - <?php echo esc_html($rep['Description']); ?>
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
        <div class="myvh-empty-state myvh-portal-dashboard-empty-state">
          <p class="myvh-portal-dashboard-empty-state__title"><?php echo $is_client_admin ? 'No bookings found for this client.' : 'You have no upcoming bookings.'; ?></p>
          <p><?php echo $is_client_admin ? 'New bookings will appear here once this site has active reservations.' : 'Your upcoming bookings will appear here once a room has been reserved.'; ?></p>
        </div>
      <?php endif; ?>
    </div>

    <div class="myvh-portal-dashboard-side">
      <div class="myvh-card myvh-account-card myvh-portal-dashboard-side-card">
        <div class="myvh-account-card-head">
          <div>
            <h3>Quick Actions</h3>
            <span>Common portal shortcuts</span>
          </div>
        </div>
        <div class="myvh-portal-quick-actions">

          <a class="myvh-portal-quick-action" href="#bookings">
            <span class="myvh-portal-quick-action__icon" aria-hidden="true">📅</span>
            <span class="myvh-portal-quick-action__text"><?php echo $is_client_admin ? 'View Bookings' : 'Book a Room'; ?></span>
          </a>

          <a class="myvh-portal-quick-action" href="#calendar">
            <span class="myvh-portal-quick-action__icon" aria-hidden="true">🗓</span>
            <span class="myvh-portal-quick-action__text">View Calendar</span>
          </a>

          <a class="myvh-portal-quick-action" href="#bookings">
            <span class="myvh-portal-quick-action__icon" aria-hidden="true">📋</span>
            <span class="myvh-portal-quick-action__text"><?php echo $is_client_admin ? 'All Bookings' : 'My Bookings'; ?></span>
          </a>

          <?php if (!empty($customer['Id']) || $is_client_admin): ?>
            <a class="myvh-portal-quick-action" href="#organisations">
              <span class="myvh-portal-quick-action__icon" aria-hidden="true">👥</span>
              <span class="myvh-portal-quick-action__text">Organisations</span>
            </a>
          <?php endif; ?>

          <?php if ($is_client_admin): ?>
            <a class="myvh-portal-quick-action" href="#client-admins">
              <span class="myvh-portal-quick-action__icon" aria-hidden="true">🛡</span>
              <span class="myvh-portal-quick-action__text">Client Admins</span>
            </a>
            <a class="myvh-portal-quick-action" href="#customers">
              <span class="myvh-portal-quick-action__icon" aria-hidden="true">👤</span>
              <span class="myvh-portal-quick-action__text">Customers</span>
            </a>
            <a class="myvh-portal-quick-action" href="#settings">
              <span class="myvh-portal-quick-action__icon" aria-hidden="true">⚙</span>
              <span class="myvh-portal-quick-action__text">Settings</span>
            </a>
          <?php endif; ?>

        </div>
      </div>

      <div class="myvh-card myvh-account-card myvh-portal-dashboard-side-card">
        <div class="myvh-account-card-head">
          <div>
            <h3>Hall Notices</h3>
            <span>Latest site updates</span>
          </div>
        </div>
        <ul class="myvh-portal-notices-list">
          <li>Kitchen refurbishment starting next month.</li>
          <li>AGM scheduled for April 23rd.</li>
        </ul>
      </div>
    </div>

  </div>

</div>
