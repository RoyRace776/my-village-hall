<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('manage_myvh')) {
    wp_die(__('Permission denied', 'my-village-hall'));
}

use MYVH\AutoInvoicing\SingleBookingAutoInvoiceRuleRepository;

global $myvh_container;

$rule_repository = $myvh_container->get(SingleBookingAutoInvoiceRuleRepository::class);
$rules = $rule_repository->get_all_rules();
?>

<div class="wrap">
    <h1><?php esc_html_e('Single Booking Auto-Invoice Rules', 'my-village-hall'); ?></h1>
    <p><?php esc_html_e('Define reusable rules for single booking invoice automation. Choose the default rule from Settings > Invoicing.', 'my-village-hall'); ?></p>

    <?php if (isset($_GET['updated'])): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e('Rules saved.', 'my-village-hall'); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="myvh_save_single_booking_auto_invoice_rules">
        <?php wp_nonce_field('myvh_save_single_booking_auto_invoice_rules'); ?>

        <table class="wp-list-table widefat striped" id="myvh-single-booking-rules-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Rule Name', 'my-village-hall'); ?></th>
                    <th><?php esc_html_e('Trigger', 'my-village-hall'); ?></th>
                    <th><?php esc_html_e('Offset Days', 'my-village-hall'); ?></th>
                    <th><?php esc_html_e('Group By', 'my-village-hall'); ?></th>
                    <th><?php esc_html_e('Due Date Offset', 'my-village-hall'); ?></th>
                    <th><?php esc_html_e('Active', 'my-village-hall'); ?></th>
                    <th><?php esc_html_e('Actions', 'my-village-hall'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rules)): ?>
                    <tr class="myvh-rule-row">
                        <td>
                            <input type="hidden" name="rules[0][id]" value="0">
                            <input type="text" name="rules[0][name]" class="regular-text" placeholder="Default single booking rule">
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
                        <td><input type="number" name="rules[0][trigger_offset_days]" value="0" min="0" class="small-text"></td>
                        <td>
                            <select name="rules[0][group_by]">
                                <option value="per_booking">One invoice per booking</option>
                                <option value="by_customer">Group bookings by customer</option>
                                <option value="by_organisation">Group bookings by organisation</option>
                            </select>
                        </td>
                        <td><input type="number" name="rules[0][due_date_offset_days]" value="30" min="0" class="small-text"></td>
                        <td><input type="checkbox" name="rules[0][is_active]" value="1" checked></td>
                        <td><button type="button" class="button button-link-delete myvh-remove-rule-row"><?php esc_html_e('Remove', 'my-village-hall'); ?></button></td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rules as $index => $rule): ?>
                        <tr class="myvh-rule-row">
                            <td>
                                <input type="hidden" name="rules[<?php echo \intval($index); ?>][id]" value="<?php echo \intval($rule['Id']); ?>">
                                <input type="text" name="rules[<?php echo \intval($index); ?>][name]" class="regular-text" value="<?php echo esc_attr($rule['Name'] ?? ''); ?>">
                            </td>
                            <td>
                                <select name="rules[<?php echo \intval($index); ?>][trigger_timing]">
                                    <option value="confirmation" <?php selected((string) ($rule['TriggerTiming'] ?? ''), 'confirmation'); ?>>On booking confirmation</option>
                                    <option value="booking_date" <?php selected((string) ($rule['TriggerTiming'] ?? ''), 'booking_date'); ?>>On booking date</option>
                                    <option value="days_before_booking_date" <?php selected((string) ($rule['TriggerTiming'] ?? ''), 'days_before_booking_date'); ?>>N days before booking date</option>
                                    <option value="days_after_booking_date" <?php selected((string) ($rule['TriggerTiming'] ?? ''), 'days_after_booking_date'); ?>>N days after booking date</option>
                                    <option value="manual_invoicing" <?php selected((string) ($rule['TriggerTiming'] ?? ''), 'manual_invoicing'); ?>>Manual invoicing</option>
                                </select>
                            </td>
                            <td><input type="number" name="rules[<?php echo \intval($index); ?>][trigger_offset_days]" value="<?php echo \intval($rule['TriggerOffsetDays'] ?? 0); ?>" min="0" class="small-text"></td>
                            <td>
                                <select name="rules[<?php echo \intval($index); ?>][group_by]">
                                    <option value="per_booking" <?php selected((string) ($rule['GroupBy'] ?? ''), 'per_booking'); ?>>One invoice per booking</option>
                                    <option value="by_customer" <?php selected((string) ($rule['GroupBy'] ?? ''), 'by_customer'); ?>>Group bookings by customer</option>
                                    <option value="by_organisation" <?php selected((string) ($rule['GroupBy'] ?? ''), 'by_organisation'); ?>>Group bookings by organisation</option>
                                </select>
                            </td>
                            <td><input type="number" name="rules[<?php echo \intval($index); ?>][due_date_offset_days]" value="<?php echo \intval($rule['DueDateOffsetDays'] ?? 30); ?>" min="0" class="small-text"></td>
                            <td><input type="checkbox" name="rules[<?php echo \intval($index); ?>][is_active]" value="1" <?php checked(!empty($rule['IsActive'])); ?>></td>
                            <td><button type="button" class="button button-link-delete myvh-remove-rule-row"><?php esc_html_e('Remove', 'my-village-hall'); ?></button></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <p>
            <button type="button" class="button" id="myvh-add-rule-row"><?php esc_html_e('Add Rule', 'my-village-hall'); ?></button>
        </p>

        <?php submit_button(__('Save Rules', 'my-village-hall')); ?>
    </form>
