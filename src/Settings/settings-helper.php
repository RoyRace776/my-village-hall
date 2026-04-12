<?php

/**
 * Get a plugin setting value.
 *
 * Usage:
 *   myvh_setting('general.site_label');
 *   myvh_setting('booking.require_approval');
 *
 * @param string $key Dot notation: group.field
 * @param mixed $default Value if not set
 * @return mixed
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

/**
 * Convert a DayPilot-like date pattern into a PHP date() pattern.
 *
 * Supported tokens: yyyy, MMMM, MMM, MM, M, ddd, dd, d
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