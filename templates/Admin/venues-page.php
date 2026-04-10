<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('manage_myvh')) {
    wp_die(__('You do not have sufficient permissions to access this page.', 'my-village-hall'));
}

global $myvh_container;

use MYVH\Venues\VenueService;
use MYVH\Venues\VenueHoursRepository;
use MYVH\Availability\AvailabilityService;

$action   = isset($_GET['action']) ? sanitize_key($_GET['action']) : 'list';
$venue_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$venue_service = $myvh_container->get(VenueService::class);
$venue_hours_repository = $myvh_container->get(VenueHoursRepository::class);
$availability_service = $myvh_container->get(AvailabilityService::class);
$venue  = $venue_id ? $venue_service->get($venue_id) : null;
$venues = $venue_service->get_all();

$day_labels = [
    0 => __('Sunday', 'my-village-hall'),
    1 => __('Monday', 'my-village-hall'),
    2 => __('Tuesday', 'my-village-hall'),
    3 => __('Wednesday', 'my-village-hall'),
    4 => __('Thursday', 'my-village-hall'),
    5 => __('Friday', 'my-village-hall'),
    6 => __('Saturday', 'my-village-hall'),
];

$venue_hours_rows = $venue_id ? $venue_hours_repository->get_by_venue($venue_id) : [];
$venue_hours_index = [];
foreach ((array) $venue_hours_rows as $row) {
    $venue_hours_index[(int) ($row['DayOfWeek'] ?? -1)] = $row;
}

$default_open = $venue ? substr((string) ($venue['OpeningTime'] ?? '09:00'), 0, 5) : '09:00';
$default_close = $venue ? substr((string) ($venue['ClosingTime'] ?? '17:00'), 0, 5) : '17:00';

