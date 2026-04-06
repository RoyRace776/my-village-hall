<?php
if (!defined('ABSPATH')) {
    exit;
}

$single_uninvoiced_bookings = array_values(array_filter(
    $uninvoiced_bookings ?? [],
    static function ($booking): bool {
        return empty($booking['RecurringPatternId']);
    }
));

$recurring_uninvoiced_bookings = array_values(array_filter(
    $uninvoiced_bookings ?? [],
    static function ($booking): bool {
        return !empty($booking['RecurringPatternId']);
    }
));
?>

<div class="myvh-dashboard-section myvh-client-settings-page myvh-invoices-page">
    <div class="myvh-account-header myvh-settings-header">
        <div>
            <h2>Generate Invoices</h2>
            <p>Select uninvoiced bookings and group them into invoices for customers or organisations.</p>
        </div>
        <div class="myvh-account-actions">
            <a href="#invoices" class="myvh-button">View Invoices</a>
        </div>
    </div>

    <div class="myvh-settings-tabs myvh-invoices-tabs" role="tablist" aria-label="Invoice generation views">
        <button type="button" class="myvh-settings-tab myvh-invoices-tab is-active" role="tab" aria-selected="true" data-invoices-tab="create">Create Invoices</button>
        <button type="button" class="myvh-settings-tab myvh-invoices-tab" role="tab" aria-selected="false" data-invoices-tab="by-customer">By Customer</button>
        <button type="button" class="myvh-settings-tab myvh-invoices-tab" role="tab" aria-selected="false" data-invoices-tab="by-organisation">By Organisation</button>
    </div>

    <div class="myvh-card myvh-account-card myvh-settings-group myvh-invoices-panel is-active" data-invoices-panel="create">
        <div class="myvh-section-header" style="margin-bottom: 12px;">
            <h3>Manual Invoice Creation</h3>
        </div>

        <form class="myvh-account-form"
              data-portal-action="myvh_portal_create_invoice"
              data-message-target="myvh-invoice-create-message"
              data-reload-page="invoices">
            <p>Select uninvoiced bookings and choose how to group them into invoices.</p>

            <div class="myvh-settings-tabs myvh-invoices-tabs" role="tablist" aria-label="Manual invoice booking types" style="margin-bottom: 12px;">
                <button type="button" class="myvh-settings-tab myvh-booking-type-tab is-active" role="tab" aria-selected="true" data-booking-type-tab="single">Single Bookings (<?php echo intval(count($single_uninvoiced_bookings)); ?>)</button>
                <button type="button" class="myvh-settings-tab myvh-booking-type-tab" role="tab" aria-selected="false" data-booking-type-tab="recurring">Recurring Bookings (<?php echo intval(count($recurring_uninvoiced_bookings)); ?>)</button>
            </div>

            <div class="myvh-field-grid" style="margin-bottom: 12px;">
                <div>
                    <label for="myvh-group-by"><strong>Grouping</strong></label>
                    <select id="myvh-group-by" name="group_by" class="myvh-input">
                        <option value="per_booking">One invoice per booking</option>
                        <option value="by_customer">Group by customer</option>
                        <option value="by_organisation">Group by organisation</option>
                    </select>
                </div>
            </div>

            <?php if (!empty($uninvoiced_bookings)): ?>
                <div class="myvh-booking-type-panel is-active" data-booking-type-panel="single">
                    <div style="margin-bottom: 8px;">
                        <button type="button" class="button myvh-select-all-uninvoiced" data-booking-type="single">Select all</button>
                        <button type="button" class="button myvh-clear-all-uninvoiced" data-booking-type="single">Clear</button>
                    </div>

                    <?php if (!empty($single_uninvoiced_bookings)): ?>
                        <div style="max-height: 320px; overflow: auto; border: 1px solid #d0d3d8; border-radius: 6px; padding: 8px; margin-bottom: 12px;">
                            <table class="myvh-invoices-table">
                                <thead>
                                    <tr>
                                        <th>Select</th>
                                        <th>Booking</th>
                                        <th>Customer</th>
                                        <th>Organisation</th>
                                        <th>Description</th>
                                        <th>Date</th>
                                        <th>Room</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($single_uninvoiced_bookings as $booking): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" name="booking_ids[]" value="<?php echo intval($booking['Id']); ?>" class="myvh-uninvoiced-checkbox" data-booking-type="single">
                                            </td>
                                            <td>#<?php echo intval($booking['Id']); ?></td>
                                            <td><?php echo esc_html($booking['CustomerName'] ?? 'Unknown'); ?></td>
                                            <td><?php echo esc_html($booking['OrganisationName'] ?? '-'); ?></td>
                                            <td><?php echo esc_html($booking['Description'] ?? '-'); ?></td>
                                            <td><?php echo esc_html(date('j M Y', strtotime((string) ($booking['StartDate'] ?? '')))); ?></td>
                                            <td><?php echo esc_html($booking['RoomName'] ?? '-'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p>No uninvoiced single bookings found.</p>
                    <?php endif; ?>
                </div>

                <div class="myvh-booking-type-panel" data-booking-type-panel="recurring" hidden>
                    <div style="margin-bottom: 8px;">
                        <button type="button" class="button myvh-select-all-uninvoiced" data-booking-type="recurring">Select all</button>
                        <button type="button" class="button myvh-clear-all-uninvoiced" data-booking-type="recurring">Clear</button>
                    </div>

                    <?php if (!empty($recurring_uninvoiced_bookings)): ?>
                        <div style="max-height: 320px; overflow: auto; border: 1px solid #d0d3d8; border-radius: 6px; padding: 8px; margin-bottom: 12px;">
                            <table class="myvh-invoices-table">
                                <thead>
                                    <tr>
                                        <th>Select</th>
                                        <th>Booking</th>
                                        <th>Pattern</th>
                                        <th>Customer</th>
                                        <th>Organisation</th>
                                        <th>Description</th>
                                        <th>Date</th>
                                        <th>Room</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recurring_uninvoiced_bookings as $booking): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" name="booking_ids[]" value="<?php echo intval($booking['Id']); ?>" class="myvh-uninvoiced-checkbox" data-booking-type="recurring" disabled>
                                            </td>
                                            <td>#<?php echo intval($booking['Id']); ?></td>
                                            <td>#<?php echo intval($booking['RecurringPatternId'] ?? 0); ?></td>
                                            <td><?php echo esc_html($booking['CustomerName'] ?? 'Unknown'); ?></td>
                                            <td><?php echo esc_html($booking['OrganisationName'] ?? '-'); ?></td>
                                            <td><?php echo esc_html($booking['Description'] ?? '-'); ?></td>
                                            <td><?php echo esc_html(date('j M Y', strtotime((string) ($booking['StartDate'] ?? '')))); ?></td>
                                            <td><?php echo esc_html($booking['RoomName'] ?? '-'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p>No uninvoiced recurring bookings found.</p>
                    <?php endif; ?>
                </div>

                <div class="myvh-account-actions">
                    <button type="submit" class="myvh-button myvh-button-primary">Create Invoice(s)</button>
                </div>
            <?php else: ?>
                <p>No uninvoiced confirmed/completed bookings found.</p>
            <?php endif; ?>

            <p id="myvh-invoice-create-message" class="myvh-form-message" role="status" aria-live="polite"></p>
        </form>
    </div>

    <div class="myvh-card myvh-account-card myvh-settings-group myvh-invoices-panel" data-invoices-panel="by-customer" hidden>
        <div class="myvh-section-header" style="margin-bottom: 12px;">
            <h3>Uninvoiced Bookings by Customer</h3>
        </div>
        <?php if (!empty($uninvoiced_by_customer)): ?>
            <table class="myvh-invoices-table">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Email</th>
                        <th>Uninvoiced Bookings</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($uninvoiced_by_customer as $customer): ?>
                        <tr>
                            <td><?php echo esc_html($customer['CustomerName'] ?? 'Unknown'); ?></td>
                            <td><?php echo esc_html($customer['CustomerEmail'] ?? '-'); ?></td>
                            <td><?php echo intval($customer['UninvoicedCount']); ?></td>
                            <td><button type="button" class="myvh-drilldown-btn" data-customer-id="<?php echo intval($customer['CustomerId']); ?>">View Bookings</button></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No customers with uninvoiced bookings found.</p>
        <?php endif; ?>
        <div id="myvh-customer-drilldown" style="margin-top:20px;"></div>
    </div>

    <div class="myvh-card myvh-account-card myvh-settings-group myvh-invoices-panel" data-invoices-panel="by-organisation" hidden>
        <div class="myvh-section-header" style="margin-bottom: 12px;">
            <h3>Uninvoiced Bookings by Organisation</h3>
        </div>
        <?php if (!empty($uninvoiced_by_organisation)): ?>
            <table class="myvh-invoices-table">
                <thead>
                    <tr>
                        <th>Organisation</th>
                        <th>Uninvoiced Bookings</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($uninvoiced_by_organisation as $org): ?>
                        <tr>
                            <td><?php echo esc_html($org['OrganisationName'] ?? 'Unknown'); ?></td>
                            <td><?php echo intval($org['UninvoicedCount']); ?></td>
                            <td><button type="button" class="myvh-drilldown-btn" data-organisation-id="<?php echo intval($org['OrganisationId']); ?>">View Bookings</button></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No organisations with uninvoiced bookings found.</p>
        <?php endif; ?>
        <div id="myvh-organisation-drilldown" style="margin-top:20px;"></div>
    </div>
</div>