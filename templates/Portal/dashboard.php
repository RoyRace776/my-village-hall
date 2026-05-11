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

$myvh_active_notices = function_exists('myvh_get_active_notices') ? myvh_get_active_notices() : [];
$member_organisations = array_values(array_filter(
  $member_organisations ?? [],
  static function ($organisation): bool {
    return empty($organisation['IsSystem']);
  }
));
$dashboard_unpaid_invoices = array_values($dashboard_unpaid_invoices ?? []);

$today = date('Y-m-d');
$window_start = date('Y-m-d', strtotime('-1 month'));
$window_end = date('Y-m-d', strtotime('+1 month'));
$all_member_bookings = [];

foreach ($groups as $group) {
  foreach (($group['bookings'] ?? []) as $booking) {
    $booking_id = intval($booking['Id'] ?? 0);
    if ($booking_id <= 0) {
      continue;
    }

    if (($booking['Status'] ?? '') === 'cancelled') {
      continue;
    }

    $all_member_bookings[$booking_id] = $booking;
  }
}

$all_member_bookings = array_values($all_member_bookings);
usort($all_member_bookings, function ($a, $b) {
  return strcmp(
    ($a['StartDate'] ?? '') . ' ' . ($a['StartTime'] ?? ''),
    ($b['StartDate'] ?? '') . ' ' . ($b['StartTime'] ?? '')
  );
});

$previous_booking = null;
$next_month_bookings = [];
foreach ($all_member_bookings as $booking) {
  $booking_date = (string) ($booking['StartDate'] ?? '');
  if ($booking_date >= $window_start && $booking_date < $today) {
    $previous_booking = $booking;
    continue;
  }

  if ($booking_date >= $today && $booking_date <= $window_end) {
    $next_month_bookings[] = $booking;
  }
}

$next_month_bookings = array_slice($next_month_bookings, 0, 8);
?>

