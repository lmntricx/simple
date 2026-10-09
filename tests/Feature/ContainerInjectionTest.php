<?php

namespace Tests\Feature;

use Closure;
use Simple\Http\Middleware;
use Simple\Http\Request;
use Simple\Http\Response;
use Simple\Testing\TestCase;

class Greeter
{
    public function __construct(public string $greeting = 'Hello')
    {
    }
}

class GreetingController
{
    public function __construct(private Greeter $greeter)
    {
    }

    public function show(Request $request, string $name): Response
    {
        return Response::success("{$this->greeter->greeting}, $name");
    }

    public function withService(string $name, Greeter $greeter, Request $request): Response
    {
        return Response::success("{$greeter->greeting} via method, $name ({$request->method()})");
    }
}

class GreeterHeaderMiddleware implements Middleware
{
    public function __construct(private Greeter $greeter, private string $suffix = '')
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request)->header('X-Greeting', $this->greeter->greeting . $this->suffix);
    }
}

class ContainerInjectionTest extends TestCase
{
    protected bool $migrate = false;

    public function test_controllers_get_constructor_dependencies(): void
    {
        $this->app->singleton(Greeter::class, fn () => new Greeter('Howdy'));
        $this->app->router->get('/greet/{name}', [GreetingController::class, 'show']);

        $this->getJson('/greet/ada')->assertOk()->assertJsonPath('data', 'Howdy, ada');
    }

    public function test_actions_get_method_injection_in_any_order(): void
    {
        $this->app->router->get('/greet2/{name}', [GreetingController::class, 'withService']);

        $this->getJson('/greet2/bob')->assertJsonPath('data', 'Hello via method, bob (GET)');
    }

    public function test_middleware_get_dependencies_and_string_parameters(): void
    {
        $this->app->singleton(Greeter::class, fn () => new Greeter('Hi'));
        $this->app->router->get('/mw', fn () => 'ok')->middleware(GreeterHeaderMiddleware::class . ':!');

        $this->getJson('/mw')->assertHeader('X-Greeting', 'Hi!');
    }

    public function test_app_helper_and_current_request(): void
    {
        $this->assertSame($this->app, app());
        $this->assertInstanceOf(Greeter::class, app(Greeter::class));

        $this->app->router->get('/req', fn () => app(Request::class)->path());
        $this->getJson('/req')->assertOk();
        $this->assertSame('/req', app(Request::class)->path());
    }

    public function test_service_providers_are_registered_from_config(): void
    {
        $this->assertSame(
            [\App\Providers\AppServiceProvider::class],
            $this->app->config->get('app.providers'),
        );
    }
}
