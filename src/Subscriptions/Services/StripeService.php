<?php

declare(strict_types=1);

namespace MYVH\Subscriptions\Services;

use MYVH\Subscriptions\Repositories\SettingsRepository;
use Stripe\StripeClient;

if (!defined('ABSPATH')) {
    exit;
}

class StripeService {
    public function __construct(
        private SettingsRepository $settings_repository
    ) {
    }

    public function createCustomer(string $email, string $name = '', array $metadata = []): array {
        $email = sanitize_email($email);
        $name = sanitize_text_field($name);

        if ($email === '') {
            return [];
        }

        $customer = $this->getStripeClient()->customers->create([
            'email' => $email,
            'name' => $name !== '' ? $name : null,
            'metadata' => $metadata,
        ]);

        $customer_id = trim((string) ($customer->id ?? ''));
        if ($customer_id === '') {
            return [];
        }

        return [
            'id' => $customer_id,
            'email' => (string) ($customer->email ?? ''),
            'name' => (string) ($customer->name ?? ''),
        ];
    }

    public function createSubscription(string $customerId, string $priceId): array {
        $customer_id = trim($customerId);
        $price_id = trim($priceId);

        if ($customer_id === '' || $price_id === '') {
            return [];
        }

        $subscription = $this->getStripeClient()->subscriptions->create([
            'customer' => $customer_id,
            'items' => [
                ['price' => $price_id],
            ],
            'payment_behavior' => 'default_incomplete',
        ]);

        $subscription_id = trim((string) ($subscription->id ?? ''));
        if ($subscription_id === '') {
            return [];
        }

        return [
            'id' => $subscription_id,
            'customer' => (string) ($subscription->customer ?? ''),
            'status' => (string) ($subscription->status ?? ''),
            'price_id' => $price_id,
        ];
    }

    public function cancelSubscription(string $subscriptionId): bool {
        $subscription_id = trim($subscriptionId);
        if ($subscription_id === '') {
            return false;
        }

        $this->getStripeClient()->subscriptions->cancel($subscription_id, []);

        return true;
    }

    public function createCheckoutSession(
        string $customer_id,
        string $price_id,
        string $success_url,
        string $cancel_url,
        array $metadata = []
    ): array {
        $customer_id = trim($customer_id);
        $price_id = trim($price_id);
        $success_url = trim($success_url);
        $cancel_url = trim($cancel_url);

        if ($customer_id === '' || $price_id === '' || $success_url === '' || $cancel_url === '') {
            return [];
        }

        $session = $this->getStripeClient()->checkout->sessions->create([
            'mode' => 'subscription',
            'customer' => $customer_id,
            'line_items' => [
                [
                    'price' => $price_id,
                    'quantity' => 1,
                ],
            ],
            'success_url' => $success_url,
            'cancel_url' => $cancel_url,
            'metadata' => $metadata,
        ]);

        $session_id = trim((string) ($session->id ?? ''));
        $url = trim((string) ($session->url ?? ''));

        if ($session_id === '' || $url === '') {
            return [];
        }

        return [
            'id' => $session_id,
            'url' => $url,
        ];
    }

    protected function getStripeClient(): StripeClient {
        $secret = (string) $this->settings_repository->get_global_setting('stripe_secret_key', '');
        $secret = trim($secret);

        if ($secret === '') {
            throw new \RuntimeException('Stripe secret key is not configured');
        }

        return new StripeClient($secret);
    }
}
