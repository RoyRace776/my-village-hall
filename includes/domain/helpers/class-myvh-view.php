<?php
if (!defined('ABSPATH')) exit;

class MYVH_View
{
    public static function render($view, $vars = [])
    {
        $file = MYVH_PLUGIN_DIR . 'includes/admin/views/' . $view . '.php';

        if (!file_exists($file)) {
            wp_die('View not found: ' . esc_html($view));
        }

        if (!empty($vars)) {
            extract($vars, EXTR_SKIP);
        }

        include $file;
    }
}