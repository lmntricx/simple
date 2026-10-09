<?php

namespace Simple\Database;

use Closure;
use Simple\Support\Fake;

/**
 * Builds models with fake data, for tests and seeders.
 *
 *   class PostFactory extends Factory {
 *       protected string $model = Post::class;
 *       public function definition(): array {
 *           return ['title' => $this->fake->sentence(), 'user_id' => UserFactory::new()];
 *       }
 *       public function published(): static { return $this->state(['published' => true]); }
 *   }
 *
 *   Post::factory()->create();                         // one saved Post
 *   Post::factory()->count(3)->published()->create();  // array of 3
 *   Post::factory()->make(['title' => 'Draft']);       // not saved
 *
 * A Factory as an attribute value creates that related model and uses its key.
 */
abstract class Factory
{
    /** @var class-string<Model> */
    protected string $model;

    protected Fake $fake;
    private ?int $count = null;
    /** @var array<array|Closure> */
    private array $states = [];

    final public function __construct()
    {
        $this->fake = Fake::instance();
    }

    /**
     * Default attributes for one model.
     */
    abstract public function definition(): array;

    public static function new(): static
    {
        return new static();
    }

    public function count(int $count): static
    {
        $clone = clone $this;
        $clone->count = $count;
        return $clone;
    }

    /**
     * Override attributes: state(['active' => false]) or state(fn (array $attrs) => [...]).
     */
    public function state(array|Closure $state): static
    {
        $clone = clone $this;
        $clone->states[] = $state;
        return $clone;
    }

    /**
     * Build without saving. One model, or an array when count() was used.
     *
     * @return Model|Model[]
     */
    public function make(array $attributes = []): Model|array
    {
        $models = array_map(fn () => $this->makeOne($attributes, persist: false), range(1, $this->count ?? 1));
        return $this->count === null ? $models[0] : $models;
    }

    /**
     * Build and save. One model, or an array when count() was used.
     *
     * @return Model|Model[]
     */
    public function create(array $attributes = []): Model|array
    {
        $models = array_map(fn () => $this->makeOne($attributes, persist: true), range(1, $this->count ?? 1));
        return $this->count === null ? $models[0] : $models;
    }

    /**
     * The attributes that would be used, without building a model.
     */
    public function raw(array $attributes = []): array
    {
        return $this->resolveAttributes($attributes, persist: false);
    }

    private function makeOne(array $attributes, bool $persist): Model
    {
        /** @var Model $model */
        $model = new $this->model();
        // forceFill: factories may set any column, and mutators (e.g. password hashing) still run.
        $model->forceFill($this->resolveAttributes($attributes, $persist));

        if ($persist) {
            $model->save();
        }

        return $model;
    }

    private function resolveAttributes(array $overrides, bool $persist): array
    {
        $attributes = $this->definition();

        foreach ([...$this->states, $overrides] as $state) {
            $attributes = array_replace($attributes, $state instanceof Closure ? $state($attributes) : $state);
        }

        foreach ($attributes as $key => $value) {
            if ($value instanceof self) {
                // Related model: create it (or make it, when not persisting) and use its key.
                $related = $persist ? $value->count(1)->create()[0] : null;
                $attributes[$key] = $related?->getKey();
            } elseif ($value instanceof Closure) {
                $attributes[$key] = $value($attributes);
            }
        }

        return $attributes;
    }
}
