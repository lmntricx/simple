<?php

namespace Simple\Http\Middleware;

use Closure;
use Simple\Http\Middleware;
use Simple\Http\Request;
use Simple\Http\Response;

/**
 * Adds CORS headers and answers preflight requests. Configure in config/cors.php.
 */
class Cors implements Middleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $request->isMethod('OPTIONS') && $request->header('access-control-request-method')
            ? Response::noContent()
            : $next($request);

        $origin = $request->header('origin');
        if ($origin === null) {
            return $response;
        }

        $allowed = (array) config('cors.allowed_origins', ['*']);
        $credentials = (bool) config('cors.supports_credentials', false);

        if (in_array('*', $allowed, true) && !$credentials) {
            $response->header('Access-Control-Allow-Origin', '*');
        } elseif (in_array($origin, $allowed, true) || in_array('*', $allowed, true)) {
            // Browsers reject "*" together with credentials, so echo the origin back.
            $response->header('Access-Control-Allow-Origin', $origin)->header('Vary', 'Origin');
        } else {
            return $response;
        }

        if ($credentials) {
            $response->header('Access-Control-Allow-Credentials', 'true');
        }

        return $response
            ->header('Access-Control-Allow-Methods', implode(', ', (array) config('cors.allowed_methods', [])))
            ->header('Access-Control-Allow-Headers', implode(', ', (array) config('cors.allowed_headers', [])))
            ->header('Access-Control-Max-Age', (string) config('cors.max_age', 0));
    }
}
