<?php
if (!defined('ABSPATH')) exit;

class CustomerUserSync {

    private $customer_repo;
    private $syncing = false;

    public function __construct(CustomerRepository $customer_repo) {
        $this->customer_repo = $customer_repo;
    }

    public function register(): void {
        add_action('profile_update', [$this, 'sync_customer_from_user'], 10, 2);
        add_action('user_register', [$this, 'link_or_sync_customer_from_user'], 10, 1);
    }

    public function sync_customer_from_user($user_id, $old_user_data = null): void {
        if ($this->syncing) {
            return;
        }

        $user = get_userdata((int) $user_id);

        if (!$user) {
            return;
        }

        $customer = $this->customer_repo->get_by_user_id((int) $user_id);

        if (!$customer || empty($customer['Id'])) {
            return;
        }

        $this->syncing = true;

        $this->customer_repo->update([
            'Name' => sanitize_text_field($user->display_name),
            'Email' => sanitize_email($user->user_email),
        ], [
            'Id' => (int) $customer['Id'],
        ]);

        $this->syncing = false;
    }

    public function link_or_sync_customer_from_user($user_id): void {

        $user = get_userdata((int) $user_id);

        if (!$user) {
            return;
        }

        $existing = $this->customer_repo->get_by_user_id((int) $user_id);

        if ($existing && !empty($existing['Id'])) {
            return;
        }

        $customer_by_email = $this->customer_repo->get_by_email($user->user_email);

        if ($customer_by_email && !empty($customer_by_email['Id'])) {
            $this->customer_repo->update([
                'WPUserId' => (int) $user_id,
                'Name' => sanitize_text_field($user->display_name),
                'Email' => sanitize_email($user->user_email),
            ], [
                'Id' => (int) $customer_by_email['Id'],
            ]);
        }
    }
}
