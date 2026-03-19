<?php
if (!defined('ABSPATH')) exit;
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
add_action('admin_footer', function() {
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
                </table>

                <p>
                    <button type="submit" class="button button-primary">Create Booking</button>
                    <button type="button" class="button myvh-cancel">Cancel</button>
                </p>
            </form>
        </div>
    </div>
    <?php
});
