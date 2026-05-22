<?php

declare(strict_types=1);

namespace MYVH\Subscriptions\Services;

use MYVH\Subscriptions\Entities\SubscriptionStatus;
use MYVH\Subscriptions\Repositories\AccountRepository;

if (!defined('ABSPATH')) {
    exit;
}

class AccountService {
    public function __construct(private AccountRepository $account_repository) {
    }

    public function createAccount(string $name, string $email): array {
        $blog_id = function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 0;

        $name = sanitize_text_field($name);
        if ($name === '') {
            $name = sanitize_text_field((string) get_bloginfo('name'));
        }

        $email = sanitize_email($email);
        if ($email === '') {
            $email = sanitize_email((string) get_option('admin_email', ''));
        }

        if ($name === '' || $email === '') {
            return [];
        }

        if ($blog_id > 0) {
            $existing = $this->resolveAccountFromBlogId($blog_id);
            if (is_array($existing)) {
                return $existing;
            }
        }

        $account_id = $this->account_repository->create([
            'blog_id' => $blog_id > 0 ? $blog_id : null,
            'account_name' => $name,
            'contact_email' => $email,
            'status' => SubscriptionStatus::ACTIVE,
            'external_reference' => $blog_id > 0 ? 'blog:' . $blog_id : null,
        ]);

        if ($account_id === false) {
            return [];
        }

        return $this->account_repository->get_by_id((int) $account_id) ?? [];
    }

    public function resolveAccountFromBlogId(int $blog_id): ?array {
        if ($blog_id <= 0) {
            return null;
        }

        $account = $this->account_repository->get_by_blog_id($blog_id);
        if (is_array($account)) {
            return $account;
        }

        return $this->account_repository->get_by_external_reference('blog:' . $blog_id);
    }
}
