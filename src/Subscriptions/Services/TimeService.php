<?php

declare(strict_types=1);

namespace MYVH\Subscriptions\Services;

if (!defined('ABSPATH')) {
    exit;
}

class TimeService {
    private const STORAGE_FORMAT = 'Y-m-d H:i:s';

    public function now(): \DateTimeImmutable {
        return new \DateTimeImmutable('now', wp_timezone());
    }

    public function utcNow(): \DateTimeImmutable {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function toUtcString(\DateTimeImmutable $date_time): string {
        return $date_time
            ->setTimezone(new \DateTimeZone('UTC'))
            ->format(self::STORAGE_FORMAT);
    }
}