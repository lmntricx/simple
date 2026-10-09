<?php

namespace App\Models;

use Simple\Auth\Hash;
use Simple\Auth\HasApiTokens;
use Simple\Database\Model;

class User extends Model
{
    use HasApiTokens;

    protected array $fillable = ['name', 'email', 'password'];

    protected array $hidden = ['password'];

    protected array $casts = ['id' => 'int'];

    protected function setEmailAttribute(string $value): string
    {
        return strtolower(trim($value));
    }

    /**
     * Passwords are always hashed before they are stored.
     */
    protected function setPasswordAttribute(string $value): string
    {
        return Hash::make($value);
    }

    public function verifyPassword(string $password): bool
    {
        return Hash::check($password, $this->attributes['password'] ?? null);
    }
}
