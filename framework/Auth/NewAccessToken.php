<?php

namespace Simple\Auth;

/**
 * Returned by createToken(). $plainTextToken is the only time the full token is
 * available — send it to the client; it can't be recovered later.
 */
final class NewAccessToken
{
    public function __construct(
        public readonly PersonalAccessToken $accessToken,
        public readonly string $plainTextToken,
    ) {
    }
}
