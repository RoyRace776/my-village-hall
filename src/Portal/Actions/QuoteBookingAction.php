<?php
namespace MYVH\Portal\Actions;

use MYVH\Customers\CustomerService;
use MYVH\Deposits\DepositService;
use MYVH\Portal\ClientAdminService;
use MYVH\Pricing\PricingService;
use WP_Error;

class QuoteBookingAction {
    public function __construct(
        private CustomerService $customer_service,
        private ClientAdminService $client_admin_service,
        private PricingService $pricing_service,
        private DepositService $deposit_service
    ) {}

    public function execute(array $request): array|WP_Error {
        [$start_date, $start_time] = $this->split_datetime($request['start'] ?? '');
        [$end_date, $end_time] = $this->split_datetime($request['end'] ?? '', $start_date);

        $data = [
            'start_date' => $start_date,
            'start_time' => $start_time,
            'end_date' => $end_date,
            'end_time' => $end_time,
            'room_id' => \intval($request['room_id'] ?? 0),
            'customer_id' => \intval($request['customer_id'] ?? 0),
            'organisation_id' => \intval($request['organisation_id'] ?? 0),
        ];

        $viewer_user_id = get_current_user_id();
        $is_client_admin = $this->client_admin_service->can_administer_blog($viewer_user_id, get_current_blog_id());

        if (!$is_client_admin) {
            $portal_customer = $this->customer_service->get_by_user_id($viewer_user_id);
            if (empty($portal_customer['Id'])) {
                return new WP_Error('validation', __('No customer profile found', 'my-village-hall'));
            }

            $allowed_org_ids = $this->resolve_allowed_organisation_ids($viewer_user_id, (int) $portal_customer['Id']);
            $selected_org_id = (int) $data['organisation_id'];
            if (!empty($allowed_org_ids) && !in_array($selected_org_id, $allowed_org_ids, true)) {
                $selected_org_id = (int) $allowed_org_ids[0];
            }

            $data['customer_id'] = (int) $portal_customer['Id'];
            $data['organisation_id'] = $selected_org_id;
        }

        $validation = $this->validate_quote_payload($data);
        if (is_wp_error($validation)) {
            return $validation;
        }

        $charge = $this->pricing_service->get_charge_snapshot_for_data($data);
        if (is_wp_error($charge)) {
            return $charge;
        }

        $addons = $this->normalize_addons($request['addons'] ?? []);
        $addons_total = $this->calculate_addon_total($addons);

        $deposit = null;
        try {
            $deposit = $this->deposit_service->evaluate(
                (int) $data['room_id'],
                new \DateTime(trim($data['end_date'] . ' ' . $data['end_time']))
            );
        } catch (\Throwable $exception) {
            $deposit = null;
        }

        $deposit_amount = max(0.0, round((float) ($deposit['amount'] ?? 0), 2));
        $room_charge = round((float) ($charge['TotalAmount'] ?? 0), 2);

        return [
            'room_charge' => $room_charge,
            'addons_total' => $addons_total,
            'deposit_amount' => $deposit_amount,
            'booking_total' => round($room_charge + $addons_total + $deposit_amount, 2),
            'charge' => $charge,
            'deposit' => $deposit,
        ];
    }

    private function resolve_allowed_organisation_ids(int $viewer_user_id, int $viewer_customer_id): array {
        $organisation_ids = array_map(static function ($org) {
            return (int) ($org['Id'] ?? 0);
        }, (array) $this->customer_service->get_organisations_for_user_id($viewer_user_id));

        if ($viewer_customer_id > 0) {
            $organisation_ids = array_merge(
                $organisation_ids,
                array_map(static function ($org) {
                    return (int) ($org['Id'] ?? 0);
                }, (array) $this->customer_service->get_organisations_for_customer($viewer_customer_id))
            );
        }

        return array_values(array_unique(array_filter($organisation_ids)));
    }

    private function validate_quote_payload(array $data): true|WP_Error {
        if (empty($data['room_id'])) {
            return new WP_Error('validation', __('Room is required', 'my-village-hall'));
        }

        if (empty($data['customer_id'])) {
            return new WP_Error('validation', __('Customer is required', 'my-village-hall'));
        }

        if (empty($data['organisation_id'])) {
            return new WP_Error('validation', __('Organisation is required', 'my-village-hall'));
        }

        if (empty($data['start_date']) || empty($data['start_time']) || empty($data['end_date']) || empty($data['end_time'])) {
            return new WP_Error('validation', __('Start and end date/time are required', 'my-village-hall'));
        }

        $start_stamp = strtotime($data['start_date'] . ' ' . $data['start_time']);
        $end_stamp = strtotime($data['end_date'] . ' ' . $data['end_time']);
        if ($start_stamp === false || $end_stamp === false || $end_stamp <= $start_stamp) {
            return new WP_Error('validation', __('End time must be later than the start time', 'my-village-hall'));
        }

        return true;
    }

    private function split_datetime( mixed $value, mixed $default_date = ''): array {
        $raw = trim((string) $value);

        if ($raw === '') {
            return [sanitize_text_field($default_date), ''];
        }

        $timestamp = strtotime($raw);
        if ($timestamp !== false) {
            return [date('Y-m-d', $timestamp), date('H:i:s', $timestamp)];
        }

        $parts = preg_split('/[T\s]/', $raw);
        $date = sanitize_text_field($parts[0] ?? $default_date);
        $time = sanitize_text_field($parts[1] ?? '');
        if (preg_match('/^\d{2}:\d{2}$/', $time)) {
            $time .= ':00';
        }

        return [$date, $time];
    }

    private function normalize_addons($raw_addons): array {
        if (!is_array($raw_addons)) {
            return [];
        }

        $normalized = [];
        foreach ($raw_addons as $addon) {
            if (!is_array($addon)) {
                continue;
            }

            $addon_id = \intval($addon['addon_id'] ?? 0);
            if ($addon_id <= 0) {
                continue;
            }

            $enabled_raw = strtolower(trim((string) ($addon['enabled'] ?? '')));
            $enabled = in_array($enabled_raw, ['1', 'true', 'on', 'yes'], true);
            if (!$enabled && array_key_exists('enabled', $addon)) {
                continue;
            }

            $quantity = \floatval($addon['quantity'] ?? 1);
            if ($quantity <= 0) {
                continue;
            }

            $normalized[] = [
                'addon_id' => $addon_id,
                'quantity' => $quantity,
                'unit_price' => \floatval($addon['unit_price'] ?? 0),
            ];
        }

        return $normalized;
    }

    private function calculate_addon_total(array $addons): float {
        $total = 0.0;
        foreach ($addons as $addon) {
            $total += round((float) ($addon['unit_price'] ?? 0), 2) * (float) ($addon['quantity'] ?? 0);
        }

        return round($total, 2);
    }
}
