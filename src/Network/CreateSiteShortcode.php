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

        if (!is_multisite()) {
            return '<div class="myvh-site-request myvh-site-request--error">' . esc_html__('This shortcode requires WordPress multisite.', 'my-village-hall') . '</div>';
        }

        if (!empty($_GET['myvh_site_verify']) && !empty($_GET['token'])) {
            $result = $this->service->verify_and_provision((string) $_GET['token']);
            $state['status'] = !empty($result['ok']) ? 'success' : 'error';
            $state['message'] = (string) ($result['message'] ?? '');
            $state['site_url'] = (string) ($result['site_url'] ?? '');
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['myvh_create_site_action'])) {
            if (!isset($_POST['myvh_create_site_nonce']) || !wp_verify_nonce((string) $_POST['myvh_create_site_nonce'], 'myvh_create_site_request')) {
                $state['status'] = 'error';
                $state['message'] = __('Invalid request nonce.', 'my-village-hall');
            } else {
                $result = $this->service->submit($_POST, $_FILES);
                $state['status'] = !empty($result['ok']) ? 'success' : 'error';
                $state['message'] = (string) ($result['message'] ?? '');
            }
        }

        $form_values = [
            'site_name' => sanitize_text_field($_POST['site_name'] ?? ''),
            'subdomain' => sanitize_title($_POST['subdomain'] ?? ''),
            'admin_email' => sanitize_email($_POST['admin_email'] ?? ''),
            'admin_first_name' => sanitize_text_field($_POST['admin_first_name'] ?? ''),
            'admin_last_name' => sanitize_text_field($_POST['admin_last_name'] ?? ''),
        ];
        $captcha_site_key = NetworkProvisioningSettings::captcha_site_key();

        ob_start();
        include MYVH_PLUGIN_DIR . 'templates/Network/create-site-form.php';
        return (string) ob_get_clean();
    }
}
