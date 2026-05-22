<?php

declare(strict_types=1);

namespace MYVH\Subscriptions\Services;

use MYVH\Subscriptions\Entities\Subscription;
use MYVH\Subscriptions\Exceptions\SubscriptionRequiredException;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class SubscriptionGuard {
    private const SECONDS_PER_DAY = 86400;

    private ?WP_Error $last_error = null;

    public function __construct(
        private TrialService $trial_service,
        private SettingsService $settings_service,
        private UsageService $usage_service,
        private AccountService $account_service
    ) {
    }

    public function assertCanCreateBookingForCurrentSite(): bool|WP_Error {
        if (!function_exists('get_current_blog_id')) {
            return new WP_Error('subscription_guard', __('Unable to resolve current site', 'my-village-hall'));
        }

        return $this->assertCanCreateBookingForBlog((int) get_current_blog_id());
    }

    public function assertCanCreateBookingForBlog(int $blog_id): bool|WP_Error {
        $account_id = $this->resolveAccountId($blog_id);

        return $this->assertCanCreateBookingForAccount($account_id);
    }

    public function assertCanCreateBookingForAccount(int $account_id): bool|WP_Error {
        $result = $this->getBookingPermission($account_id);
        if ($result['allowed']) {
            return true;
        }

        return $this->last_error
            ?? new WP_Error('subscription_enforcement', __('Booking creation is not allowed for this account', 'my-village-hall'));
    }

    public function canCreateBooking(int $account_id): bool {
        $result = $this->getBookingPermission($account_id);

        return $result['allowed'];
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
            $grace_days = $this->settings_service->getGracePeriodDays();
            $period_end = $subscription->getCurrentPeriodEnd();
            if ($this->isBeyondGraceWindow($period_end, $grace_days)) {
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

    public function assertWithinUsageLimitForCurrentSite(): bool|WP_Error {
        if (!function_exists('get_current_blog_id')) {
            return new WP_Error('subscription_guard', __('Unable to resolve current site', 'my-village-hall'));
        }

        return $this->assertWithinUsageLimitForBlog((int) get_current_blog_id());
    }

    public function assertWithinUsageLimitForBlog(int $blog_id): bool|WP_Error {
        $account_id = $this->resolveAccountId($blog_id);
        if ($account_id <= 0) {
            return new WP_Error('subscription_account_missing', __('Unable to resolve account for this site', 'my-village-hall'));
        }

        if ($this->usage_service->exceedsLimit($account_id)) {
            return new WP_Error('usage_limit', __('Booking limit reached for this account', 'my-village-hall'));
        }

        return true;
    }

    public function resolveCurrentAccountId(): int {
        if (!function_exists('get_current_blog_id')) {
            return 0;
        }

        return $this->resolveAccountId((int) get_current_blog_id());
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

        return (int) ($account['Id'] ?? 0);
    }

    public function requireCanCreateBookingForAccount(int $account_id): void {
        $result = $this->getBookingPermission($account_id);
        if (!$result['allowed']) {
            $reason = $result['reason'];
            throw SubscriptionRequiredException::fromReason(self::mapReasonFromCode(self::guardReasonToErrorCode($reason)));
        }
    }

    public static function mapReasonFromCode(string $wp_error_code): string {
        return match ($wp_error_code) {
            'subscription_expired', 'subscription_missing', 'subscription_account_missing' => 'trial_expired',
            'usage_limit' => 'usage_limit',
            'feature_locked' => 'feature_locked',
            'subscription_past_due', 'subscription_cancelled' => 'past_due',
            default => 'subscription_missing',
        };
    }

    private static function guardReasonToErrorCode(string $guard_reason): string {
        return match ($guard_reason) {
            'expired' => 'subscription_expired',
            'subscription_missing' => 'subscription_missing',
            'account_missing' => 'subscription_account_missing',
            'usage_limit' => 'usage_limit',
            'past_due' => 'subscription_past_due',
            'cancelled' => 'subscription_cancelled',
            default => 'subscription_missing',
        };
    }

    public function getLastError(): ?WP_Error {
        return $this->last_error;
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
        if ($period_end_raw === '') {
            return true;
        }

        $period_end = strtotime($period_end_raw);
        if ($period_end === false) {
            return true;
        }

        $cutoff = $period_end + ($grace_days * self::SECONDS_PER_DAY);
        $now = (int) current_time('timestamp');

        return $now > $cutoff;
    }
}
