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
        <button class="button myvh-view-btn" data-view="Resources">
            <?php _e('Rooms', 'my-village-hall'); ?>
        </button>

        <button class="button myvh-view-btn" data-view="Day">
            <?php _e('Day', 'my-village-hall'); ?>
        </button>

        <button class="button myvh-view-btn active" data-view="Week">
            <?php _e('Week', 'my-village-hall'); ?>
        </button>

        <button class="button myvh-view-btn" data-view="Month">
            <?php _e('Month', 'my-village-hall'); ?>
        </button>
    </div>

</div>

<!-- Week / Day calendar -->
<div id="myvh-calendar" style="height:700px;"></div>


</div>
