<?php

namespace Simple\Auth;

use Simple\Database\Model;
use Simple\Http\Request;
use Simple\Http\UrlSigner;

/**
 * Signed, expiring verification links. The link embeds a hash of the email
 * address, so it stops working if the user changes their email.
 *
 * The user model needs a nullable "email_verified_at" column.
 */
final class EmailVerification
{
    /**
     * @param string $routePath the verify route without its parameters, e.g. "/auth/verify-email"
     *                          (it must be defined as "{routePath}/{id}/{hash}")
     */
    public static function url(Model $user, string $routePath = '/auth/verify-email'): string
    {
        $prefix = trim((string) config('app.route_prefix', ''), '/');
        $path = '/' . trim($prefix . '/' . trim($routePath, '/'), '/')
            . '/' . $user->getKey() . '/' . self::emailHash($user);

        $minutes = (int) config('auth.verification_expire', 60);

        return UrlSigner::sign($path, [], time() + $minutes * 60);
    }

    /**
     * Validate a clicked link and mark the user verified.
     * Returns the user, or null if the link is invalid or expired.
     */
    public static function verify(Request $request, string $id, string $hash): ?Model
    {
        if (!UrlSigner::isValid($request)) {
            return null;
        }

        /** @var class-string<Model> $model */
        $model = config('auth.model');
        $user = $model::find($id);

        if ($user === null || !hash_equals(self::emailHash($user), $hash)) {
            return null;
        }

        if (!self::isVerified($user)) {
            $user->forceFill(['email_verified_at' => date('Y-m-d H:i:s')])->save();
        }

        return $user;
    }

    public static function isVerified(Model $user): bool
    {
        return $user->getAttribute('email_verified_at') !== null;
    }

    private static function emailHash(Model $user): string
    {
        return hash('sha256', strtolower((string) $user->getAttribute('email')));
    }
}
