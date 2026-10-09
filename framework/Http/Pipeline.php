<?php

namespace Simple\Http;

use Closure;
use InvalidArgumentException;

final class Pipeline
{
    /**
     * Send the request through each middleware, then into $destination.
     *
     * @param array<string|Middleware> $middleware class names, aliases or instances;
     *        "name:a,b" passes "a" and "b" to the middleware's constructor
     * @param array<string, string> $aliases short name => class name
     */
    public static function run(Request $request, array $middleware, Closure $destination, array $aliases = []): Response
    {
        $core = fn (Request $request): Response => Response::from($destination($request));

        $chain = array_reduce(
            array_reverse($middleware),
            fn (Closure $next, string|Middleware $item) => function (Request $request) use ($next, $item, $aliases): Response {
                return self::resolve($item, $aliases)->handle($request, $next);
            },
            $core,
        );

        return $chain($request);
    }

    private static function resolve(string|Middleware $item, array $aliases): Middleware
    {
        if ($item instanceof Middleware) {
            return $item;
        }

        [$name, $params] = array_pad(explode(':', $item, 2), 2, null);
        $class = $aliases[$name] ?? $name;

        if (!class_exists($class) || !is_subclass_of($class, Middleware::class)) {
            throw new InvalidArgumentException("Middleware [$item] does not exist or does not implement " . Middleware::class);
        }

        return new $class(...($params === null || $params === '' ? [] : explode(',', $params)));
    }
}
