<?php
if (!defined('ABSPATH')) exit;

if (!is_user_logged_in()) {
    echo '<p>Please log in to view the calendar.</p>';
    return;
}

// use MYVH\Addons\AddonService;

// global $myvh_container;
// $addon_service = $myvh_container->get(AddonService::class);
// $all_addons = $addon_service->get_all(['orderby' => 'DisplayOrder', 'order' => 'ASC']);
// $available_addons = array_values(array_filter($all_addons ?? [], fn($a) => !empty($a['IsActive'])));
// ?>

<div class="myvh-dashboard-section myvh-portal-calendar">

    <div class="myvh-section-header">
        <h2>Calendar</h2>
    </div>

    <?php if (empty($can_create_booking)): ?>
        <div class="myvh-surface-panel myvh-bookings-panel">
            <div class="myvh-card">
                <p>A customer profile is required before this account can create portal bookings.</p>
            </div>
        </div>
        <?php return; ?>
    <?php endif; ?>

    <div class="myvh-calendar-shell">
        <div class="myvh-calendar-main">
            <aside class="myvh-calendar-sidebar">
                <div id="myvh-calendar-nav-picker" class="myvh-cal-nav-picker"></div>
            </aside>

            <div class="myvh-calendar-content">
                <div class="myvh-calendar-toolbar">
            <div class="myvh-calendar-nav myvh-pill-group">
                <button id="myvh-prev" class="myvh-cal-btn myvh-cal-nav-btn" type="button" aria-label="Previous">&lt;</button>
                <button id="myvh-today" class="myvh-cal-btn myvh-cal-nav-btn" type="button">Today</button>
                <button id="myvh-next" class="myvh-cal-btn myvh-cal-nav-btn" type="button" aria-label="Next">&gt;</button>
            </div>

            <div id="myvh-calendar-venue-wrap" class="myvh-calendar-venue-filter" style="display:none;">
                <label class="screen-reader-text" for="myvh-calendar-venue-select">Venue</label>
                <select id="myvh-calendar-venue-select" class="myvh-cal-btn myvh-calendar-venue-select"></select>
            </div>

            <div class="myvh-calendar-views myvh-pill-group">
                <button id="myvh-mode-calendar" class="myvh-cal-btn myvh-view-btn myvh-mode-btn active" data-mode="Calendar" type="button">Calendar</button>
                <button id="myvh-mode-scheduler" class="myvh-cal-btn myvh-view-btn myvh-mode-btn" data-mode="Scheduler" type="button">Scheduler</button>
                <button id="myvh-day" class="myvh-cal-btn myvh-view-btn myvh-detail-btn" data-view="Day" type="button">Day</button>
                <button id="myvh-week" class="myvh-cal-btn myvh-view-btn myvh-detail-btn active" data-view="Week" type="button">Week</button>
                <button id="myvh-month" class="myvh-cal-btn myvh-view-btn myvh-detail-btn" data-view="Month" type="button">Month</button>
            </div>
                </div>

                <div id="myvh-calendar-key" class="myvh-calendar-key" aria-label="Calendar key">
                    <div class="myvh-calendar-key-section">
                        <h3 class="myvh-calendar-key-title">Statuses</h3>
                        <div class="myvh-calendar-key-items myvh-calendar-key-status-items"></div>
                    </div>
                    <div class="myvh-calendar-key-section">
                        <h3 class="myvh-calendar-key-title">Rooms</h3>
                        <div class="myvh-calendar-key-items myvh-calendar-key-room-items"></div>
                    </div>
                </div>

                <div id="myvh-calendar" class="myvh-daypilot-frame myvh-portal-calendar-frame"></div>
            </div>
        </div>
    </div>

</div>

<?php
include MYVH_PLUGIN_DIR . 'templates/Bookings/booking-modal-create.php';
include MYVH_PLUGIN_DIR . 'templates/Bookings/booking-modal-view.php';

?>