$venue_hours_by_day = [];
for ($day = 0; $day <= 6; $day++) {
    $row = $venue_hours_index[$day] ?? null;
    $is_closed = !empty($row['IsClosed']) ? 1 : 0;
    $opening_time = $is_closed ? '' : substr((string) ($row['OpeningTime'] ?? $default_open), 0, 5);
    $closing_time = $is_closed ? '' : substr((string) ($row['ClosingTime'] ?? $default_close), 0, 5);

    $venue_hours_by_day[$day] = [
        'is_closed' => $is_closed,
        'opening_time' => $opening_time,
        'closing_time' => $closing_time,
    ];
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <?php echo ($action === 'edit' || $action === 'add')
            ? ($venue_id ? __('Edit Venue', 'my-village-hall') : __('Add New Venue', 'my-village-hall'))
            : __('Venues', 'my-village-hall'); ?>
    </h1>

    <?php if ($action === 'list'): ?>

        <a href="<?php echo admin_url('admin.php?page=myvh-venues&action=add'); ?>" class="page-title-action">
            <?php _e('Add New', 'my-village-hall'); ?>
        </a>

        <hr class="wp-header-end">

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th><?php _e('Name', 'my-village-hall'); ?></th>
                    <th><?php _e('Short Name', 'my-village-hall'); ?></th>
                    <th><?php _e('Post Code', 'my-village-hall'); ?></th>
                    <th><?php _e('Address', 'my-village-hall'); ?></th>
                    <th><?php _e('Actions', 'my-village-hall'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($venues)): ?>
                    <tr><td colspan="6"><?php _e('No venues found', 'my-village-hall'); ?></td></tr>
                <?php else: ?>
                    <?php foreach ($venues as $venue): ?>
                        <tr>
                            <td><?php echo intval($venue['Id']); ?></td>
                            <td><strong><?php echo esc_html($venue['Name']); ?></strong></td>
                            <td><?php echo esc_html($venue['ShortName']); ?></td>
                            <td><?php echo esc_html($venue['PostCode']); ?></td>
                            <td><?php echo esc_html($venue['AddressLine1']); ?></td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=myvh-venues&action=edit&id=' . $venue['Id']); ?>">
                                    <?php _e('Edit', 'my-village-hall'); ?>
                                </a> |
                                <a href="<?php echo wp_nonce_url(
                                    admin_url('admin-post.php?action=myvh_delete_venue&id=' . $venue['Id']),
                                    'myvh_delete_venue'
                                ); ?>" class="link-delete">
                                    <?php _e('Delete', 'my-village-hall'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

    <?php else: ?>

        <hr class="wp-header-end">

        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="max-width:800px;">
            <input type="hidden" name="action" value="myvh_save_venue">
            <?php wp_nonce_field('myvh_save_venue'); ?>
            <input type="hidden" name="venue_id" value="<?php echo $venue_id; ?>">

            <table class="form-table">
                <tr>
                    <th><label for="name"><?php _e('Venue Name', 'my-village-hall'); ?> *</label></th>
                    <td><input type="text" name="name" required class="regular-text"
                        value="<?php echo $venue ? esc_attr($venue['Name']) : ''; ?>"></td>
                </tr>

                <tr>
                    <th><label><?php _e('Short Name', 'my-village-hall'); ?></label></th>
                    <td><input type="text" name="short_name" class="regular-text"
                        value="<?php echo $venue ? esc_attr($venue['ShortName']) : ''; ?>"></td>
                </tr>

                <tr>
                    <th><label><?php _e('Post Code', 'my-village-hall'); ?></label></th>
                    <td><input type="text" name="post_code" class="regular-text"
                        value="<?php echo $venue ? esc_attr($venue['PostCode']) : ''; ?>"></td>
                </tr>

                <tr>
                    <th><label><?php _e('Address', 'my-village-hall'); ?></label></th>
                    <td><input type="text" name="address_line1" class="large-text"
                        value="<?php echo $venue ? esc_attr($venue['AddressLine1']) : ''; ?>"></td>
                </tr>

                <tr>
                    <th><label><?php _e('Opening Time', 'my-village-hall'); ?></label></th>
                    <td>
                        <select name="opening_time">
                            <?php echo $availability_service->get_time_options($venue ? $venue['OpeningTime'] : '09:00', 0, 23,true); ?>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th><label><?php _e('Closing Time', 'my-village-hall'); ?></label></th>
                    <td>
                        <select name="closing_time">
                            <?php echo $availability_service->get_time_options($venue ? $venue['ClosingTime'] : '17:00', 0, 23,true); ?>
                            </select>
                    </td>
                </tr>

                <tr>
                    <th><?php _e('Daily Opening Hours', 'my-village-hall'); ?></th>
                    <td>
                        <table class="widefat striped" style="max-width:560px;">
                            <thead>
                                <tr>
                                    <th><?php _e('Day', 'my-village-hall'); ?></th>
                                    <th><?php _e('Closed', 'my-village-hall'); ?></th>
                                    <th><?php _e('Opens', 'my-village-hall'); ?></th>
                                    <th><?php _e('Closes', 'my-village-hall'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($day_labels as $day => $label): ?>
                                    <?php $day_row = $venue_hours_by_day[$day]; ?>
                                    <tr>
                                        <td>
                                            <?php echo esc_html($label); ?>
                                            <input type="hidden" name="opening_hours_by_day[<?php echo (int) $day; ?>][day_of_week]" value="<?php echo (int) $day; ?>">
                                        </td>
                                        <td>
                                            <input type="hidden" name="opening_hours_by_day[<?php echo (int) $day; ?>][is_closed]" value="0">
                                            <label>
                                                <input type="checkbox" name="opening_hours_by_day[<?php echo (int) $day; ?>][is_closed]" value="1" <?php checked((int) $day_row['is_closed'], 1); ?>>
                                                <?php _e('Closed', 'my-village-hall'); ?>
                                            </label>
                                        </td>
                                        <td>
                                            <select name="opening_hours_by_day[<?php echo (int) $day; ?>][opening_time]">
                                                <?php echo $availability_service->get_time_options($day_row['opening_time'] ?: $default_open, 0, 23, true); ?>
                                            </select>
                                        </td>
                                        <td>
                                            <select name="opening_hours_by_day[<?php echo (int) $day; ?>][closing_time]">
                                                <?php echo $availability_service->get_time_options($day_row['closing_time'] ?: $default_close, 0, 23, true); ?>
                                            </select>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <p class="description"><?php _e('Set each day as closed or choose opening and closing hours.', 'my-village-hall'); ?></p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <button class="button button-primary">
                    <?php echo $venue_id ? __('Update Venue', 'my-village-hall') : __('Create Venue', 'my-village-hall'); ?>
                </button>
                <a href="<?php echo admin_url('admin.php?page=myvh-venues'); ?>" class="button">
                    <?php _e('Cancel', 'my-village-hall'); ?>
                </a>
            </p>
        </form>

    <?php endif; ?>
</div>