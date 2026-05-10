<?php
if (!defined('ABSPATH')) {
    exit;
}

$rules = (isset($rules) && is_array($rules)) ? $rules : [];
$default_rule_id = isset($default_rule_id) ? intval($default_rule_id) : 0;
$load_error = isset($load_error) ? trim((string) $load_error) : '';
?>

<div class="myvh-dashboard-section myvh-client-settings-page myvh-single-invoice-rules-page">
    <div class="myvh-account-header">
        <div>
            <h2>Single Booking Invoice Rules</h2>

    <?php if ($load_error !== ''): ?>
        <p class="myvh-error"><?php echo esc_html($load_error); ?></p>
    <?php endif; ?>
            <p>Manage automatic invoicing rules for single bookings and choose the default rule.</p>
        </div>
    </div>

    <div class="myvh-card myvh-account-card myvh-settings-group is-active">
        <form class="myvh-account-form" data-portal-action="myvh_portal_save_single_booking_invoice_rules" data-message-target="myvh-single-booking-rules-message" data-reload-page="single-booking-invoice-rules">
            <label class="myvh-account-field" for="myvh-default-single-booking-rule">
                <span>Default rule</span>
                <select id="myvh-default-single-booking-rule" name="default_rule_id">
                    <option value="0">Auto-select first active rule</option>
                    <?php foreach ($rules as $rule): ?>
                        <?php if (empty($rule['IsActive'])) { continue; } ?>
                        <option value="<?php echo intval($rule['Id']); ?>" <?php selected($default_rule_id, intval($rule['Id'])); ?>>
                            <?php echo esc_html((string) ($rule['Name'] ?? 'Unnamed rule')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="myvh-muted">Used when a customer and organisation do not have specific overrides.</small>
            </label>

            <div class="myvh-invoices-table-wrap myvh-generate-table-wrap">
                <table class="myvh-customer-list-table myvh-invoices-table" id="myvh-single-booking-rules-table">
                    <thead>
                        <tr>
                            <th>Rule</th>
                            <th>Trigger</th>
                            <th>Offset</th>
                            <th>Group By</th>
                            <th>Due Days</th>
                            <th>Sort</th>
                            <th>Active</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rules)): ?>
                            <tr class="myvh-rule-row">
                                <td>
                                    <input type="hidden" name="rules[0][id]" value="0">
                                    <input type="text" name="rules[0][name]" required>
                                </td>
                                <td>
                                    <select name="rules[0][trigger_timing]">
                                        <option value="confirmation">On booking confirmation</option>
                                        <option value="booking_date">On booking date</option>
                                        <option value="days_before_booking_date">N days before booking date</option>
                                        <option value="days_after_booking_date">N days after booking date</option>
                                        <option value="manual_invoicing">Manual invoicing</option>
                                    </select>
                                </td>
                                <td><input type="number" name="rules[0][trigger_offset_days]" min="0" value="0"></td>
                                <td>
                                    <select name="rules[0][group_by]">
                                        <option value="per_booking">Per booking</option>
                                        <option value="by_customer">By customer</option>
                                        <option value="by_organisation">By organisation</option>
                                    </select>
                                </td>
                                <td><input type="number" name="rules[0][due_date_offset_days]" min="0" value="30"></td>
                                <td><input type="number" name="rules[0][sort_order]" min="0" value="0"></td>
                                <td><input type="checkbox" name="rules[0][is_active]" value="1" checked></td>
                                <td><button type="button" class="button myvh-remove-rule-row">Remove</button></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($rules as $index => $rule): ?>
                                <tr class="myvh-rule-row">
                                    <td>
                                        <input type="hidden" name="rules[<?php echo intval($index); ?>][id]" value="<?php echo intval($rule['Id']); ?>">
                                        <input type="text" name="rules[<?php echo intval($index); ?>][name]" required value="<?php echo esc_attr((string) ($rule['Name'] ?? '')); ?>">
                                    </td>
                                    <td>
                                        <select name="rules[<?php echo intval($index); ?>][trigger_timing]">
                                            <option value="confirmation" <?php selected((string) ($rule['TriggerTiming'] ?? ''), 'confirmation'); ?>>On booking confirmation</option>
                                            <option value="booking_date" <?php selected((string) ($rule['TriggerTiming'] ?? ''), 'booking_date'); ?>>On booking date</option>
                                            <option value="days_before_booking_date" <?php selected((string) ($rule['TriggerTiming'] ?? ''), 'days_before_booking_date'); ?>>N days before booking date</option>
                                            <option value="days_after_booking_date" <?php selected((string) ($rule['TriggerTiming'] ?? ''), 'days_after_booking_date'); ?>>N days after booking date</option>
                                            <option value="manual_invoicing" <?php selected((string) ($rule['TriggerTiming'] ?? ''), 'manual_invoicing'); ?>>Manual invoicing</option>
                                        </select>
                                    </td>
                                    <td><input type="number" name="rules[<?php echo intval($index); ?>][trigger_offset_days]" min="0" value="<?php echo intval($rule['TriggerOffsetDays'] ?? 0); ?>"></td>
                                    <td>
                                        <select name="rules[<?php echo intval($index); ?>][group_by]">
                                            <option value="per_booking" <?php selected((string) ($rule['GroupBy'] ?? ''), 'per_booking'); ?>>Per booking</option>
                                            <option value="by_customer" <?php selected((string) ($rule['GroupBy'] ?? ''), 'by_customer'); ?>>By customer</option>
                                            <option value="by_organisation" <?php selected((string) ($rule['GroupBy'] ?? ''), 'by_organisation'); ?>>By organisation</option>
                                        </select>
                                    </td>
                                    <td><input type="number" name="rules[<?php echo intval($index); ?>][due_date_offset_days]" min="0" value="<?php echo intval($rule['DueDateOffsetDays'] ?? 30); ?>"></td>
                                    <td><input type="number" name="rules[<?php echo intval($index); ?>][sort_order]" min="0" value="<?php echo intval($rule['SortOrder'] ?? 0); ?>"></td>
                                    <td><input type="checkbox" name="rules[<?php echo intval($index); ?>][is_active]" value="1" <?php checked(!empty($rule['IsActive'])); ?>></td>
                                    <td><button type="button" class="button myvh-remove-rule-row">Remove</button></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="myvh-account-actions">
                <button type="button" class="button" id="myvh-add-single-booking-rule">Add Rule</button>
                <button type="submit" class="button button-primary">Save Rules</button>
                <div id="myvh-single-booking-rules-message" class="myvh-muted" aria-live="polite"></div>
            </div>
        </form>
    </div>
</div>

