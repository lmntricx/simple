<?php

namespace App\Models;

use Simple\Database\Model;

class User extends Model
{
    protected array $fillable = ['name', 'email', 'password'];

    protected array $hidden = ['password'];

    protected array $casts = ['id' => 'int'];

    /**
     * Passwords are always hashed before they are stored.
     */
    protected function setPasswordAttribute(string $value): string
    {
        return password_get_info($value)['algo'] ? $value : password_hash($value, PASSWORD_DEFAULT);
    }

    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->attributes['password'] ?? '');
    }
}
