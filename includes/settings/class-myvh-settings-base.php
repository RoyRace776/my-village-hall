<?php

abstract class MYVH_Settings_Base {

    protected $option_name = '';
    protected $schema = [];
    protected $settings = null;


    /**
     * Return schema
     */
    public function schema() {
        return $this->schema;
    }


    /**
     * Load settings from database
     */
    protected function load() {

        if ($this->settings !== null) {
            return;
        }

        $saved = get_option($this->option_name, []);

        $with_defaults = $this->apply_defaults($saved);

        // Auto-heal missing fields
        if ($saved !== $with_defaults) {
            update_option($this->option_name, $with_defaults);
        }

        $this->settings = $with_defaults;
    }


    /**
     * Apply schema defaults
     */
    protected function apply_defaults($settings) {

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
    public function all() {

        $this->load();

        return $this->settings;
    }


    /**
     * Save settings
     */
    public function save($input) {

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

        update_option($this->option_name, $clean);

        $this->settings = $clean;
    }

}