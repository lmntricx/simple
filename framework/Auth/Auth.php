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
    /**
     * Used when no user matches, so a failed login takes as long whether or not
     * the account exists (prevents discovering registered emails by timing).
     */
    private const DUMMY_HASH = '$2y$12$HCvEswn.V/ROBTLjEGPCAOr0A1wfJndYXMRObVa7hRpJ9JLRXqhOq';

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

        $valid = password_verify($password, is_string($hash) ? $hash : self::DUMMY_HASH);

        return $valid && $user ? $user : null;
    }
}
