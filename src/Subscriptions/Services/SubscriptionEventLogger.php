<?php

declare(strict_types=1);

namespace MYVH\Subscriptions\Services;

use MYVH\Subscriptions\Repositories\SubscriptionEventLogRepository;

if (!defined('ABSPATH')) {
    exit;
}

class SubscriptionEventLogger {
    public function __construct(private SubscriptionEventLogRepository $event_log_repository) {
    }

    public function log(
        int $account_id,
        string $event_type,
        ?string $previous_status = null,
        ?string $new_status = null,
        string $source = 'system',
        ?array $payload = null,
        ?int $blog_id = null
    ): bool {
        if ($account_id <= 0) {
            return false;
        }

        $event_type = sanitize_key($event_type);
        if ($event_type === '') {
            return false;
        }

        $result = $this->event_log_repository->create([
            'account_id' => $account_id,
            'blog_id' => $blog_id ?: null,
            'event_type' => $event_type,
            'previous_status' => $previous_status ? sanitize_key($previous_status) : null,
            'new_status' => $new_status ? sanitize_key($new_status) : null,
            'source' => sanitize_key($source) ?: 'system',
            'payload' => $payload ? wp_json_encode($payload) : null,
            'created_at' => current_time('mysql'),
        ]);

        return $result !== false;
    }
}
