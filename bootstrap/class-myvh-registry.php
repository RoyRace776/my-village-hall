<?php
/**
 * MYVH_Registry — lightweight static service locator.
 *
 * Provides a global key-value store for shared objects.
 * Prefer injecting dependencies through the MYVH_Container where possible;
 * use this registry only for cases where constructor injection is impractical
 * (e.g. legacy code, or objects instantiated outside the container).
 *
 * Usage:
 *   MYVH_Registry::set( 'booking_repo', new MYVH_Booking_Repository() );
 *   $repo = MYVH_Registry::get( 'booking_repo' );
 *
 * @since 0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MYVH_Registry {

    /** @var array<string, mixed> Registered instances, keyed by name. */
    private static array $instances = [];

    /**
     * Store an instance under the given key.
     *
     * Overwrites any previously registered value for the same key.
     *
     * @param string $key      Unique identifier, e.g. 'booking_repo'.
     * @param mixed  $instance The object or value to store.
     */
    public static function set( string $key, $instance ): void {
        self::$instances[ $key ] = $instance;
    }

    /**
     * Retrieve a registered instance by key.
     *
     * @param  string $key
     * @return mixed
     * @throws RuntimeException If the key has not been registered.
     */
    public static function get( string $key ) {
        if ( ! array_key_exists( $key, self::$instances ) ) {
            throw new RuntimeException(
                "MYVH_Registry: '{$key}' has not been registered. Check myvh-bootstrap.php."
            );
        }
        return self::$instances[ $key ];
    }

    /**
     * Check whether a key has been registered.
     *
     * @param string $key
     * @return bool
     */
    public static function has( string $key ): bool {
        return array_key_exists( $key, self::$instances );
    }

    /**
     * Return all registered keys — useful for debugging.
     *
     * @return string[]
     */
    public static function keys(): array {
        return array_keys( self::$instances );
    }
}
