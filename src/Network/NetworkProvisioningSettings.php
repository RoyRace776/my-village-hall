<?php
namespace MYVH\Network;

if (!defined('ABSPATH')) {
    exit;
}

class NetworkProvisioningSettings {
    private const OPTION_KEY = 'myvh_network_provisioning_settings';

    public static function get(): array {
        $saved = get_site_option(self::OPTION_KEY, []);
        if (!is_array($saved)) {
            $saved = [];
        }

        return [
            'template_site_id' => isset($saved['template_site_id']) ? (int) $saved['template_site_id'] : 0,
            'captcha_site_key' => isset($saved['captcha_site_key']) ? (string) $saved['captcha_site_key'] : '',
            'captcha_secret_key' => isset($saved['captcha_secret_key']) ? (string) $saved['captcha_secret_key'] : '',
        ];
    }

    public static function save(array $data): void {
        $clean = [
            'template_site_id' => absint($data['template_site_id'] ?? 0),
            'captcha_site_key' => sanitize_text_field((string) ($data['captcha_site_key'] ?? '')),
            'captcha_secret_key' => sanitize_text_field((string) ($data['captcha_secret_key'] ?? '')),
        ];

        update_site_option(self::OPTION_KEY, $clean);
    }

    public static function template_site_id(): int {
        $settings = self::get();
        return (int) $settings['template_site_id'];
    }

    public static function captcha_site_key(): string {
        $settings = self::get();
        return (string) $settings['captcha_site_key'];
    }

    public static function captcha_secret_key(): string {
        $settings = self::get();
        return (string) $settings['captcha_secret_key'];
    }
}
