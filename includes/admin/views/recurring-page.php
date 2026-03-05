<?php
if (!defined('ABSPATH')) exit;

if (!current_user_can('manage_myvh')) {
    wp_die(__('Permission denied', 'my-village-hall'));
}


$edit_id = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
$view_id = isset($_GET['view']) ? intval($_GET['view']) : 0;

// ── EDIT / VIEW a single pattern ─────────────────────────────────────────────
if ($edit_id || $view_id) {
    $pattern_id   = $edit_id ?: $view_id;
    $is_view_mode = !$edit_id;
    $pattern      = MYVH_Registry::get('recurring_pattern_service')->get($pattern_id);

    if (!$pattern) {
        wp_die(__('Pattern not found.', 'my-village-hall'));
    }

    $parent_booking  = MYVH_Registry::get('booking_repo')->get_by_id($pattern['ParentBookingId']);
    $bookings        = MYVH_Registry::get('recurring_pattern_service')->get_bookings_for_pattern($pattern_id);
    $customers       = MYVH_Registry::get('customer_repo')->get_all();
    $rooms           = MYVH_Registry::get('room_repo')->get_all_with_venues();
    $customer_map    = array_column($customers ?? [], null, 'Id');
    $room_map        = array_column($rooms ?? [], null, 'Id');

    $today     = date('Y-m-d');
    $future    = array_filter($bookings, fn($b) => $b['StartDate'] >= $today && $b['Status'] !== 'cancelled');
    $past      = array_filter($bookings, fn($b) => $b['StartDate'] < $today  || $b['Status'] === 'cancelled');
    ?>
    <div class="wrap">
        <h1>
            <?php echo $is_view_mode ? __('View Recurring Pattern', 'my-village-hall') : __('Edit Recurring Pattern', 'my-village-hall'); ?>
            <a href="<?php echo admin_url('admin.php?page=myvh-recurring'); ?>" class="page-title-action">
                <?php _e('← Back to All Patterns', 'my-village-hall'); ?>
            </a>
        </h1>
        <hr class="wp-header-end">

        <?php if (isset($_GET['error'])): ?>
            <div class="notice notice-error is-dismissible"><p><?php echo esc_html($_GET['error']); ?></p></div>
        <?php endif; ?>
        <?php if (isset($_GET['updated'])): ?>
            <div class="notice notice-success is-dismissible"><p><?php echo esc_html($_GET['message'] ?? __('Saved.', 'my-village-hall')); ?></p></div>
        <?php endif; ?>

        <div class="myvh-row">
            <!-- Left: pattern details / edit form -->
            <div class="myvh-col-60">
                <div class="myvh-card">
                    <h2><?php _e('Pattern Settings', 'my-village-hall'); ?></h2>

                    <?php if ($parent_booking): ?>
                    <table class="form-table" style="margin-bottom:10px;">
                        <tr>
                            <th><?php _e('Booking', 'my-village-hall'); ?></th>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=my-village-hall&view=' . $pattern['ParentBookingId']); ?>">
                                    #<?php echo $pattern['ParentBookingId']; ?>
                                </a>
                                &nbsp;–&nbsp;
                                <?php
                                $c = $customer_map[$parent_booking['CustomerId']] ?? null;
                                $r = $room_map[$parent_booking['RoomId']] ?? null;
                                echo esc_html(($c['Name'] ?? '?') . ' @ ' . ($r['Name'] ?? '?'));
                                ?>
                                <br><small style="color:#666;">
                                    <?php echo date('g:i A', strtotime($parent_booking['StartTime'])); ?> –
                                    <?php echo date('g:i A', strtotime($parent_booking['EndTime'])); ?>
                                </small>
                            </td>
                        </tr>
                    </table>
                    <?php endif; ?>

                    <?php if ($is_view_mode): ?>
                        <!-- View-only table -->
                        <table class="form-table">
                            <tr><th><?php _e('Schedule', 'my-village-hall'); ?></th>
                                <td><strong><?php echo esc_html(MYVH_Recurring_Pattern_Service::describe($pattern)); ?></strong></td></tr>
                            <tr><th><?php _e('Start Date', 'my-village-hall'); ?></th>
                                <td><?php echo date('D j M Y', strtotime($pattern['StartDate'])); ?></td></tr>
                            <tr><th><?php _e('Ends', 'my-village-hall'); ?></th>
                                <td>
                                    <?php if ($pattern['EndDate']): ?>
                                        <?php echo date('D j M Y', strtotime($pattern['EndDate'])); ?>
                                    <?php elseif ($pattern['MaxOccurrences']): ?>
                                        <?php echo sprintf(__('After %d occurrences', 'my-village-hall'), $pattern['MaxOccurrences']); ?>
                                    <?php else: ?>
                                        <?php _e('No end', 'my-village-hall'); ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr><th><?php _e('Status', 'my-village-hall'); ?></th>
                                <td>
                                    <?php if ($pattern['IsActive']): ?>
                                        <span style="color:#46b450;">● <?php _e('Active', 'my-village-hall'); ?></span>
                                    <?php else: ?>
                                        <span style="color:#999;">● <?php _e('Inactive', 'my-village-hall'); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr><th><?php _e('Bookings created', 'my-village-hall'); ?></th>
                                <td><?php echo count($bookings); ?></td></tr>
                        </table>
                        <p>
                            <a href="<?php echo admin_url('admin.php?page=myvh-recurring&edit=' . $pattern_id); ?>" class="button button-primary">
                                <?php _e('Edit Pattern', 'my-village-hall'); ?>
                            </a>
                        </p>

                    <?php else: ?>
                        <!-- Edit form -->
                        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                            <input type="hidden" name="action" value="myvh_save_recurring_pattern">
                            <input type="hidden" name="pattern_id" value="<?php echo $pattern_id; ?>">
                            <input type="hidden" name="parent_booking_id" value="<?php echo $pattern['ParentBookingId']; ?>">
                            <?php wp_nonce_field('myvh_save_recurring_pattern'); ?>

                            <table class="form-table">
                                <tr>
                                    <th><?php _e('Repeat', 'my-village-hall'); ?> *</th>
                                    <td>
                                        <select name="recurrence_type" class="regular-text" id="rp-type">
                                            <option value="daily"       <?php selected($pattern['RecurrenceType'], 'daily'); ?>><?php _e('Daily', 'my-village-hall'); ?></option>
                                            <option value="weekly"      <?php selected($pattern['RecurrenceType'], 'weekly'); ?>><?php _e('Weekly', 'my-village-hall'); ?></option>
                                            <option value="monthly"     <?php selected($pattern['RecurrenceType'], 'monthly'); ?>><?php _e('Monthly (same date)', 'my-village-hall'); ?></option>
                                            <option value="monthly_day" <?php selected($pattern['RecurrenceType'], 'monthly_day'); ?>><?php _e('Monthly (specific weekday)', 'my-village-hall'); ?></option>
                                            <option value="yearly"      <?php selected($pattern['RecurrenceType'], 'yearly'); ?>><?php _e('Yearly', 'my-village-hall'); ?></option>
                                        </select>
                                    </td>
                                </tr>

                                <!-- Shown for all except monthly_day -->
                                <tr id="rp-interval-row">
                                    <th><?php _e('Every', 'my-village-hall'); ?></th>
                                    <td>
                                        <input type="number" name="recurrence_interval" min="1" max="52" class="small-text"
                                            value="<?php echo intval($pattern['RecurrenceInterval']); ?>">
                                        <span id="rp-interval-label"></span>
                                    </td>
                                </tr>

                                <!-- Shown only for monthly_day -->
                                <tr id="rp-monthly-day-row" style="display:none;">
                                    <th><?php _e('On the…', 'my-village-hall'); ?> *</th>
                                    <td>
                                        <select name="recurrence_week" id="rp-week">
                                            <?php
                                            $weeks = ['1'=>__('1st','my-village-hall'),'2'=>__('2nd','my-village-hall'),
                                                      '3'=>__('3rd','my-village-hall'),'4'=>__('4th','my-village-hall'),
                                                      'last'=>__('Last','my-village-hall')];
                                            foreach ($weeks as $v => $l): ?>
                                                <option value="<?php echo $v; ?>" <?php selected($pattern['RecurrenceWeek'], $v); ?>>
                                                    <?php echo esc_html($l); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <select name="recurrence_day" id="rp-day">
                                            <?php
                                            $days = ['monday'=>__('Monday','my-village-hall'),'tuesday'=>__('Tuesday','my-village-hall'),
                                                     'wednesday'=>__('Wednesday','my-village-hall'),'thursday'=>__('Thursday','my-village-hall'),
                                                     'friday'=>__('Friday','my-village-hall'),'saturday'=>__('Saturday','my-village-hall'),
                                                     'sunday'=>__('Sunday','my-village-hall')];
                                            foreach ($days as $v => $l): ?>
                                                <option value="<?php echo $v; ?>" <?php selected(strtolower($pattern['RecurrenceDay'] ?? ''), $v); ?>>
                                                    <?php echo esc_html($l); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <span><?php _e('of every', 'my-village-hall'); ?></span>
                                        <input type="number" name="recurrence_interval_md" min="1" max="24" class="small-text"
                                            value="<?php echo intval($pattern['RecurrenceInterval']); ?>">
                                        <span><?php _e('month(s)', 'my-village-hall'); ?></span>
                                        <p class="description" id="rp-preview" style="margin-top:6px;color:#2271b1;font-style:italic;"></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php _e('Start Date', 'my-village-hall'); ?> *</th>
                                    <td>
                                        <input type="date" name="start_date" required class="regular-text"
                                            value="<?php echo esc_attr($pattern['StartDate']); ?>">
                                        <p class="description"><?php _e('Future bookings are generated from this date.', 'my-village-hall'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php _e('Ends', 'my-village-hall'); ?></th>
                                    <td>
                                        <label>
                                            <input type="radio" name="recurrence_end_type" value="date"
                                                <?php checked(empty($pattern['MaxOccurrences'])); ?> id="rp-end-date-radio">
                                            <?php _e('On date', 'my-village-hall'); ?>
                                            <input type="date" name="end_date" id="rp-end-date"
                                                value="<?php echo esc_attr($pattern['EndDate'] ?? ''); ?>">
                                        </label>
                                        <br><br>
                                        <label>
                                            <input type="radio" name="recurrence_end_type" value="count"
                                                <?php checked(!empty($pattern['MaxOccurrences'])); ?> id="rp-end-count-radio">
                                            <?php _e('After', 'my-village-hall'); ?>
                                            <input type="number" name="max_occurrences" min="1" max="365" class="small-text"
                                                id="rp-max-occ"
                                                value="<?php echo esc_attr($pattern['MaxOccurrences'] ?? 10); ?>">
                                            <?php _e('occurrences', 'my-village-hall'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th><?php _e('Active', 'my-village-hall'); ?></th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="is_active" value="1"
                                                <?php checked($pattern['IsActive']); ?>>
                                            <?php _e('Pattern is active', 'my-village-hall'); ?>
                                        </label>
                                    </td>
                                </tr>
                            </table>

                            <div class="notice notice-warning inline" style="margin:15px 0;">
                                <p>⚠️ <?php _e('Saving will delete all <strong>future</strong> bookings for this pattern and regenerate them with the new schedule. Past bookings are preserved.', 'my-village-hall'); ?></p>
                            </div>

                            <p class="submit">
                                <button type="submit" class="button button-primary button-large">
                                    <?php _e('Save Pattern & Regenerate Bookings', 'my-village-hall'); ?>
                                </button>
                                <a href="<?php echo admin_url('admin.php?page=myvh-recurring&view=' . $pattern_id); ?>" class="button button-large">
                                    <?php _e('Cancel', 'my-village-hall'); ?>
                                </a>
                            </p>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right: actions panel -->
            <div class="myvh-col-40">
                <div class="myvh-card">
                    <h2><?php _e('Actions', 'my-village-hall'); ?></h2>
                    <p>
                        <?php if ($pattern['IsActive']): ?>
                            <a href="<?php echo wp_nonce_url(
                                admin_url('admin-post.php?action=myvh_deactivate_recurring_pattern&id=' . $pattern_id),
                                'myvh_deactivate_recurring_pattern'
                            ); ?>" class="button" onclick="return confirm('<?php _e('Deactivate this pattern? No new bookings will be created.', 'my-village-hall'); ?>');">
                                ⏸ <?php _e('Deactivate Pattern', 'my-village-hall'); ?>
                            </a>
                        <?php endif; ?>
                    </p>
                    <p>
                        <a href="<?php echo wp_nonce_url(
                            admin_url('admin-post.php?action=myvh_delete_future_bookings&id=' . $pattern_id),
                            'myvh_delete_future_bookings'
                        ); ?>" class="button" onclick="return confirm('<?php _e('Delete all future bookings in this pattern? This cannot be undone.', 'my-village-hall'); ?>');">
                            🗑 <?php _e('Delete Future Bookings', 'my-village-hall'); ?>
                        </a>
                    </p>
                    <p>
                        <a href="<?php echo wp_nonce_url(
                            admin_url('admin-post.php?action=myvh_delete_recurring_pattern&id=' . $pattern_id),
                            'myvh_delete_recurring_pattern'
                        ); ?>" class="button" style="color:#dc3232;" onclick="return confirm('<?php _e('Delete this pattern AND all its future bookings? This cannot be undone.', 'my-village-hall'); ?>');">
                            ✕ <?php _e('Delete Pattern & Future Bookings', 'my-village-hall'); ?>
                        </a>
                    </p>
                </div>

                <div class="myvh-card" style="margin-top:20px;">
                    <h2><?php _e('Summary', 'my-village-hall'); ?></h2>
                    <table class="form-table">
                        <tr><th><?php _e('Total bookings', 'my-village-hall'); ?></th><td><?php echo count($bookings); ?></td></tr>
                        <tr><th><?php _e('Upcoming', 'my-village-hall'); ?></th><td><?php echo count($future); ?></td></tr>
                        <tr><th><?php _e('Past / cancelled', 'my-village-hall'); ?></th><td><?php echo count($past); ?></td></tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Bookings table -->
        <div class="myvh-card" style="margin-top:20px;">
            <h2><?php _e('Bookings in this Pattern', 'my-village-hall'); ?></h2>
            <?php if (empty($bookings)): ?>
                <p><?php _e('No bookings yet.', 'my-village-hall'); ?></p>
            <?php else: ?>
            <table class="wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th><?php _e('Date', 'my-village-hall'); ?></th>
                        <th><?php _e('Time', 'my-village-hall'); ?></th>
                        <th><?php _e('Status', 'my-village-hall'); ?></th>
                        <th><?php _e('Actions', 'my-village-hall'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $status_colors = ['confirmed'=>'#46b450','pending'=>'#2271b1','cancelled'=>'#dc3232','completed'=>'#999'];
                foreach ($bookings as $b):
                    $is_past  = $b['StartDate'] < $today;
                    $sc = $status_colors[$b['Status']] ?? '#999';
                ?>
                <tr <?php if ($is_past) echo 'style="opacity:0.55;"'; ?>>
                    <td>
                        <strong><?php echo date('D j M Y', strtotime($b['StartDate'])); ?></strong>
                        <?php if ($b['StartDate'] === $today): ?>
                            <span style="background:#46b450;color:#fff;padding:1px 6px;border-radius:3px;font-size:11px;margin-left:5px;">TODAY</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php echo date('g:i A', strtotime($b['StartTime'])); ?> –
                        <?php echo date('g:i A', strtotime($b['EndTime'])); ?>
                    </td>
                    <td>
                        <span style="color:<?php echo $sc; ?>;">●</span>
                        <?php echo esc_html(ucfirst($b['Status'])); ?>
                    </td>
                    <td>
                        <a href="<?php echo admin_url('admin.php?page=my-village-hall&view=' . $b['Id']); ?>">
                            <?php _e('View', 'my-village-hall'); ?>
                        </a> |
                        <a href="<?php echo admin_url('admin.php?page=my-village-hall&edit=' . $b['Id']); ?>">
                            <?php _e('Edit', 'my-village-hall'); ?>
                        </a>
                        <?php if ($b['Status'] !== 'cancelled'): ?>
                            |
                            <a href="<?php echo wp_nonce_url(
                                admin_url('admin-post.php?action=myvh_cancel_booking&id=' . $b['Id']),
                                'myvh_cancel_booking'
                            ); ?>" style="color:#dc3232;" onclick="return confirm('<?php _e('Cancel this booking?', 'my-village-hall'); ?>');">
                                <?php _e('Cancel', 'my-village-hall'); ?>
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {

        var intervalLabels = {
            daily:       '<?php _e('day(s)', 'my-village-hall'); ?>',
            weekly:      '<?php _e('week(s)', 'my-village-hall'); ?>',
            monthly:     '<?php _e('month(s)', 'my-village-hall'); ?>',
            monthly_day: '',
            yearly:      '<?php _e('year(s)', 'my-village-hall'); ?>'
        };

        var ordinalLabels = {
            '1':'1st', '2':'2nd', '3':'3rd', '4':'4th', 'last':'last'
        };

        function syncType() {
            var t = $('#rp-type').val();
            var isMonthlyDay = (t === 'monthly_day');

            $('#rp-interval-row').toggle(!isMonthlyDay);
            $('#rp-monthly-day-row').toggle(isMonthlyDay);
            $('#rp-interval-label').text(intervalLabels[t] || '');

            // Keep hidden recurrence_interval in sync with the monthly_day variant
            if (isMonthlyDay) {
                $('input[name="recurrence_interval"]').val($('input[name="recurrence_interval_md"]').val());
            }
            updatePreview();
        }

        function updatePreview() {
            if ($('#rp-type').val() !== 'monthly_day') { $('#rp-preview').text(''); return; }
            var week = ordinalLabels[$('#rp-week').val()] || $('#rp-week').val();
            var day  = $('#rp-day option:selected').text();
            var n    = parseInt($('input[name="recurrence_interval_md"]').val()) || 1;
            var suffix = n > 1 ? ', every ' + n + ' months' : '';
            $('#rp-preview').text('e.g. ' + week + ' ' + day + ' of the month' + suffix);
        }

        // When monthly_day interval changes, keep the hidden field in sync
        $('input[name="recurrence_interval_md"]').on('input', function() {
            $('input[name="recurrence_interval"]').val($(this).val());
            updatePreview();
        });

        $('#rp-type').on('change', syncType);
        $('#rp-week, #rp-day').on('change', updatePreview);

        // Initialise
        syncType();

        // End condition toggles
        $('input[name="recurrence_end_type"]').on('change', function() {
            var isDate = $(this).val() === 'date';
            $('#rp-end-date').prop('disabled', !isDate);
            $('#rp-max-occ').prop('disabled', isDate);
        }).trigger('change');
    });
    </script>
    <?php
    return; // Don't fall through to list view
}

