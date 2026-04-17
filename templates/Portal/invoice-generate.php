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

$recurring_booking_groups = [];
foreach ($recurring_uninvoiced_bookings as $booking) {
    $pattern_id = intval($booking['RecurringPatternId'] ?? 0);
    if ($pattern_id <= 0) {
        $pattern_id = intval($booking['Id'] ?? 0);
    }

    if (!isset($recurring_booking_groups[$pattern_id])) {
        $recurring_booking_groups[$pattern_id] = [
            'pattern_id' => $pattern_id,
            'bookings' => [],
        ];
    }

    $recurring_booking_groups[$pattern_id]['bookings'][] = $booking;
}

foreach ($recurring_booking_groups as &$group) {
    usort($group['bookings'], static function (array $left, array $right): int {
        $left_timestamp = strtotime((string) ($left['StartDate'] ?? ''));
        $right_timestamp = strtotime((string) ($right['StartDate'] ?? ''));

        if ($left_timestamp === $right_timestamp) {
            return intval($left['Id'] ?? 0) <=> intval($right['Id'] ?? 0);
        }

        if (false === $left_timestamp) {
            return 1;
        }

        if (false === $right_timestamp) {
            return -1;
        }

        return $left_timestamp <=> $right_timestamp;
    });

    $group['first_booking'] = $group['bookings'][0] ?? [];
}
unset($group);

$recurring_booking_groups = array_values($recurring_booking_groups);
usort($recurring_booking_groups, static function (array $left, array $right): int {
    $left_timestamp = strtotime((string) (($left['first_booking']['StartDate'] ?? '')));
    $right_timestamp = strtotime((string) (($right['first_booking']['StartDate'] ?? '')));

    if ($left_timestamp === $right_timestamp) {
        return intval($left['pattern_id'] ?? 0) <=> intval($right['pattern_id'] ?? 0);
    }

    if (false === $left_timestamp) {
        return 1;
    }

    if (false === $right_timestamp) {
        return -1;
    }

    return $left_timestamp <=> $right_timestamp;
});

$single_booking_count = count($single_uninvoiced_bookings);
$recurring_booking_count = count($recurring_uninvoiced_bookings);
$uninvoiced_booking_count = count($uninvoiced_bookings ?? []);
$customer_group_count = count($uninvoiced_by_customer ?? []);
$organisation_group_count = count($uninvoiced_by_organisation ?? []);
?>

