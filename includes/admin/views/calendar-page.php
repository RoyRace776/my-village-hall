<?php
if (!defined('ABSPATH')) exit;
?>

<div class="wrap">

<h1><?php _e('Bookings Calendar', 'my-village-hall'); ?></h1>

<div class="myvh-calendar-toolbar">

    <div class="myvh-calendar-nav">
        <button id="vbc-prev" class="button">&lt;</button>
        <button id="vbc-today" class="button button-primary">
            <?php _e('Today', 'my-village-hall'); ?>
        </button>
        <button id="vbc-next" class="button">&gt;</button>
    </div>

    <div class="myvh-calendar-views">
        <button class="button vbc-view-btn active" data-view="Resources">
            <?php _e('Rooms', 'my-village-hall'); ?>
        </button>

        <button class="button vbc-view-btn" data-view="Day">
            <?php _e('Day', 'my-village-hall'); ?>
        </button>

        <button class="button vbc-view-btn" data-view="Week">
            <?php _e('Week', 'my-village-hall'); ?>
        </button>

        <button class="button vbc-view-btn" data-view="Month">
            <?php _e('Month', 'my-village-hall'); ?>
        </button>
    </div>

</div>

<div id="vbc-calendar" style="height:700px;"></div>

</div>