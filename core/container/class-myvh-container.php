<?php

class MYVH_Container
{
    private $bindings = [];
    private $instances = [];

    public function singleton($id, $factory = null)
    {
        $this->bindings[$id] = $factory ?? $id;
    }

    public function get($id)
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        $object = $this->resolve($id);

        $this->instances[$id] = $object;

        return $object;
    }

    private function resolve($id)
    {
        if (isset($this->bindings[$id]) && is_callable($this->bindings[$id])) {
            return $this->bindings[$id]($this);
        }

        return $this->build($id);
    }

    private function build($class)
    {
        $reflection = new ReflectionClass($class);

        if (!$reflection->isInstantiable()) {
            throw new Exception("Class $class is not instantiable");
        }

        $constructor = $reflection->getConstructor();

        if (!$constructor) {
            return new $class;
        }

        $dependencies = [];

        foreach ($constructor->getParameters() as $param) {

            $type = $param->getType();

            if (!$type) {
                throw new Exception("Cannot resolve {$param->getName()} in {$class}");
            }

            $dependencies[] = $this->get($type->getName());
        }

        return $reflection->newInstanceArgs($dependencies);
    }
}