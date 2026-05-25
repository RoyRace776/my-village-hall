<?php

declare(strict_types=1);

namespace MYVH\Subscriptions\Services;

if (!defined('ABSPATH')) {
    exit;
}

class FeatureService {
    public function __construct(
        private FeatureGate $feature_gate,
        private AccountService $account_service
    ) {
    }

    public function allowsForAccount(int $account_id, string $feature): bool {
        return $this->feature_gate->allows($account_id, $feature);
    }

    public function allowsForCurrentSite(string $feature): bool {
        if (!function_exists('get_current_blog_id')) {
            return false;
        }

        $account_id = $this->resolveAccountId((int) get_current_blog_id());

        return $account_id > 0 && $this->allowsForAccount($account_id, $feature);
    }

    private function resolveAccountId(int $blog_id): int {
        if ($blog_id <= 0) {
            return 0;
        }

        try {
            $account = $this->account_service->resolveAccountFromBlogId($blog_id);
        } catch (\Throwable $exception) {
            return 0;
        }

        return (int) ($account['id'] ?? $account['Id'] ?? 0);
    }
}
