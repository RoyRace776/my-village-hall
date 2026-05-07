<?php

declare(strict_types=1);

namespace MYVH\Rooms;

if (!defined('ABSPATH')) {
    exit;
}

class RoomDepositRepository {
    private const META_ENABLED = 'myvh_deposit_enabled';
    private const META_DAYS = 'myvh_deposit_days';
    private const META_END_AFTER = 'myvh_deposit_end_after';
    private const META_AMOUNT = 'myvh_deposit_amount';
    private const META_ACTION = 'myvh_deposit_action';

    private const VALID_DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
    private const VALID_ACTIONS = ['auto_add', 'require_review'];

    /**
     * Get room deposit configuration.
     *
     * @param int $room_id
     * @return array{enabled: bool, days: array<int,string>, end_after: string|null, amount: float, action: string}
     */
    public function get(int $room_id): array {
        $enabled = (bool) get_post_meta($room_id, self::META_ENABLED, true);
        $days_csv = (string) get_post_meta($room_id, self::META_DAYS, true);
        $end_after_raw = (string) get_post_meta($room_id, self::META_END_AFTER, true);
        $amount = (float) get_post_meta($room_id, self::META_AMOUNT, true);
        $action = sanitize_key((string) get_post_meta($room_id, self::META_ACTION, true));

        $days = $this->sanitize_days($days_csv === '' ? [] : explode(',', $days_csv));
        $end_after = $this->sanitize_end_after($end_after_raw);
        $amount = max(0.0, $amount);

        if (!in_array($action, self::VALID_ACTIONS, true)) {
            $action = 'auto_add';
        }

        return [
            'enabled' => $enabled,
            'days' => $days,
            'end_after' => $end_after,
            'amount' => $amount,
            'action' => $action,
        ];
    }

    /**
     * Save room deposit configuration.
     *
     * @param int $room_id
     * @param array<string,mixed> $data
     * @return void
     */
    public function save(int $room_id, array $data): void {
        $enabled = !empty($data['enabled']);
        $days = $this->sanitize_days($data['days'] ?? []);
        $end_after = $this->sanitize_end_after((string) ($data['end_after'] ?? ''));
        $amount = max(0.0, (float) ($data['amount'] ?? 0));
        $action = sanitize_key((string) ($data['action'] ?? 'auto_add'));

        if (!in_array($action, self::VALID_ACTIONS, true)) {
            $action = 'auto_add';
        }

        update_post_meta($room_id, self::META_ENABLED, $enabled ? '1' : '0');
        update_post_meta($room_id, self::META_DAYS, implode(',', $days));

        if ($end_after === null) {
            delete_post_meta($room_id, self::META_END_AFTER);
        } else {
            update_post_meta($room_id, self::META_END_AFTER, $end_after);
        }

        update_post_meta($room_id, self::META_AMOUNT, (string) round($amount, 2));
        update_post_meta($room_id, self::META_ACTION, $action);
    }

    /**
     * @param mixed $days
     * @return array<int,string>
     */
    private function sanitize_days(mixed $days): array {
        if (is_string($days)) {
            $days = explode(',', $days);
        }

        if (!is_array($days)) {
            return [];
        }

        $normalized = [];
        foreach ($days as $day) {
            $value = strtolower(trim(sanitize_key((string) $day)));
            if (in_array($value, self::VALID_DAYS, true)) {
                $normalized[] = $value;
            }
        }

        return array_values(array_unique($normalized));
    }

    private function sanitize_end_after(string $value): ?string {
        $value = trim(sanitize_text_field($value));
        if ($value === '') {
            return null;
        }

        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) ? $value : null;
    }
}