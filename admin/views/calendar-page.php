<?php
if (!defined('ABSPATH')) exit;

global $myvh_container;
$addon_service = $myvh_container->get(MYVH_Addon_Service::class);
$all_addons = $addon_service->get_all(['orderby' => 'DisplayOrder', 'order' => 'ASC']);
$available_addons = array_values(array_filter($all_addons ?? [], fn($a) => !empty($a['IsActive'])));
?>

<div class="wrap">

<h1><?php _e('Bookings Calendar', 'my-village-hall'); ?></h1>

<div class="myvh-calendar-toolbar">

    <div class="myvh-calendar-nav">
        <button id="myvh-prev" class="button">&lt;</button>
        <button id="myvh-today" class="button button-primary">
            <?php _e('Today', 'my-village-hall'); ?>
        </button>
        <button id="myvh-next" class="button">&gt;</button>
    </div>

    <div class="myvh-calendar-views">
        <button id="myvh_rooms" class="button myvh-view-btn" data-view="Resources">
            <?php _e('Rooms', 'my-village-hall'); ?>
        </button>

        <button id="myvh-day" class="button myvh-view-btn" data-view="Day">
            <?php _e('Day', 'my-village-hall'); ?>
        </button>

        <button id="myvh-week" class="button myvh-view-btn active" data-view="Week">
            <?php _e('Week', 'my-village-hall'); ?>
        </button>

        <button id="myvh-month" class="button myvh-view-btn" data-view="Month">
            <?php _e('Month', 'my-village-hall'); ?>
        </button>
    </div>

</div>

<!-- Week / Day calendar -->
<div id="myvh-calendar" style="height:700px;"></div>


</div>

<?php
add_action('admin_footer', function() use ($available_addons) {
    ?>
        <div id="myvh-booking-modal" class="myvh-modal hidden">
        <div class="myvh-modal-content">
            <h2>Create Booking</h2>

            <form id="myvh-booking-form">
                <input type="hidden" name="start">
                <input type="hidden" name="end">

                <table class="form-table">
                    <tr>
                        <th>Room</th>
                        <td><select name="room_id"></select></td>
                    </tr>
                    <tr>
                        <th>Customer</th>
                        <td><select name="customer_id"></select></td>
                    </tr>
                    <tr>
                        <th>Organisation</th>
                        <td><select name="organisation_id"></select></td>
                    </tr>
                    <tr>
                        <th>Description</th>
                        <td><input type="text" name="text"></td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td>
                            <select name="status">
                                <option value="pending" selected>Pending</option>
                                <option value="confirmed">Confirmed</option>
                                <option value="cancelled">Cancelled</option>
                                <option value="completed">Completed</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>Recurring</th>
                        <td>
                            <label>
                                <input type="checkbox" name="is_recurring" value="1" id="myvh-modal-is-recurring">
                                Create recurring bookings
                            </label>
                        </td>
                    </tr>
                </table>

                <div id="myvh-modal-recurring-options" style="display:none; margin: 15px 0; padding: 12px; background: #f6f7f7; border: 1px solid #dcdcde; border-radius: 4px;">
                    <table class="form-table" style="margin-top:0;">
                        <tr>
                            <th>Type</th>
                            <td>
                                <select name="recurrence_type" id="myvh-modal-rec-type">
                                    <option value="daily">Daily</option>
                                    <option value="weekly" selected>Weekly</option>
                                    <option value="monthly">Monthly (same date)</option>
                                    <option value="monthly_day">Monthly (specific weekday)</option>
                                    <option value="yearly">Yearly</option>
                                </select>
                            </td>
                        </tr>
                        <tr id="myvh-modal-interval-row">
                            <th>Interval</th>
                            <td>
                                Every <input type="number" name="recurrence_interval" value="1" min="1" max="52" class="small-text"> <span id="myvh-modal-interval-label">week(s)</span>
                            </td>
                        </tr>
                        <tr id="myvh-modal-monthly-day-row" style="display:none;">
                            <th>Pattern</th>
                            <td>
                                <select name="recurrence_week">
                                    <option value="1">1st</option>
                                    <option value="2">2nd</option>
                                    <option value="3">3rd</option>
                                    <option value="4">4th</option>
                                    <option value="last">Last</option>
                                </select>
                                <select name="recurrence_day">
                                    <option value="monday">Monday</option>
                                    <option value="tuesday">Tuesday</option>
                                    <option value="wednesday">Wednesday</option>
                                    <option value="thursday">Thursday</option>
                                    <option value="friday">Friday</option>
                                    <option value="saturday">Saturday</option>
                                    <option value="sunday">Sunday</option>
                                </select>
                                Every <input type="number" name="recurrence_interval_md" value="1" min="1" max="24" class="small-text"> month(s)
                            </td>
                        </tr>
                        <tr>
                            <th>Ends</th>
                            <td>
                                <label><input type="radio" name="recurrence_end_type" value="date" checked> On date</label>
                                <input type="date" name="recurrence_end_date" value="<?php echo esc_attr(date('Y-m-d', strtotime('+3 months'))); ?>">
                                <br>
                                <label><input type="radio" name="recurrence_end_type" value="count"> After occurrences</label>
                                <input type="number" name="max_occurrences" value="10" min="1" max="365" class="small-text" disabled>
                            </td>
                        </tr>
                    </table>
                </div>

                <?php if (!empty($available_addons)): ?>
                <div style="margin: 15px 0;">
                    <h3 style="margin:0 0 8px;">Add-ons</h3>
                    <table class="widefat striped" id="myvh-modal-addons-table">
                        <thead>
                            <tr>
                                <th style="width:30px;"></th>
                                <th>Add-on</th>
                                <th style="width:110px;">Unit Price</th>
                                <th style="width:90px;">Qty</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($available_addons as $i => $addon): ?>
                                <tr class="myvh-modal-addon-row">
                                    <td>
                                        <input type="checkbox" class="myvh-modal-addon-checkbox" value="1">
                                        <input type="hidden" name="addons[<?php echo $i; ?>][addon_id]" value="<?php echo intval($addon['Id']); ?>">
                                        <input type="hidden" name="addons[<?php echo $i; ?>][enabled]" class="myvh-modal-addon-enabled" value="0">
                                    </td>
                                    <td>
                                        <strong><?php echo esc_html($addon['Name']); ?></strong>
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" min="0" name="addons[<?php echo $i; ?>][unit_price]" class="small-text myvh-modal-addon-price" value="<?php echo esc_attr(number_format((float)$addon['Price'], 2, '.', '')); ?>" disabled>
                                    </td>
                                    <td>
                                        <input type="number" step="0.5" min="0.5" name="addons[<?php echo $i; ?>][quantity]" class="small-text myvh-modal-addon-qty" value="1" disabled>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

                <p>
                    <button type="submit" class="button button-primary">Create Booking</button>
                    <button type="button" class="button myvh-cancel">Cancel</button>
                </p>
            </form>
        </div>
    </div>
    <?php
});
