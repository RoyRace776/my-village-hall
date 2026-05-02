<?php
if (!defined('ABSPATH')) exit;

use MYVH\Bookings\RecurringPatternService;

$current_user = wp_get_current_user();
?>

<div class="myvh-dashboard">

  <div class="myvh-header">
    <h1 class="greeting">Welcome, <em><?= esc_html($current_user->display_name) ?></em></h1>
    <span class="tagline">My Village Hall &mdash; Member Portal</span>
  </div>

  <div class="myvh-dashboard-columns">

    <div class="dashboard-left">
      <h2 class="section-title">Upcoming Bookings</h2>

      <?php if (!empty($groups)): ?>
        <div class="myvh-bookings-list dashboard-style">
          <?php
            $today = date('Y-m-d');
            $status_colors = [
              'pending'   => '#2271b1',
              'confirmed' => '#46b450',
              'cancelled' => '#dc3232',
              'completed' => '#777',
            ];
            foreach ($groups as $group_key => $group):
              $is_recurring = ($group['type'] === 'recurring');
              if ($is_recurring):
                $pattern   = $group['pattern'];
                $members   = $group['bookings'];
                $count     = count($members);
                $rep       = $members[0];
                $upcoming = null;
                foreach (array_reverse($members) as $mb) {
                  if ($mb['StartDate'] >= $today && $mb['Status'] !== 'cancelled') {
                    $upcoming = $mb;
                    break;
                  }
                }
                $schedule = RecurringPatternService::describe($pattern);
                $group_id = 'rg_' . $pattern['Id'];
                ?>
                <div class="myvh-booking-group">
                  <div class="myvh-group-header" data-group="<?php echo esc_attr($group_id); ?>" style="cursor:pointer;padding:8px;border-bottom:1px solid #ddd;display:flex;justify-content:space-between;align-items:center;gap:12px;">
                    <div class="myvh-group-toggle" style="color:#2271b1;font-weight:bold;min-width:16px;">▶</div>
                    <div class="myvh-group-main" style="flex-grow:1;">
                      <strong>🔄 <?php echo esc_html($schedule); ?></strong>
                      <div style="font-size:0.9em;color:#666;"><?php echo $count; ?> bookings <?php if ($upcoming): ?>· next: <?php echo date('j M', strtotime($upcoming['StartDate'])); ?><?php endif; ?></div>
                    </div>
                    <div class="myvh-group-room" style="text-align:right;color:#666;"><?php echo esc_html($rep['RoomName']); ?></div>
                  </div>
                  <div class="myvh-group-children" data-group="<?php echo esc_attr($group_id); ?>" style="padding:4px 0;border-left:2px solid #ddd;margin-left:12px;padding-left:8px;">
                    <?php foreach ($members as $b): ?>
                      <?php $is_past = $b['StartDate'] < $today; ?>
                      <div style="padding:4px 0;opacity:<?php echo $is_past ? '0.55' : '1'; ?>;font-size:0.9em;">
                        <span style="color:<?php echo $status_colors[$b['Status']] ?? '#777'; ?>;margin-right:4px;">●</span>
                        <span><?php echo date('j M Y', strtotime($b['StartDate'])); ?></span>
                        <span style="color:#666;"><?php echo date('g:i A', strtotime($b['StartTime'])); ?> – <?php echo date('g:i A', strtotime($b['EndTime'])); ?></span>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
                <?php
              else:
                $b = $group['bookings'][0];
                $is_past = $b['StartDate'] < $today;
                $sc = $status_colors[$b['Status']] ?? '#777';
                ?>
                <div style="padding:8px;border-bottom:1px solid #ddd;opacity:<?php echo $is_past ? '0.55' : '1'; ?>;">
                  <div style="display:flex;justify-content:space-between;align-items:center;">
                    <div>
                      <strong><?php echo esc_html($b['RoomName']); ?></strong><br>
                      <small style="color:#666;"><?php echo date('j M Y', strtotime($b['StartDate'])); ?></small>
                    </div>
                    <div style="text-align:right;">
                      <small style="color:#666;"><?php echo date('g:i A', strtotime($b['StartTime'])); ?> – <?php echo date('g:i A', strtotime($b['EndTime'])); ?></small><br>
                      <span style="color:<?php echo $sc; ?>;margin-right:4px;">●</span>
                      <small><?php echo esc_html(ucfirst($b['Status'])); ?></small>
                    </div>
                  </div>
                </div>
                <?php
              endif;
            endforeach;
          ?>
        </div>

      <?php else: ?>
        <p class="no-bookings">You have no upcoming bookings.</p>
      <?php endif; ?>
    </div>

    <div class="dashboard-right">
      <h2 class="section-title">Quick Actions</h2>
      <div class="action-cards">

        <div class="dashboard-card">
          <a href="#bookings">
            <span class="card-icon">📅</span>
            Book a Room
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
            My Bookings
          </a>
        </div>

        <div class="dashboard-card">
          <a href="#organisations">
            <span class="card-icon">👥</span>
            Organisations
          </a>
        </div>

      </div>
    </div>

  </div>

  <div class="dashboard-notices">
    <h2 class="section-title">Hall Notices</h2>
    <?php
    $myvh_active_notices = function_exists('myvh_get_active_notices') ? myvh_get_active_notices() : [];
    ?>
    <?php if (!empty($myvh_active_notices)): ?>
    <ul class="notices-list">
      <?php foreach ($myvh_active_notices as $myvh_notice): ?>
      <li><?php echo esc_html($myvh_notice['message']); ?></li>
      <?php endforeach; ?>
    </ul>
    <?php else: ?>
    <p class="notices-empty"><?php esc_html_e('No current notices.', 'my-village-hall'); ?></p>
    <?php endif; ?>
  </div>

</div>
