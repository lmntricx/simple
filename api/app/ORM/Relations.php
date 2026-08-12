<?php

namespace app\ORM;

trait Relations
{
    public function hasOne(string $relatedClass, string $foreignKey = null, string $localKey = 'id'): ?Model
    {
        $related = new $relatedClass();
        $foreignKey = $foreignKey ?: strtolower((new \ReflectionClass($related))->getShortName()) . '_id';
        $value = $this->attributes[$localKey] ?? null;

        return $related->findBy($foreignKey, $value);
    }

    public function hasMany(string $relatedClass, string $foreignKey = null, string $localKey = 'id'): array
    {
        $related = new $relatedClass();
        $foreignKey = $foreignKey ?: strtolower((new \ReflectionClass($this))->getShortName()) . '_id';

        return $related->hydrate(
            $related->newQuery()
                ->where($foreignKey, '=', $this->attributes[$localKey] ?? null)
                ->get()
        );
    }

    public function belongsTo(string $relatedClass, string $foreignKey = null, string $ownerKey = 'id'): ?Model
    {
        $related = new $relatedClass();
        $foreignKey = $foreignKey ?: strtolower((new \ReflectionClass($related))->getShortName()) . '_id';

        return $related->findBy($ownerKey, $this->attributes[$foreignKey] ?? null);
    }
}