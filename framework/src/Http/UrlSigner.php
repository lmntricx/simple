<?php

namespace Simple\Http;

/**
 * Tamper-proof links: the query string carries an HMAC (keyed with APP_KEY)
 * of the path and parameters, and optionally an expiry time.
 *
 *   $url = UrlSigner::sign('/invites/42', ['role' => 'editor'], time() + 3600);
 *   UrlSigner::isValid($request);   // in the controller that handles the link
 */
final class UrlSigner
{
    /**
     * @param string $path app-relative path, including any route prefix
     */
    public static function sign(string $path, array $params = [], ?int $expiresAt = null): string
    {
        $path = '/' . trim($path, '/');

        if ($expiresAt !== null) {
            $params['expires'] = $expiresAt;
        }

        unset($params['signature']);
        ksort($params);
        $params['signature'] = self::signature($path, $params);

        return rtrim((string) config('app.url'), '/') . $path . '?' . http_build_query($params);
    }

    public static function isValid(Request $request): bool
    {
        $params = $request->query();
        $signature = $params['signature'] ?? null;
        unset($params['signature']);
        ksort($params);

        if (!is_string($signature) || !hash_equals(self::signature($request->path(), $params), $signature)) {
            return false;
        }

        return !isset($params['expires']) || (int) $params['expires'] >= time();
    }

    private static function signature(string $path, array $params): string
    {
        return hash_hmac('sha256', $path . '?' . http_build_query($params), app()->key());
    }
}
