<?php

namespace Simple\Tests\Fixtures\Models;

use Simple\Auth\Hash;
use Simple\Auth\HasApiTokens;
use Simple\Database\Model;

/**
 * The user model of the fixture app (config/auth.php points here).
 */
class AuthUser extends Model
{
    use HasApiTokens;

    protected string $table = 'users';
    protected array $fillable = ['name', 'email', 'password'];
    protected array $hidden = ['password'];
    protected array $casts = ['id' => 'int'];

    protected function setEmailAttribute(string $value): string
    {
        return strtolower(trim($value));
    }

    protected function setPasswordAttribute(string $value): string
    {
        return Hash::make($value);
    }

    public static function make(string $email = 'ada@x.io', string $password = 'password123', string $name = 'Ada'): static
    {
        return static::create(compact('name', 'email', 'password'));
    }
}
