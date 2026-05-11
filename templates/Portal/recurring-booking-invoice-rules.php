<?php
if (!defined('ABSPATH')) {
    exit;
}

$rules = (isset($rules) && is_array($rules)) ? $rules : [];
$default_rule_id = isset($default_rule_id) ? intval($default_rule_id) : 0;
$load_error = isset($load_error) ? trim((string) $load_error) : '';
?>

<div class="myvh-dashboard-section myvh-client-settings-page myvh-recurring-invoice-rules-page">
    <div class="myvh-account-header">
        <div>
            <h2>Recurring Booking Invoice Rules</h2>

    <?php if ($load_error !== ''): ?>
        <p class="myvh-error"><?php echo esc_html($load_error); ?></p>
    <?php endif; ?>
            <p>Manage automatic invoicing rules for recurring bookings and choose the default rule.</p>
        </div>
    </div>

    <div class="myvh-card myvh-account-card myvh-settings-group is-active">
        <form class="myvh-account-form" data-portal-action="myvh_portal_save_recurring_booking_invoice_rules" data-message-target="myvh-recurring-booking-rules-message" data-reload-page="recurring-booking-invoice-rules">
            <label class="myvh-account-field" for="myvh-default-recurring-booking-rule">
                <span>Default rule</span>
                <select id="myvh-default-recurring-booking-rule" name="default_rule_id">
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
                <table class="myvh-customer-list-table myvh-invoices-table" id="myvh-recurring-booking-rules-table">
                    <thead>
                        <tr>
                            <th>Rule</th>
                            <th>Trigger</th>
                            <th>Timing</th>
                            <th style="width: 74px;">Period</th>
                            <th>Group By</th>
                            <th>Due Days</th>
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
                                    <select name="rules[0][trigger_timing]" style="min-width: 190px;">
                                        <option value="start_of_month">Start of month</option>
                                        <option value="start_of_quarter">Start of quarter</option>
                                        <option value="start_of_week">Start of week</option>
                                        <option value="manual_invoicing">Manual invoicing</option>
                                        <option value="treat_as_single_bookings">Treat as single bookings</option>
                                    </select>
                                </td>
                                <td>
                                    <select name="rules[0][trigger_direction]" style="min-width: 150px;">
                                        <option value="in_advance">In advance</option>
                                        <option value="in_arrears">In arrears</option>
                                    </select>
                                </td>
                                <td><input type="number" name="rules[0][trigger_period_count]" min="0" max="99" value="0" style="width: 46px;"></td>
                                <td>
                                    <select name="rules[0][group_by]" style="min-width: 160px;">
                                        <option value="per_booking">Per booking</option>
                                        <option value="by_customer">By customer</option>
                                        <option value="by_organisation">By organisation</option>
                                    </select>
                                </td>
                                <td><input type="number" name="rules[0][due_date_offset_days]" min="0" value="30"></td>
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
                                        <select name="rules[<?php echo intval($index); ?>][trigger_timing]" style="min-width: 190px;">
                                            <option value="start_of_month" <?php selected((string) ($rule['TriggerTiming'] ?? ''), 'start_of_month'); ?>>Start of month</option>
                                            <option value="start_of_quarter" <?php selected((string) ($rule['TriggerTiming'] ?? ''), 'start_of_quarter'); ?>>Start of quarter</option>
                                            <option value="start_of_week" <?php selected((string) ($rule['TriggerTiming'] ?? ''), 'start_of_week'); ?>>Start of week</option>
                                            <option value="manual_invoicing" <?php selected((string) ($rule['TriggerTiming'] ?? ''), 'manual_invoicing'); ?>>Manual invoicing</option>
                                            <option value="treat_as_single_bookings" <?php selected((string) ($rule['TriggerTiming'] ?? ''), 'treat_as_single_bookings'); ?>>Treat as single bookings</option>
                                        </select>
                                    </td>
                                    <td>
                                        <select name="rules[<?php echo intval($index); ?>][trigger_direction]" style="min-width: 150px;">
                                            <option value="in_advance" <?php selected((string) ($rule['TriggerDirection'] ?? ''), 'in_advance'); ?>>In advance</option>
                                            <option value="in_arrears" <?php selected((string) ($rule['TriggerDirection'] ?? ''), 'in_arrears'); ?>>In arrears</option>
                                        </select>
                                    </td>
                                    <td><input type="number" name="rules[<?php echo intval($index); ?>][trigger_period_count]" min="0" max="99" value="<?php echo intval($rule['TriggerOffsetDays'] ?? 0); ?>" style="width: 46px;"></td>
                                    <td>
                                        <select name="rules[<?php echo intval($index); ?>][group_by]" style="min-width: 160px;">
                                            <option value="per_booking" <?php selected((string) ($rule['GroupBy'] ?? ''), 'per_booking'); ?>>Per booking</option>
                                            <option value="by_customer" <?php selected((string) ($rule['GroupBy'] ?? ''), 'by_customer'); ?>>By customer</option>
                                            <option value="by_organisation" <?php selected((string) ($rule['GroupBy'] ?? ''), 'by_organisation'); ?>>By organisation</option>
                                        </select>
                                    </td>
                                    <td><input type="number" name="rules[<?php echo intval($index); ?>][due_date_offset_days]" min="0" value="<?php echo intval($rule['DueDateOffsetDays'] ?? 30); ?>"></td>
                                    <td><input type="checkbox" name="rules[<?php echo intval($index); ?>][is_active]" value="1" <?php checked(!empty($rule['IsActive'])); ?>></td>
                                    <td><button type="button" class="button myvh-remove-rule-row">Remove</button></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="myvh-account-actions">
                <button type="button" class="button" id="myvh-add-recurring-booking-rule">Add Rule</button>
                <button type="submit" class="button button-primary">Save Rules</button>
                <div id="myvh-recurring-booking-rules-message" class="myvh-muted" aria-live="polite"></div>
            </div>
        </form>
    </div>
</div>
