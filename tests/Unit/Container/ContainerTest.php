<?php

namespace Tests\Unit\Container;

use PHPUnit\Framework\TestCase;
use Simple\Container\BindingResolutionException;
use Simple\Container\Container;

interface Clock
{
    public function now(): string;
}

class FixedClock implements Clock
{
    public function __construct(private string $time = '2026-01-01')
    {
    }

    public function now(): string
    {
        return $this->time;
    }
}

class Repository
{
    public function __construct(public Clock $clock)
    {
    }
}

class Service
{
    public function __construct(public Repository $repository, public int $limit = 10)
    {
    }
}

class NeedsScalar
{
    public function __construct(public string $name)
    {
    }
}

class CircularA
{
    public function __construct(CircularB $b)
    {
    }
}

class CircularB
{
    public function __construct(CircularA $a)
    {
    }
}

class OptionalDependency
{
    public function __construct(public ?Clock $clock = null)
    {
    }
}

class ContainerTest extends TestCase
{
    public function test_builds_classes_without_constructors(): void
    {
        $this->assertInstanceOf(FixedClock::class, (new Container())->make(FixedClock::class));
    }

    public function test_autowires_nested_dependencies(): void
    {
        $container = new Container();
        $container->bind(Clock::class, FixedClock::class);

        $service = $container->make(Service::class);

        $this->assertInstanceOf(FixedClock::class, $service->repository->clock);
        $this->assertSame(10, $service->limit, 'default value used');
    }

    public function test_unbound_interface_gives_a_helpful_error(): void
    {
        $this->expectException(BindingResolutionException::class);
        $this->expectExceptionMessage('is not instantiable');
        (new Container())->make(Repository::class);
    }

    public function test_bind_creates_new_instances_and_singleton_shares_one(): void
    {
        $container = new Container();
        $container->bind(Clock::class, FixedClock::class);
        $this->assertNotSame($container->make(Clock::class), $container->make(Clock::class));

        $container->singleton(Clock::class, FixedClock::class);
        $this->assertSame($container->make(Clock::class), $container->make(Clock::class));
    }

    public function test_closure_bindings_receive_the_container(): void
    {
        $container = new Container();
        $container->singleton(Clock::class, fn (Container $c) => new FixedClock('1999-12-31'));

        $this->assertSame('1999-12-31', $container->make(Repository::class)->clock->now());
    }

    public function test_instance(): void
    {
        $container = new Container();
        $clock = new FixedClock('x');
        $container->instance(Clock::class, $clock);

        $this->assertSame($clock, $container->make(Clock::class));
        $this->assertTrue($container->has(Clock::class));
    }

    public function test_parameters_by_name_and_position(): void
    {
        $container = new Container();
        $this->assertSame('ada', $container->make(NeedsScalar::class, ['name' => 'ada'])->name);
        $this->assertSame('bob', $container->make(NeedsScalar::class, ['bob'])->name);
    }

    public function test_missing_scalar_parameter_is_reported(): void
    {
        $this->expectException(BindingResolutionException::class);
        $this->expectExceptionMessage('$name');
        (new Container())->make(NeedsScalar::class);
    }

    public function test_circular_dependencies_are_detected(): void
    {
        $this->expectException(BindingResolutionException::class);
        $this->expectExceptionMessage('Circular dependency');
        (new Container())->make(CircularA::class);
    }

    public function test_optional_unresolvable_dependency_uses_default(): void
    {
        $this->assertNull((new Container())->make(OptionalDependency::class)->clock);
    }

    public function test_call_injects_services_and_matches_values_by_name(): void
    {
        $container = new Container();
        $container->bind(Clock::class, FixedClock::class);

        $result = $container->call(
            fn (string $id, Clock $clock, string $format = 'short') => "$id@{$clock->now()}/$format",
            ['id' => '7'],
        );

        $this->assertSame('7@2026-01-01/short', $result);
    }

    public function test_call_matches_objects_by_type(): void
    {
        $clock = new FixedClock('given');
        $result = (new Container())->call(fn (Clock $c) => $c->now(), [$clock]);
        $this->assertSame('given', $result);
    }

    public function test_call_resolves_class_name_callables(): void
    {
        $container = new Container();
        $container->bind(Clock::class, FixedClock::class);

        $this->assertSame('2026-01-01', $container->call([FixedClock::class, 'now']));
    }
}
