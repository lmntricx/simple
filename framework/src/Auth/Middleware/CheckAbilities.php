<?php

namespace Simple\Auth\Middleware;

use Closure;
use Simple\Auth\Auth;
use Simple\Http\Middleware;
use Simple\Http\Request;
use Simple\Http\Response;

/**
 * Requires the current token to have every listed ability.
 * Use after "auth": ->middleware('auth', 'ability:posts:write,posts:delete')
 */
class CheckAbilities implements Middleware
{
    private array $abilities;

    public function __construct(string ...$abilities)
    {
        $this->abilities = $abilities;
    }

    public function handle(Request $request, Closure $next): Response
    {
        $token = Auth::token();

        if ($token === null) {
            return Response::error('Unauthenticated', 401)->header('WWW-Authenticate', 'Bearer');
        }

        foreach ($this->abilities as $ability) {
            if (!$token->can($ability)) {
                return Response::error("This token is missing the [$ability] ability.", 403);
            }
        }

        return $next($request);
    }
}
