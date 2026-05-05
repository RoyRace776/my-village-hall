<?php
namespace MYVH\Network;

use MYVH\Core\Shortcode\ShortcodeInterface;

if (!defined('ABSPATH')) {
    exit;
}

class CreateSiteShortcode implements ShortcodeInterface {
    private const TAG = 'myvh_create_site';

    public function __construct(private SiteProvisioningService $service) {}

    public function tag(): string {
        return self::TAG;
    }

    public function render($atts = [], $content = null): string {
        wp_enqueue_style(
            'myvh-network-create-site',
            MYVH_PLUGIN_URL . 'assets/css/network-create-site.css',
            [],
            MYVH_VERSION
        );

        $state = [
            'status' => '',
            'message' => '',
            'site_url' => '',
        ];
        $submitted = false;
        $verification_result = false;
        $verification_details = [];
        $verification_action = 'verify';

        if (!is_multisite()) {
            return '<div class="myvh-site-request myvh-site-request--error">' . esc_html__('This shortcode requires WordPress multisite.', 'my-village-hall') . '</div>';
        }

        if (!empty($_GET['myvh_site_cancel']) && !empty($_GET['token'])) {
            $token = sanitize_text_field(wp_unslash((string) $_GET['token']));
            $result = $this->service->cancel_request($token);
            $state['status'] = !empty($result['ok']) ? 'success' : 'error';
            $state['message'] = (string) ($result['message'] ?? '');
            $verification_result = true;
            $verification_details = is_array($result['details'] ?? null) ? $result['details'] : [];
            $verification_action = 'cancel';
        } elseif (!empty($_GET['myvh_site_verify']) && !empty($_GET['token'])) {
            $token = sanitize_text_field(wp_unslash((string) $_GET['token']));
            $result = $this->service->verify_and_provision($token);
            $state['status'] = !empty($result['ok']) ? 'success' : 'error';
            $state['message'] = (string) ($result['message'] ?? '');
            $state['site_url'] = (string) ($result['site_url'] ?? '');
            $verification_result = true;
            $verification_details = is_array($result['details'] ?? null) ? $result['details'] : [];
            $verification_action = 'verify';
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['myvh_create_site_action'])) {
            if (!isset($_POST['myvh_create_site_nonce']) || !wp_verify_nonce((string) $_POST['myvh_create_site_nonce'], 'myvh_create_site_request')) {
                $state['status'] = 'error';
                $state['message'] = __('Invalid request nonce.', 'my-village-hall');
            } else {
                $result = $this->service->submit($_POST);
                $state['status'] = !empty($result['ok']) ? 'success' : 'error';
                $state['message'] = (string) ($result['message'] ?? '');
                $submitted = !empty($result['ok']);
            }
        }

        $form_values = [
            'site_name' => sanitize_text_field($_POST['site_name'] ?? ''),
            'subdomain' => sanitize_title($_POST['subdomain'] ?? ''),
            'admin_email' => sanitize_email($_POST['admin_email'] ?? ''),
            'admin_first_name' => sanitize_text_field($_POST['admin_first_name'] ?? ''),
            'admin_last_name' => sanitize_text_field($_POST['admin_last_name'] ?? ''),
        ];

        if (!empty($verification_details)) {
            $form_values['site_name'] = sanitize_text_field((string) ($verification_details['site_name'] ?? $form_values['site_name']));
            $form_values['subdomain'] = sanitize_title((string) ($verification_details['subdomain'] ?? $form_values['subdomain']));
            $form_values['admin_email'] = sanitize_email((string) ($verification_details['admin_email'] ?? $form_values['admin_email']));
            $form_values['admin_first_name'] = sanitize_text_field((string) ($verification_details['admin_first_name'] ?? $form_values['admin_first_name']));
            $form_values['admin_last_name'] = sanitize_text_field((string) ($verification_details['admin_last_name'] ?? $form_values['admin_last_name']));
        }
        $captcha_site_key = NetworkProvisioningSettings::captcha_site_key();
        $current_request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash((string) $_SERVER['REQUEST_URI']) : '/';
        $request_page_url = esc_url_raw(remove_query_arg(['myvh_site_verify', 'myvh_site_cancel', 'token'], home_url($current_request_uri)));

        $network = get_network();
        $network_domain = $network ? preg_replace('/^www\./', '', (string) $network->domain) : '';
        $is_subdomain = is_subdomain_install();
        $network_path = $network ? (string) $network->path : '/';

        ob_start();
        include MYVH_PLUGIN_DIR . 'templates/Network/create-site-form.php';
        return (string) ob_get_clean();
    }
}
