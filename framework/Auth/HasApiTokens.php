<?php

namespace Simple\Auth;

use DateTimeInterface;
use Simple\Database\QueryBuilder;

/**
 * Add to your user model:  class User extends Model { use HasApiTokens; }
 */
trait HasApiTokens
{
    /**
     * @param string[] $abilities e.g. ['posts:read'], or ['*'] for everything
     */
    public function createToken(string $name, array $abilities = ['*'], ?DateTimeInterface $expiresAt = null): NewAccessToken
    {
        $secret = bin2hex(random_bytes(20));

        $token = PersonalAccessToken::create([
            'user_id' => $this->getKey(),
            'name' => $name,
            'token' => hash('sha256', $secret),
            'abilities' => $abilities,
            'expires_at' => $expiresAt?->format('Y-m-d H:i:s'),
        ]);

        return new NewAccessToken($token, $token->id . '|' . $secret);
    }

    public function tokens(): QueryBuilder
    {
        return $this->hasMany(PersonalAccessToken::class, 'user_id');
    }

    /**
     * Whether the token used for the current request has an ability.
     */
    public function tokenCan(string $ability): bool
    {
        $token = Auth::token();

        return $token !== null && (string) $token->user_id === (string) $this->getKey() && $token->can($ability);
    }
}