<?php if (!$is_client_admin): ?>
<div class="myvh-dashboard-section myvh-portal-dashboard-page myvh-portal-dashboard-member-page">

  <div class="myvh-account-header">
    <div>
      <h2>Welcome, <?php echo esc_html($current_user->display_name); ?></h2>
      <p>My Village Hall - Member Portal</p>
    </div>
    <a href="#bookings" class="myvh-portal-add-btn myvh-portal-nav-btn">
      <span class="myvh-portal-add-btn__icon" aria-hidden="true">→</span>
      <span>My Bookings</span>
    </a>
  </div>

  <div class="myvh-card myvh-account-card myvh-portal-dashboard-notices-card">
    <div class="myvh-account-card-head">
      <div>
        <h3>Hall Notices</h3>
        <span>Latest site updates</span>
      </div>
    </div>
    <?php if (!empty($myvh_active_notices)): ?>
      <ul class="myvh-portal-notices-list">
        <?php foreach ($myvh_active_notices as $myvh_notice): ?>
          <li><?php echo esc_html($myvh_notice['message']); ?></li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p class="myvh-portal-notices-empty"><?php esc_html_e('No current notices.', 'my-village-hall'); ?></p>
    <?php endif; ?>
  </div>

  <div class="myvh-portal-dashboard-grid myvh-portal-dashboard-grid-member">

    <div class="myvh-card myvh-account-card myvh-portal-dashboard-main-card">
      <div class="myvh-account-card-head">
        <div>
          <h3>Bookings Snapshot</h3>
          <span>Previous booking plus bookings in the next month</span>
        </div>
      </div>

      <?php if (empty($customer['Id'])): ?>
        <div class="myvh-empty-state myvh-portal-dashboard-empty-state">
          <p class="myvh-portal-dashboard-empty-state__title">No customer profile is linked to this account yet.</p>
          <p>Your dashboard will start showing bookings as soon as this account is linked to a customer record.</p>
        </div>
      <?php else: ?>
        <div class="myvh-dashboard-subsection">
          <h4 class="myvh-dashboard-subtitle">Previous Booking</h4>
          <?php if (!empty($previous_booking)): ?>
            <?php $prev_status_class = 'is-' . sanitize_html_class($previous_booking['Status'] ?? ''); ?>
            <div class="myvh-dashboard-inline-row">
              <div>
                <strong>
                  <?php echo esc_html($format_booking_date($previous_booking['StartDate'] ?? '')); ?>
                  <?php echo esc_html(date('H:i', strtotime($previous_booking['StartTime'] ?? '00:00:00'))); ?>-
                  <?php echo esc_html(date('H:i', strtotime($previous_booking['EndTime'] ?? '00:00:00'))); ?>
                </strong>
                <div class="myvh-muted"><?php echo esc_html($previous_booking['RoomName'] ?? 'Room booking'); ?></div>
              </div>
              <div class="myvh-dashboard-inline-actions">
                <span class="myvh-status-chip <?php echo esc_attr($prev_status_class); ?>"><?php echo esc_html(ucfirst((string) ($previous_booking['Status'] ?? ''))); ?></span>
                <a class="myvh-action-icon" href="#booking-view?booking_id=<?php echo intval($previous_booking['Id'] ?? 0); ?>" aria-label="View booking" title="View booking">👁</a>
              </div>
            </div>
          <?php else: ?>
            <p class="myvh-muted">No previous booking found.</p>
          <?php endif; ?>
        </div>

        <div class="myvh-dashboard-subsection">
          <h4 class="myvh-dashboard-subtitle">Next Month</h4>
          <?php if (!empty($next_month_bookings)): ?>
            <div class="myvh-bookings-list myvh-portal-dashboard-bookings-list myvh-portal-dashboard-bookings-list-compact">
              <table class="myvh-customer-list-table myvh-portal-bookings-table">
                <thead>
                  <tr>
                    <th>Date &amp; Time</th>
                    <th>Booking</th>
                    <th>Status</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($next_month_bookings as $b): ?>
                    <?php $status_class = 'is-' . sanitize_html_class($b['Status'] ?? ''); ?>
                    <tr class="myvh-bookings-table-row">
                      <td>
                        <strong>
                          <?php echo esc_html($format_booking_date($b['StartDate'] ?? '')); ?>
                          <?php echo esc_html(date('H:i', strtotime($b['StartTime'] ?? '00:00:00'))); ?>-
                          <?php echo esc_html(date('H:i', strtotime($b['EndTime'] ?? '00:00:00'))); ?>
                        </strong>
                      </td>
                      <td>
                        <strong><?php echo esc_html($b['RoomName'] ?? 'Room booking'); ?></strong>
                        <?php if (!empty($b['Description'])): ?>
                          <div class="myvh-muted"><?php echo esc_html(wp_trim_words((string) $b['Description'], 6, '...')); ?></div>
                        <?php endif; ?>
                      </td>
                      <td>
                        <span class="myvh-status-chip <?php echo esc_attr($status_class); ?>"><?php echo esc_html(ucfirst((string) ($b['Status'] ?? ''))); ?></span>
                      </td>
                      <td>
                        <a class="myvh-action-icon" href="#booking-view?booking_id=<?php echo intval($b['Id'] ?? 0); ?>" aria-label="View booking" title="View booking">👁</a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <p class="myvh-muted">No bookings are scheduled in the next month.</p>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="myvh-portal-dashboard-side">
      <div class="myvh-card myvh-account-card myvh-portal-dashboard-side-card">
        <div class="myvh-account-card-head">
          <div>
            <h3>My Organisations</h3>
            <span><?php echo esc_html((string) count($member_organisations)); ?> total</span>
          </div>
        </div>
        <?php if (!empty($member_organisations)): ?>
          <ul class="myvh-portal-summary-list">
            <?php foreach ($member_organisations as $organisation): ?>
              <li>
                <strong><?php echo esc_html($organisation['Name'] ?? 'Organisation'); ?></strong>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <p class="myvh-muted">You are not currently linked to any organisations.</p>
        <?php endif; ?>
      </div>

      <div class="myvh-card myvh-account-card myvh-portal-dashboard-side-card">
        <div class="myvh-account-card-head">
          <div>
            <h3>Unpaid Invoices</h3>
            <span><?php echo esc_html((string) count($dashboard_unpaid_invoices)); ?> shown</span>
          </div>
        </div>
        <?php if (!empty($dashboard_unpaid_invoices)): ?>
          <div class="myvh-bookings-list myvh-portal-dashboard-bookings-list myvh-portal-dashboard-bookings-list-compact">
            <table class="myvh-customer-list-table myvh-portal-bookings-table">
              <thead>
                <tr>
                  <th>Invoice</th>
                  <th>Due</th>
                  <th>Amount</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($dashboard_unpaid_invoices as $invoice): ?>
                  <tr class="myvh-bookings-table-row">
                    <td>
                      <strong><?php echo esc_html((string) ($invoice['InvoiceNumber'] ?? '')); ?></strong>
                    </td>
                    <td>
                      <?php echo esc_html($format_booking_date($invoice['DueDate'] ?? '')); ?>
                    </td>
                    <td>
                      £<?php echo esc_html(number_format(floatval($invoice['AmountDue'] ?? 0), 2)); ?>
                    </td>
                    <td>
                      <a class="myvh-action-icon" href="#invoice-view?invoice_id=<?php echo intval($invoice['Id'] ?? 0); ?>" aria-label="View invoice" title="View invoice">👁</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <p class="myvh-muted">No unpaid invoices right now.</p>
        <?php endif; ?>
      </div>
    </div>

  </div>

