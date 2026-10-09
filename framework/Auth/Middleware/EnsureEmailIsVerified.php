<?php

namespace Simple\Auth\Middleware;

use Closure;
use Simple\Auth\EmailVerification;
use Simple\Http\Middleware;
use Simple\Http\Request;
use Simple\Http\Response;

/**
 * Blocks users who haven't verified their email. Use after "auth":
 *   ->middleware('auth', 'verified')
 */
class EnsureEmailIsVerified implements Middleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return Response::error('Unauthenticated', 401)->header('WWW-Authenticate', 'Bearer');
        }

        if (!EmailVerification::isVerified($user)) {
            return Response::error('Your email address is not verified.', 403);
        }

        return $next($request);
    }
}
