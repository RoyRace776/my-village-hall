<?php

if (!defined('ABSPATH')) exit;

/**
 * Abstract base class for validating request data.
 * Provides helpers for common validation patterns and error handling.
 */
abstract class MYVH_Request_Validator_Base {

    /**
     * Return a WP_Error for validation failures.
     */
    protected function validation_error($message): \WP_Error {
        return new WP_Error('validation', $message);
    }

    /**
     * Require a field to be present and non-empty in data.
     */
    protected function require_field(array $data, string $key, string $message): bool|\WP_Error {
        if (empty($data[$key])) {
            return $this->validation_error($message);
        }
        return true;
    }

    /**
     * Require a valid email address in data.
     */
    protected function require_email(array $data, string $key, string $required_message, string $invalid_message): bool|\WP_Error {
        $required = $this->require_field($data, $key, $required_message);
        if (is_wp_error($required)) {
            return $required;
        }
        if (!is_email((string) $data[$key])) {
            return $this->validation_error($invalid_message);
        }
        return true;
    }

    /**
     * Require a field to be non-negative (>= 0).
     */
    protected function require_non_negative(array $data, string $key, string $message): bool|\WP_Error {
        if (!isset($data[$key]) || floatval($data[$key]) < 0) {
            return $this->validation_error($message);
        }
        return true;
    }

    /**
     * Require a value to be in a list of allowed values.
     */
    protected function require_in_list($value, array $allowed, string $message): bool|\WP_Error {
        if (!in_array($value, $allowed, true)) {
            return $this->validation_error($message);
        }
        return true;
    }
}