<?php

namespace Simple\Auth;

/**
 * Password hashing (bcrypt). The cost comes from auth.bcrypt_rounds
 * (BCRYPT_ROUNDS, default 12); tests lower it to stay fast.
 */
final class Hash
{
    public static function make(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => self::rounds()]);
    }

    public static function check(string $password, ?string $hash): bool
    {
        return $hash !== null && $hash !== '' && password_verify($password, $hash);
    }

    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => self::rounds()]);
    }

    public static function isHashed(string $value): bool
    {
        return password_get_info($value)['algo'] !== null;
    }

    /**
     * A well-formed bcrypt hash at the configured cost that matches no password.
     * Verifying against it takes as long as verifying a real one.
     */
    public static function dummy(): string
    {
        return sprintf('$2y$%02d$HCvEswn.V/ROBTLjEGPCAOr0A1wfJndYXMRObVa7hRpJ9JLRXqhOq', self::rounds());
    }

    private static function rounds(): int
    {
        return max(4, min(31, (int) config('auth.bcrypt_rounds', 12)));
    }
}
