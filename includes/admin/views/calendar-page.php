<?php
/**
 * Admin calendar page – powered by DayPilot Lite.
 *
 * @package MyVillageHall
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$venues = MYVH_Registry::get( 'venue_service' )->get_all();
$rooms  = MYVH_Registry::get( 'room_service'  )->get_all_with_venues();
$customers = MYVH_Registry::get( 'customer_service' )->get_all( [ 'orderby' => 'Name', 'order' => 'ASC', 'limit' => 500 ] );
?>
<div class="wrap vbc-calendar-wrap">
    <h1><?php esc_html_e( 'Booking Calendar', 'my-village-hall' ); ?>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=my-village-hall&add=1' ) ); ?>" class="page-title-action">
            <?php esc_html_e( 'Add Booking', 'my-village-hall' ); ?>
        </a>
    </h1>

    <?php if ( isset( $_GET['updated'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Booking updated.', 'my-village-hall' ); ?></p></div>
    <?php endif; ?>

    <div class="vbc-calendar-container">

        <!-- Filters -->
        <div class="vbc-filters">
            <div class="vbc-filter-group">
                <label for="vbc-venue-filter"><?php esc_html_e( 'Venue', 'my-village-hall' ); ?></label>
                <select id="vbc-venue-filter">
                    <option value=""><?php esc_html_e( 'All Venues', 'my-village-hall' ); ?></option>
                    <?php foreach ( $venues as $venue ) : ?>
                        <option value="<?php echo esc_attr( $venue['Id'] ); ?>"><?php echo esc_html( $venue['Name'] ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="vbc-filter-group">
                <label for="vbc-room-filter"><?php esc_html_e( 'Room', 'my-village-hall' ); ?></label>
                <select id="vbc-room-filter">
                    <option value=""><?php esc_html_e( 'All Rooms', 'my-village-hall' ); ?></option>
                    <?php foreach ( $rooms as $room ) : ?>
                        <option value="<?php echo esc_attr( $room['Id'] ); ?>" data-venue="<?php echo esc_attr( $room['VenueId'] ); ?>">
                            <?php echo esc_html( $room['VenueName'] . ' › ' . $room['Name'] ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="vbc-filter-group">
                <label>
                    <input type="checkbox" id="vbc-show-cancelled">
                    <?php esc_html_e( 'Show cancelled', 'my-village-hall' ); ?>
                </label>
            </div>
            <div class="vbc-filter-actions">
                <button type="button" id="vbc-new-booking" class="button button-primary"><?php esc_html_e( '+ New Booking', 'my-village-hall' ); ?></button>
                <button type="button" id="vbc-refresh-calendar" class="button"><?php esc_html_e( 'Refresh', 'my-village-hall' ); ?></button>
            </div>
        </div>

        <!-- View switcher + title -->
        <div class="vbc-toolbar">
            <div class="vbc-toolbar-nav">
                <button type="button" id="vbc-prev" class="button" aria-label="<?php esc_attr_e( 'Previous', 'my-village-hall' ); ?>">&#8249;</button>
                <button type="button" id="vbc-today" class="button"><?php esc_html_e( 'Today', 'my-village-hall' ); ?></button>
                <button type="button" id="vbc-next" class="button" aria-label="<?php esc_attr_e( 'Next', 'my-village-hall' ); ?>">&#8250;</button>
                <span id="vbc-cal-title" class="vbc-cal-title"></span>
            </div>
            <div class="vbc-toolbar-views">
                <button type="button" class="button vbc-view-btn" data-view="Day"><?php esc_html_e( 'Day', 'my-village-hall' ); ?></button>
                <button type="button" class="button vbc-view-btn active" data-view="Week"><?php esc_html_e( 'Week', 'my-village-hall' ); ?></button>
                <button type="button" class="button vbc-view-btn" data-view="Month"><?php esc_html_e( 'Month', 'my-village-hall' ); ?></button>
            </div>
        </div>

        <!-- Calendar mount point -->
        <div id="vbc-calendar" style="min-height:600px;"></div>

        <!-- Legend -->
        <div class="vbc-legend">
            <h3><?php esc_html_e( 'Status', 'my-village-hall' ); ?></h3>
            <div class="vbc-legend-items">
                <span class="vbc-legend-item"><span class="vbc-legend-color" style="background:#2271b1;"></span><?php esc_html_e( 'Confirmed', 'my-village-hall' ); ?></span>
                <span class="vbc-legend-item"><span class="vbc-legend-color" style="background:#f0a500;"></span><?php esc_html_e( 'Pending', 'my-village-hall' ); ?></span>
                <span class="vbc-legend-item"><span class="vbc-legend-color" style="background:#46b450;"></span><?php esc_html_e( 'Completed', 'my-village-hall' ); ?></span>
                <span class="vbc-legend-item"><span class="vbc-legend-color" style="background:#888;"></span><?php esc_html_e( 'Cancelled', 'my-village-hall' ); ?></span>
            </div>
        </div>
    </div><!-- .vbc-calendar-container -->
</div><!-- .vbc-calendar-wrap -->


<!-- ══════════════════════════════════════════════════════════════════════════
     Booking Modal
     ══════════════════════════════════════════════════════════════════════════ -->
<div id="vbc-booking-modal" class="vbc-modal" style="display:none;">
    <div class="vbc-modal-content">
        <div class="vbc-modal-header">
            <h2 id="vbc-modal-title"><?php esc_html_e( 'New Booking', 'my-village-hall' ); ?></h2>
            <button class="vbc-modal-close" type="button" aria-label="<?php esc_attr_e( 'Close', 'my-village-hall' ); ?>">&times;</button>
        </div>
        <div class="vbc-modal-body">
            <input type="hidden" id="vbc-booking-id" value="">

            <div class="vbc-form-row">
                <div class="vbc-form-group">
                    <label for="vbc-modal-customer"><?php esc_html_e( 'Customer', 'my-village-hall' ); ?> <span class="required">*</span></label>
                    <select id="vbc-modal-customer" required>
                        <option value=""><?php esc_html_e( '— Select customer —', 'my-village-hall' ); ?></option>
                        <?php foreach ( $customers as $c ) : ?>
                            <option value="<?php echo esc_attr( $c['Id'] ); ?>"><?php echo esc_html( $c['Name'] . ( $c['Email'] ? ' (' . $c['Email'] . ')' : '' ) ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="vbc-form-row">
                <div class="vbc-form-group">
                    <label for="vbc-modal-room"><?php esc_html_e( 'Venue &amp; Room', 'my-village-hall' ); ?> <span class="required">*</span></label>
                    <select id="vbc-modal-room" required>
                        <option value=""><?php esc_html_e( '— Select a room —', 'my-village-hall' ); ?></option>
                        <?php
                        // Group rooms by venue using optgroup
                        $rooms_by_venue = [];
                        foreach ( $rooms as $room ) {
                            $rooms_by_venue[ $room['VenueId'] ][] = $room;
                        }
                        foreach ( $venues as $venue ) :
                            if ( empty( $rooms_by_venue[ $venue['Id'] ] ) ) continue;
                        ?>
                        <optgroup label="<?php echo esc_attr( $venue['Name'] ); ?>">
                            <?php foreach ( $rooms_by_venue[ $venue['Id'] ] as $room ) : ?>
                                <option value="<?php echo esc_attr( $room['Id'] ); ?>">
                                    <?php echo esc_html( $room['Name'] ); ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="vbc-form-row-2col">
                <div class="vbc-form-group">
                    <label for="vbc-modal-date"><?php esc_html_e( 'Date', 'my-village-hall' ); ?> <span class="required">*</span></label>
                    <input type="date" id="vbc-modal-date" required>
                </div>
                <div class="vbc-form-group">
                    <label for="vbc-modal-status"><?php esc_html_e( 'Status', 'my-village-hall' ); ?></label>
                    <select id="vbc-modal-status">
                        <option value="pending"><?php esc_html_e( 'Pending', 'my-village-hall' ); ?></option>
                        <option value="confirmed"><?php esc_html_e( 'Confirmed', 'my-village-hall' ); ?></option>
                        <option value="completed"><?php esc_html_e( 'Completed', 'my-village-hall' ); ?></option>
                        <option value="cancelled"><?php esc_html_e( 'Cancelled', 'my-village-hall' ); ?></option>
                    </select>
                </div>
            </div>

            <div class="vbc-form-row-2col">
                <div class="vbc-form-group">
                    <label for="vbc-modal-start-time"><?php esc_html_e( 'Start Time', 'my-village-hall' ); ?> <span class="required">*</span></label>
                    <input type="time" id="vbc-modal-start-time" step="900" required>
                </div>
                <div class="vbc-form-group">
                    <label for="vbc-modal-end-time"><?php esc_html_e( 'End Time', 'my-village-hall' ); ?> <span class="required">*</span></label>
                    <input type="time" id="vbc-modal-end-time" step="900" required>
                </div>
            </div>

            <div class="vbc-form-row">
                <div class="vbc-form-group">
                    <label for="vbc-modal-description"><?php esc_html_e( 'Description', 'my-village-hall' ); ?></label>
                    <textarea id="vbc-modal-description" rows="2"></textarea>
                </div>
            </div>

            <div class="vbc-form-row">
                <label>
                    <input type="checkbox" id="vbc-modal-public" value="1" checked>
                    <?php esc_html_e( 'Public (show on customer calendar)', 'my-village-hall' ); ?>
                </label>
            </div>
        </div>
        <div class="vbc-modal-footer">
            <button type="button" id="vbc-save-booking" class="button button-primary"><?php esc_html_e( 'Save Booking', 'my-village-hall' ); ?></button>
            <button type="button" id="vbc-cancel-booking" class="button button-link-delete" style="display:none;"><?php esc_html_e( 'Cancel Booking', 'my-village-hall' ); ?></button>
            <button class="vbc-modal-close button button-secondary" type="button"><?php esc_html_e( 'Close', 'my-village-hall' ); ?></button>
            <a id="vbc-edit-full" href="#" class="button" style="display:none;margin-left:auto;"><?php esc_html_e( 'Full Edit', 'my-village-hall' ); ?></a>
        </div>
    </div>
</div>
