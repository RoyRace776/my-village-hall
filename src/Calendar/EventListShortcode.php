<?php

namespace MYVH\Calendar;

use MYVH\Core\Shortcode\ShortcodeInterface;
use MYVH\Core\Support\AssetLoader;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EventListShortcode implements ShortcodeInterface {
	private const TAG = 'myvh_event_list';

	public function tag(): string {
		return self::TAG;
	}

	public function init(): void {
		add_shortcode( self::TAG, [ $this, 'render' ] );
	}

	public function render( mixed $atts = [], mixed $content = null ): string {
		$atts = shortcode_atts(
			[
				'start' => gmdate( 'Y-m-01' ),
				'end' => gmdate( 'Y-m-t' ),
				'venue_id' => 0,
				'room_id' => 0,
			],
			(array) $atts,
			self::TAG
		);

		AssetLoader::enqueue_portal_assets();
		wp_enqueue_style( 'myvh-event-list' );

		$services = $this->resolve_services();
		if ( $services === null ) {
			return $this->render_notice( __( 'Event list is temporarily unavailable.', 'my-village-hall' ) );
		}

		try {
			$events = $services['calendar_service']->get_public_feed_events(
				(string) $atts['start'],
				(string) $atts['end'],
				is_user_logged_in() ? get_current_user_id() : 0,
				[
					'venue_id' => (int) $atts['venue_id'],
					'room_id' => (int) $atts['room_id'],
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
			<?php foreach ( $this->group_events_by_day( $events ) as $day => $day_events ) : ?>
				<section class="myvh-event-diary-day">
					<header class="myvh-event-diary-day-header">
						<span class="myvh-event-diary-day-name"><?php echo esc_html( wp_date( 'D', strtotime( $day ) ) ); ?></span>
						<span class="myvh-event-diary-day-date"><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $day ) ) ); ?></span>
					</header>
					<table class="myvh-event-diary-table">
						<tbody>
							<?php foreach ( $day_events as $event ) : ?>
								<tr>
									<td class="col-time">
										<span class="myvh-event-time-start"><?php echo esc_html( wp_date( get_option( 'time_format' ), strtotime( (string) ( $event['start'] ?? '' ) ) ) ); ?></span>
										<span class="myvh-event-time-end"><?php echo esc_html( wp_date( get_option( 'time_format' ), strtotime( (string) ( $event['end'] ?? '' ) ) ) ); ?></span>
									</td>
									<td class="col-event">
										<?php if ( ! empty( $event['id'] ) ) : ?>
											<a class="myvh-event-title" href="<?php echo esc_url( add_query_arg( 'booking_id', (int) $event['id'], get_permalink() ?: home_url( '/' ) ) ); ?>"><?php echo esc_html( (string) ( $event['text'] ?? __( 'Booking', 'my-village-hall' ) ) ); ?></a>
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

	private function resolve_services(): ?array {
		global $myvh_container;

		if ( ! isset( $myvh_container ) ) {
			return null;
		}

		try {
			return [
				'calendar_service' => $myvh_container->get( CalendarService::class ),
			];
		} catch ( \Throwable $exception ) {
			return null;
		}
	}

	private function group_events_by_day( array $events ): array {
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

	private function render_notice( string $message ): string {
		return '<div class="myvh-event-diary"><p class="myvh-event-diary-empty">' . esc_html( $message ) . '</p></div>';
	}
}
