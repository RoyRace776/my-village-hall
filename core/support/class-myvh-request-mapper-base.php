<?php

if (!defined('ABSPATH')) exit;

abstract class MYVH_Request_Mapper_Base {

    protected static function as_int(array $data, string $key, int $default = 0): int {
        return intval($data[$key] ?? $default);
    }

    protected static function as_float(array $data, string $key, float $default = 0.0): float {
        return floatval($data[$key] ?? $default);
    }

    protected static function as_bool_int(array $data, string $key): int {
        return !empty($data[$key]) ? 1 : 0;
    }

    protected static function as_text(array $data, string $key, string $default = ''): string {
        return sanitize_text_field($data[$key] ?? $default);
    }

    protected static function as_textarea(array $data, string $key, string $default = ''): string {
        return sanitize_textarea_field($data[$key] ?? $default);
    }

    protected static function as_email(array $data, string $key, string $default = ''): string {
        return sanitize_email($data[$key] ?? $default);
    }

    protected static function as_redirect(array $data, string $key, string $default = ''): string {
        return wp_validate_redirect($data[$key] ?? $default, $default);
    }
}