</div>
<?php else: ?>
<div class="myvh-dashboard-section myvh-portal-dashboard-page">

  <div class="myvh-account-header">
    <div>
      <h2>Welcome, <?php echo esc_html($current_user->display_name); ?></h2>
      <p><?php echo esc_html(get_bloginfo('name') . ' - Client Admin Portal'); ?></p>
    </div>
    <a href="#bookings" class="myvh-portal-add-btn myvh-portal-nav-btn">
      <span class="myvh-portal-add-btn__icon" aria-hidden="true">→</span>
      <span>View Bookings</span>
    </a>
  </div>

  <div class="myvh-card myvh-account-card myvh-portal-dashboard-notices-card">
    <div class="myvh-account-card-head">
      <div>
        <h3>Hall Notices</h3>
        <span>Latest site updates</span>
      </div>
    </div>
    <?php if (!empty($myvh_active_notices)): ?>
      <ul class="myvh-portal-notices-list">
        <?php foreach ($myvh_active_notices as $myvh_notice): ?>
          <li><?php echo esc_html($myvh_notice['message']); ?></li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <p class="myvh-portal-notices-empty"><?php esc_html_e('No current notices.', 'my-village-hall'); ?></p>
    <?php endif; ?>
  </div>

  <div class="myvh-portal-dashboard-kpi-grid">
    <div class="myvh-card myvh-account-card myvh-portal-dashboard-kpi-card">
      <span class="myvh-portal-dashboard-kpi-label">Organisations</span>
      <strong class="myvh-portal-dashboard-kpi-value"><?php echo esc_html((string) intval($admin_dashboard_counts['organisations_count'] ?? 0)); ?></strong>
      <span class="myvh-portal-dashboard-kpi-meta">Excluding system organisations</span>
    </div>
    <div class="myvh-card myvh-account-card myvh-portal-dashboard-kpi-card">
      <span class="myvh-portal-dashboard-kpi-label">Outstanding Member Requests</span>
      <strong class="myvh-portal-dashboard-kpi-value"><?php echo esc_html((string) intval($admin_dashboard_counts['pending_member_requests_count'] ?? 0)); ?></strong>
      <span class="myvh-portal-dashboard-kpi-meta">Pending approval</span>
    </div>
    <div class="myvh-card myvh-account-card myvh-portal-dashboard-kpi-card">
      <span class="myvh-portal-dashboard-kpi-label">Customers</span>
      <strong class="myvh-portal-dashboard-kpi-value"><?php echo esc_html((string) intval($admin_dashboard_counts['customers_count'] ?? 0)); ?></strong>
      <span class="myvh-portal-dashboard-kpi-meta">All non-system customers</span>
    </div>
    <div class="myvh-card myvh-account-card myvh-portal-dashboard-kpi-card">
      <span class="myvh-portal-dashboard-kpi-label">New Customers (Last Month)</span>
      <strong class="myvh-portal-dashboard-kpi-value"><?php echo esc_html((string) intval($admin_dashboard_counts['customers_last_month_count'] ?? 0)); ?></strong>
      <span class="myvh-portal-dashboard-kpi-meta">Created in the last month</span>
    </div>
  </div>

  <div class="myvh-portal-dashboard-grid myvh-portal-dashboard-grid-admin">
    <div class="myvh-card myvh-account-card myvh-portal-dashboard-main-card">
      <div class="myvh-account-card-head">
        <div>
          <h3>Room Activity</h3>
          <span>Bookings and booked hours (excluding buffer time)</span>
        </div>
      </div>
      <?php if (empty($admin_room_activity)): ?>
        <div class="myvh-empty-state myvh-portal-dashboard-empty-state">
          <p class="myvh-portal-dashboard-empty-state__title">No room booking activity in the last or next month.</p>
          <p>Activity data will appear when bookings are present in these windows.</p>
        </div>
      <?php else: ?>
        <div class="myvh-bookings-list myvh-portal-dashboard-bookings-list myvh-portal-dashboard-bookings-list-compact">
          <table class="myvh-customer-list-table myvh-portal-bookings-table">
            <thead>
              <tr>
                <th>Room</th>
                <th>Pending Bookings</th>
                <th>Last Month Bookings</th>
                <th>Last Month Hours</th>
                <th>Next Month Bookings</th>
                <th>Next Month Hours</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($admin_room_activity as $row): ?>
                <tr class="myvh-bookings-table-row">
                  <td><strong><?php echo esc_html((string) ($row['room_name'] ?? 'Room booking')); ?></strong></td>
                  <td><a href="#bookings?status=pending&room=<?php echo urlencode((string) ($row['room_name'] ?? 'Room booking')); ?>" style="text-decoration: none; color: inherit; cursor: pointer;"><?php echo esc_html((string) intval($row['pending_bookings'] ?? 0)); ?></a></td>
                  <td><a href="#bookings?datePreset=past&room=<?php echo urlencode((string) ($row['room_name'] ?? 'Room booking')); ?>" style="text-decoration: none; color: inherit; cursor: pointer;"><?php echo esc_html((string) intval($row['last_month_bookings'] ?? 0)); ?></a></td>
                  <td><?php echo esc_html(number_format((float) ($row['last_month_hours'] ?? 0), 2)); ?></td>
                  <td><a href="#bookings?datePreset=upcoming&room=<?php echo urlencode((string) ($row['room_name'] ?? 'Room booking')); ?>" style="text-decoration: none; color: inherit; cursor: pointer;"><?php echo esc_html((string) intval($row['next_month_bookings'] ?? 0)); ?></a></td>
                  <td><?php echo esc_html(number_format((float) ($row['next_month_hours'] ?? 0), 2)); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <div class="myvh-portal-dashboard-side myvh-portal-dashboard-side-admin">
      <div class="myvh-card myvh-account-card myvh-portal-dashboard-side-card">
        <div class="myvh-account-card-head">
          <div>
            <h3>Bookings Awaiting Invoicing</h3>
            <span>Not invoiced yet or linked only to draft invoices</span>
          </div>
        </div>
        <?php if (empty($admin_invoice_action_bookings)): ?>
          <p class="myvh-muted">No bookings currently need invoice action.</p>
        <?php else: ?>
          <div class="myvh-bookings-list myvh-portal-dashboard-bookings-list myvh-portal-dashboard-bookings-list-compact">
            <table class="myvh-customer-list-table myvh-portal-bookings-table">
              <thead>
                <tr>
                  <th>Booking</th>
                  <th>When</th>
                  <th>Invoice State</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($admin_invoice_action_bookings as $booking): ?>
                  <?php
                    $invoice_state = !empty($booking['DraftInvoiceNumber'])
                      ? ('Draft: ' . (string) $booking['DraftInvoiceNumber'])
                      : 'Uninvoiced';
                  ?>
                  <tr class="myvh-bookings-table-row">
                    <td>
                      <strong><?php echo esc_html((string) ($booking['RoomName'] ?? 'Room booking')); ?></strong>
                      <?php if (!empty($booking['CustomerName'])): ?>
                        <div class="myvh-muted"><?php echo esc_html((string) $booking['CustomerName']); ?></div>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php echo esc_html($format_booking_date($booking['StartDate'] ?? '')); ?>
                      <?php echo esc_html(date('H:i', strtotime((string) ($booking['StartTime'] ?? '00:00:00')))); ?>-
                      <?php echo esc_html(date('H:i', strtotime((string) ($booking['EndTime'] ?? '00:00:00')))); ?>
                    </td>
                    <td><?php echo esc_html($invoice_state); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

      <div class="myvh-card myvh-account-card myvh-portal-dashboard-side-card">
        <div class="myvh-account-card-head">
          <div>
            <h3>Overdue Invoices</h3>
            <span>Past due date and not fully paid</span>
          </div>
        </div>
        <?php if (empty($admin_overdue_invoices)): ?>
          <p class="myvh-muted">No overdue invoices at the moment.</p>
        <?php else: ?>
          <div class="myvh-bookings-list myvh-portal-dashboard-bookings-list myvh-portal-dashboard-bookings-list-compact">
            <table class="myvh-customer-list-table myvh-portal-bookings-table">
              <thead>
                <tr>
                  <th>Invoice</th>
                  <th>Due Date</th>
                  <th>Amount Due</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($admin_overdue_invoices as $invoice): ?>
                  <tr class="myvh-bookings-table-row">
                    <td>
                      <strong><?php echo esc_html((string) ($invoice['InvoiceNumber'] ?? '')); ?></strong>
                      <?php if (!empty($invoice['CustomerName'])): ?>
                        <div class="myvh-muted"><?php echo esc_html((string) $invoice['CustomerName']); ?></div>
                      <?php endif; ?>
                    </td>
                    <td><?php echo esc_html($format_booking_date($invoice['DueDate'] ?? '')); ?></td>
                    <td>£<?php echo esc_html(number_format((float) ($invoice['AmountDue'] ?? 0), 2)); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>

  </div>

</div>
<?php endif; ?>
