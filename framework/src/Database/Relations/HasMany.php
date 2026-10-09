<?php

namespace Simple\Database\Relations;

use Simple\Database\Model;

/**
 * $user->hasMany(Post::class) — posts.user_id = users.id
 */
class HasMany extends Relation
{
    public function __construct(Model $parent, string $related, protected string $foreignKey, protected string $localKey)
    {
        parent::__construct($parent, $related);

        // Locked, so a later ->orWhere() can't widen the query to other owners' rows.
        $this->query = $related::query()
            ->where($foreignKey, $parent->getAttribute($localKey))
            ->lockConstraints();
    }

    public function getResults(): mixed
    {
        return $this->parent->getAttribute($this->localKey) === null ? [] : $this->query->get();
    }

    public function eagerLoad(array $models, string $name): array
    {
        $keys = self::keys($models, $this->localKey);
        $results = $keys === [] ? [] : $this->related::query()->whereIn($this->foreignKey, $keys)->get();

        $grouped = [];
        foreach ($results as $result) {
            $grouped[(string) $result->getAttribute($this->foreignKey)][] = $result;
        }

        foreach ($models as $model) {
            $model->setRelation($name, $this->match($grouped[(string) $model->getAttribute($this->localKey)] ?? []));
        }

        return $results;
    }

    /**
     * @param Model[] $matches
     */
    protected function match(array $matches): mixed
    {
        return $matches;
    }
}
