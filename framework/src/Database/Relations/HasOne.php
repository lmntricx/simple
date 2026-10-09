<?php

namespace Simple\Database\Relations;

/**
 * $user->hasOne(Profile::class) — profiles.user_id = users.id
 */
class HasOne extends HasMany
{
    public function getResults(): mixed
    {
        return $this->parent->getAttribute($this->localKey) === null ? null : $this->query->first();
    }

    protected function match(array $matches): mixed
    {
        return $matches[0] ?? null;
    }
}
