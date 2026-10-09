<?php

namespace Tests\Fixtures\Models;

use Simple\Database\Model;
use Simple\Database\QueryBuilder;

class User extends Model
{
    protected string $table = 'users';
    protected array $fillable = ['name', 'email', 'password', 'active', 'settings'];
    protected array $hidden = ['password'];
    protected array $casts = ['id' => 'int', 'active' => 'bool', 'settings' => 'array'];

    public function posts(): QueryBuilder
    {
        return $this->hasMany(Post::class);
    }

    public function profile(): ?Profile
    {
        return $this->hasOne(Profile::class);
    }

    public function shout(): string // not a relation: wrong return type
    {
        return 'HEY';
    }

    protected function setPasswordAttribute(?string $value): ?string
    {
        return $value === null ? null : \Simple\Auth\Hash::make($value);
    }
}
