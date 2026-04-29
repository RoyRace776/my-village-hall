<?php

namespace MYVH\Network;

class SiteProvisioningRequestHandler {

    public static function register(SiteProvisioningService $service): void {

        add_action('init', function () use ($service) {

            if (empty($_GET['myvh_site_verify']) || empty($_GET['token'])) {
                return;
            }

            $token = sanitize_text_field(wp_unslash($_GET['token']));

            $result = $service->verify_and_provision($token);

            // Simple output (you can improve this later)
            wp_die(
                esc_html($result['message']),
                __('Site Provisioning', 'my-village-hall'),
                ['response' => $result['ok'] ? 200 : 400]
            );
        });
    }
}