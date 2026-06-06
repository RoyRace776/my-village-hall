<?php
namespace MYVH\Network;

use MYVH\Core\Shortcode\ShortcodeInterface;

if (!defined('ABSPATH')) {
    exit;
}

class CreateSiteShortcode implements ShortcodeInterface {
    private const TAG = 'myvh_create_site';
    private const DRAFT_COOKIE = 'myvh_setup_draft_token';
    private const DRAFT_PREFIX = 'myvh_setup_draft_';

    public function __construct(private SiteProvisioningService $service) {}

    public function tag(): string {
        return self::TAG;
    }

    public function render( mixed $atts = [], mixed $content = null): string {
        return ( new \MYVH\Application\Services\CreateSiteRenderer( $this->service ) )->render( (array) $atts );
    }

    private function ensure_draft_token(): string {
        $cookie = isset($_COOKIE[self::DRAFT_COOKIE]) ? sanitize_text_field(wp_unslash((string) $_COOKIE[self::DRAFT_COOKIE])) : '';
        if ($cookie !== '') {
            return $cookie;
        }

        $token = wp_generate_password(24, false, false);
        $expiry = time() + (7 * 24 * 60 * 60);
        $cookie_path = defined('COOKIEPATH') ? COOKIEPATH : '/';
        $cookie_domain = defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '';
        setcookie(
            self::DRAFT_COOKIE,
            $token,
            $expiry,
            $cookie_path,
            $cookie_domain,
            is_ssl(),
            true
        );

        return $token;
    }

    private function handle_draft_save(string $draft_token): void {
        if (!isset($_POST['myvh_create_site_nonce']) || !wp_verify_nonce((string) $_POST['myvh_create_site_nonce'], 'myvh_create_site_request')) {
            wp_send_json_error(['message' => __('Invalid request nonce.', 'my-village-hall')], 403);
        }

        $setup_payload = [];
        $raw_setup_payload = wp_unslash((string) ($_POST['setup_payload'] ?? ''));
        if ($raw_setup_payload !== '') {
            $decoded = json_decode($raw_setup_payload, true);
            if (!is_array($decoded)) {
                wp_send_json_error(['message' => __('Setup payload is invalid.', 'my-village-hall')], 400);
            }
            $setup_payload = $decoded;
        }

        set_transient(self::DRAFT_PREFIX . $draft_token, [
            'site_name' => sanitize_text_field($_POST['site_name'] ?? ''),
            'subdomain' => sanitize_title($_POST['subdomain'] ?? ''),
            'admin_email' => sanitize_email($_POST['admin_email'] ?? ''),
            'admin_first_name' => sanitize_text_field($_POST['admin_first_name'] ?? ''),
            'admin_last_name' => sanitize_text_field($_POST['admin_last_name'] ?? ''),
            'setup_payload' => $setup_payload,
            'updated_at' => current_time('mysql'),
        ], 7 * 24 * 60 * 60);

        wp_send_json_success(['saved' => true]);
    }

    private function handle_logo_upload(mixed $file): string|\WP_Error {
        if (!is_array($file) || empty($file['name'])) {
            return '';
        }

        $upload_error = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
        if ($upload_error === UPLOAD_ERR_NO_FILE) {
            return '';
        }

        if ($upload_error !== UPLOAD_ERR_OK) {
            return new \WP_Error(
                'myvh_logo_upload_failed',
                __('Logo upload failed. Please try again.', 'my-village-hall')
            );
        }

        if (!function_exists('wp_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $result = wp_handle_upload($file, [
            'test_form' => false,
        ]);

        if (!is_array($result) || !empty($result['error'])) {
            return new \WP_Error(
                'myvh_logo_upload_failed',
                __('Logo upload failed. Please choose a valid image file and try again.', 'my-village-hall')
            );
        }

        return esc_url_raw((string) ($result['url'] ?? ''));
    }
}
