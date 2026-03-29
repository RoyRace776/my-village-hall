<?php
namespace MYVH\Settings;

class SettingsRegistry {

    private static $groups = [];


    public static function register($key, $label, $class): void {

        self::$groups[$key] = [
            'label' => $label,
            'class' => $class
        ];

    }


    public static function groups(): array {
        return self::$groups;
    }


    public static function get($key): ?SettingsBase {

        if (!isset(self::$groups[$key])) {
            return null;
        }

        $class = self::$groups[$key]['class'];

        return new $class();

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
                $class
            );

        }

    }

}