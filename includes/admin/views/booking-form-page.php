<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('manage_myvh')) {
    wp_die(__('Permission denied', 'my-village-hall'));
}

global $myvh_booking_repo, $myvh_customer_repo, $myvh_room_repo, $myvh_recurring_pattern_repo;

$edit_id = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
$view_id = isset($_GET['view']) ? intval($_GET['view']) : 0;
$booking_id = $edit_id ?: $view_id;

$edit_booking = $booking_id ? $myvh_booking_repo->get_by_id($booking_id) : null;
$is_view_mode = $view_id > 0;

$customers = $myvh_customer_repo->get_all();
$rooms = $myvh_room_repo->get_all_with_venues();

// Get recurring pattern if exists
$recurring_pattern = null;
if ($edit_booking && $edit_booking['RecurringPatternId']) {
    $recurring_pattern = $myvh_recurring_pattern_repo->get_by_id($edit_booking['RecurringPatternId']);
}
?>

<div class="wrap">
    <?php if ($is_view_mode): ?>
        <h1><?php _e('View Booking', 'my-village-hall'); ?></h1>
    <?php elseif ($edit_booking): ?>
        <h1><?php _e('Edit Booking', 'my-village-hall'); ?></h1>
    <?php else: ?>
        <h1><?php _e('Add New Booking', 'my-village-hall'); ?></h1>
    <?php endif; ?>
    
    <hr class="wp-header-end">

    <?php if (isset($_GET['error'])): ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html($_GET['error']); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($is_view_mode): ?>
        <!-- VIEW MODE -->
        <div class="myvh-row">
            <div class="myvh-col-60">
                <div class="myvh-card">
                    <h2><?php _e('Booking Details', 'my-village-hall'); ?></h2>
                    
                    <?php
                    $customer = $myvh_customer_repo->get_by_id($edit_booking['CustomerId']);
                    $room = $myvh_room_repo->get_by_id($edit_booking['RoomId']);
                    
                    $status_colors = [
                        'pending' => '#2271b1',
                        'confirmed' => '#46b450',
                        'cancelled' => '#dc3232',
                        'completed' => '#999'
                    ];
                    $status_color = $status_colors[$edit_booking['Status']] ?? '#999';
                    ?>
                    
                    <table class="form-table">
                        <tr>
                            <th><?php _e('Booking ID', 'my-village-hall'); ?></th>
                            <td>#<?php echo $edit_booking['Id']; ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Status', 'my-village-hall'); ?></th>
                            <td>
                                <span style="color: <?php echo $status_color; ?>; font-weight: bold;">
                                    ● <?php echo esc_html(ucfirst($edit_booking['Status'])); ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Customer', 'my-village-hall'); ?></th>
                            <td>
                                <strong><?php echo $customer ? esc_html($customer['Name']) : __('Unknown', 'my-village-hall'); ?></strong>
                                <?php if ($customer): ?>
                                    <br><?php echo esc_html($customer['Email']); ?>
                                    <?php if ($customer['PhoneNumber']): ?>
                                        <br><?php echo esc_html($customer['PhoneNumber']); ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Room', 'my-village-hall'); ?></th>
                            <td>
                                <strong><?php echo $room ? esc_html($room['Name']) : __('Unknown', 'my-village-hall'); ?></strong>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Date', 'my-village-hall'); ?></th>
                            <td>
                                <strong><?php echo date('l, F j, Y', strtotime($edit_booking['StartDate'])); ?></strong>
                                <?php if ($edit_booking['StartDate'] !== $edit_booking['EndDate']): ?>
                                    to <?php echo date('l, F j, Y', strtotime($edit_booking['EndDate'])); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Time', 'my-village-hall'); ?></th>
                            <td>
                                <?php echo date('g:i A', strtotime($edit_booking['StartTime'])); ?> - 
                                <?php echo date('g:i A', strtotime($edit_booking['EndTime'])); ?>
                                
                                <?php
                                $duration_start = new DateTime($edit_booking['StartTime']);
                                $duration_end = new DateTime($edit_booking['EndTime']);
                                $duration = $duration_start->diff($duration_end);
                                $hours = $duration->h + ($duration->days * 24);
                                ?>
                                <br><small style="color: #666;">
                                    (<?php echo $hours; ?> <?php echo $hours == 1 ? __('hour', 'my-village-hall') : __('hours', 'my-village-hall'); ?>
                                    <?php if ($duration->i > 0): ?>
                                        <?php echo $duration->i; ?> <?php _e('minutes', 'my-village-hall'); ?>
                                    <?php endif; ?>)
                                </small>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Description', 'my-village-hall'); ?></th>
                            <td><?php echo $edit_booking['Description'] ? esc_html($edit_booking['Description']) : '<em>' . __('None', 'my-village-hall') . '</em>'; ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Visibility', 'my-village-hall'); ?></th>
                            <td>
                                <?php if ($edit_booking['Public']): ?>
                                    🌐 <?php _e('Public (visible on calendar)', 'my-village-hall'); ?>
                                <?php else: ?>
                                    🔒 <?php _e('Private (hidden from public)', 'my-village-hall'); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if ($recurring_pattern): ?>
                        <tr>
                            <th><?php _e('Recurring Pattern', 'my-village-hall'); ?></th>
                            <td>
                                🔄 <?php echo esc_html(ucfirst($recurring_pattern['RecurrenceType'])); ?>
                                <?php if ($recurring_pattern['RecurrenceInterval'] > 1): ?>
                                    (every <?php echo $recurring_pattern['RecurrenceInterval']; ?>)
                                <?php endif; ?>
                                <br>
                                <a href="<?php echo admin_url('admin.php?page=myvh-recurring'); ?>">
                                    <?php _e('View Recurring Patterns', 'my-village-hall'); ?>
                                </a>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <th><?php _e('Created', 'my-village-hall'); ?></th>
                            <td><?php echo date('F j, Y g:i A', strtotime($edit_booking['Created'])); ?></td>
                        </tr>
                    </table>

                    <p>
                        <a href="<?php echo admin_url('admin.php?page=my-village-hall&edit=' . $booking_id); ?>" class="button button-primary">
                            <?php _e('Edit Booking', 'my-village-hall'); ?>
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=my-village-hall'); ?>" class="button">
                            <?php _e('Back to All Bookings', 'my-village-hall'); ?>
                        </a>
                        <?php if ($edit_booking['Status'] !== 'cancelled'): ?>
                            <a href="<?php echo wp_nonce_url(
                                admin_url('admin-post.php?action=myvh_cancel_booking&id=' . $booking_id),
                                'myvh_cancel_booking'
                            ); ?>" class="button" style="color: #dc3232;" onclick="return confirm('<?php _e('Cancel this booking?', 'my-village-hall'); ?>');">
                                <?php _e('Cancel Booking', 'my-village-hall'); ?>
                            </a>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>

    <?php else: ?>
        <!-- EDIT/ADD MODE -->
        <div class="myvh-card">
            <?php if (empty($customers)): ?>
                <div class="notice notice-warning">
                    <p>
                        <?php _e('You need to create at least one customer before making bookings.', 'my-village-hall'); ?>
                        <a href="<?php echo admin_url('admin.php?page=myvh-customers'); ?>" class="button button-small">
                            <?php _e('Add Customer', 'my-village-hall'); ?>
                        </a>
                    </p>
                </div>
            <?php elseif (empty($rooms)): ?>
                <div class="notice notice-warning">
                    <p>
                        <?php _e('You need to create at least one room before making bookings.', 'my-village-hall'); ?>
                        <a href="<?php echo admin_url('admin.php?page=myvh-rooms'); ?>" class="button button-small">
                            <?php _e('Add Room', 'my-village-hall'); ?>
                        </a>
                    </p>
                </div>
            <?php else: ?>

            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" id="booking-form">
                <input type="hidden" name="action" value="myvh_save_booking">
                <?php wp_nonce_field('myvh_save_booking'); ?>
                <?php if ($edit_booking): ?>
                    <input type="hidden" name="booking_id" value="<?php echo $edit_booking['Id']; ?>">
                <?php endif; ?>

                <div class="myvh-row">
                    <!-- Left Column -->
                    <div class="myvh-col-60">
                        <h2><?php _e('Booking Information', 'my-village-hall'); ?></h2>
                        
                        <table class="form-table">
                            <tr>
                                <th><?php _e('Customer', 'my-village-hall'); ?> *</th>
                                <td>
                                    <select name="customer_id" required class="regular-text">
                                        <option value=""><?php _e('Select Customer', 'my-village-hall'); ?></option>
                                        <?php foreach ($customers as $customer): ?>
                                            <option value="<?php echo $customer['Id']; ?>" 
                                                <?php selected($edit_booking && $edit_booking['CustomerId'] == $customer['Id']); ?>>
                                                <?php echo esc_html($customer['Name'] . ' (' . $customer['Email'] . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description">
                                        <?php _e('Or', 'my-village-hall'); ?> 
                                        <a href="<?php echo admin_url('admin.php?page=myvh-customers&add=1'); ?>" target="_blank">
                                            <?php _e('add a new customer', 'my-village-hall'); ?>
                                        </a>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th><?php _e('Room', 'my-village-hall'); ?> *</th>
                                <td>
                                    <select name="room_id" required class="regular-text" id="room-select">
                                        <option value=""><?php _e('Select Room', 'my-village-hall'); ?></option>
                                        <?php 
                                        $current_venue = '';
                                        foreach ($rooms as $room): 
                                            if ($current_venue !== $room['VenueName']): 
                                                if ($current_venue !== '') echo '</optgroup>';
                                                echo '<optgroup label="' . esc_attr($room['VenueName']) . '">';
                                                $current_venue = $room['VenueName'];
                                            endif;
                                        ?>
                                            <option value="<?php echo $room['Id']; ?>" 
                                                data-opening="<?php echo esc_attr($room['OpeningTime']); ?>"
                                                data-closing="<?php echo esc_attr($room['ClosingTime']); ?>"
                                                <?php selected($edit_booking && $edit_booking['RoomId'] == $room['Id']); ?>>
                                                <?php echo esc_html($room['Name']); ?>
                                                <?php if ($room['Capacity']): ?>
                                                    (<?php echo $room['Capacity']; ?> people)
                                                <?php endif; ?>
                                            </option>
                                        <?php 
                                        endforeach;
                                        if ($current_venue !== '') echo '</optgroup>';
                                        ?>
                                    </select>
                                </td>
                            </tr>

                            <tr>
                                <th><?php _e('Start Date', 'my-village-hall'); ?> *</th>
                                <td>
                                    <input type="date" name="start_date" required class="regular-text"
                                        value="<?php echo $edit_booking ? esc_attr($edit_booking['StartDate']) : date('Y-m-d'); ?>"
                                        min="<?php echo date('Y-m-d'); ?>">
                                </td>
                            </tr>

                            <tr>
                                <th><?php _e('End Date', 'my-village-hall'); ?></th>
                                <td>
                                    <input type="date" name="end_date" class="regular-text"
                                        value="<?php echo $edit_booking ? esc_attr($edit_booking['EndDate']) : ''; ?>">
                                    <p class="description"><?php _e('Leave blank for same-day booking', 'my-village-hall'); ?></p>
                                </td>
                            </tr>

                            <tr>
                                <th><?php _e('Start Time', 'my-village-hall'); ?> *</th>
                                <td>
                                    <input type="time" name="start_time" required class="regular-text" id="start-time"
                                        value="<?php echo $edit_booking ? esc_attr($edit_booking['StartTime']) : '09:00'; ?>">
                                </td>
                            </tr>

                            <tr>
                                <th><?php _e('End Time', 'my-village-hall'); ?> *</th>
                                <td>
                                    <input type="time" name="end_time" required class="regular-text" id="end-time"
                                        value="<?php echo $edit_booking ? esc_attr($edit_booking['EndTime']) : '17:00'; ?>">
                                    <p class="description" id="duration-display"></p>
                                </td>
                            </tr>

                            <tr>
                                <th><?php _e('Description', 'my-village-hall'); ?></th>
                                <td>
                                    <textarea name="description" class="large-text" rows="3" 
                                        placeholder="<?php _e('Purpose of the booking, event details, etc.', 'my-village-hall'); ?>"><?php echo $edit_booking ? esc_textarea($edit_booking['Description']) : ''; ?></textarea>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- Right Column -->
                    <div class="myvh-col-40">
                        <h2><?php _e('Booking Options', 'my-village-hall'); ?></h2>
                        
                        <table class="form-table">
                            <tr>
                                <th><?php _e('Status', 'my-village-hall'); ?></th>
                                <td>
                                    <select name="status" class="regular-text">
                                        <option value="pending" <?php selected($edit_booking && $edit_booking['Status'] == 'pending'); ?>>
                                            <?php _e('Pending', 'my-village-hall'); ?>
                                        </option>
                                        <option value="confirmed" <?php selected(!$edit_booking || $edit_booking['Status'] == 'confirmed'); ?>>
                                            <?php _e('Confirmed', 'my-village-hall'); ?>
                                        </option>
                                        <option value="cancelled" <?php selected($edit_booking && $edit_booking['Status'] == 'cancelled'); ?>>
                                            <?php _e('Cancelled', 'my-village-hall'); ?>
                                        </option>
                                        <option value="completed" <?php selected($edit_booking && $edit_booking['Status'] == 'completed'); ?>>
                                            <?php _e('Completed', 'my-village-hall'); ?>
                                        </option>
                                    </select>
                                </td>
                            </tr>

                            <tr>
                                <th><?php _e('Visibility', 'my-village-hall'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="public" value="1" 
                                            <?php checked(!$edit_booking || $edit_booking['Public']); ?>>
                                        <?php _e('Show on public calendar', 'my-village-hall'); ?>
                                    </label>
                                    <p class="description"><?php _e('Uncheck to hide from public view', 'my-village-hall'); ?></p>
                                </td>
                            </tr>

                            <?php if (!$edit_booking): ?>
                            <tr>
                                <th><?php _e('Recurring', 'my-village-hall'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="is_recurring" value="1" id="is-recurring">
                                        <?php _e('Make this a recurring booking', 'my-village-hall'); ?>
                                    </label>
                                </td>
                            </tr>
                            </table>

                            <div id="recurring-options" style="display: none; margin-top: 20px; padding: 15px; background: #f9f9f9; border-radius: 4px;">
                                <h3><?php _e('Recurring Pattern', 'my-village-hall'); ?></h3>
                                
                                <table class="form-table">
                                    <tr>
                                        <th><?php _e('Repeat', 'my-village-hall'); ?></th>
                                        <td>
                                            <select name="recurrence_type" class="regular-text">
                                                <option value="daily"><?php _e('Daily', 'my-village-hall'); ?></option>
                                                <option value="weekly" selected><?php _e('Weekly', 'my-village-hall'); ?></option>
                                                <option value="monthly"><?php _e('Monthly', 'my-village-hall'); ?></option>
                                                <option value="yearly"><?php _e('Yearly', 'my-village-hall'); ?></option>
                                            </select>
                                        </td>
                                    </tr>

                                    <tr>
                                        <th><?php _e('Every', 'my-village-hall'); ?></th>
                                        <td>
                                            <input type="number" name="recurrence_interval" value="1" min="1" max="52" class="small-text">
                                            <span id="interval-label"><?php _e('week(s)', 'my-village-hall'); ?></span>
                                        </td>
                                    </tr>

                                    <tr>
                                        <th><?php _e('Ends', 'my-village-hall'); ?></th>
                                        <td>
                                            <label>
                                                <input type="radio" name="recurrence_end_type" value="date" checked>
                                                <?php _e('On', 'my-village-hall'); ?>
                                                <input type="date" name="recurrence_end_date" class="regular-text" 
                                                    value="<?php echo date('Y-m-d', strtotime('+3 months')); ?>">
                                            </label>
                                            <br>
                                            <label>
                                                <input type="radio" name="recurrence_end_type" value="count">
                                                <?php _e('After', 'my-village-hall'); ?>
                                                <input type="number" name="max_occurrences" value="10" min="1" max="365" class="small-text">
                                                <?php _e('occurrences', 'my-village-hall'); ?>
                                            </label>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <table class="form-table">
                            <?php endif; ?>
                        </table>

                        <?php if ($edit_booking && $recurring_pattern): ?>
                        <div style="padding: 15px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; margin-top: 20px;">
                            <p style="margin: 0;">
                                <strong>⚠️ <?php _e('Note:', 'my-village-hall'); ?></strong>
                                <?php _e('This is part of a recurring pattern. Changes will only affect this booking.', 'my-village-hall'); ?>
                                <a href="<?php echo admin_url('admin.php?page=myvh-recurring'); ?>">
                                    <?php _e('Manage pattern', 'my-village-hall'); ?>
                                </a>
                            </p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <p class="submit">
                    <button type="submit" class="button button-primary button-large">
                        <?php echo $edit_booking ? __('Update Booking', 'my-village-hall') : __('Create Booking', 'my-village-hall'); ?>
                    </button>
                    <a href="<?php echo admin_url('admin.php?page=my-village-hall'); ?>" class="button button-large">
                        <?php _e('Cancel', 'my-village-hall'); ?>
                    </a>
                </p>
            </form>

            <?php endif; ?>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Show/hide recurring options
            $('#is-recurring').on('change', function() {
                $('#recurring-options').toggle(this.checked);
            });

            // Update interval label based on recurrence type
            $('select[name="recurrence_type"]').on('change', function() {
                const labels = {
                    'daily': '<?php _e('day(s)', 'my-village-hall'); ?>',
                    'weekly': '<?php _e('week(s)', 'my-village-hall'); ?>',
                    'monthly': '<?php _e('month(s)', 'my-village-hall'); ?>',
                    'yearly': '<?php _e('year(s)', 'my-village-hall'); ?>'
                };
                $('#interval-label').text(labels[$(this).val()]);
            });

            // Calculate and display duration
            function updateDuration() {
                const startTime = $('#start-time').val();
                const endTime = $('#end-time').val();
                
                if (startTime && endTime) {
                    const start = new Date('2000-01-01 ' + startTime);
                    const end = new Date('2000-01-01 ' + endTime);
                    const diff = (end - start) / 1000 / 60; // minutes
                    
                    if (diff > 0) {
                        const hours = Math.floor(diff / 60);
                        const minutes = diff % 60;
                        let durationText = '';
                        
                        if (hours > 0) {
                            durationText += hours + ' <?php _e('hour(s)', 'my-village-hall'); ?> ';
                        }
                        if (minutes > 0) {
                            durationText += minutes + ' <?php _e('minutes', 'my-village-hall'); ?>';
                        }
                        
                        $('#duration-display').text('<?php _e('Duration:', 'my-village-hall'); ?> ' + durationText.trim());
                    } else {
                        $('#duration-display').text('<?php _e('End time must be after start time', 'my-village-hall'); ?>').css('color', 'red');
                    }
                }
            }

            $('#start-time, #end-time').on('change', updateDuration);
            updateDuration(); // Initial calculation

            // Auto-fill times based on room selection
            $('#room-select').on('change', function() {
                const selected = $(this).find(':selected');
                const opening = selected.data('opening');
                const closing = selected.data('closing');
                
                if (opening && !$('#start-time').val()) {
                    $('#start-time').val(opening);
                }
                if (closing && !$('#end-time').val()) {
                    $('#end-time').val(closing);
                }
                updateDuration();
            });

            // Auto-set end date to start date if empty
            $('input[name="start_date"]').on('change', function() {
                if (!$('input[name="end_date"]').val()) {
                    $('input[name="end_date"]').val($(this).val());
                }
            });

            // Form validation
            $('#booking-form').on('submit', function(e) {
                const startTime = $('#start-time').val();
                const endTime = $('#end-time').val();
                
                if (startTime && endTime) {
                    const start = new Date('2000-01-01 ' + startTime);
                    const end = new Date('2000-01-01 ' + endTime);
                    
                    if (end <= start) {
                        e.preventDefault();
                        alert('<?php _e('End time must be after start time', 'my-village-hall'); ?>');
                        return false;
                    }
                }
            });
        });
        </script>
    <?php endif; ?>
</div>
