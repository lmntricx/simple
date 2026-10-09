<?php

namespace Simple\Database;

use LogicException;

/**
 * Model::factory() — finds Database\Factories\{Model}Factory, or the class
 * named in a static $factory property on the model.
 */
trait HasFactory
{
    public static function factory(?int $count = null): Factory
    {
        $class = property_exists(static::class, 'factory')
            ? static::$factory
            : 'Database\\Factories\\' . (new \ReflectionClass(static::class))->getShortName() . 'Factory';

        if (!class_exists($class)) {
            throw new LogicException("Factory [$class] not found for " . static::class . '. Create it with `php simple make:factory`.');
        }

        $factory = $class::new();

        return $count === null ? $factory : $factory->count($count);
    }
}
