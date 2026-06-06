<?php

declare(strict_types=1);

namespace MYVH\Application\Services;

use MYVH\Bookings\BookingService;
use MYVH\Customers\CustomerService;
use MYVH\Organisations\OrganisationMemberRepository;
use MYVH\Organisations\OrganisationService;
use MYVH\Organisations\OrganisationTypeService;
use MYVH\Pages\Support\PageContentMatcher;
use MYVH\Portal\ClientAdminService;
use MYVH\Portal\Support\BookingAccess;

final class EventDetailRenderer {
    public function __construct(
        private BookingService $booking_service,
        private CustomerService $customer_service,
        private OrganisationService $organisation_service,
        private OrganisationTypeService $organisation_type_service,
        private ClientAdminService $client_admin_service,
        private OrganisationMemberRepository $organisation_member_repo
    ) {
    }

    public function render( array $attributes = [] ): string {
        $attributes = shortcode_atts(
            [
                'booking_id' => 0,
                'event_id' => 0,
                'id' => 0,
            ],
            $attributes
        );

        $booking_id = $this->resolve_booking_id( $attributes );
        if ( $booking_id <= 0 ) {
            return $this->render_notice( __( 'Booking not found.', 'my-village-hall' ) );
        }

        wp_enqueue_style( 'myvh-event-list' );

        $booking = $this->resolve_booking( $booking_id );
        if ( empty( $booking ) ) {
            return $this->render_notice( __( 'Booking not found or you do not have permission to view it.', 'my-village-hall' ) );
        }

        $list_page_url = $this->resolve_event_list_page_url();
        $organisation_meta = $this->resolve_organisation_meta( $booking );

        ob_start();
        ?>
        <div class="myvh-event-diary myvh-event-detail-diary">
            <section class="myvh-event-diary-day myvh-event-detail-section">
                <header class="myvh-event-diary-day-header myvh-event-detail-header">
                    <span class="myvh-event-diary-day-name"><?php echo esc_html( wp_date( 'D', strtotime( (string) ( $booking['StartDate'] ?? '' ) ) ) ); ?></span>
                    <span class="myvh-event-diary-day-date"><?php echo esc_html( sprintf( __( 'Booking #%d', 'my-village-hall' ), (int) ( $booking['Id'] ?? $booking_id ) ) ); ?></span>
                </header>

                <table class="myvh-event-diary-table myvh-event-detail-table">
                    <tbody>
                        <tr>
                            <td class="col-time"><?php echo esc_html__( 'When', 'my-village-hall' ); ?></td>
                            <td class="col-event">
                                <strong><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( (string) ( $booking['StartDate'] ?? '' ) ) ) ); ?></strong>
                                <span class="myvh-event-time-end"><?php echo esc_html( substr( (string) ( $booking['StartTime'] ?? '' ), 0, 5 ) . ' - ' . substr( (string) ( $booking['EndTime'] ?? '' ), 0, 5 ) ); ?></span>
                            </td>
                            <td class="col-location"><a class="myvh-event-detail-return" href="<?php echo esc_url( $list_page_url ); ?>"><?php echo esc_html__( 'Back to event list', 'my-village-hall' ); ?></a></td>
                        </tr>
                        <tr>
                            <td class="col-time"><?php echo esc_html__( 'Customer', 'my-village-hall' ); ?></td>
                            <td class="col-event" colspan="2"><span class="myvh-event-title-plain"><?php echo esc_html( $booking['CustomerName'] ?? '-' ); ?></span></td>
                        </tr>
                        <tr>
                            <td class="col-time"><?php echo esc_html__( 'Venue', 'my-village-hall' ); ?></td>
                            <td class="col-event" colspan="2"><span class="myvh-event-title-plain"><?php echo esc_html( $booking['VenueName'] ?? '-' ); ?></span></td>
                        </tr>
                        <tr>
                            <td class="col-time"><?php echo esc_html__( 'Room', 'my-village-hall' ); ?></td>
                            <td class="col-event" colspan="2"><span class="myvh-event-title-plain"><?php echo esc_html( $booking['RoomName'] ?? '-' ); ?></span></td>
                        </tr>
                        <tr>
                            <td class="col-time"><?php echo esc_html__( 'Organisation', 'my-village-hall' ); ?></td>
                            <td class="col-event" colspan="2"><span class="myvh-event-title-plain"><?php echo esc_html( $booking['OrganisationName'] ?? '-' ); ?></span></td>
                        </tr>
                        <?php if ( ! empty( $organisation_meta['website_url'] ) ) : ?>
                            <tr>
                                <td class="col-time"><?php echo esc_html__( 'Website', 'my-village-hall' ); ?></td>
                                <td class="col-event" colspan="2">
                                    <a class="myvh-event-title" href="<?php echo esc_url( $organisation_meta['website_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $organisation_meta['website_url'] ); ?></a>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <tr>
                            <td class="col-time"><?php echo esc_html__( 'Status', 'my-village-hall' ); ?></td>
                            <td class="col-event" colspan="2"><span class="myvh-event-title-plain"><?php echo esc_html( ucfirst( (string) ( $booking['Status'] ?? '' ) ) ); ?></span></td>
                        </tr>
                        <tr>
                            <td class="col-time"><?php echo esc_html__( 'Description', 'my-village-hall' ); ?></td>
                            <td class="col-event" colspan="2"><span class="myvh-event-title-plain"><?php echo esc_html( $booking['Description'] ?? '-' ); ?></span></td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    private function resolve_booking_id( array $attributes ): int {
        $candidates = [
            (int) ( $attributes['booking_id'] ?? 0 ),
            (int) ( $attributes['event_id'] ?? 0 ),
            (int) ( $attributes['id'] ?? 0 ),
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

    private function resolve_booking( int $booking_id ): ?array {
        if ( ! is_user_logged_in() ) {
            $booking = $this->booking_service->get_by_id_with_details( $booking_id );
            if ( empty( $booking ) ) {
                return null;
            }

            if ( empty( $booking['Public'] ) && empty( $booking['IsPublic'] ) ) {
                return null;
            }

            return $booking;
        }

        $customer = $this->customer_service->get_by_user_id( get_current_user_id() );
        $is_client_admin = $this->client_admin_service->can_administer_blog( get_current_user_id(), get_current_blog_id() );

        return BookingAccess::get_accessible_booking(
            $booking_id,
            (int) ( $customer['Id'] ?? 0 ),
            $is_client_admin,
            $this->booking_service,
            $this->organisation_member_repo
        );
    }

    private function resolve_event_list_page_url(): string {
        $filtered = apply_filters( 'myvh_event_list_page_url', '' );
        if ( is_string( $filtered ) && $filtered !== '' ) {
            return esc_url_raw( $filtered );
        }

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

            if ( PageContentMatcher::containsFeature( (string) ( $page->post_content ?? '' ), 'myvh_event_list', 'myvh/event-list' ) ) {
                $permalink = get_permalink( (int) $page->ID );
                if ( is_string( $permalink ) && $permalink !== '' ) {
                    return $permalink;
                }
            }
        }

        $current_permalink = get_permalink();
        if ( is_string( $current_permalink ) && $current_permalink !== '' ) {
            return $current_permalink;
        }

        return home_url( '/' );
    }

    private function resolve_organisation_meta( array $booking ): array {
        $organisation_id = (int) ( $booking['OrganisationId'] ?? 0 );
        if ( $organisation_id <= 0 ) {
            return [ 'label' => '', 'website_url' => '' ];
        }

        $organisation = $this->organisation_service->get_by_id( $organisation_id );
        $organisation_type = ! empty( $organisation['OrganisationTypeId'] )
            ? $this->organisation_type_service->get( (int) $organisation['OrganisationTypeId'] )
            : null;
        $is_system = ! empty( $organisation_type['IsSystem'] );
        $website_url = ! $is_system && ! empty( $organisation['WebsiteUrl'] ) ? esc_url_raw( (string) $organisation['WebsiteUrl'] ) : '';

        return [
            'label' => $is_system ? __( 'Personal booking', 'my-village-hall' ) : ( $website_url !== '' ? __( 'Organisation website', 'my-village-hall' ) : '' ),
            'website_url' => $website_url,
        ];
    }

    private function render_notice( string $message ): string {
        return '<div class="myvh-event-detail-notice myvh-card"><p>' . esc_html( $message ) . '</p></div>';
    }
}