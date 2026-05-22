<?php

declare(strict_types=1);

namespace MYVH\Subscriptions\Exceptions;

if (!defined('ABSPATH')) {
    exit;
}

class SubscriptionRequiredException extends \RuntimeException {
    private string $reason;
    private array $context;

    private function __construct(string $reason, array $context = []) {
        $this->reason = $reason;
        $this->context = $context;

        $message = match ($reason) {
            'trial_expired'      => 'Your free trial has expired. Please upgrade to continue.',
            'usage_limit'        => 'You have reached your booking limit for this billing period. Please upgrade your plan to create more bookings.',
            'feature_locked'     => 'This feature is not available on your current plan. Please upgrade to access it.',
            'past_due'           => 'Your subscription payment is overdue. Please update your payment details to continue.',
            'subscription_missing' => 'No active subscription found. Please subscribe to continue.',
            default              => 'A subscription is required to perform this action.',
        };

        parent::__construct($message, 402);
    }

    public static function fromReason(string $reason, array $context = []): self {
        return new self($reason, $context);
    }

    public function getReason(): string {
        return $this->reason;
    }

    public function getContext(): array {
        return $this->context;
    }
}
