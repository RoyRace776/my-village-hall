<?php

declare(strict_types=1);

namespace MYVH\Subscriptions\Entities;

if (!defined('ABSPATH')) {
    exit;
}

final class SubscriptionStatus {
    public const TRIALING = 'trialing';
    public const ACTIVE = 'active';
    public const PAST_DUE = 'past_due';
    public const CANCELLED = 'cancelled';
    public const EXPIRED = 'expired';

    public static function activeLike(): array {
        return [
            self::TRIALING,
            self::ACTIVE,
            self::PAST_DUE,
        ];
    }

    public static function normalize(string $status): string {
        $normalized = sanitize_key($status);

        return match ($normalized) {
            'trial' => self::TRIALING,
            'pending_payment' => self::PAST_DUE,
            default => $normalized,
        };
    }

    public static function isValid(string $status): bool {
        return in_array($status, [
            self::TRIALING,
            self::ACTIVE,
            self::PAST_DUE,
            self::CANCELLED,
            self::EXPIRED,
        ], true);
    }
}
