<?php
namespace MYVH\Settings;

class SettingsRegistry {

    private static $groups = [];

    public static function register( mixed $key, mixed $label, mixed $class, array $meta = []): void {

        self::$groups[$key] = array_merge([
            'label' => $label,
            'class' => $class,
            'required_capability' => '',
            'hide_from_client_admin' => false,
        ], $meta);
    }

    public static function groups(): array {
        return self::$groups;
    }

    public static function reset(): void {
        self::$groups = [];
    }

    public static function get($key): ?SettingsBase {

        if (!isset(self::$groups[$key])) {
            return null;
        }

        $class = self::$groups[$key]['class'];

        return new $class();
    }

    public static function user_can_access_group($key, int $user_id = 0): bool {
        $settings = self::get($key);

        if (!$settings) {
            return false;
        }

        return $settings->user_can_access($user_id);
    }

    public static function group_visible_to_client_admin($key, int $user_id = 0): bool {
        $settings = self::get($key);

        if (!$settings) {
            return false;
        }

        return $settings->is_visible_to_client_admin($user_id);
    }

    /**
     * Automatically load settings classes
     */
    public static function auto_register($settings_dir): void {

        $files = glob($settings_dir . '/*.php');

        foreach ($files as $file) {
            require_once $file;
        }

        foreach (get_declared_classes() as $class) {

            if (!is_subclass_of($class, 'MYVH\Settings\SettingsBase')) {
                continue;
            }

            $instance = new $class();

            if (!method_exists($instance, 'tab')) {
                continue;
            }

            $tab = $instance->tab();

            self::register(
                $tab['key'],
                $tab['label'],
                $class,
                [
                    'required_capability' => $instance->get_required_capability(),
                    'hide_from_client_admin' => $instance->should_hide_from_client_admin(),
                ]
            );
        }
    }
}
