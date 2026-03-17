<?php
if (!defined('ABSPATH')) exit;

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

      <?php if (!empty($bookings)): ?>
        <table class="booking-table">
          <thead>
            <tr>
              <th>Room</th>
              <th>Date</th>
              <th>Time</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($bookings as $booking): ?>
              <tr>
                <td class="col-room"><?= esc_html($booking['RoomName']) ?></td>
                <td class="col-date"><?= esc_html(date('D j M', strtotime($booking['StartTime']))) ?></td>
                <td class="col-time">
                  <?= esc_html(date('H:i', strtotime($booking['StartTime']))) ?>
                  &ndash;
                  <?= esc_html(date('H:i', strtotime($booking['EndTime']))) ?>
                </td>
                <td class="col-actions">
                  <a class="btn-edit"   href="/edit-booking?id=<?= intval($booking['Id']) ?>">Edit</a>
                  <a class="btn-cancel" href="/cancel-booking?id=<?= intval($booking['Id']) ?>">Cancel</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

      <?php else: ?>
        <p class="no-bookings">You have no upcoming bookings.</p>
      <?php endif; ?>
    </div>

    <div class="dashboard-right">
      <h2 class="section-title">Quick Actions</h2>
      <div class="action-cards">

        <div class="dashboard-card">
          <a href="/book-room">
            <span class="card-icon">📅</span>
            Book a Room
          </a>
        </div>

        <div class="dashboard-card">
          <a href="/calendar">
            <span class="card-icon">🗓</span>
            View Calendar
          </a>
        </div>

        <div class="dashboard-card">
          <a href="/my-bookings">
            <span class="card-icon">📋</span>
            My Bookings
          </a>
        </div>

      </div>
    </div>

  </div>

  <div class="dashboard-notices">
    <h2 class="section-title">Hall Notices</h2>
    <ul class="notices-list">
      <li>Kitchen refurbishment starting next month.</li>
      <li>AGM scheduled for April 23rd.</li>
    </ul>
  </div>

</div>
