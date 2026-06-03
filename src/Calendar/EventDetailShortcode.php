<?php

namespace MYVH\Calendar;

use MYVH\Bookings\BookingService;
use MYVH\Core\Shortcode\ShortcodeInterface;
use MYVH\Core\Support\AssetLoader;
use MYVH\Customers\CustomerService;
use MYVH\Organisations\OrganisationMemberRepository;
use MYVH\Portal\ClientAdminService;
use MYVH\Portal\Support\BookingAccess;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EventDetailShortcode implements ShortcodeInterface {
	private const TAG = 'myvh_event_detail';
	private const LEGACY_TAGS = [ 'event_deatils', 'event_details' ];

	public function tag(): string {
		return self::TAG;
	}

	public function init(): void {
		foreach ( self::all_tags() as $tag ) {
			add_shortcode( $tag, [ $this, 'render' ] );
		}
	}

	public static function all_tags(): array {
		return array_values( array_unique( array_merge( [ self::TAG ], self::LEGACY_TAGS ) ) );
	}

	public function render( mixed $atts = [], mixed $content = null ): string {
		$atts = shortcode_atts(
			[
				'booking_id' => 0,
				'event_id' => 0,
				'id' => 0,
			],
			(array) $atts,
			self::TAG
		);

		$booking_id = $this->resolve_booking_id( $atts );
		if ( $booking_id <= 0 ) {
			return $this->render_notice( __( 'Booking not found.', 'my-village-hall' ) );
		}

		$services = $this->resolve_services();
		if ( $services === null ) {
			return $this->render_notice( __( 'Booking details are temporarily unavailable.', 'my-village-hall' ) );
		}

		AssetLoader::enqueue_portal_assets();
		wp_enqueue_style( 'myvh-event-list' );

		$booking = $this->resolve_booking(
			$booking_id,
			$services['booking_service'],
			$services['customer_service'],
			$services['client_admin_service'],
			$services['organisation_member_repo']
		);

		if ( empty( $booking ) ) {
			return $this->render_notice( __( 'Booking not found or you do not have permission to view it.', 'my-village-hall' ) );
		}

		$is_client_admin = $services['client_admin_service']->can_administer_blog( get_current_user_id(), get_current_blog_id() );
		$delete_rules = [ 'can_delete' => false, 'reason' => '' ];
		$can_delete = false;

		if ( is_user_logged_in() ) {
			$delete_rules = $services['booking_service']->can_delete( $booking );
			$can_delete = ! empty( $delete_rules['can_delete'] );
		}

		ob_start();
		?>
		<div class="myvh-dashboard-section myvh-event-detail-page">
			<div class="myvh-account-header">
				<div>
					<h2><?php echo esc_html( $booking['RoomName'] ?? __( 'Event details', 'my-village-hall' ) ); ?></h2>
					<p><?php echo esc_html( $booking['Description'] ?? __( 'Review booking details.', 'my-village-hall' ) ); ?></p>
				</div>
				<a href="#bookings" class="myvh-button"><?php echo esc_html__( 'Back to bookings', 'my-village-hall' ); ?></a>
			</div>

			<div class="myvh-surface-panel myvh-bookings-panel">
				<div class="myvh-card myvh-account-card">
					<div class="myvh-account-card-head">
						<h3><?php echo esc_html( $booking['RoomName'] ?? __( 'Booking', 'my-village-hall' ) ); ?></h3>
						<span><?php echo esc_html( sprintf( __( 'Booking reference #%d', 'my-village-hall' ), (int) ( $booking['Id'] ?? $booking_id ) ) ); ?></span>
					</div>

					<div class="myvh-account-grid">
						<div class="myvh-account-card">
							<div class="myvh-account-card-head">
								<h3><?php echo esc_html__( 'Date & Time', 'my-village-hall' ); ?></h3>
							</div>
							<p><strong><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( (string) ( $booking['StartDate'] ?? '' ) ) ) ); ?></strong></p>
							<p>
								<?php echo esc_html( substr( (string) ( $booking['StartTime'] ?? '' ), 0, 5 ) ); ?>
								-
								<?php echo esc_html( substr( (string) ( $booking['EndTime'] ?? '' ), 0, 5 ) ); ?>
							</p>
						</div>

						<div class="myvh-account-card">
							<div class="myvh-account-card-head">
								<h3><?php echo esc_html__( 'Details', 'my-village-hall' ); ?></h3>
							</div>
							<p><strong><?php echo esc_html__( 'Status:', 'my-village-hall' ); ?></strong> <?php echo esc_html( ucfirst( (string) ( $booking['Status'] ?? '' ) ) ); ?></p>
							<p><strong><?php echo esc_html__( 'Venue:', 'my-village-hall' ); ?></strong> <?php echo esc_html( $booking['VenueName'] ?? '-' ); ?></p>
							<p><strong><?php echo esc_html__( 'Organisation:', 'my-village-hall' ); ?></strong> <?php echo esc_html( $booking['OrganisationName'] ?? '-' ); ?></p>
							<p><strong><?php echo esc_html__( 'Description:', 'my-village-hall' ); ?></strong> <?php echo esc_html( $booking['Description'] ?? '-' ); ?></p>
						</div>
					</div>

					<?php if ( is_user_logged_in() ) : ?>
						<div class="myvh-account-actions" style="margin-top:12px;">
							<?php if ( $is_client_admin ) : ?>
								<a href="#booking-edit?booking_id=<?php echo (int) $booking['Id']; ?>" class="myvh-button myvh-button-primary"><?php echo esc_html__( 'Edit Booking', 'my-village-hall' ); ?></a>
							<?php endif; ?>

							<?php if ( $can_delete ) : ?>
								<a href="#booking-delete?booking_id=<?php echo (int) $booking['Id']; ?>" class="myvh-button"><?php echo esc_html__( 'Delete Booking', 'my-village-hall' ); ?></a>
							<?php elseif ( ! empty( $delete_rules['reason'] ) ) : ?>
								<span class="myvh-muted"><?php echo esc_html( (string) $delete_rules['reason'] ); ?></span>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php

		return (string) ob_get_clean();
	}

	private function resolve_booking_id( array $atts ): int {
		$candidates = [
			(int) ( $atts['booking_id'] ?? 0 ),
			(int) ( $atts['event_id'] ?? 0 ),
			(int) ( $atts['id'] ?? 0 ),
			(int) ( $_GET['booking_id'] ?? 0 ),
			(int) ( $_GET['event_id'] ?? 0 ),
			(int) ( $_GET['id'] ?? 0 ),
		];

		foreach ( $candidates as $candidate ) {
			if ( $candidate > 0 ) {
				return $candidate;
			}
		}

		return 0;
	}

	private function resolve_services(): ?array {
		global $myvh_container;

		if ( ! isset( $myvh_container ) ) {
			return null;
		}

		try {
			return [
				'booking_service' => $myvh_container->get( BookingService::class ),
				'customer_service' => $myvh_container->get( CustomerService::class ),
				'client_admin_service' => $myvh_container->get( ClientAdminService::class ),
				'organisation_member_repo' => $myvh_container->get( OrganisationMemberRepository::class ),
			];
		} catch ( \Throwable $exception ) {
			return null;
		}
	}

	private function resolve_booking( int $booking_id, BookingService $booking_service, CustomerService $customer_service, ClientAdminService $client_admin_service, OrganisationMemberRepository $organisation_member_repo ): ?array {
		if ( ! is_user_logged_in() ) {
			$booking = $booking_service->get_by_id_with_details( $booking_id );
			if ( empty( $booking ) ) {
				return null;
			}

			if ( empty( $booking['Public'] ) && empty( $booking['IsPublic'] ) ) {
				return null;
			}

			return $booking;
		}

		$customer = $customer_service->get_by_user_id( get_current_user_id() );
		$is_client_admin = $client_admin_service->can_administer_blog( get_current_user_id(), get_current_blog_id() );

		return BookingAccess::get_accessible_booking(
			$booking_id,
			(int) ( $customer['Id'] ?? 0 ),
			$is_client_admin,
			$booking_service,
			$organisation_member_repo
		);
	}

	private function render_notice( string $message ): string {
		return '<div class="myvh-event-detail-notice myvh-card"><p>' . esc_html( $message ) . '</p></div>';
	}
}
