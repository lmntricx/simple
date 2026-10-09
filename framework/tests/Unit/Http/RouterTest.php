<?php

namespace Simple\Tests\Unit\Http;

use Closure;
use PHPUnit\Framework\TestCase;
use Simple\Http\HttpException;
use Simple\Http\Middleware;
use Simple\Http\Request;
use Simple\Http\Response;
use Simple\Http\Router;

class RouterTest extends TestCase
{
    private function dispatch(Router $router, string $method, string $path): Response
    {
        return $router->dispatch(new Request($method, $path));
    }

    public function test_matches_static_and_parameterised_routes(): void
    {
        $router = new Router();
        $router->get('/', fn () => 'home');
        $router->get('/users/{id}', fn (Request $r, string $id) => "user $id");
        $router->get('/posts/{post}/comments/{comment}', fn (Request $r, string $post, string $comment) => "$post/$comment");

        $this->assertSame('home', $this->dispatch($router, 'GET', '/')->content());
        $this->assertSame('user 42', $this->dispatch($router, 'GET', '/users/42')->content());
        $this->assertSame('7/9', $this->dispatch($router, 'GET', '/posts/7/comments/9')->content());
        $this->assertSame('user 42', $this->dispatch($router, 'GET', '/users/42/')->content(), 'trailing slash');
    }

    public function test_optional_parameters(): void
    {
        $router = new Router();
        $router->get('/archive/{year?}', fn (Request $r, ?string $year = null) => $year ?? 'all');

        $this->assertSame('all', $this->dispatch($router, 'GET', '/archive')->content());
        $this->assertSame('2026', $this->dispatch($router, 'GET', '/archive/2026')->content());
    }

    public function test_parameters_do_not_match_across_slashes(): void
    {
        $router = new Router();
        $router->get('/users/{id}', fn () => 'x');

        $this->expectException(HttpException::class);
        $this->dispatch($router, 'GET', '/users/1/extra');
    }

    public function test_404_and_405(): void
    {
        $router = new Router();
        $router->get('/users', fn () => 'x');
        $router->post('/users', fn () => 'x');

        $response = $this->dispatch($router, 'DELETE', '/users');
        $this->assertSame(405, $response->status());
        $this->assertSame('GET, POST', $response->headers()['Allow']);

        try {
            $this->dispatch($router, 'GET', '/nope');
            $this->fail('expected 404');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->status);
        }
    }

    public function test_head_is_served_by_get_routes(): void
    {
        $router = new Router();
        $router->get('/ping', fn () => 'pong');
        $this->assertSame(200, $this->dispatch($router, 'HEAD', '/ping')->status());
    }

    public function test_groups_combine_prefixes_and_middleware(): void
    {
        $router = new Router();
        $router->group(['prefix' => 'api', 'middleware' => ['a']], function (Router $r) {
            $r->group(['prefix' => '/v1/', 'middleware' => ['b']], function (Router $r) {
                $r->get('/users', fn () => 'x')->middleware('c');
            });
        });

        $route = $router->routes()[0];
        $this->assertSame('/api/v1/users', $route->uri);
        $this->assertSame(['a', 'b', 'c'], $route->getMiddleware());
    }

    public function test_resource_routes(): void
    {
        $router = new Router();
        $router->resource('photos', \stdClass::class);

        $routes = array_map(fn ($r) => $r->method . ' ' . $r->uri . ' ' . $r->action[1], $router->routes());
        $this->assertSame([
            'GET /photos index',
            'POST /photos store',
            'GET /photos/{id} show',
            'PUT /photos/{id} update',
            'PATCH /photos/{id} update',
            'DELETE /photos/{id} destroy',
        ], $routes);
    }

    public function test_middleware_runs_in_order_and_can_short_circuit(): void
    {
        $router = new Router();
        $router->aliasMiddleware('tag', TagMiddleware::class);
        $router->aliasMiddleware('block', BlockMiddleware::class);
        $router->get('/ok', fn (Request $r) => implode(',', $r->attribute('tags', [])))->middleware('tag:one', 'tag:two');
        $router->get('/blocked', fn () => 'unreachable')->middleware('block');

        $this->assertSame('one,two', $this->dispatch($router, 'GET', '/ok')->content());
        $this->assertSame(403, $this->dispatch($router, 'GET', '/blocked')->status());
    }

    public function test_array_and_model_return_values_become_json(): void
    {
        $router = new Router();
        $router->get('/array', fn () => ['a' => 1]);
        $router->get('/null', fn () => null);

        $array = $this->dispatch($router, 'GET', '/array');
        $this->assertSame('{"a":1}', $array->content());
        $this->assertStringStartsWith('application/json', $array->headers()['Content-Type']);
        $this->assertSame(204, $this->dispatch($router, 'GET', '/null')->status());
    }
}

class TagMiddleware implements Middleware
{
    public function __construct(private string $tag)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $request->setAttribute('tags', [...$request->attribute('tags', []), $this->tag]);
        return $next($request);
    }
}

class BlockMiddleware implements Middleware
{
    public function handle(Request $request, Closure $next): Response
    {
        return Response::error('Blocked', 403);
    }
}
