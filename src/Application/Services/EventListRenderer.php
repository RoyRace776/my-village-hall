<?php

declare(strict_types=1);

namespace MYVH\Application\Services;

use MYVH\Calendar\CalendarService;
use MYVH\Calendar\EventDetailShortcode;
use MYVH\Pages\Support\PageContentMatcher;

final class EventListRenderer {
    public function __construct(private CalendarService $calendar_service) {
    }

    public function render( array $attributes = [] ): string {
        $attributes = shortcode_atts(
            [
                'start' => gmdate( 'Y-m-01' ),
                'end' => gmdate( 'Y-m-t' ),
                'venue_id' => 0,
                'room_id' => 0,
            ],
            $attributes
        );

        wp_enqueue_style( 'myvh-event-list' );

        try {
            $events = $this->calendar_service->get_public_feed_events(
                (string) $attributes['start'],
                (string) $attributes['end'],
                is_user_logged_in() ? get_current_user_id() : 0,
                [
                    'venue_id' => (int) $attributes['venue_id'],
                    'room_id' => (int) $attributes['room_id'],
                ],
                __( 'Private booking', 'my-village-hall' )
            );
        } catch ( \Throwable $exception ) {
            $events = [];
        }

        if ( empty( $events ) ) {
            return '<div class="myvh-event-diary"><p class="myvh-event-diary-empty">' . esc_html__( 'No events found for this period.', 'my-village-hall' ) . '</p></div>';
        }

        usort(
            $events,
            static function ( array $left, array $right ): int {
                return strcmp( (string) ( $left['start'] ?? '' ), (string) ( $right['start'] ?? '' ) );
            }
        );

        ob_start();
        ?>
        <div class="myvh-event-diary">
            <?php foreach ( $this->groupEventsByDay( $events ) as $day => $day_events ) : ?>
                <section class="myvh-event-diary-day">
                    <table class="myvh-event-diary-table">
                        <tbody>
                            <?php foreach ( $day_events as $index => $event ) : ?>
                                <tr>
                                    <?php if ( $index === 0 ) : ?>
                                        <td class="col-day" rowspan="<?php echo esc_attr( (string) count( $day_events ) ); ?>">
                                            <div class="myvh-event-diary-day-cell">
                                                <span class="myvh-event-diary-day-name"><?php echo esc_html( wp_date( 'D', strtotime( $day ) ) ); ?></span>
                                                <span class="myvh-event-diary-day-date"><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $day ) ) ); ?></span>
                                            </div>
                                        </td>
                                    <?php endif; ?>
                                    <td class="col-time">
                                        <span class="myvh-event-time-start"><?php echo esc_html( wp_date( get_option( 'time_format' ), strtotime( (string) ( $event['start'] ?? '' ) ) ) ); ?></span>
                                        <span class="myvh-event-time-end"><?php echo esc_html( wp_date( get_option( 'time_format' ), strtotime( (string) ( $event['end'] ?? '' ) ) ) ); ?></span>
                                    </td>
                                    <td class="col-event">
                                        <?php if ( ! empty( $event['id'] ) ) : ?>
                                            <a class="myvh-event-title" href="<?php echo esc_url( add_query_arg( 'booking_id', (int) $event['id'], $this->resolveEventDetailPageUrl() ) ); ?>"><?php echo esc_html( (string) ( $event['text'] ?? __( 'Booking', 'my-village-hall' ) ) ); ?></a>
                                        <?php else : ?>
                                            <span class="myvh-event-title-plain"><?php echo esc_html( (string) ( $event['text'] ?? __( 'Booking', 'my-village-hall' ) ) ); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-location">
                                        <span class="myvh-event-venue"><?php echo esc_html( (string) ( $event['tags']['venue'] ?? '' ) ); ?></span>
                                        <span class="myvh-event-room"><?php echo esc_html( (string) ( $event['tags']['room'] ?? '' ) ); ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </section>
            <?php endforeach; ?>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    private function resolveEventDetailPageUrl(): string {
        $filtered = apply_filters( 'myvh_event_detail_page_url', '' );
        if ( is_string( $filtered ) && $filtered !== '' ) {
            return esc_url_raw( $filtered );
        }

        $detail_tags = EventDetailShortcode::all_tags();
        $template_pages = get_posts([
            'post_type' => 'page',
            'post_status' => 'publish',
            'posts_per_page' => 200,
            'orderby' => 'menu_order title',
            'order' => 'ASC',
            'suppress_filters' => false,
        ]);

        foreach ( (array) $template_pages as $page ) {
            if ( empty( $page->ID ) ) {
                continue;
            }

            $content = (string) ( $page->post_content ?? '' );
            foreach ( $detail_tags as $detail_tag ) {
                if ( PageContentMatcher::containsFeature( $content, $detail_tag, 'myvh/event-detail' ) ) {
                    $permalink = get_permalink( (int) $page->ID );
                    if ( is_string( $permalink ) && $permalink !== '' ) {
                        return $permalink;
                    }
                }
            }
        }

        $current_permalink = get_permalink();
        if ( is_string( $current_permalink ) && $current_permalink !== '' ) {
            return $current_permalink;
        }

        return home_url( '/' );
    }

    private function groupEventsByDay( array $events ): array {
        $grouped = [];

        foreach ( $events as $event ) {
            $day = substr( (string) ( $event['start'] ?? '' ), 0, 10 );
            if ( $day === '' ) {
                continue;
            }

            if ( ! isset( $grouped[ $day ] ) ) {
                $grouped[ $day ] = [];
            }

            $grouped[ $day ][] = $event;
        }

        return $grouped;
    }
}