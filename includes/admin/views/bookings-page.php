<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('manage_myvh')) {
    wp_die(__('Permission denied', 'my-village-hall'));
}

global $myvh_booking_repo, $myvh_customer_repo, $myvh_room_repo;

// Get filter parameters
$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'all';
$room_filter = isset($_GET['room_id']) ? intval($_GET['room_id']) : 0;
$customer_filter = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;

// Get data for filters
$rooms = $myvh_room_repo->get_all_with_venues();
$customers = $myvh_customer_repo->get_all();

// Get bookings (you'll need to implement filtering in your repository)
$bookings = $myvh_booking_repo->get_all(['orderby' => 'StartDate', 'order' => 'DESC']);

// Filter bookings in PHP (alternatively, add these filters to repository)
if ($status_filter !== 'all') {
    $bookings = array_filter($bookings, function($booking) use ($status_filter) {
        return $booking['Status'] === $status_filter;
    });
}

if ($room_filter) {
    $bookings = array_filter($bookings, function($booking) use ($room_filter) {
        return $booking['RoomId'] == $room_filter;
    });
}

if ($customer_filter) {
    $bookings = array_filter($bookings, function($booking) use ($customer_filter) {
        return $booking['CustomerId'] == $customer_filter;
    });
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('All Bookings', 'my-village-hall'); ?></h1>
    <a href="<?php echo admin_url('admin.php?page=myvh-calendar'); ?>" class="page-title-action">
        <?php _e('Calendar View', 'my-village-hall'); ?>
    </a>
    <a href="<?php echo admin_url('admin.php?page=my-village-hall&add=1'); ?>" class="page-title-action">
        <?php _e('Add New Booking', 'my-village-hall'); ?>
    </a>
    <hr class="wp-header-end">

    <?php if (isset($_GET['updated'])): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e('Booking saved successfully', 'my-village-hall'); ?></p>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['deleted'])): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e('Booking deleted successfully', 'my-village-hall'); ?></p>
        </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="myvh-card" style="margin-bottom: 20px;">
        <form method="get" action="<?php echo admin_url('admin.php'); ?>">
            <input type="hidden" name="page" value="my-village-hall">
            
            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <label>
                    <strong><?php _e('Status:', 'my-village-hall'); ?></strong>
                    <select name="status" style="margin-left: 5px;">
                        <option value="all" <?php selected($status_filter, 'all'); ?>><?php _e('All Statuses', 'my-village-hall'); ?></option>
                        <option value="pending" <?php selected($status_filter, 'pending'); ?>><?php _e('Pending', 'my-village-hall'); ?></option>
                        <option value="confirmed" <?php selected($status_filter, 'confirmed'); ?>><?php _e('Confirmed', 'my-village-hall'); ?></option>
                        <option value="cancelled" <?php selected($status_filter, 'cancelled'); ?>><?php _e('Cancelled', 'my-village-hall'); ?></option>
                        <option value="completed" <?php selected($status_filter, 'completed'); ?>><?php _e('Completed', 'my-village-hall'); ?></option>
                    </select>
                </label>

                <label>
                    <strong><?php _e('Room:', 'my-village-hall'); ?></strong>
                    <select name="room_id" style="margin-left: 5px;">
                        <option value="0"><?php _e('All Rooms', 'my-village-hall'); ?></option>
                        <?php foreach ($rooms as $room): ?>
                            <option value="<?php echo $room['Id']; ?>" <?php selected($room_filter, $room['Id']); ?>>
                                <?php echo esc_html($room['Name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    <strong><?php _e('Customer:', 'my-village-hall'); ?></strong>
                    <select name="customer_id" style="margin-left: 5px;">
                        <option value="0"><?php _e('All Customers', 'my-village-hall'); ?></option>
                        <?php foreach ($customers as $customer): ?>
                            <option value="<?php echo $customer['Id']; ?>" <?php selected($customer_filter, $customer['Id']); ?>>
                                <?php echo esc_html($customer['Name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <button type="submit" class="button"><?php _e('Filter', 'my-village-hall'); ?></button>
                
                <?php if ($status_filter !== 'all' || $room_filter || $customer_filter): ?>
                    <a href="<?php echo admin_url('admin.php?page=my-village-hall'); ?>" class="button">
                        <?php _e('Clear Filters', 'my-village-hall'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="myvh-card">
        <table class="wp-list-table widefat striped">
            <thead>
                <tr>
                    <th><?php _e('Date & Time', 'my-village-hall'); ?></th>
                    <th><?php _e('Customer', 'my-village-hall'); ?></th>
                    <th><?php _e('Room', 'my-village-hall'); ?></th>
                    <th><?php _e('Description', 'my-village-hall'); ?></th>
                    <th><?php _e('Status', 'my-village-hall'); ?></th>
                    <th><?php _e('Actions', 'my-village-hall'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($bookings)): ?>
                    <tr>
                        <td colspan="6">
                            <?php _e('No bookings found.', 'my-village-hall'); ?>
                            <a href="<?php echo admin_url('admin.php?page=my-village-hall&add=1'); ?>">
                                <?php _e('Create your first booking', 'my-village-hall'); ?>
                            </a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($bookings as $booking): ?>
                        <?php
                        // Get customer name
                        $customer = null;
                        foreach ($customers as $c) {
                            if ($c['Id'] == $booking['CustomerId']) {
                                $customer = $c;
                                break;
                            }
                        }
                        
                        // Get room name
                        $room = null;
                        foreach ($rooms as $r) {
                            if ($r['Id'] == $booking['RoomId']) {
                                $room = $r;
                                break;
                            }
                        }

                        // Status colors
                        $status_colors = [
                            'pending' => '#2271b1',
                            'confirmed' => '#46b450',
                            'cancelled' => '#dc3232',
                            'completed' => '#999'
                        ];
                        $status_color = $status_colors[$booking['Status']] ?? '#999';
                        
                        // Check if booking is in the past
                        $is_past = strtotime($booking['StartDate']) < strtotime('today');
                        ?>
                        <tr <?php if ($is_past) echo 'style="opacity: 0.6;"'; ?>>
                            <td>
                                <strong><?php echo date('D, M j, Y', strtotime($booking['StartDate'])); ?></strong>
                                <br>
                                <small style="color: #666;">
                                    <?php echo date('g:i A', strtotime($booking['StartTime'])); ?> - 
                                    <?php echo date('g:i A', strtotime($booking['EndTime'])); ?>
                                </small>
                                <?php if ($booking['RecurringPatternId']): ?>
                                    <br><small style="color: #2271b1;">🔄 <?php _e('Recurring', 'my-village-hall'); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($customer): ?>
                                    <strong><?php echo esc_html($customer['Name']); ?></strong>
                                    <br><small><?php echo esc_html($customer['Email']); ?></small>
                                <?php else: ?>
                                    <em><?php _e('Unknown', 'my-village-hall'); ?></em>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($room): ?>
                                    <?php echo esc_html($room['Name']); ?>
                                    <br><small style="color: #666;"><?php echo esc_html($room['VenueName']); ?></small>
                                <?php else: ?>
                                    <em><?php _e('Unknown', 'my-village-hall'); ?></em>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($booking['Description']): ?>
                                    <?php echo esc_html($booking['Description']); ?>
                                <?php else: ?>
                                    <span style="color: #999;">—</span>
                                <?php endif; ?>
                                
                                <?php if (!$booking['Public']): ?>
                                    <br><small style="color: #999;">🔒 <?php _e('Private', 'my-village-hall'); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="color: <?php echo $status_color; ?>;">●</span>
                                <?php echo esc_html(ucfirst($booking['Status'])); ?>
                            </td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=my-village-hall&edit=' . $booking['Id']); ?>">
                                    <?php _e('Edit', 'my-village-hall'); ?>
                                </a> |
                                <a href="<?php echo admin_url('admin.php?page=my-village-hall&view=' . $booking['Id']); ?>">
                                    <?php _e('View', 'my-village-hall'); ?>
                                </a>
                                <?php if ($booking['Status'] !== 'cancelled'): ?>
                                    |
                                    <a href="<?php echo wp_nonce_url(
                                        admin_url('admin-post.php?action=myvh_cancel_booking&id=' . $booking['Id']),
                                        'myvh_cancel_booking'
                                    ); ?>" style="color: #dc3232;" onclick="return confirm('<?php _e('Cancel this booking?', 'my-village-hall'); ?>');">
                                        <?php _e('Cancel', 'my-village-hall'); ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if (!empty($bookings)): ?>
            <div style="margin-top: 15px; padding: 10px; background: #f9f9f9; border-radius: 3px;">
                <strong><?php _e('Total:', 'my-village-hall'); ?></strong> 
                <?php echo count($bookings); ?> <?php _e('booking(s)', 'my-village-hall'); ?>
                
                <?php if ($status_filter !== 'all' || $room_filter || $customer_filter): ?>
                    <span style="color: #666;">
                        (<?php _e('filtered', 'my-village-hall'); ?>)
                    </span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="myvh-card" style="margin-top: 20px;">
        <h3><?php _e('Quick Actions', 'my-village-hall'); ?></h3>
        <p>
            <a href="<?php echo admin_url('admin.php?page=myvh-calendar'); ?>" class="button">
                📅 <?php _e('View Calendar', 'my-village-hall'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=my-village-hall&add=1'); ?>" class="button button-primary">
                ➕ <?php _e('New Booking', 'my-village-hall'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=myvh-customers'); ?>" class="button">
                👥 <?php _e('Manage Customers', 'my-village-hall'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=myvh-rooms'); ?>" class="button">
                🏛️ <?php _e('Manage Rooms', 'my-village-hall'); ?>
            </a>
        </p>
    </div>
</div>
