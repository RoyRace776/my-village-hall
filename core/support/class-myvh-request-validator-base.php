<?php

if (!defined('ABSPATH')) exit;

abstract class MYVH_Request_Validator_Base {

    protected function validation_error($message) {
        return new WP_Error('validation', $message);
    }

    protected function require_field(array $data, string $key, string $message) {
        if (empty($data[$key])) {
            return $this->validation_error($message);
        }

        return true;
    }

    protected function require_email(array $data, string $key, string $required_message, string $invalid_message) {
        $required = $this->require_field($data, $key, $required_message);
        if (is_wp_error($required)) {
            return $required;
        }

        if (!is_email((string) $data[$key])) {
            return $this->validation_error($invalid_message);
        }

        return true;
    }

    protected function require_non_negative(array $data, string $key, string $message) {
        if (!isset($data[$key]) || floatval($data[$key]) < 0) {
            return $this->validation_error($message);
        }

        return true;
    }

    protected function require_in_list($value, array $allowed, string $message) {
        if (!in_array($value, $allowed, true)) {
            return $this->validation_error($message);
        }

        return true;
    }
}