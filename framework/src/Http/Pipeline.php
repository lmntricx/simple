<?php

namespace Simple\Http;

use Closure;
use InvalidArgumentException;
use Simple\Container\Container;

final class Pipeline
{
    /**
     * Send the request through each middleware, then into $destination.
     *
     * @param array<string|Middleware> $middleware class names, aliases or instances;
     *        "name:a,b" passes "a" and "b" to the middleware's constructor
     * @param array<string, string> $aliases short name => class name
     * @param Container|null $container builds middleware (so they can have dependencies)
     */
    public static function run(
        Request $request,
        array $middleware,
        Closure $destination,
        array $aliases = [],
        ?Container $container = null,
    ): Response {
        $core = fn (Request $request): Response => Response::from($destination($request));

        $chain = array_reduce(
            array_reverse($middleware),
            fn (Closure $next, string|Middleware $item) => function (Request $request) use ($next, $item, $aliases, $container): Response {
                return self::resolve($item, $aliases, $container)->handle($request, $next);
            },
            $core,
        );

        return $chain($request);
    }

    private static function resolve(string|Middleware $item, array $aliases, ?Container $container): Middleware
    {
        if ($item instanceof Middleware) {
            return $item;
        }

        [$name, $params] = array_pad(explode(':', $item, 2), 2, null);
        $class = $aliases[$name] ?? $name;

        if (!class_exists($class) || !is_subclass_of($class, Middleware::class)) {
            throw new InvalidArgumentException("Middleware [$item] does not exist or does not implement " . Middleware::class);
        }

        $params = $params === null || $params === '' ? [] : explode(',', $params);

        return ($container ?? new Container())->make($class, $params);
    }
}
