<?php
namespace MYVH\Network;

if (!defined('ABSPATH')) {
    exit;
}

class SiteProvisioningService {
    private const VERIFY_PREFIX = 'myvh_site_request_';
    private const RATE_IP_PREFIX = 'myvh_site_req_ip_';
    private const RATE_EMAIL_PREFIX = 'myvh_site_req_email_';

    public function __construct(
        private CreateSiteRequestValidator $validator,
        private NsClonerAdapter $cloner
    ) {}

    public function submit(array $raw_data, array $files = []): array {
        $data = $this->sanitize($raw_data);

        $validated = $this->validator->validate($data);
        if (is_wp_error($validated)) {
            return [
                'ok' => false,
                'message' => $validated->get_error_message(),
            ];
        }

        $rate_check = $this->enforce_rate_limit($data['admin_email']);
        if (is_wp_error($rate_check)) {
            return [
                'ok' => false,
                'message' => $rate_check->get_error_message(),
            ];
        }

        $captcha_check = $this->verify_captcha($raw_data['captcha_token'] ?? '');
        if (is_wp_error($captcha_check)) {
            return [
                'ok' => false,
                'message' => $captcha_check->get_error_message(),
            ];
        }

        $logo_url = $this->handle_logo_upload($files['logo'] ?? null);
        if (is_wp_error($logo_url)) {
            return [
                'ok' => false,
                'message' => $logo_url->get_error_message(),
            ];
        }

        $payload = [
            'site_name' => $data['site_name'],
            'subdomain' => $data['subdomain'],
            'admin_email' => $data['admin_email'],
            'admin_first_name' => $data['admin_first_name'],
            'admin_last_name' => $data['admin_last_name'],
            'admin_password' => $data['admin_password'],
            'logo_url' => is_string($logo_url) ? $logo_url : '',
            'requested_at' => time(),
            'ip' => $this->get_request_ip(),
        ];

        $token = bin2hex(random_bytes(16));
        set_transient(self::VERIFY_PREFIX . $token, $payload, HOUR_IN_SECONDS);

        $verify_url = add_query_arg([
            'myvh_site_verify' => 1,
            'token' => rawurlencode($token),
        ], $this->current_url());

        $subject = sprintf(__('Verify your %s site setup request', 'my-village-hall'), wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES));

        $message = sprintf(
            "Hello %s,\n\nPlease verify your site creation request by opening the link below:\n%s\n\nThis link expires in 1 hour.",
            $payload['admin_first_name'],
            esc_url_raw($verify_url)
        );

        $sent = wp_mail($payload['admin_email'], $subject, $message);
        if (!$sent) {
            delete_transient(self::VERIFY_PREFIX . $token);
            return [
                'ok' => false,
                'message' => __('Unable to send verification email. Please try again.', 'my-village-hall'),
            ];
        }

        do_action('myvh_event_site.request_submitted', [
            'email' => $payload['admin_email'],
            'subdomain' => $payload['subdomain'],
        ]);

