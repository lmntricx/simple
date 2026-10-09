<?php

namespace Simple\Http;

use Closure;

class Route
{
    protected array $middleware = [];
    protected ?string $regex = null;

    public function __construct(
        public readonly string $method,
        public readonly string $uri,
        public readonly Closure|array $action,
    ) {
    }

    public function middleware(string|array ...$middleware): static
    {
        foreach ($middleware as $item) {
            array_push($this->middleware, ...(array) $item);
        }
        return $this;
    }

    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /**
     * Returns the route parameters if the path matches, or null.
     * "{id}" matches one segment; "{id?}" makes a trailing segment optional.
     */
    public function match(string $path): ?array
    {
        if ($this->regex === null) {
            $pattern = preg_replace_callback(
                '#/\{([a-zA-Z_][a-zA-Z0-9_]*)(\?)?\}#',
                fn ($m) => isset($m[2]) ? "(?:/(?P<{$m[1]}>[^/]+))?" : "/(?P<{$m[1]}>[^/]+)",
                $this->uri,
            );
            $this->regex = '#^' . $pattern . '$#';
        }

        if (!preg_match($this->regex, $path, $matches)) {
            return null;
        }

        return array_filter($matches, fn ($value, $key) => is_string($key) && $value !== '', ARRAY_FILTER_USE_BOTH);
    }
}
