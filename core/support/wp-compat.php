<?php
// Compatibility shim for wp_timestamp() for WP < 5.3
if (!function_exists('wp_timestamp')) {
    function wp_timestamp(): int { return current_time('timestamp'); }
}
