<?php

declare(strict_types=1);

namespace MYVH\Subscriptions\Entities;

if (!defined('ABSPATH')) {
    exit;
}

class Plan {
    public function __construct(private array $attributes) {
    }

    public static function fromArray(array $attributes): self {
        return new self($attributes);
    }

    public function toArray(): array {
        return $this->attributes;
    }

    public function getId(): int {
        return (int) ($this->attributes['id'] ?? 0);
    }

    public function getCode(): string {
        return strtolower(trim((string) ($this->attributes['plan_key'] ?? '')));
    }

    public function getName(): string {
        $name = trim((string) ($this->attributes['name'] ?? ''));

        return $name !== '' ? $name : $this->getCode();
    }

    public function getPrice(): float {
        return round((float) ($this->attributes['price'] ?? 0), 2);
    }

    public function getBillingInterval(): string {
        return strtolower(trim((string) ($this->attributes['billing_interval'] ?? '')));
    }

    public function getStripePriceIdMonthly(): string {
        return trim((string) ($this->attributes['stripe_price_id_monthly'] ?? ''));
    }

    public function getFeatures(): array {
        $features_json = $this->attributes['features'] ?? null;

        if (!is_string($features_json) || $features_json === '') {
            $features_json = $this->attributes['metadata'] ?? '';
        }

        if (!is_string($features_json) || $features_json === '') {
            return [];
        }

        $decoded = json_decode($features_json, true);
        if (!is_array($decoded)) {
            return [];
        }

        if (isset($decoded['features']) && is_array($decoded['features'])) {
            return $decoded['features'];
        }

        return $decoded;
    }

    public function getBookingLimit(): ?int {
        $features = $this->getFeatures();
        $raw_limit = $features['booking_limit'] ?? $features['bookings'] ?? null;

        if ($raw_limit === null || $raw_limit === '') {
            return null;
        }

        if (is_string($raw_limit) && strtolower(trim($raw_limit)) === 'unlimited') {
            return null;
        }

        $limit = (int) $raw_limit;

        return $limit >= 0 ? $limit : null;
    }
}