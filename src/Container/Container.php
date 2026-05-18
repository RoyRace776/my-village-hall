<?php
namespace MYVH\Container;
/**
 * Class Container
 *
 * Simple dependency injection container for managing class bindings and singletons.
 * Provides automatic resolution of class dependencies using reflection.
 */
class Container
{
    /**
     * @var array Stores class bindings (id => factory or class name)
     */
    private $bindings = [];
    /**
     * @var array Stores singleton instances (id => object)
     */
    private $instances = [];

    /**
     * Register a singleton binding.
     *
     * @param string $id Identifier for the binding
     * @param callable|string|null $factory Factory callback or class name
     */
    public function singleton( mixed $id, mixed $factory = null): void
    {
        $this->bindings[$id] = $factory ?? $id;
    }

    /**
     * Retrieve an instance by id, resolving dependencies if needed.
     *
     * @param string $id
     * @return object
     * @throws Exception
     */
    public function get($id): object
    {
        // Return existing instance if already resolved
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        // Resolve and instantiate
        $object = $this->resolve($id);

        // Store for future calls
        $this->instances[$id] = $object;

        return $object;
    }

    /**
     * Resolve a binding or build the class.
     *
     * @param string $id
     * @return object
     * @throws Exception
     */
    private function resolve($id): object
    {
        // If a factory is registered, call it
        if (isset($this->bindings[$id]) && is_callable($this->bindings[$id])) {
            return $this->bindings[$id]($this);
        }

        // Otherwise, build the class directly
        return $this->build($id);
    }

    /**
     * Build a class instance, resolving constructor dependencies recursively.
     *
     * @param string $class
     * @return object
     * @throws Exception
     */
    private function build($class): object
    {
        $reflection = new \ReflectionClass($class);

        if (!$reflection->isInstantiable()) {
            throw new \Exception("Class $class is not instantiable");
        }

        $constructor = $reflection->getConstructor();

        if (!$constructor) {
            // No constructor, instantiate directly
            return new $class;
        }

        $dependencies = [];

        // Resolve each constructor parameter
        foreach ($constructor->getParameters() as $param) {
            $type = $param->getType();

            // Only named, non-built-in types can be resolved from the container.
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                try {
                    $dependencies[] = $this->get($type->getName());
                    continue;
                } catch (\Exception $e) {
                    // Fall through to default/null handling below.
                }
            }

            // Use the parameter's default value when available.
            if ($param->isOptional()) {
                $dependencies[] = $param->getDefaultValue();
                continue;
            }

            // Nullable parameters without a default can safely receive null.
            if ($type && $type->allowsNull()) {
                $dependencies[] = null;
                continue;
            }

            throw new \Exception("Cannot resolve {$param->getName()} in {$class}");
        }

        // Instantiate with resolved dependencies
        return $reflection->newInstanceArgs($dependencies);
    }
}
