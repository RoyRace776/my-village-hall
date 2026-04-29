<?php

namespace MYVH\Network;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class SiteProvisioningService {

    private const VERIFY_PREFIX = 'myvh_site_request_';

    public function __construct(
        private CreateSiteRequestValidator $validator,
        private WpSiteCloner $cloner,
        private SiteProvisioningRepository $repo
    ) {}

    /**
     * Step 1: Submit request
     */
    public function submit(array $raw_data): array {

        $data = $this->sanitize($raw_data);

        $validated = $this->validator->validate($data);
        if (is_wp_error($validated)) {
            return ['ok' => false, 'message' => $validated->get_error_message()];
        }

        $token = bin2hex(random_bytes(16));

        $provision_id = $this->repo->create([
            'token' => $token,
            'subdomain' => $data['subdomain'],
            'site_name' => $data['site_name'],
            'admin_email' => $data['admin_email'],
            'admin_first_name' => $data['admin_first_name'],
            'admin_last_name' => $data['admin_last_name'],
            'admin_password' => $data['admin_password'], // TODO: hash password
            'user_id' => 0, // resolved at provisioning step
            'blog_id' => 0, // resolved at provisioning step
            'status' => 'pending',
            'error' => '',
            'logo_url' => $data['logo_url'] ?? '',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ]);

        set_transient(self::VERIFY_PREFIX . $token, true, HOUR_IN_SECONDS);

        $verify_url = add_query_arg([
            'myvh_site_verify' => 1,
            'token' => rawurlencode($token),
        ], home_url('/'));

        wp_mail(
            $data['admin_email'],
            __('Verify your site request', 'my-village-hall'),
            sprintf(
                "Hello,\n\nVerify your site:\n%s\n\nLink expires in 1 hour.",
                esc_url_raw($verify_url)
            )
        );

        return [
            'ok' => true,
            'message' => __('Check your email to continue.', 'my-village-hall'),
        ];
    }

    /**
     * Step 2: Verify + trigger provisioning
     */
    public function verify_and_provision(string $token): array {

        if (!get_transient(self::VERIFY_PREFIX . $token)) {
            return ['ok' => false, 'message' => __('Invalid or expired link.', 'my-village-hall')];
        }

        delete_transient(self::VERIFY_PREFIX . $token);

        $row = $this->repo->find_by_token($token);

        if (!$row) {
            return ['ok' => false, 'message' => __('Provisioning record not found.', 'my-village-hall')];
        }

        $this->repo->update_status($row['id'], 'verified');

        return $this->provision($row['id'], $row);
    }

    /**
     * Step 3: Provision
     */
    private function provision(int $provision_id, array $payload): array {

        $source_site_id = NetworkProvisioningSettings::template_site_id();

        if ($source_site_id <= 0) {
            return [
                'ok' => false,
                'message' => __('Template site not configured.', 'my-village-hall'),
            ];
        }

        $user_id = $this->resolve_admin_user($payload);

        if (is_wp_error($user_id)) {
            return ['ok' => false, 'message' => $user_id->get_error_message()];
        }

        $this->repo->update_status($provision_id, 'cloning');

        $result = $this->cloner->clone(
            $source_site_id,
            [
                'name'  => $payload['subdomain'],
                'title' => $payload['site_name'],
                'email' => $payload['admin_email'],
            ],
            [
                'provision_id' => $provision_id,
                'user_id'      => $user_id,
                'logo_url'     => $payload['logo_url'] ?? '',
            ]
        );

        if (is_wp_error($result)) {

            $this->repo->update_status($provision_id, 'failed', [
                'error' => $result->get_error_message(),
            ]);

            do_action('myvh_event_site.clone_failed', [
                'provision_id' => $provision_id,
                'reason' => $result->get_error_message(),
            ]);

            return [
                'ok' => false,
                'message' => __('Site creation failed.', 'my-village-hall'),
            ];
        }

        $this->repo->update_status($provision_id, 'site cloned');

        return [
            'ok' => true,
            'message' => __('Site has been cloned.', 'my-village-hall'),
        ];
    }

    /**
     * Resolve or create admin user
     */
    private function resolve_admin_user(array $payload): int|WP_Error {

        $email = $payload['admin_email'];

        $user = get_user_by('email', $email);
        if ($user) {
            return (int) $user->ID;
        }

        $base = sanitize_user(strtok($email, '@'), true) ?: 'siteadmin';
        $username = $base;
        $i = 1;

        while (username_exists($username)) {
            $username = $base . $i++;
        }

        $user_id = wp_create_user($username, $payload['admin_password'], $email);

        if (is_wp_error($user_id)) {
            return $user_id;
        }

        wp_update_user([
            'ID' => $user_id,
            'first_name' => $payload['admin_first_name'] ?? '',
            'last_name'  => $payload['admin_last_name'] ?? '',
        ]);

        return (int) $user_id;
    }

    /**
     * Sanitisation
     */
    private function sanitize(array $raw): array {
        return [
            'site_name'        => sanitize_text_field($raw['site_name'] ?? ''),
            'subdomain'        => sanitize_title($raw['subdomain'] ?? ''),
            'admin_email'      => sanitize_email($raw['admin_email'] ?? ''),
            'admin_first_name' => sanitize_text_field($raw['admin_first_name'] ?? ''),
            'admin_last_name'  => sanitize_text_field($raw['admin_last_name'] ?? ''),
            'admin_password'   => (string) ($raw['admin_password'] ?? ''),
            'logo_url'         => esc_url_raw($raw['logo_url'] ?? ''),
        ];
    }
}