<?php

namespace Simple\Container;

use Closure;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * Builds objects and their dependencies.
 *
 *   $container->make(ReportService::class);           // constructor dependencies built automatically
 *   $container->singleton(Cache::class, FileCache::class);   // interface => implementation, one shared instance
 *   $container->bind(Clock::class, fn () => new FixedClock('2026-01-01'));
 *   $container->call([$controller, 'show'], ['id' => '5']);  // method injection
 *
 * Classes with no binding are built by reading their constructor's type hints.
 */
class Container
{
    /** @var array<string, array{concrete: Closure|string|null, shared: bool}> */
    protected array $bindings = [];
    /** @var array<string, mixed> */
    protected array $instances = [];
    /** @var string[] classes being built right now, to detect circular dependencies */
    private array $buildStack = [];

    /**
     * Build a new instance every time it's requested.
     */
    public function bind(string $abstract, Closure|string|null $concrete = null): void
    {
        unset($this->instances[$abstract]);
        $this->bindings[$abstract] = ['concrete' => $concrete, 'shared' => false];
    }

    /**
     * Build it once, then return the same instance.
     */
    public function singleton(string $abstract, Closure|string|null $concrete = null): void
    {
        unset($this->instances[$abstract]);
        $this->bindings[$abstract] = ['concrete' => $concrete, 'shared' => true];
    }

    /**
     * Register an existing object.
     */
    public function instance(string $abstract, mixed $instance): mixed
    {
        $this->instances[$abstract] = $instance;
        return $instance;
    }

    public function forgetInstance(string $abstract): void
    {
        unset($this->instances[$abstract]);
    }

    public function has(string $abstract): bool
    {
        return isset($this->instances[$abstract]) || isset($this->bindings[$abstract]);
    }

    /**
     * @template T
     * @param class-string<T>|string $abstract
     * @param array $parameters values for constructor parameters the container can't
     *                          resolve itself, by name ('id' => 5) or position ([5])
     * @return T|mixed
     */
    public function make(string $abstract, array $parameters = []): mixed
    {
        if (array_key_exists($abstract, $this->instances) && $parameters === []) {
            return $this->instances[$abstract];
        }

        $binding = $this->bindings[$abstract] ?? null;
        $concrete = $binding['concrete'] ?? $abstract;

        $object = match (true) {
            $concrete instanceof Closure => $concrete($this, $parameters),
            $concrete === $abstract => $this->build($concrete, $parameters),
            default => $this->make($concrete, $parameters),
        };

        if (($binding['shared'] ?? false) && $parameters === []) {
            $this->instances[$abstract] = $object;
        }

        return $object;
    }

    /**
     * Call a function or method, injecting its parameters: values from
     * $parameters (by name, then by position), objects in $parameters matched
     * by type, then anything else the container can build.
     */
    public function call(callable|array $callable, array $parameters = []): mixed
    {
        if (is_array($callable) && is_string($callable[0])) {
            $callable[0] = $this->make($callable[0]);
        }

        $reflection = is_array($callable)
            ? new ReflectionMethod($callable[0], $callable[1])
            : new ReflectionFunction(Closure::fromCallable($callable));

        return $callable(...$this->resolveParameters($reflection, $parameters));
    }

    protected function build(string $class, array $parameters): object
    {
        if (in_array($class, $this->buildStack, true)) {
            throw new BindingResolutionException(
                'Circular dependency: ' . implode(' -> ', [...$this->buildStack, $class])
            );
        }

        try {
            $reflection = new ReflectionClass($class);
        } catch (ReflectionException) {
            throw new BindingResolutionException("Class [$class] does not exist.");
        }

        if (!$reflection->isInstantiable()) {
            throw new BindingResolutionException(
                "[$class] is not instantiable. Bind it to a concrete class, e.g. \$app->singleton($class::class, Implementation::class)."
            );
        }

        $constructor = $reflection->getConstructor();
        if ($constructor === null) {
            return new $class();
        }

        $this->buildStack[] = $class;
        try {
            $args = $this->resolveParameters($constructor, $parameters);
        } finally {
            array_pop($this->buildStack);
        }

        return $reflection->newInstanceArgs($args);
    }

    protected function resolveParameters(ReflectionFunctionAbstract $function, array $given): array
    {
        $named = array_filter($given, 'is_string', ARRAY_FILTER_USE_KEY);
        $positional = array_values(array_filter($given, 'is_int', ARRAY_FILTER_USE_KEY));
        $args = [];

        foreach ($function->getParameters() as $parameter) {
            $name = $parameter->getName();

            if ($parameter->isVariadic()) {
                array_push($args, ...array_values($named), ...$positional);
                break;
            }

            if (array_key_exists($name, $named)) {
                $args[] = $named[$name];
                unset($named[$name]);
                continue;
            }

            $class = $this->parameterClass($parameter);

            if ($class !== null) {
                // An object passed in that fits the type (e.g. the current Request).
                foreach ($positional as $i => $value) {
                    if ($value instanceof $class) {
                        $args[] = $value;
                        array_splice($positional, $i, 1);
                        continue 2;
                    }
                }

                try {
                    $args[] = $this->make($class);
                    continue;
                } catch (BindingResolutionException $e) {
                    if (!$parameter->isDefaultValueAvailable() && !$parameter->allowsNull()) {
                        throw $e;
                    }
                }
            } elseif ($positional !== []) {
                $args[] = array_shift($positional);
                continue;
            } elseif ($named !== [] && !$parameter->isDefaultValueAvailable()) {
                // A value whose name doesn't match any parameter: use it in order.
                $args[] = array_shift($named);
                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $args[] = $parameter->getDefaultValue();
            } elseif ($parameter->allowsNull()) {
                $args[] = null;
            } else {
                $where = $function instanceof ReflectionMethod
                    ? $function->class . '::' . $function->getName() . '()'
                    : $function->getName() . '()';
                throw new BindingResolutionException("Can't resolve parameter \$$name of $where.");
            }
        }

        return $args;
    }

    private function parameterClass(ReflectionParameter $parameter): ?string
    {
        $type = $parameter->getType();

        return $type instanceof ReflectionNamedType && !$type->isBuiltin() ? $type->getName() : null;
    }
}
