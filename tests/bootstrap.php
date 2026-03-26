<?php
/**
 * PHPUnit bootstrap for My Village Hall plugin tests.
 *
 * Loads Composer's autoloader and sets up Brain Monkey / WP stubs
 * so unit tests can run without a real WordPress install.
 */
if (!class_exists('WP_Error')) {
    class WP_Error {
        private $code;
        private $message;
        public function __construct($code = '', $message = '', $data = '') {
            $this->code = $code;
            $this->message = $message;
        }
        public function get_error_code() {
            return $this->code;
        }
        public function get_error_message() {
            return $this->message;
        }
    }
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

if (!defined('ABSPATH'))         define('ABSPATH', '/tmp/wordpress/');
if (!defined('MYVH_PLUGIN_DIR')) define('MYVH_PLUGIN_DIR', dirname(__DIR__) . '/');

// Stubs must be loaded before any service class is autoloaded,
// so PHP never tries to require_once the real implementations.
//require_once __DIR__ . '/stubs/class-myvh-booking-status.php';
//require_once __DIR__ . '/stubs/class-myvh-event-dispatcher.php';
