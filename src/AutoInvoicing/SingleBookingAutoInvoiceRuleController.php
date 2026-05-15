<?php
namespace MYVH\AutoInvoicing;

if (!defined('ABSPATH')) {
    exit;
}

class SingleBookingAutoInvoiceRuleController {
    public function __construct(private SingleBookingAutoInvoiceRuleRepository $rule_repository) {}

    public function save(): void {
        if (!current_user_can('manage_myvh')) {
            wp_die(__('Permission denied', 'my-village-hall'));
        }

        check_admin_referer('myvh_save_single_booking_auto_invoice_rules');

        $rows = wp_unslash($_POST['rules'] ?? []);
        if (!is_array($rows)) {
            $rows = [];
        }

        $active_rule_ids = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $name = sanitize_text_field($row['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $trigger_timing = sanitize_key($row['trigger_timing'] ?? 'confirmation');
            if (!in_array($trigger_timing, ['confirmation', 'booking_date', 'days_before_booking_date', 'days_after_booking_date', 'manual_invoicing'], true)) {
                $trigger_timing = 'confirmation';
            }

            $group_by = sanitize_key($row['group_by'] ?? 'per_booking');
            if (!in_array($group_by, ['per_booking', 'by_customer', 'by_organisation'], true)) {
                $group_by = 'per_booking';
            }

            $record = [
                'Id' => \intval($row['id'] ?? 0),
                'Name' => $name,
                'TriggerTiming' => $trigger_timing,
                'TriggerOffsetDays' => max(0, \intval($row['trigger_offset_days'] ?? 0)),
                'GroupBy' => $group_by,
                'DueDateOffsetDays' => max(0, \intval($row['due_date_offset_days'] ?? 30)),
                'IsActive' => !empty($row['is_active']) ? 1 : 0,
            ];

            $saved_rule_id = $this->rule_repository->upsert_rule($record);
            if (!$saved_rule_id) {
                continue;
            }

            if ($record['IsActive'] === 1) {
                $active_rule_ids[] = \intval($saved_rule_id);
            }
        }

        $this->rule_repository->deactivate_rules_not_in($active_rule_ids);

        $settings = get_option('myvh_invoicing_settings', []);
        if (!is_array($settings)) {
            $settings = [];
        }

        $default_rule_id = \intval($settings['single_default_rule_id'] ?? 0);
        if ($default_rule_id <= 0 || !$this->rule_repository->is_active_rule($default_rule_id)) {
            $settings['single_default_rule_id'] = \intval($this->rule_repository->get_first_active_rule_id() ?? 0);
            update_option('myvh_invoicing_settings', $settings);
        }

        wp_redirect(admin_url('admin.php?page=myvh-single-booking-invoice-rules&updated=1'));
        exit;
    }
}
