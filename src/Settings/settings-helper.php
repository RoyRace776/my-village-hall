<?php

if (!function_exists('myvh_setting')) {
/**
 * Get a plugin setting value.
 */
function myvh_setting($key, $default = null) {

    $parts = explode('.', $key, 2);

    if (count($parts) !== 2) {
        return $default;
    }

    list($group, $field) = $parts;

    $settings = MYVH\Settings\SettingsRegistry::get($group);

    if (!$settings) {
        return $default;
    }

    $value = $settings->get($field);

    return $value !== null ? $value : $default;
}
}

if (!function_exists('myvh_daypilot_to_php_date_format')) {
/**
 * Convert a DayPilot-like date pattern into a PHP date() pattern.
 */
function myvh_daypilot_to_php_date_format(string $pattern, string $fallback = 'M j'): string {
    $pattern = trim($pattern);

    if ($pattern === '') {
        return $fallback;
    }

    $token_map = [
        'yyyy' => 'Y',
        'MMMM' => 'F',
        'MMM'  => 'M',
        'MM'   => 'm',
        'M'    => 'n',
        'ddd'  => 'D',
        'dd'   => 'd',
        'd'    => 'j',
    ];

    $php_pattern = preg_replace_callback('/yyyy|MMMM|MMM|MM|M|ddd|dd|d/', static function (array $matches) use ($token_map): string {
        $token = (string) ($matches[0] ?? '');
        return $token_map[$token] ?? $token;
    }, $pattern);

    return is_string($php_pattern) && $php_pattern !== '' ? $php_pattern : $fallback;
}
}

if (!function_exists('myvh_format_date_with_pattern')) {
/**
 * Format a date value using a DayPilot-like pattern string.
 */
function myvh_format_date_with_pattern($date_value, string $daypilot_pattern, string $fallback_pattern = 'M j'): string {
    $timestamp = strtotime((string) $date_value);

    if ($timestamp === false) {
        return '';
    }

    $php_pattern = myvh_daypilot_to_php_date_format($daypilot_pattern, $fallback_pattern);

    if (function_exists('date_i18n')) {
        return (string) date_i18n($php_pattern, $timestamp);
    }

    return date($php_pattern, $timestamp);
}
}

if (!function_exists('myvh_get_active_notices')) {
/**
 * Return hall notices that are currently active based on their start/end dates.
 *
 * - start_date empty → display from now
 * - end_date   empty → display forever
 *
 * @return array<int, array{message: string, start_date: string, end_date: string}>
 */
function myvh_get_active_notices(): array {
    $settings = MYVH\Settings\SettingsRegistry::get('notices');

    if (!$settings) {
        return [];
    }

    $notices = $settings->get('notices');

    if (!is_array($notices) || empty($notices)) {
        return [];
    }

    $now = function_exists('current_time') ? current_time('timestamp') : time();

    return array_values(array_filter($notices, static function (array $notice) use ($now): bool {
        $start = !empty($notice['start_date']) ? strtotime($notice['start_date']) : false;
        $end   = !empty($notice['end_date'])   ? strtotime($notice['end_date'])   : false;

        if ($start !== false && $now < $start) {
            return false;
        }

        if ($end !== false && $now > $end) {
            return false;
        }

        return true;
    }));
}
}