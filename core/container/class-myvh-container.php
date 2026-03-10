<?php
if (!defined('ABSPATH')) exit;

class MYVH_Container {

    private $bindings = [];
    private $instances = [];

    public function bind($key, callable $factory) {
        $this->bindings[$key] = $factory;
    }

    public function singleton($key, callable $factory) {
        $this->bindings[$key] = function($c) use ($factory, $key) {

            if (!isset($this->instances[$key])) {
                $this->instances[$key] = $factory($c);
            }

            return $this->instances[$key];
        };
    }

    public function get($key) {

        if (!isset($this->bindings[$key])) {
            throw new Exception("Service not bound: {$key}");
        }

        return $this->bindings[$key]($this);
    }

}