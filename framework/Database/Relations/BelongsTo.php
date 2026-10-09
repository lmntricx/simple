<?php

namespace Simple\Database\Relations;

use Simple\Database\Model;

/**
 * $post->belongsTo(User::class) — users.id = posts.user_id
 */
class BelongsTo extends Relation
{
    public function __construct(Model $child, string $related, protected string $foreignKey, protected string $ownerKey)
    {
        parent::__construct($child, $related);

        $this->query = $related::query()
            ->where($ownerKey, $child->getAttribute($foreignKey))
            ->lockConstraints();
    }

    public function getResults(): mixed
    {
        return $this->parent->getAttribute($this->foreignKey) === null ? null : $this->query->first();
    }

    public function eagerLoad(array $models, string $name): array
    {
        $keys = self::keys($models, $this->foreignKey);
        $results = $keys === [] ? [] : $this->related::query()->whereIn($this->ownerKey, $keys)->get();

        $byKey = [];
        foreach ($results as $result) {
            $byKey[(string) $result->getAttribute($this->ownerKey)] = $result;
        }

        foreach ($models as $model) {
            $key = $model->getAttribute($this->foreignKey);
            $model->setRelation($name, $key === null ? null : ($byKey[(string) $key] ?? null));
        }

        return $results;
    }
}