        return [
            'ok' => true,
            'message' => __('Check your email to verify your request and start site provisioning.', 'my-village-hall'),
        ];
    }

    public function verify_and_provision(string $token): array {
        $token = preg_replace('/[^a-f0-9]/', '', strtolower($token));
        if (!$token) {
            return [
                'ok' => false,
                'message' => __('Verification token is missing.', 'my-village-hall'),
            ];
        }

        $payload = get_transient(self::VERIFY_PREFIX . $token);
        if (!is_array($payload) || empty($payload['subdomain'])) {
            return [
                'ok' => false,
                'message' => __('Invalid or expired verification link.', 'my-village-hall'),
            ];
        }

        $validated = $this->validator->validate($payload);
        if (is_wp_error($validated)) {
            delete_transient(self::VERIFY_PREFIX . $token);
            return [
                'ok' => false,
                'message' => $validated->get_error_message(),
            ];
        }

        $result = $this->provision($payload);
        if (!$result['ok']) {
            return $result;
        }

        delete_transient(self::VERIFY_PREFIX . $token);

        return $result;
    }

    private function provision(array $payload): array {
        $network = get_network();
        if (!$network) {
            return [
                'ok' => false,
                'message' => __('Unable to resolve multisite network settings.', 'my-village-hall'),
            ];
        }

        $network_domain = preg_replace('/^www\./', '', (string) $network->domain);
        $domain = $payload['subdomain'] . '.' . $network_domain;

        $user_id = $this->resolve_admin_user($payload);
        if (is_wp_error($user_id)) {
            return [
                'ok' => false,
                'message' => $user_id->get_error_message(),
            ];
        }

        $blog_id = wpmu_create_blog(
            $domain,
            '/',
            $payload['site_name'],
            (int) $user_id,
            ['public' => 1],
            (int) $network->id
        );

        if (is_wp_error($blog_id)) {
            return [
                'ok' => false,
                'message' => $blog_id->get_error_message(),
            ];
        }

        add_user_to_blog((int) $blog_id, (int) $user_id, 'administrator');

        $logo_result = $this->copy_logo_to_site((int) $blog_id, (int) $user_id, (string) ($payload['logo_url'] ?? ''));
        if (is_wp_error($logo_result)) {
            do_action('myvh_event_site.clone_failed', [
                'blog_id' => (int) $blog_id,
                'reason' => $logo_result->get_error_message(),
            ]);
        }

        $source_site_id = (int) apply_filters('myvh_network_template_site_id', (int) myvh_setting('network.template_site_id', 0));
        if ($source_site_id <= 0) {
            return [
                'ok' => false,
                'message' => __('Template site ID is not configured for cloning.', 'my-village-hall'),
            ];
        }

        $clone = $this->cloner->clone_site($source_site_id, (int) $blog_id, [
            'site_name' => $payload['site_name'],
            'subdomain' => $payload['subdomain'],
            'admin_email' => $payload['admin_email'],
        ]);

        if (is_wp_error($clone)) {
            do_action('myvh_event_site.clone_failed', [
                'blog_id' => (int) $blog_id,
                'reason' => $clone->get_error_message(),
            ]);

            return [
                'ok' => false,
                'message' => __('The site was created but cloning failed. Please contact support.', 'my-village-hall'),
            ];
        }

        do_action('myvh_event_site.provisioned', [
            'blog_id' => (int) $blog_id,
            'domain' => $domain,
        ]);

        return [
            'ok' => true,
            'message' => __('Site created and clone process started successfully.', 'my-village-hall'),
            'site_url' => esc_url_raw('https://' . $domain . '/'),
        ];
    }

    private function sanitize(array $raw): array {
        return [
            'site_name' => sanitize_text_field($raw['site_name'] ?? ''),
            'subdomain' => sanitize_title($raw['subdomain'] ?? ''),
            'admin_email' => sanitize_email($raw['admin_email'] ?? ''),
            'admin_first_name' => sanitize_text_field($raw['admin_first_name'] ?? ''),
            'admin_last_name' => sanitize_text_field($raw['admin_last_name'] ?? ''),
            'admin_password' => (string) ($raw['admin_password'] ?? ''),
        ];
    }

    private function enforce_rate_limit(string $email): true|\WP_Error {
        $ip_key = self::RATE_IP_PREFIX . md5($this->get_request_ip());
        $email_key = self::RATE_EMAIL_PREFIX . md5(strtolower($email));

        $ip_count = (int) get_transient($ip_key);
        $email_count = (int) get_transient($email_key);

        if ($ip_count >= 5 || $email_count >= 3) {
            return new \WP_Error('myvh_site_request_rate_limit', __('Too many requests. Please wait before trying again.', 'my-village-hall'));
        }

        set_transient($ip_key, $ip_count + 1, HOUR_IN_SECONDS);
        set_transient($email_key, $email_count + 1, HOUR_IN_SECONDS);

        return true;
    }

    private function verify_captcha($token): true|\WP_Error {
        $token = is_string($token) ? trim($token) : '';
        $is_valid = apply_filters('myvh_network_verify_captcha', true, $token, [
            'ip' => $this->get_request_ip(),
        ]);

        if ($is_valid !== true) {
            return new \WP_Error('myvh_site_request_captcha', __('Captcha verification failed. Please try again.', 'my-village-hall'));
        }

        return true;
    }

    private function handle_logo_upload($file): string|\WP_Error {
        if (!is_array($file) || empty($file['name'])) {
            return '';
        }

        if (!function_exists('wp_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $uploaded = wp_handle_upload($file, [
            'test_form' => false,
            'mimes' => [
                'jpg|jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'webp' => 'image/webp',
                'svg' => 'image/svg+xml',
            ],
        ]);

        if (!is_array($uploaded) || !empty($uploaded['error'])) {
            return new \WP_Error('myvh_site_request_logo', __('Logo upload failed.', 'my-village-hall'));
        }

        return esc_url_raw((string) ($uploaded['url'] ?? ''));
    }

    private function copy_logo_to_site(int $blog_id, int $user_id, string $logo_url): true|\WP_Error {
        if ($logo_url === '') {
            return true;
        }

        if (!function_exists('download_url')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        if (!function_exists('media_handle_sideload')) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        $tmp = download_url($logo_url);
        if (is_wp_error($tmp)) {
            return $tmp;
        }

        $filename = basename(parse_url($logo_url, PHP_URL_PATH) ?: 'logo.png');
        $file_array = [
            'name' => $filename,
            'tmp_name' => $tmp,
        ];

        switch_to_blog($blog_id);
        wp_set_current_user($user_id);
        $attachment_id = media_handle_sideload($file_array, 0, __('Site logo', 'my-village-hall'));

        if (is_wp_error($attachment_id)) {
            @unlink($tmp);
            restore_current_blog();
            return $attachment_id;
        }

        $logo = wp_get_attachment_url((int) $attachment_id);
        $settings = get_option('myvh_general_settings', []);
        if (!is_array($settings)) {
            $settings = [];
        }

        $settings['portal_logo_url'] = $logo ? esc_url_raw((string) $logo) : '';
        update_option('myvh_general_settings', $settings);

        restore_current_blog();

        return true;
    }

    private function resolve_admin_user(array $payload): int|\WP_Error {
        $email = (string) $payload['admin_email'];
        $existing = get_user_by('email', $email);
        if ($existing && !empty($existing->ID)) {
            return (int) $existing->ID;
        }

        $base = sanitize_user(strtok($email, '@') ?: 'siteadmin', true);
        if ($base === '') {
            $base = 'siteadmin';
        }

        $username = $base;
        $suffix = 1;
        while (username_exists($username)) {
            $suffix++;
            $username = $base . $suffix;
        }

        $user_id = wp_create_user($username, (string) $payload['admin_password'], $email);
        if (is_wp_error($user_id)) {
            return $user_id;
        }

        wp_update_user([
            'ID' => (int) $user_id,
            'first_name' => (string) $payload['admin_first_name'],
            'last_name' => (string) $payload['admin_last_name'],
            'display_name' => trim((string) $payload['admin_first_name'] . ' ' . (string) $payload['admin_last_name']),
        ]);

        return (int) $user_id;
    }

    private function current_url(): string {
        $scheme = is_ssl() ? 'https://' : 'http://';
        $host = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '';
        $uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '/';

        if ($host === '') {
            return home_url('/');
        }

        return esc_url_raw($scheme . $host . $uri);
    }

    private function get_request_ip(): string {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        return is_string($ip) ? $ip : '';
    }
}
