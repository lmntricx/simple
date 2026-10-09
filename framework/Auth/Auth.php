<?php

namespace Simple\Auth;

use Simple\Database\Model;

/**
 * The authenticated user for the current request.
 *
 *   Auth::user()   Auth::id()   Auth::check()   Auth::token()
 *
 * Set by the "auth" middleware; in controllers you can also use $request->user().
 */
final class Auth
{
    private static ?Model $user = null;
    private static ?PersonalAccessToken $token = null;

    public static function user(): ?Model
    {
        return self::$user;
    }

    public static function id(): mixed
    {
        return self::$user?->getKey();
    }

    public static function check(): bool
    {
        return self::$user !== null;
    }

    /**
     * The token used to authenticate this request.
     */
    public static function token(): ?PersonalAccessToken
    {
        return self::$token;
    }

    public static function login(Model $user, ?PersonalAccessToken $token = null): void
    {
        self::$user = $user;
        self::$token = $token;
    }

    public static function forget(): void
    {
        self::$user = null;
        self::$token = null;
    }

    /**
     * Check credentials. Returns the user, or null if they don't match.
     * Does not log anyone in; issue a token with $user->createToken().
     */
    public static function attempt(string $username, string $password): ?Model
    {
        /** @var class-string<Model> $model */
        $model = config('auth.model');
        $user = $model::firstWhere(config('auth.username', 'email'), $username);
        $hash = $user?->getAttribute('password');

        // With no user, verify against a dummy hash anyway, so a failed login takes as
        // long whether or not the account exists (no discovering emails by timing).
        $valid = Hash::check($password, is_string($hash) ? $hash : Hash::dummy());

        return $valid && $user ? $user : null;
    }
}
