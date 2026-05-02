<?php

namespace MYVH\Tests\Unit\Container;

use MYVH\Container\Container;
use MYVH\Tests\Unit\UnitTestCase;

class ContainerTest extends UnitTestCase
{
    // ── singleton / get ──────────────────────────────────────────────────

    /** @test */
    public function it_returns_same_instance_on_repeated_get(): void
    {
        $container = new Container();
        $container->singleton(SimpleClass::class);

        $a = $container->get(SimpleClass::class);
        $b = $container->get(SimpleClass::class);

        $this->assertSame($a, $b);
    }

    /** @test */
    public function it_uses_callable_factory_when_registered(): void
    {
        $container = new Container();
        $container->singleton(SimpleClass::class, fn($c) => new SimpleClass());

        $instance = $container->get(SimpleClass::class);

        $this->assertInstanceOf(SimpleClass::class, $instance);
    }

    // ── auto-wiring ──────────────────────────────────────────────────────

    /** @test */
    public function it_auto_wires_constructor_dependencies(): void
    {
        $container = new Container();
        $container->singleton(SimpleClass::class);
        $container->singleton(DependentClass::class);

        $instance = $container->get(DependentClass::class);

        $this->assertInstanceOf(DependentClass::class, $instance);
        $this->assertInstanceOf(SimpleClass::class, $instance->dep);
    }

    /** @test */
    public function it_resolves_class_with_no_constructor(): void
    {
        $container = new Container();

        $instance = $container->get(SimpleClass::class);

        $this->assertInstanceOf(SimpleClass::class, $instance);
    }

    /** @test */
    public function it_uses_default_value_for_optional_scalar_parameter(): void
    {
        $container = new Container();

        $instance = $container->get(ClassWithOptionalScalar::class);

        $this->assertInstanceOf(ClassWithOptionalScalar::class, $instance);
        $this->assertSame(42, $instance->value);
    }

    // ── error cases ──────────────────────────────────────────────────────

    /** @test */
    public function it_throws_when_class_is_not_instantiable(): void
    {
        $container = new Container();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/not instantiable/');

        $container->get(AbstractClass::class);
    }
}

// ── Helper classes (local to this test namespace) ────────────────────────────

class SimpleClass {}

class DependentClass
{
    public SimpleClass $dep;

    public function __construct(SimpleClass $dep)
    {
        $this->dep = $dep;
    }
}

class ClassWithOptionalScalar
{
    public int $value;

    public function __construct(int $value = 42)
    {
        $this->value = $value;
    }
}

abstract class AbstractClass {}
