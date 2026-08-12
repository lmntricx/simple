<?php

namespace app\Models;

use app\ORM\Blueprint;

class User extends BaseModel
{
    protected string $table = 'users';
    protected array $fillable = [
        'name',
        'email',
        'password',
    ];
    protected array $hidden = [
        'password',
    ];

    protected static function schema(Blueprint $table): void
    {
        $table->id();
        $table->string('name', 255);
        $table->string('email', 255)->unique();
        $table->string('password', 255);
        $table->timestamps();
    }
}