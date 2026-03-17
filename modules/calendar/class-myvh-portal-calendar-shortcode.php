<?php

class MYVH_Portal_Calendar_Shortcode {

    public function register() {
        add_shortcode('myvh_portal_calendar', [$this, 'render']);
    }

    public function render($atts = []) {

        if (!is_user_logged_in()) {
            return '<p>Please log in to view the calendar.</p>';
        }

        // Ensure assets are loaded
        //AssetLoader::enqueue_portal();

        ob_start();
        ?>
        <div id="myvh-calendar-app">

            <div class="myvh-calendar-toolbar">
                <button data-view="day">Day</button>
                <button data-view="week">Week</button>
                <button data-view="month">Month</button>
            </div>

            <div id="myvh-calendar"></div>

        </div>

        <script>
            window.myvhCalendarConfig = {
                ajaxUrl: "<?php echo admin_url('admin-ajax.php'); ?>",
                endpoint: "myvh_portal_calendar_events"
            };
        </script>
        <?php
        return ob_get_clean();
    }
}
