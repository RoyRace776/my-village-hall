<?php

class MYVH_Admin_Notices {
    private static $transient_key = 'myvh_admin_notices';

    public static function add($type = 'success', $message) {

        $notices = get_transient(self::$transient_key);

        if (!is_array($notices)) {
            $notices = [];
        }

        $notices[] = [
            'type' => $type,
            'message' => $message
        ];

        set_transient(self::$transient_key, $notices, 30);
    }

    public static function success($message) {
        self::add('success', $message);
    }

    public static function error($message) {
        self::add('error', $message);
    }

    public static function warning($message) {
        self::add('warning', $message);
    }

    public static function render() {
        $notices = get_transient(self::$transient_key);

        if (!$notices) {
            return;
        }

        foreach ($notices as $notice) {

            $class = 'notice';

            switch ($notice['type']) {
                case 'success':
                    $class .= ' notice-success';
                    break;
                case 'error':
                    $class .= ' notice-error';
                    break;
                case 'warning':
                    $class .= ' notice-warning';
                    break;
                default:
                    $class .= ' notice-info';
            }

            echo '<div class="' . esc_attr($class) . ' is-dismissible">';
            echo '<p>' . esc_html($notice['message']) . '</p>';
            echo '</div>';
        }

        delete_transient(self::$transient_key);
    }
}

add_action('admin_notices', ['MYVH_Admin_Notices', 'render']);