// ── LIST VIEW ─────────────────────────────────────────────────────────────────
$patterns = MYVH_Registry::get('recurring_pattern_service')->get_active_with_bookings() ?? [];
?>
<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Recurring Patterns', 'my-village-hall'); ?></h1>
    <hr class="wp-header-end">

    <?php if (isset($_GET['updated'])): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html($_GET['message'] ?? __('Saved.', 'my-village-hall')); ?></p>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['deleted'])): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e('Pattern deleted.', 'my-village-hall'); ?></p>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html($_GET['error']); ?></p>
        </div>
    <?php endif; ?>

    <div class="myvh-card">
        <?php if (empty($patterns)): ?>
            <p><?php _e('No recurring patterns found.', 'my-village-hall'); ?>
               <?php _e('Create a recurring booking via', 'my-village-hall'); ?>
               <a href="<?php echo admin_url('admin.php?page=my-village-hall&add=1'); ?>">
                   <?php _e('Add New Booking', 'my-village-hall'); ?>
               </a>.
            </p>
        <?php else: ?>
        <table class="wp-list-table widefat striped">
            <thead>
                <tr>
                    <th><?php _e('Customer', 'my-village-hall'); ?></th>
                    <th><?php _e('Room / Venue', 'my-village-hall'); ?></th>
                    <th><?php _e('Schedule', 'my-village-hall'); ?></th>
                    <th><?php _e('Range', 'my-village-hall'); ?></th>
                    <th><?php _e('Bookings', 'my-village-hall'); ?></th>
                    <th><?php _e('Status', 'my-village-hall'); ?></th>
                    <th><?php _e('Actions', 'my-village-hall'); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($patterns as $p):
                $interval_label = $p['RecurrenceInterval'] > 1
                    ? 'Every ' . $p['RecurrenceInterval'] . ' ' . $p['RecurrenceType'] . 's'
                    : ucfirst($p['RecurrenceType']);

                $range = date('j M Y', strtotime($p['StartDate']));
                if ($p['EndDate']) {
                    $range .= ' – ' . date('j M Y', strtotime($p['EndDate']));
                } elseif ($p['MaxOccurrences']) {
                    $range .= ' (' . $p['MaxOccurrences'] . ' times)';
                } else {
                    $range .= ' (ongoing)';
                }
            ?>
            <tr>
                <td>
                    <strong><?php echo esc_html($p['CustomerName']); ?></strong>
                </td>
                <td>
                    <?php echo esc_html($p['RoomName']); ?>
                    <br><small style="color:#666;"><?php echo esc_html($p['VenueName']); ?></small>
                </td>
                <td><?php echo esc_html(MYVH_Recurring_Pattern_Service::describe($p)); ?></td>
                <td><small><?php echo esc_html($range); ?></small></td>
                <td><?php echo intval($p['OccurrenceCount']); ?></td>
                <td>
                    <?php if ($p['IsActive']): ?>
                        <span style="color:#46b450;">● <?php _e('Active', 'my-village-hall'); ?></span>
                    <?php else: ?>
                        <span style="color:#999;">● <?php _e('Inactive', 'my-village-hall'); ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <a href="<?php echo admin_url('admin.php?page=myvh-recurring&edit=' . $p['Id']); ?>">
                        <?php _e('Edit', 'my-village-hall'); ?>
                    </a> |
                    <a href="<?php echo admin_url('admin.php?page=myvh-recurring&view=' . $p['Id']); ?>">
                        <?php _e('View', 'my-village-hall'); ?>
                    </a> |
                    <a href="<?php echo wp_nonce_url(
                        admin_url('admin-post.php?action=myvh_delete_recurring_pattern&id=' . $p['Id']),
                        'myvh_delete_recurring_pattern'
                    ); ?>" style="color:#dc3232;"
                       onclick="return confirm('<?php _e('Delete this pattern and all its future bookings?', 'my-village-hall'); ?>');">
                        <?php _e('Delete', 'my-village-hall'); ?>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
