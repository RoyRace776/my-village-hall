<?php
/**
 * MYVH_Registry — lightweight service locator.
 *
 * Replaces all global $myvh_* variables. Everything is registered once
 * during bootstrap and retrieved by key wherever it is needed.
 *
 * Usage:
 *   $myvh_container->set('booking_repo', new MYVH_Booking_Repository());
 *   $repo = $myvh_container->get('booking_repo');
 */

if (!defined('ABSPATH')) exit;

class MYVH_Registry {

    /** @var array<string, mixed> */
    private static array $instances = [];

    /**
     * Register an instance under the given key.
     *
     * @param string $key      Unique identifier (e.g. 'booking_repo').
     * @param mixed  $instance The object or value to store.
     */
    public static function set(string $key, $instance): void {
        self::$instances[$key] = $instance;
    }

    /**
     * Retrieve a registered instance.
     *
     * @param  string $key
     * @return mixed
     * @throws RuntimeException if the key has not been registered.
     */
    public static function get(string $key) {
        if (!array_key_exists($key, self::$instances)) {
            throw new RuntimeException(
                "MYVH_Registry: '{$key}' has not been registered. " .
                "Check myvh-bootstrap.php."
            );
        }
        return self::$instances[$key];
    }

    /**
     * Check whether a key has been registered.
     */
    public static function has(string $key): bool {
        return array_key_exists($key, self::$instances);
    }

    /**
     * Return all registered keys (useful for debugging).
     *
     * @return string[]
     */
    public static function keys(): array {
        return array_keys(self::$instances);
    }
}
