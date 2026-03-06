<?php

abstract class MYVH_Settings_Base {

    protected $option_name;
    protected $schema = [];
    protected $settings = null;

    protected function load() {

        if ($this->settings !== null) {
            return;
        }

        $this->settings = get_option($this->option_name, []);
    }

    public function get($key) {

        $this->load();

        if (isset($this->settings[$key])) {
            return $this->settings[$key];
        }

        return $this->schema[$key]['default'] ?? null;
    }

    public function all() {

        $this->load();

        $data = [];

        foreach ($this->schema as $key => $rule) {
            $data[$key] = $this->get($key);
        }

        return $data;
    }

    public function save($input) {

        $clean = [];

        foreach ($this->schema as $key => $rule) {

            if (!isset($input[$key])) {
                continue;
            }

            $sanitize = $rule['sanitize'] ?? null;

            if ($sanitize) {
                $clean[$key] = $sanitize($input[$key]);
            } else {
                $clean[$key] = $input[$key];
            }
        }

        update_option($this->option_name, $clean);

        $this->settings = $clean;
    }

    public function schema() {
        return $this->schema;
    }

}