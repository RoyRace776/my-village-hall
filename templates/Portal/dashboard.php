<?php
if (!defined('ABSPATH')) exit;

use MYVH\Bookings\RecurringPatternService;

$current_user = wp_get_current_user();
$customer = $customer ?? null;
$is_client_admin = !empty($is_client_admin);

$groups = array_values($groups ?? []);
$group_count = count($groups);
$portal_bookings_date_format = (string) myvh_setting('general.portal_bookings_date_format', 'd MMM');
$format_booking_date = static function ($date_value) use ($portal_bookings_date_format): string {
  return myvh_format_date_with_pattern($date_value, $portal_bookings_date_format, 'M j');
};
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

    $today = date('Y-m-d');

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
        <!-- Client-side filter section -->
        <div class="myvh-bookings-filter-panel">
          <div class="myvh-bookings-filter-expanded-toggle">
              <button type="button" class="myvh-filter-toggle-btn" aria-expanded="false" aria-controls="myvh-dashboard-expanded-filters">
                  <span class="myvh-filter-toggle-icon">▼</span>
                  <span><?php _e('Filters', 'my-village-hall'); ?></span>
              </button>
          </div>

          <div id="myvh-dashboard-expanded-filters" class="myvh-bookings-filter-expanded" hidden>
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

              <div class="myvh-filter-row">
                  <div class="myvh-filter-field">
                      <label for="myvh-dashboard-filter-room"><?php _e('Room:', 'my-village-hall'); ?></label>
                      <select id="myvh-dashboard-filter-room" class="myvh-booking-filter-select" data-filter="room">
                          <option value=""><?php _e('All Rooms', 'my-village-hall'); ?></option>
                          <?php
                          $rooms_in_bookings = [];
                          foreach ($groups as $group) {
                              foreach ($group['bookings'] as $booking) {
                                  if (!empty($booking['RoomName']) && $booking['RoomName'] !== 'Room booking') {
                                      $room_name = $booking['RoomName'];
                                      if (!isset($rooms_in_bookings[$room_name])) {
                                          $rooms_in_bookings[$room_name] = true;
                                          ?>
                                          <option value="<?php echo esc_attr($room_name); ?>">
                                              <?php echo esc_html($room_name); ?>
                                          </option>
                                          <?php
                                      }
                                  }
                              }
                          }
                          ?>
                      </select>
                  </div>

                  <?php if ($is_client_admin): ?>
                      <div class="myvh-filter-field">
                          <label for="myvh-dashboard-filter-customer"><?php _e('Customer:', 'my-village-hall'); ?></label>
                          <select id="myvh-dashboard-filter-customer" class="myvh-booking-filter-select" data-filter="customer">
                              <option value=""><?php _e('All Customers', 'my-village-hall'); ?></option>
                              <?php
                              $customers_in_bookings = [];
                              foreach ($groups as $group) {
                                  foreach ($group['bookings'] as $booking) {
                                      if (!empty($booking['CustomerId'])) {
                                          $customer_id = $booking['CustomerId'];
                                          $customer_name = $booking['CustomerName'] ?? 'Unknown';
                                          if (!isset($customers_in_bookings[$customer_id])) {
                                              $customers_in_bookings[$customer_id] = $customer_name;
                                              ?>
                                              <option value="<?php echo esc_attr($customer_id); ?>">
                                                  <?php echo esc_html($customer_name); ?>
                                              </option>
                                              <?php
                                          }
                                      }
                                  }
                              }
                              ?>
                          </select>
                      </div>

                      <div class="myvh-filter-field">
                          <label for="myvh-dashboard-filter-organisation"><?php _e('Organisation:', 'my-village-hall'); ?></label>
                          <select id="myvh-dashboard-filter-organisation" class="myvh-booking-filter-select" data-filter="organisation">
                              <option value=""><?php _e('All Organisations', 'my-village-hall'); ?></option>
                              <?php
                              $organisations_in_bookings = [];
                              foreach ($groups as $group) {
                                  foreach ($group['bookings'] as $booking) {
                                      if (!empty($booking['OrganisationId'])) {
                                          $org_id = $booking['OrganisationId'];
                                          $org_name = $booking['OrganisationName'] ?? 'Unknown';
                                          if (!isset($organisations_in_bookings[$org_id])) {
                                              $organisations_in_bookings[$org_id] = $org_name;
                                              ?>
                                              <option value="<?php echo esc_attr($org_id); ?>">
                                                  <?php echo esc_html($org_name); ?>
                                              </option>
                                              <?php
                                          }
                                      }
                                  }
                              }
                              ?>
                          </select>
                      </div>
                  <?php endif; ?>
              </div>

              <div class="myvh-filter-row">
                  <div class="myvh-filter-field">
                      <label for="myvh-dashboard-filter-description"><?php _e('Search Description:', 'my-village-hall'); ?></label>
                      <input type="text" id="myvh-dashboard-filter-description" class="myvh-booking-filter-text" data-filter="description" placeholder="<?php _e('Enter keyword...', 'my-village-hall'); ?>">
                  </div>
              </div>

              <div class="myvh-filter-actions">
                  <button type="button" class="button button-secondary" id="myvh-dashboard-filter-clear">
                      <?php _e('Clear Filters', 'my-village-hall'); ?>
                  </button>
              </div>
          </div>
        </div>

        <div class="myvh-bookings-list myvh-portal-dashboard-bookings-list">
          <table id="myvh-dashboard-bookings-table" class="myvh-customer-list-table myvh-portal-bookings-table">
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
                      if (($mb['StartDate'] ?? '') >= $today && ($mb['Status'] ?? '') !== 'cancelled') {
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
                                <?php echo esc_html(date('H:i', strtotime($summary_booking['EndTime'] ?? '00:00:00'))); ?> ·
                              <?php endif; ?>
                              <?php echo esc_html((string) $count); ?> bookings · <?php echo esc_html($rep['RoomName'] ?? 'Room booking'); ?>
                              <?php if (!empty($rep['Description'])): ?>
                                - <?php echo esc_html($rep['Description']); ?>
                              <?php endif; ?>
                            </small>
                          </div>
                        </div>
                      </td>
                    </tr>

                    <?php foreach ($members as $b): ?>
                      <?php $is_past = ($b['StartDate'] ?? '') < $today; ?>
                      <?php $status_class = 'is-' . sanitize_html_class($b['Status'] ?? ''); ?>
                      <?php $can_delete = $can_delete_booking($b); ?>
                      <tr class="myvh-bookings-table-row myvh-recurring-child <?php echo $is_past ? 'is-past' : ''; ?>"
                          data-group="<?php echo esc_attr($group_id); ?>"
                          data-status="<?php echo esc_attr(strtolower($b['Status'] ?? '')); ?>"
                          data-room="<?php echo esc_attr($b['RoomName'] ?? ''); ?>"
                          data-customer="<?php echo esc_attr($b['CustomerId'] ?? ''); ?>"
                          data-organisation="<?php echo esc_attr($b['OrganisationId'] ?? ''); ?>"
                          data-description-search="<?php echo esc_attr(strtolower($b['description'] ?? '')); ?>"
                          data-booking-date="<?php echo esc_attr($b['StartDate'] ?? ''); ?>">
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
                  <?php
                  else:
                    $b = $group['bookings'][0];
                    $is_past = ($b['StartDate'] ?? '') < $today;
                    $status_class = 'is-' . sanitize_html_class($b['Status'] ?? '');
                    $can_delete = $can_delete_booking($b);
                    ?>
                    <tr class="myvh-bookings-table-row <?php echo $is_past ? 'is-past' : ''; ?>"
                        data-status="<?php echo esc_attr(strtolower($b['Status'] ?? '')); ?>"
                        data-room="<?php echo esc_attr($b['RoomName'] ?? ''); ?>"
                        data-customer="<?php echo esc_attr($b['CustomerId'] ?? ''); ?>"
                        data-organisation="<?php echo esc_attr($b['OrganisationId'] ?? ''); ?>"
                        data-description-search="<?php echo esc_attr(strtolower($b['description'] ?? '')); ?>"
                        data-booking-date="<?php echo esc_attr($b['StartDate'] ?? ''); ?>"
                        data-group="single-<?php echo esc_attr($b['Id'] ?? ''); ?>">
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
                  <?php
                  endif;
                endforeach;
              ?>
            </tbody>
          </table>
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
            <a class="myvh-portal-quick-action" href="#venues">
              <span class="myvh-portal-quick-action__icon" aria-hidden="true">🏛</span>
              <span class="myvh-portal-quick-action__text">Venues</span>
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
