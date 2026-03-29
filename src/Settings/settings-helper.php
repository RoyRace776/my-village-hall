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