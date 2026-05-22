<?php

declare(strict_types=1);

namespace MYVH\Subscriptions\Repositories;

use wpdb;

if (!defined('ABSPATH')) {
    exit;
}

class ProcessedStripeEventRepository extends WpdbCrudRepository {
    public function __construct(wpdb $wpdb) {
        parent::__construct($wpdb, $wpdb->base_prefix . 'myvh_processed_stripe_events');
    }

    public function hasBeenProcessed(string $event_id): bool {
        $event_id = trim($event_id);
        if ($event_id === '') {
            return false;
        }

        $sql = $this->wpdb->prepare(
            "SELECT id
             FROM {$this->table_name}
             WHERE event_id = %s
             LIMIT 1",
            $event_id
        );

        return (int) $this->wpdb->get_var($sql) > 0;
    }

    public function markProcessed(string $event_id, string $event_type): bool {
        $event_id = trim($event_id);
        $event_type = sanitize_key($event_type);

        if ($event_id === '' || $event_type === '') {
            return false;
        }

        if ($this->hasBeenProcessed($event_id)) {
            return true;
        }

        $insert = $this->wpdb->insert(
            $this->table_name,
            [
                'event_id' => $event_id,
                'event_type' => $event_type,
            ],
            ['%s', '%s']
        );

        return $insert !== false;
    }
}