</div>

<script>
(function () {
    var table = document.getElementById('myvh-single-booking-rules-table');
    var addButton = document.getElementById('myvh-add-rule-row');

    if (!table || !addButton) {
        return;
    }

    var tbody = table.querySelector('tbody');

    function nextIndex() {
        return tbody.querySelectorAll('tr.myvh-rule-row').length;
    }

    function buildRow(index) {
        var row = document.createElement('tr');
        row.className = 'myvh-rule-row';
        row.innerHTML = '' +
            '<td><input type="hidden" name="rules[' + index + '][id]" value="0"><input type="text" name="rules[' + index + '][name]" class="regular-text" placeholder="Rule name"></td>' +
            '<td><select name="rules[' + index + '][trigger_timing]">' +
                '<option value="confirmation">On booking confirmation</option>' +
                '<option value="booking_date">On booking date</option>' +
                '<option value="days_before_booking_date">N days before booking date</option>' +
                '<option value="days_after_booking_date">N days after booking date</option>' +
                '<option value="manual_invoicing">Manual invoicing</option>' +
            '</select></td>' +
            '<td><input type="number" name="rules[' + index + '][trigger_offset_days]" value="0" min="0" class="small-text"></td>' +
            '<td><select name="rules[' + index + '][group_by]">' +
                '<option value="per_booking">One invoice per booking</option>' +
                '<option value="by_customer">Group bookings by customer</option>' +
                '<option value="by_organisation">Group bookings by organisation</option>' +
            '</select></td>' +
            '<td><input type="number" name="rules[' + index + '][due_date_offset_days]" value="30" min="0" class="small-text"></td>' +
            '<td><input type="checkbox" name="rules[' + index + '][is_active]" value="1" checked></td>' +
            '<td><button type="button" class="button button-link-delete myvh-remove-rule-row">Remove</button></td>';
        return row;
    }

    addButton.addEventListener('click', function () {
        tbody.appendChild(buildRow(nextIndex()));
    });

    tbody.addEventListener('click', function (event) {
        if (!event.target.classList.contains('myvh-remove-rule-row')) {
            return;
        }

        var row = event.target.closest('tr.myvh-rule-row');
        if (row) {
            row.remove();
        }
    });
})();
</script>
