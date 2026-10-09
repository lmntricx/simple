<?php

namespace Simple\Http;

use Closure;
use LogicException;

class Router
{
    /** @var Route[] */
    protected array $routes = [];
    protected array $groupStack = [];
    protected array $aliases = [];

    public function get(string $uri, Closure|array $action): Route
    {
        return $this->add('GET', $uri, $action);
    }

    public function post(string $uri, Closure|array $action): Route
    {
        return $this->add('POST', $uri, $action);
    }

    public function put(string $uri, Closure|array $action): Route
    {
        return $this->add('PUT', $uri, $action);
    }

    public function patch(string $uri, Closure|array $action): Route
    {
        return $this->add('PATCH', $uri, $action);
    }

    public function delete(string $uri, Closure|array $action): Route
    {
        return $this->add('DELETE', $uri, $action);
    }

    /**
     * Register index/show/store/update/destroy routes for a controller.
     */
    public function resource(string $uri, string $controller): void
    {
        $uri = rtrim($uri, '/');
        $this->get($uri, [$controller, 'index']);
        $this->post($uri, [$controller, 'store']);
        $this->get("$uri/{id}", [$controller, 'show']);
        $this->put("$uri/{id}", [$controller, 'update']);
        $this->patch("$uri/{id}", [$controller, 'update']);
        $this->delete("$uri/{id}", [$controller, 'destroy']);
    }

    /**
     * Group routes under a shared prefix and/or middleware:
     *   $router->group(['prefix' => 'admin', 'middleware' => ['auth']], function (Router $router) { ... });
     */
    public function group(array $attributes, Closure $routes): void
    {
        $this->groupStack[] = $attributes;
        $routes($this);
        array_pop($this->groupStack);
    }

    public function aliasMiddleware(string $name, string $class): void
    {
        $this->aliases[$name] = $class;
    }

    public function middlewareAliases(): array
    {
        return $this->aliases;
    }

    /** @return Route[] */
    public function routes(): array
    {
        return $this->routes;
    }

    public function add(string $method, string $uri, Closure|array $action): Route
    {
        $prefix = '';
        $middleware = [];

        foreach ($this->groupStack as $group) {
            $prefix .= '/' . trim($group['prefix'] ?? '', '/');
            array_push($middleware, ...(array) ($group['middleware'] ?? []));
        }

        $full = '/' . trim(preg_replace('#/+#', '/', $prefix . '/' . trim($uri, '/')), '/');

        $route = (new Route(strtoupper($method), $full, $action))->middleware($middleware);
        $this->routes[] = $route;

        return $route;
    }

    public function dispatch(Request $request): Response
    {
        $method = $request->method() === 'HEAD' ? 'GET' : $request->method();
        $allowed = [];

        foreach ($this->routes as $route) {
            $params = $route->match($request->path());

            if ($params === null) {
                continue;
            }

            if ($route->method !== $method) {
                $allowed[] = $route->method;
                continue;
            }

            return Pipeline::run(
                $request,
                $route->getMiddleware(),
                fn (Request $request) => $this->call($route, $request, $params),
                $this->aliases,
            );
        }

        if ($allowed !== []) {
            return Response::error('Method Not Allowed', 405)
                ->header('Allow', implode(', ', array_unique($allowed)));
        }

        throw new HttpException(404);
    }

    /**
     * Controllers receive the Request first, then route parameters in order:
     *   public function show(Request $request, string $id)
     */
    protected function call(Route $route, Request $request, array $params): mixed
    {
        $action = $route->action;

        if (is_array($action)) {
            [$class, $method] = $action + [null, null];

            if (!is_string($class) || !class_exists($class)) {
                throw new LogicException("Controller [$class] not found for route {$route->method} {$route->uri}.");
            }
            if (!is_string($method) || !method_exists($class, $method)) {
                throw new LogicException("Method [$class::$method] not found for route {$route->method} {$route->uri}.");
            }

            $action = [new $class(), $method];
        }

        return $action($request, ...array_values($params));
    }
}
