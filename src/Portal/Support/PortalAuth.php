<?php
namespace MYVH\Portal\Support;

use MYVH\Portal\ClientAdminService;

class PortalAuth {

    public static function require_user(): void {
        if (!is_user_logged_in()) {
            wp_send_json_error('Not logged in', 401);
        }

        check_ajax_referer('myvh_portal', 'nonce');
    }

    public static function require_client_admin(ClientAdminService $client_admin_service): void {
        self::require_user();

        if (!$client_admin_service->can_administer_blog(get_current_user_id(), get_current_blog_id())) {
            wp_send_json_error('Permission denied', 403);
        }
    }
}