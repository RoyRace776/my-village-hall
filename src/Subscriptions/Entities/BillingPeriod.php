<?php

declare(strict_types=1);

namespace MYVH\Subscriptions\Entities;

if (!defined('ABSPATH')) {
    exit;
}

final class BillingPeriod {
    public function __construct(
        private string $start,
        private string $end
    ) {
    }

    public static function fromSubscription(Subscription $subscription): ?self {
        $start = trim($subscription->getCurrentPeriodStart());
        $end = trim($subscription->getCurrentPeriodEnd());

        if ($start === '' || $end === '') {
            return null;
        }

        return new self($start, $end);
    }

    public static function currentCalendarMonth(): self {
        $year_month = gmdate('Y-m', (int) current_time('timestamp'));

        return self::fromYearMonth($year_month);
    }

    public static function fromYearMonth(string $year_month): self {
        $normalized = preg_match('/^\d{4}-\d{2}$/', $year_month) ? $year_month : gmdate('Y-m');
        $start = $normalized . '-01 00:00:00';
        $end = gmdate('Y-m-t 23:59:59', strtotime($normalized . '-01 00:00:00'));

        return new self($start, $end);
    }

    public function getStart(): string {
        return $this->start;
    }

    public function getEnd(): string {
        return $this->end;
    }
}
