<?php

namespace Simple\Http;

use Closure;

interface Middleware
{
    /**
     * Return $next($request) to continue, or a Response to stop early.
     */
    public function handle(Request $request, Closure $next): Response;
}