<div class="myvh-dashboard-section myvh-client-settings-page myvh-invoices-page myvh-invoice-generate-page">
    <div class="myvh-account-header">
        <div>
            <h2>Generate Invoices</h2>
            <p>Select uninvoiced bookings and group them into invoices for customers or organisations.</p>
        </div>
        <a href="#invoices" class="myvh-portal-add-btn myvh-portal-nav-btn">
            <span class="myvh-portal-add-btn__icon" aria-hidden="true">&larr;</span>
            <span>View Invoices</span>
        </a>
    </div>

    <div class="myvh-settings-tabs myvh-invoices-tabs" role="tablist" aria-label="Invoice generation views">
        <button type="button" class="myvh-settings-tab myvh-invoices-tab is-active" role="tab" aria-selected="true" data-invoices-tab="create">Create Invoices</button>
        <button type="button" class="myvh-settings-tab myvh-invoices-tab" role="tab" aria-selected="false" data-invoices-tab="by-customer">By Customer</button>
        <button type="button" class="myvh-settings-tab myvh-invoices-tab" role="tab" aria-selected="false" data-invoices-tab="by-organisation">By Organisation</button>
    </div>

    <div class="myvh-card myvh-account-card myvh-settings-group myvh-invoices-panel myvh-generate-panel is-active" data-invoices-panel="create">
        <div class="myvh-account-card-head">
            <div>
                <h3>Manual Invoice Creation</h3>
                <span><?php echo esc_html((string) $uninvoiced_booking_count); ?> <?php echo 1 === $uninvoiced_booking_count ? 'uninvoiced booking ready to group' : 'uninvoiced bookings ready to group'; ?></span>
            </div>
        </div>

        <form class="myvh-account-form myvh-generate-form"
              data-portal-action="myvh_portal_create_invoice"
              data-message-target="myvh-invoice-create-message"
              data-reload-page="invoices">
            <p class="myvh-generate-intro">Select uninvoiced bookings and choose how to group them into invoices.</p>

            <div class="myvh-generate-summary-cards" aria-label="Invoice generation summary">
                <div class="myvh-generate-summary-card">
                    <span class="myvh-generate-summary-card__label">Single bookings</span>
                    <strong><?php echo esc_html((string) $single_booking_count); ?></strong>
                </div>
                <div class="myvh-generate-summary-card">
                    <span class="myvh-generate-summary-card__label">Recurring bookings</span>
                    <strong><?php echo esc_html((string) $recurring_booking_count); ?></strong>
                </div>
                <div class="myvh-generate-summary-card">
                    <span class="myvh-generate-summary-card__label">Recurring groups</span>
                    <strong><?php echo esc_html((string) count($recurring_booking_groups)); ?></strong>
                </div>
            </div>

            <div class="myvh-generate-grouping-panel">
                <label for="myvh-group-by" class="myvh-account-field myvh-generate-grouping-field">
                    <span>Grouping</span>
                    <select id="myvh-group-by" name="group_by" class="myvh-input myvh-generate-select">
                        <option value="per_booking">One invoice per booking</option>
                        <option value="by_customer">One invoice per customer</option>
                        <option value="by_organisation">One invoice per organisation</option>
                    </select>
                </label>
                <p class="myvh-account-hint">Choose how the selected bookings should be bundled into invoices before submission.</p>
            </div>

            <div class="myvh-settings-tabs myvh-invoices-tabs myvh-generate-subtabs" role="tablist" aria-label="Manual invoice booking types">
                <button type="button" class="myvh-settings-tab myvh-booking-type-tab is-active" role="tab" aria-selected="true" data-booking-type-tab="single">Single Bookings (<?php echo intval(count($single_uninvoiced_bookings)); ?>)</button>
                <button type="button" class="myvh-settings-tab myvh-booking-type-tab" role="tab" aria-selected="false" data-booking-type-tab="recurring">Recurring Bookings (<?php echo intval(count($recurring_uninvoiced_bookings)); ?>)</button>
            </div>

            <?php if (!empty($uninvoiced_bookings)): ?>
                <div class="myvh-booking-type-panel myvh-generate-booking-panel is-active" data-booking-type-panel="single">
                    <div class="myvh-generate-panel-toolbar">
                        <div>
                            <strong>Single Bookings</strong>
                            <span>Pick one-off bookings to invoice individually or as grouped bundles.</span>
                        </div>
                        <div class="myvh-generate-toolbar-actions">
                            <button type="button" class="button myvh-select-all-uninvoiced myvh-generate-toolbar-button" data-booking-type="single">Select all</button>
                            <button type="button" class="button myvh-clear-all-uninvoiced myvh-generate-toolbar-button myvh-generate-toolbar-button--muted" data-booking-type="single">Clear</button>
                        </div>
                    </div>

                    <?php if (!empty($single_uninvoiced_bookings)): ?>
                        <div class="myvh-invoices-table-wrap myvh-generate-table-wrap">
                            <table class="myvh-customer-list-table myvh-invoices-table myvh-generate-bookings-table">
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
                        <div class="myvh-empty-state myvh-invoices-empty-state myvh-generate-empty-state">
                            <p class="myvh-invoices-empty-state__title">No uninvoiced single bookings found.</p>
                            <p>Single confirmed or completed bookings will appear here when they are ready to invoice.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="myvh-booking-type-panel myvh-generate-booking-panel" data-booking-type-panel="recurring" hidden>
                    <div class="myvh-generate-panel-toolbar">
                        <div>
                            <strong>Recurring Bookings</strong>
                            <span>Expand a pattern to review its bookings before adding them to an invoice batch.</span>
                        </div>
                        <div class="myvh-generate-toolbar-actions">
                            <button type="button" class="button myvh-select-all-uninvoiced myvh-generate-toolbar-button" data-booking-type="recurring">Select all</button>
                            <button type="button" class="button myvh-clear-all-uninvoiced myvh-generate-toolbar-button myvh-generate-toolbar-button--muted" data-booking-type="recurring">Clear</button>
                        </div>
                    </div>

                    <?php if (!empty($recurring_uninvoiced_bookings)): ?>
                        <div class="myvh-invoices-table-wrap myvh-generate-table-wrap">
                            <table class="myvh-customer-list-table myvh-invoices-table myvh-generate-bookings-table myvh-generate-recurring-table">
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
                                    <?php foreach ($recurring_booking_groups as $group): ?>
                                        <?php $group_id = intval($group['pattern_id']); ?>
                                        <?php $first_booking = $group['first_booking'] ?? []; ?>
                                        <tr class="myvh-recurring-group-row">
                                            <td></td>
                                            <td class="myvh-recurring-group-cell">
                                                <button
                                                    type="button"
                                                    class="button-link myvh-recurring-group-toggle"
                                                    data-recurring-group="<?php echo esc_attr((string) $group_id); ?>"
                                                    aria-expanded="false"
                                                >
                                                    <span class="myvh-recurring-group-toggle__title">Pattern #<?php echo esc_html((string) $group_id); ?></span>
                                                    <span class="myvh-recurring-group-toggle__meta"><?php echo esc_html((string) count($group['bookings'])); ?> <?php echo count($group['bookings']) === 1 ? 'booking' : 'bookings'; ?></span>
                                                </button>
                                            </td>
                                            <td>#<?php echo esc_html((string) $group_id); ?></td>
                                            <td><?php echo esc_html($first_booking['CustomerName'] ?? 'Unknown'); ?></td>
                                            <td><?php echo esc_html($first_booking['OrganisationName'] ?? '-'); ?></td>
                                            <td><?php echo esc_html($first_booking['Description'] ?? '-'); ?></td>
                                            <td><?php echo esc_html(date('j M Y', strtotime((string) ($first_booking['StartDate'] ?? '')))); ?></td>
                                            <td><?php echo esc_html($first_booking['RoomName'] ?? '-'); ?></td>
                                        </tr>
                                        <?php foreach ($group['bookings'] as $booking): ?>
                                            <tr class="myvh-recurring-group-child" data-recurring-group-child="<?php echo esc_attr((string) $group_id); ?>" hidden>
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
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="myvh-empty-state myvh-invoices-empty-state myvh-generate-empty-state">
                            <p class="myvh-invoices-empty-state__title">No uninvoiced recurring bookings found.</p>
                            <p>Recurring bookings will appear here when a pattern has confirmed or completed items ready to invoice.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="myvh-account-actions myvh-generate-submit-row">
                    <button type="submit" class="myvh-button myvh-button-primary">Create Invoice(s)</button>
                    <p class="myvh-account-hint">The selected bookings will be invoiced using the grouping option above.</p>
                </div>
            <?php else: ?>
                <div class="myvh-empty-state myvh-invoices-empty-state myvh-generate-empty-state">
                    <p class="myvh-invoices-empty-state__title">No uninvoiced confirmed or completed bookings found.</p>
                    <p>Once eligible bookings exist for this site, they will appear here ready for invoice creation.</p>
                </div>
            <?php endif; ?>

            <p id="myvh-invoice-create-message" class="myvh-form-message" role="status" aria-live="polite"></p>
        </form>

        <form class="myvh-account-form myvh-generate-form"
              data-portal-action="myvh_portal_run_auto_invoicing"
              data-message-target="myvh-auto-invoicing-portal-message"
              data-reload-page="invoice-generate">
            <div class="myvh-account-actions myvh-generate-submit-row">
                <button type="submit" class="myvh-button">Run Auto-Invoicing</button>
                <p class="myvh-account-hint">Use your Auto-Invoicing settings to generate invoices without manually selecting bookings.</p>
            </div>
            <p id="myvh-auto-invoicing-portal-message" class="myvh-form-message" role="status" aria-live="polite"></p>
        </form>
    </div>

    <div class="myvh-card myvh-account-card myvh-settings-group myvh-invoices-panel myvh-generate-panel" data-invoices-panel="by-customer" hidden>
        <div class="myvh-account-card-head">
            <div>
                <h3>Uninvoiced Bookings by Customer</h3>
                <span><?php echo esc_html((string) $customer_group_count); ?> <?php echo 1 === $customer_group_count ? 'customer with uninvoiced bookings' : 'customers with uninvoiced bookings'; ?></span>
            </div>
        </div>
        <?php if (!empty($uninvoiced_by_customer)): ?>
            <div class="myvh-invoices-table-wrap myvh-generate-table-wrap">
                <table class="myvh-customer-list-table myvh-invoices-table myvh-generate-summary-table">
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
                                <td><strong><?php echo esc_html($customer['CustomerName'] ?? 'Unknown'); ?></strong></td>
                                <td><?php echo esc_html($customer['CustomerEmail'] ?? '-'); ?></td>
                                <td><?php echo intval($customer['UninvoicedCount']); ?></td>
                                <td><button type="button" class="myvh-drilldown-btn myvh-generate-drilldown-btn" data-customer-id="<?php echo intval($customer['CustomerId']); ?>">View Bookings</button></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="myvh-empty-state myvh-invoices-empty-state myvh-generate-empty-state">
                <p class="myvh-invoices-empty-state__title">No customers with uninvoiced bookings found.</p>
                <p>Customer groups will appear here once bookings are ready to be invoiced.</p>
            </div>
        <?php endif; ?>
        <div id="myvh-customer-drilldown" class="myvh-generate-drilldown"></div>
    </div>

    <div class="myvh-card myvh-account-card myvh-settings-group myvh-invoices-panel myvh-generate-panel" data-invoices-panel="by-organisation" hidden>
        <div class="myvh-account-card-head">
            <div>
                <h3>Uninvoiced Bookings by Organisation</h3>
                <span><?php echo esc_html((string) $organisation_group_count); ?> <?php echo 1 === $organisation_group_count ? 'organisation with uninvoiced bookings' : 'organisations with uninvoiced bookings'; ?></span>
            </div>
        </div>
        <?php if (!empty($uninvoiced_by_organisation)): ?>
            <div class="myvh-invoices-table-wrap myvh-generate-table-wrap">
                <table class="myvh-customer-list-table myvh-invoices-table myvh-generate-summary-table">
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
                                <td><strong><?php echo esc_html($org['OrganisationName'] ?? 'Unknown'); ?></strong></td>
                                <td><?php echo intval($org['UninvoicedCount']); ?></td>
                                <td><button type="button" class="myvh-drilldown-btn myvh-generate-drilldown-btn" data-organisation-id="<?php echo intval($org['OrganisationId']); ?>">View Bookings</button></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="myvh-empty-state myvh-invoices-empty-state myvh-generate-empty-state">
                <p class="myvh-invoices-empty-state__title">No organisations with uninvoiced bookings found.</p>
                <p>Organisation groups will appear here once bookings are ready to be invoiced.</p>
            </div>
        <?php endif; ?>
        <div id="myvh-organisation-drilldown" class="myvh-generate-drilldown"></div>
    </div>
</div>