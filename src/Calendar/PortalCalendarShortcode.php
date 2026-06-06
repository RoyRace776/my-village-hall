<?php
namespace MYVH\Calendar;

use MYVH\Application\Services\PortalCalendarRenderer;

class PortalCalendarShortcode {

    public function register(): void {
        add_shortcode('myvh_portal_calendar', [$this, 'render']);
    }

    public function render($atts = []): string {
        return ( new PortalCalendarRenderer() )->render( (array) $atts );
    }
}
