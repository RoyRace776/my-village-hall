<?php

declare(strict_types=1);

namespace MYVH\Subscriptions\Entities;

if (!defined('ABSPATH')) {
    exit;
}

class Subscription {
    private const MYSQL_DATETIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private array $attributes) {
    }

    public static function fromArray(array $attributes): self {
        return new self($attributes);
    }

    public function toArray(): array {
        return $this->attributes;
    }

    public function withStatus(string $status): self {
        $attributes = $this->attributes;
        $attributes['status'] = SubscriptionStatus::normalize($status);

        return new self($attributes);
    }

    public function getId(): int {
        return (int) ($this->attributes['id'] ?? 0);
    }

    public function getAccountId(): int {
        return (int) ($this->attributes['account_id'] ?? 0);
    }

    public function getPlanId(): int {
        return (int) ($this->attributes['plan_id'] ?? 0);
    }

    public function getPlanCode(): string {
        return sanitize_key((string) ($this->attributes['plan_code'] ?? ''));
    }

    public function getBookingLimitSnapshot(): ?int {
        $raw = $this->attributes['booking_limit_snapshot'] ?? null;

        if ($raw === null || $raw === '') {
            return null;
        }

        return (int) $raw;
    }

    public function getFeaturesSnapshotRaw(): ?string {
        $raw = $this->attributes['features_snapshot'] ?? null;

        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }

        return $raw;
    }

    public function getStatus(): string {
        return SubscriptionStatus::normalize((string) ($this->attributes['status'] ?? ''));
    }

    public function getCurrentPeriodStart(): string {
        return (string) ($this->attributes['current_period_start'] ?? '');
    }

    public function getTrialEndsAt(): string {
        return (string) ($this->attributes['trial_ends_at'] ?? '');
    }

    public function getCurrentPeriodEnd(): string {
        return (string) ($this->attributes['current_period_end'] ?? '');
    }

    public function getStripeCustomerId(): string {
        return trim((string) ($this->attributes['stripe_customer_id'] ?? ''));
    }

    public function getStripeSubscriptionId(): string {
        return trim((string) ($this->attributes['stripe_subscription_id'] ?? ''));
    }

    public function getProviderSubscriptionId(): string {
        return trim((string) ($this->attributes['provider_subscription_id'] ?? ''));
    }

    public function getMetadataRaw(): string {
        return (string) ($this->attributes['metadata'] ?? '');
    }

    public function isTrial(): bool {
        return $this->getStatus() === SubscriptionStatus::TRIALING;
    }

    public function isTrialActive(?\DateTimeImmutable $utc_now = null): bool {
        if (!$this->isTrial()) {
            return false;
        }

        $trial_ends_at = $this->parseUtcDateTime($this->getTrialEndsAt());
        if (!$trial_ends_at instanceof \DateTimeImmutable) {
            return true;
        }

        $now = $utc_now ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return $trial_ends_at >= $now;
    }

    public function hasExpiredTrial(?\DateTimeImmutable $utc_now = null): bool {
        if (!$this->isTrial()) {
            return false;
        }

        $trial_ends_at = $this->parseUtcDateTime($this->getTrialEndsAt());
        if (!$trial_ends_at instanceof \DateTimeImmutable) {
            return false;
        }

        $now = $utc_now ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return $trial_ends_at < $now;
    }

    public function isActive(): bool {
        return $this->getStatus() === SubscriptionStatus::ACTIVE;
    }

    public function isExpired(): bool {
        return $this->getStatus() === SubscriptionStatus::EXPIRED;
    }

    public function isPastDue(): bool {
        return $this->getStatus() === SubscriptionStatus::PAST_DUE;
    }

    public function isCancelled(): bool {
        return $this->getStatus() === SubscriptionStatus::CANCELLED;
    }

    public function isPendingPayment(): bool {
        return $this->isPastDue();
    }

    private function parseUtcDateTime(string $value): ?\DateTimeImmutable {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $date_time = \DateTimeImmutable::createFromFormat(
            self::MYSQL_DATETIME_FORMAT,
            $value,
            new \DateTimeZone('UTC')
        );

        return $date_time instanceof \DateTimeImmutable ? $date_time : null;
    }
}