<?php

declare(strict_types=1);

namespace MYVH\Subscriptions\Services;

use MYVH\Subscriptions\Entities\Subscription;
use MYVH\Subscriptions\Repositories\SettingsRepository;
use MYVH\Subscriptions\Repositories\SubscriptionRepository;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Backward-compatible subscription enforcement service.
 *
 * Newer flows use SubscriptionGuard, but this class is kept for existing
 * call sites/tests that still type-hint EnforcementService.
 */
class EnforcementService {
    private const SECONDS_PER_DAY = 86400;

    private ?WP_Error $last_error = null;

    public function __construct(
        private SubscriptionRepository $subscription_repository,
        private SettingsRepository $settings_repository,
        private UsageService $usage_service,
        private TrialService $trial_service
    ) {
    }

    public function canCreateBooking(int $account_id): bool {
        $result = $this->getBookingPermission($account_id);

        return (bool) ($result['allowed'] ?? false);
    }

    public function getBookingPermission(int $account_id): array {
        $this->last_error = null;

        if ($account_id <= 0) {
            return $this->deny('account_missing');
        }

        $subscription = $this->trial_service->checkAndExpireTrial($account_id);
        if (!$subscription instanceof Subscription) {
            return $this->deny('subscription_missing');
        }

        if ($subscription->isExpired()) {
            return $this->deny('expired');
        }

        if ($subscription->isCancelled()) {
            return $this->deny('cancelled');
        }

        if ($subscription->isPastDue()) {
            $grace_days = (int) $this->settings_repository->get_setting_value('grace_period_days', 0);
            if ($this->isBeyondGraceWindow($subscription->getCurrentPeriodEnd(), $grace_days)) {
                return $this->deny('past_due');
            }
        }

        if ($this->usage_service->exceedsLimit($account_id)) {
            return $this->deny('usage_limit');
        }

        return [
            'allowed' => true,
            'reason' => '',
        ];
    }

    public function getLastError(): ?WP_Error {
        return $this->last_error;
    }

    public function getLastErrorMessage(): string {
        return $this->last_error instanceof WP_Error
            ? (string) $this->last_error->get_error_message()
            : '';
    }

    private function deny(string $reason): array {
        $this->last_error = $this->buildErrorFromReason($reason);

        return [
            'allowed' => false,
            'reason' => $reason,
        ];
    }

    private function buildErrorFromReason(string $reason): WP_Error {
        return match ($reason) {
            'account_missing' => new WP_Error('subscription_account_missing', __('Unable to resolve account for this site', 'my-village-hall')),
            'subscription_missing' => new WP_Error('subscription_missing', __('No subscription found for this account', 'my-village-hall')),
            'expired' => new WP_Error('subscription_expired', __('Subscription has expired', 'my-village-hall')),
            'cancelled' => new WP_Error('subscription_cancelled', __('Subscription has been cancelled', 'my-village-hall')),
            'past_due' => new WP_Error('subscription_past_due', __('Subscription payment is overdue beyond grace period', 'my-village-hall')),
            'usage_limit' => new WP_Error('usage_limit', __('Booking limit reached for this account', 'my-village-hall')),
            default => new WP_Error('subscription_enforcement', __('Booking creation is not allowed for this account', 'my-village-hall')),
        };
    }

    private function isBeyondGraceWindow(string $period_end_raw, int $grace_days): bool {
        $period_end_raw = trim($period_end_raw);
        if ($period_end_raw === '') {
            return true;
        }

        $period_end = strtotime($period_end_raw);
        if ($period_end === false) {
            return true;
        }

        $now = (int) current_time('timestamp');
        $grace_seconds = max(0, $grace_days) * self::SECONDS_PER_DAY;

        return $now > ($period_end + $grace_seconds);
    }
}
