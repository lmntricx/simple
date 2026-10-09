<?php

namespace Simple\Auth;

use Simple\Database\Model;

/**
 * An API token. The plain-text token ("{id}|{secret}") is only shown once, when
 * created; the database stores a SHA-256 hash of the secret.
 */
class PersonalAccessToken extends Model
{
    protected string $table = 'personal_access_tokens';
    protected ?array $guarded = [];
    protected array $hidden = ['token'];
    protected array $casts = ['id' => 'int', 'user_id' => 'int', 'abilities' => 'array'];

    public static function findToken(string $plainText): ?static
    {
        [$id, $secret] = array_pad(explode('|', $plainText, 2), 2, '');

        if (!ctype_digit($id) || $secret === '') {
            return null;
        }

        $token = static::find((int) $id);

        return $token && hash_equals((string) $token->getAttribute('token'), hash('sha256', $secret)) ? $token : null;
    }

    /**
     * Token abilities limit what a token may do: ['*'] (default) allows everything.
     */
    public function can(string $ability): bool
    {
        $abilities = $this->abilities ?? [];

        return in_array('*', $abilities, true) || in_array($ability, $abilities, true);
    }

    public function isExpired(): bool
    {
        if ($this->expires_at !== null && strtotime($this->expires_at) <= time()) {
            return true;
        }

        $minutes = config('auth.expiration');

        return $minutes && $this->created_at !== null
            && strtotime($this->created_at) + ((int) $minutes * 60) <= time();
    }

    public function user(): ?Model
    {
        /** @var class-string<Model> $model */
        $model = config('auth.model');

        return $model::find($this->user_id);
    }
}
