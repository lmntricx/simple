<?php

namespace Simple\Auth;

use Simple\Database\Connection;
use Simple\Database\Model;
use Simple\Database\QueryBuilder;

/**
 * Password reset tokens: random, single-use, expiring, stored as SHA-256
 * hashes in the "password_reset_tokens" table (one per email).
 */
final class PasswordBroker
{
    private const TABLE = 'password_reset_tokens';

    /**
     * Create a token for $user and return it in plain text (to put in the email).
     * Returns null if one was created too recently (see auth.password_reset_throttle).
     */
    public static function createToken(Model $user): ?string
    {
        $email = self::email($user);
        $existing = self::query()->where('email', $email)->first();
        $throttle = (int) config('auth.password_reset_throttle', 60);

        if ($existing && strtotime($existing['created_at']) > time() - $throttle) {
            return null;
        }

        $token = bin2hex(random_bytes(32));

        Connection::transaction(function () use ($email, $token) {
            self::query()->where('email', $email)->delete();
            self::query()->insert([
                'email' => $email,
                'token' => hash('sha256', $token),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        });

        return $token;
    }

    public static function tokenIsValid(string $email, string $token): bool
    {
        $row = self::query()->where('email', strtolower($email))->first();

        if ($row === null) {
            return false;
        }

        $minutes = (int) config('auth.password_reset_expire', 60);
        if (strtotime($row['created_at']) + $minutes * 60 < time()) {
            return false;
        }

        return hash_equals($row['token'], hash('sha256', $token));
    }

    /**
     * Set a new password if the token is valid. The token is used up, and all
     * of the user's API tokens are revoked (logs out every device).
     * Returns the user, or null if the email/token pair is invalid or expired.
     */
    public static function reset(string $email, string $token, string $password): ?Model
    {
        if (!self::tokenIsValid($email, $token)) {
            return null;
        }

        /** @var class-string<Model> $model */
        $model = config('auth.model');
        $user = $model::firstWhere('email', $email);

        if ($user === null) {
            return null;
        }

        Connection::transaction(function () use ($user, $email, $password) {
            // The model's password mutator normally hashes; hash here if it doesn't have one.
            $user->forceFill(['password' => $password]);
            if (!Hash::isHashed((string) $user->getAttribute('password'))) {
                $user->forceFill(['password' => Hash::make($password)]);
            }
            $user->save();

            self::query()->where('email', strtolower($email))->delete();

            if (method_exists($user, 'tokens')) {
                $user->tokens()->delete();
            }
        });

        return $user;
    }

    /**
     * Delete expired tokens. Returns how many were removed.
     */
    public static function prune(): int
    {
        $minutes = (int) config('auth.password_reset_expire', 60);

        return self::query()->where('created_at', '<', date('Y-m-d H:i:s', time() - $minutes * 60))->delete();
    }

    private static function email(Model $user): string
    {
        return strtolower((string) $user->getAttribute('email'));
    }

    private static function query(): QueryBuilder
    {
        return new QueryBuilder(Connection::get(), self::TABLE);
    }
}
