<?php

class MYVH_Admin_Notices {

    public static function add($message, $type = 'success') {
        set_transient('myvh_admin_notice', [
            'message' => $message,
            'type' => $type
        ], 30);
    }

    public static function display() {
        $notice = get_transient('myvh_admin_notice');
        if (!$notice) return;

        delete_transient('myvh_admin_notice');

        printf(
            '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
            esc_attr($notice['type']),
            esc_html($notice['message'])
        );
    }
}

add_action('admin_notices', ['MYVH_Admin_Notices', 'display']);