<?php

abstract class Settings_Base {

    protected $option_name = '';
    protected $schema = [];
    protected $settings = null;

    /**
     * 'site'    → stored per-site (default, works in single-site too)
     * 'network' → stored once for the whole network
     */
    protected $multisite_scope = 'site';

    /**
     * Return schema
     */
    public function schema(): array {
        return $this->schema;
    }


    /**
     * Load settings from database
     */
    protected function load(): void {

        if ($this->settings !== null) {
            return;
        }

        $saved = $this->get_option([]);

        if (!is_array($saved)) {
            $saved = [];
        }

        $with_defaults = $this->apply_defaults($saved);

        // Auto-heal missing fields
        if ($saved !== $with_defaults) {
            $this->update_option($with_defaults);
        }

        $this->settings = $with_defaults;
    }


    /**
     * Apply schema defaults
     */
    protected function apply_defaults($settings): array {

        foreach ($this->schema as $section) {

            if (!isset($section['fields'])) {
                continue;
            }

            foreach ($section['fields'] as $key => $rule) {

                if (!array_key_exists($key, $settings)) {

                    if (isset($rule['default'])) {
                        $settings[$key] = $rule['default'];
                    } else {
                        $settings[$key] = null;
                    }

                }

            }

        }

        return $settings;
    }


    /**
     * Get single setting
     */
    public function get($key) {

        $this->load();

        return $this->settings[$key] ?? null;
    }


    /**
     * Get all settings
     */
    public function all(): array {

        $this->load();

        return $this->settings;
    }


    /**
     * Save settings
     */
    public function save($input): void {

        $clean = [];

        foreach ($this->schema as $section) {

            if (!isset($section['fields'])) {
                continue;
            }

            foreach ($section['fields'] as $key => $rule) {

                if (!isset($input[$key])) {

                    // Handle unchecked checkboxes
                    if (($rule['type'] ?? '') === 'boolean') {
                        $clean[$key] = false;
                    }

                    continue;
                }

                $value = $input[$key];

                if (isset($rule['sanitize']) && is_callable($rule['sanitize'])) {
                    $value = call_user_func($rule['sanitize'], $value);
                }

                $clean[$key] = $value;

            }

        }

        $clean = $this->apply_defaults($clean);

        $this->update_option($clean);

        $this->settings = $clean;
    }

    protected function get_multisite_scope(): string {
        $scope = $this->multisite_scope;

        if (is_multisite()) {
            $scope = apply_filters(
                'myvh_settings_multisite_scope',
                $scope,
                $this->option_name,
                static::class
            );
        }

        return $scope === 'network' ? 'network' : 'site';
    }

    protected function get_option( $default = [] ) {
        if ( $this->get_multisite_scope() === 'network' && is_multisite() ) {
            return get_site_option( $this->option_name, $default );
        }

        // Backward compatibility: if values were previously saved at network scope,
        // continue reading them until this site writes its own local settings.
        $site_value = get_option( $this->option_name, null );
        if ( $site_value !== null ) {
            return $site_value;
        }

        if ( is_multisite() ) {
            $network_value = get_site_option( $this->option_name, null );
            if ( $network_value !== null ) {
                return $network_value;
            }
        }

        return $default;
    }


    protected function update_option( $value ): bool {
        if ( $this->get_multisite_scope() === 'network' && is_multisite() ) {
            return update_site_option( $this->option_name, $value );
        }
        return update_option( $this->option_name, $value );
    }
}