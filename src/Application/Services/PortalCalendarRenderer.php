<?php

declare(strict_types=1);

namespace MYVH\Application\Services;

final class PortalCalendarRenderer {
    public function render( array $attributes = [] ): string {
        if ( ! is_user_logged_in() ) {
            return '<p>Please log in to view the calendar.</p>';
        }

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
                ajax_url: "<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>",
                endpoint: "myvh_portal_calendar_events"
            };
        </script>
        <?php
        return (string) ob_get_clean();
    }
}