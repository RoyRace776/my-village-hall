<?php
namespace MYVH\Calendar;

use MYVH\Application\Services\CalendarRenderer;
use MYVH\Availability\AvailabilityService;
use MYVH\Rooms\RoomService;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Customer-facing calendar shortcode powered by DayPilot Lite.
 *
 * Usage: [myvh_calendar]
 *        [myvh_calendar venue_id="3"]
 *        [myvh_calendar view="week"]
 *
 * @package MyVillageHall
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CalendarShortcode {

    /** Shortcode tag */
    const TAG = 'myvh_calendar';
    const TAG_PUBLIC = 'myvh_public_calendar';

    /** REST namespace / route */
    const REST_NAMESPACE = 'myvh/v1';
    const REST_ROUTE     = '/public-events';

    private ?CalendarRenderer $renderer = null;

    private function renderer(): CalendarRenderer {
        if ( $this->renderer instanceof CalendarRenderer ) {
            return $this->renderer;
        }

        global $myvh_container;
        if ( isset( $myvh_container ) && is_object( $myvh_container ) && method_exists( $myvh_container, 'get' ) ) {
            $this->renderer = $myvh_container->get( CalendarRenderer::class );
        }

        if ( ! $this->renderer instanceof CalendarRenderer ) {
            $this->renderer = new CalendarRenderer(
                $myvh_container->get( CalendarService::class ),
                $myvh_container->get( AvailabilityService::class ),
                $myvh_container->get( RoomService::class )
            );
        }

        return $this->renderer;
    }

    public function init(): void {
        add_shortcode( self::TAG, [ $this, 'render' ] );
        add_shortcode( self::TAG_PUBLIC, [ $this, 'render' ] );
        add_action( 'rest_api_init', [ $this, 'register_rest_route' ] );
    }

    // ── REST endpoint ─────────────────────────────────────────────────────────

    public function register_rest_route(): void {
        $this->renderer()->register_rest_route();
    }

    /**
     * Return events as a JSON array in the DayPilot event format.
     *
     * Only bookings that are:
     *   - Public = 1
     *   - Status IN (BookingStatus::CONFIRMED, BookingStatus::PENDING)
     * are returned. Customer names are never exposed.
     */
    public function get_events( WP_REST_Request $request ): WP_REST_Response {
        return $this->renderer()->get_events( $request );
    }

    // ── Shortcode renderer ────────────────────────────────────────────────────

    /**
     * @param array $atts  Shortcode attributes.
     * @return string      HTML output.
     */
    public function render( $atts ): string {
        return $this->renderer()->render( (array) $atts );
    }
}
