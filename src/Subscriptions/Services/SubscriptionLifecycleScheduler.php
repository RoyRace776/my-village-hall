<?php

declare(strict_types=1);

namespace MYVH\Subscriptions\Services;

if (!defined('ABSPATH')) {
    exit;
}

class SubscriptionLifecycleScheduler {
    private const CRON_HOOK = 'myvh_subscription_lifecycle_daily';

    public function __construct(private SubscriptionLifecycleService $lifecycle_service) {
    }

    public function register(): void {
        add_action('init', [$this, 'schedule']);
        add_action(self::CRON_HOOK, [$this, 'run']);
    }

    public function schedule(): void {
        if (wp_next_scheduled(self::CRON_HOOK)) {
            return;
        }

        wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK);
    }

    public function run(): void {
        $this->lifecycle_service->runDaily();
    }
}
