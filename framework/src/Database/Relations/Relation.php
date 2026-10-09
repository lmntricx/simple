<?php

namespace Simple\Database\Relations;

use Simple\Database\Model;
use Simple\Database\QueryBuilder;

/**
 * Returned by a model's relationship methods. Acts like a query scoped to the
 * parent ($user->posts()->where(...)->get()) and knows how to load itself for
 * many parents at once (eager loading).
 *
 * @mixin QueryBuilder
 */
abstract class Relation
{
    protected QueryBuilder $query;

    /**
     * @param class-string<Model> $related
     */
    public function __construct(protected Model $parent, protected string $related)
    {
    }

    /**
     * The result for $parent: a model, null, or an array of models.
     */
    abstract public function getResults(): mixed;

    /**
     * Load this relation for all $models with a single query, storing the
     * result on each model under $name.
     *
     * @param Model[] $models
     * @return Model[] every related model loaded (for nested eager loading)
     */
    abstract public function eagerLoad(array $models, string $name): array;

    public function getQuery(): QueryBuilder
    {
        return $this->query;
    }

    /**
     * @return class-string<Model>
     */
    public function getRelated(): string
    {
        return $this->related;
    }

    /**
     * Forward query methods; chainable ones keep returning the relation.
     */
    public function __call(string $method, array $arguments): mixed
    {
        $result = $this->query->$method(...$arguments);

        return $result === $this->query ? $this : $result;
    }

    /**
     * Unique, non-null values of $attribute across $models.
     */
    protected static function keys(array $models, string $attribute): array
    {
        $keys = [];
        foreach ($models as $model) {
            $value = $model->getAttribute($attribute);
            if ($value !== null) {
                $keys[(string) $value] = $value;
            }
        }
        return array_values($keys);
    }
}
