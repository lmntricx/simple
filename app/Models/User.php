<?php

namespace App\Models;

use Simple\Auth\HasApiTokens;
use Simple\Database\Model;

class User extends Model
{
    use HasApiTokens;

    protected array $fillable = ['name', 'email', 'password'];

    protected array $hidden = ['password'];

    protected array $casts = ['id' => 'int'];

    /**
     * Passwords are always hashed before they are stored.
     */
    protected function setPasswordAttribute(string $value): string
    {
        return password_hash($value, PASSWORD_DEFAULT);
    }

    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->attributes['password'] ?? '');
    }
}
