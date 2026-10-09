<?php

namespace Simple\Auth\Middleware;

use Closure;
use Simple\Auth\Auth;
use Simple\Auth\PersonalAccessToken;
use Simple\Http\Middleware;
use Simple\Http\Request;
use Simple\Http\Response;

/**
 * Requires a valid "Authorization: Bearer {token}" header.
 * Use as ->middleware('auth').
 */
class Authenticate implements Middleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $plainText = $request->bearerToken();
        $token = $plainText ? PersonalAccessToken::findToken($plainText) : null;
        $user = $token && !$token->isExpired() ? $token->user() : null;

        if ($user === null) {
            return Response::error('Unauthenticated', 401)->header('WWW-Authenticate', 'Bearer');
        }

        $token->forceFill(['last_used_at' => date('Y-m-d H:i:s')])->save();

        Auth::login($user, $token);
        $request->setUser($user);

        return $next($request);
    }
}
