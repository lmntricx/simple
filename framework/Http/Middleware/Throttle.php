<?php

namespace Simple\Http\Middleware;

use Closure;
use Simple\Http\Middleware;
use Simple\Http\Request;
use Simple\Http\Response;
use Simple\Support\RateLimiter;

/**
 * Limit requests per client (user if authenticated, otherwise IP) per route.
 * ->middleware('throttle:5,1') = 5 requests per 1 minute.
 */
class Throttle implements Middleware
{
    public function __construct(private int $maxAttempts = 60, private int $decayMinutes = 1)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $hit = RateLimiter::hit(self::key($request), $this->decayMinutes * 60);
        $remaining = max(0, $this->maxAttempts - $hit['count']);

        if ($hit['count'] > $this->maxAttempts) {
            return Response::error('Too Many Requests', 429)
                ->header('Retry-After', (string) max(1, $hit['reset_at'] - time()))
                ->header('X-RateLimit-Limit', (string) $this->maxAttempts)
                ->header('X-RateLimit-Remaining', '0');
        }

        return $next($request)
            ->header('X-RateLimit-Limit', (string) $this->maxAttempts)
            ->header('X-RateLimit-Remaining', (string) $remaining);
    }

    public static function key(Request $request): string
    {
        $client = $request->user()?->getKey() ?? $request->ip() ?? 'unknown';

        return implode('|', ['throttle', $client, $request->method(), $request->path()]);
    }
}
