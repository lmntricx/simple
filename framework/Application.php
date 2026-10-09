<?php

namespace Simple;

use ErrorException;
use LogicException;
use Simple\Auth\Auth;
use Simple\Database\Connection;
use Simple\Http\HttpException;
use Simple\Http\Pipeline;
use Simple\Http\Request;
use Simple\Http\Response;
use Simple\Http\Router;
use Simple\Http\TrustedProxies;
use Simple\Support\Config;
use Simple\Support\Env;
use Simple\Support\Log;
use Throwable;

class Application
{
    public const VERSION = '2.0.0';

    private static ?self $instance = null;

    public readonly Config $config;
    public readonly Router $router;

    /** @var \Closure[] */
    private array $deferred = [];

    public function __construct(public readonly string $basePath)
    {
        static::$instance = $this;

        // Tests set APP_ENV=testing (phpunit.xml) and must not pick up your real .env.
        if (getenv('APP_ENV') !== 'testing') {
            Env::load($basePath . '/.env');
        }
        $this->config = Config::fromDirectory($basePath . '/config');

        date_default_timezone_set($this->config->get('app.timezone', 'UTC'));
        Log::useFile($basePath . '/storage/logs/simple.log');
        Connection::configure($this->config->get('database', []));
        TrustedProxies::set($this->config->get('app.trusted_proxies', []));

        $this->router = new Router();
        foreach ($this->config->get('app.middleware_aliases', []) as $name => $class) {
            $this->router->aliasMiddleware($name, $class);
        }

        $this->registerErrorHandling();
    }

    public static function getInstance(): self
    {
        return static::$instance ?? throw new LogicException('The application has not been created yet.');
    }

    public function debug(): bool
    {
        return (bool) $this->config->get('app.debug', false);
    }

    /**
     * Load a routes file that returns function (Router $router) { ... }.
     */
    public function loadRoutes(string $file, array $groupAttributes = []): static
    {
        $routes = require $file;
        $this->router->group($groupAttributes, $routes);
        return $this;
    }

    public function handle(Request $request): Response
    {
        Auth::forget();

        try {
            return Pipeline::run(
                $request,
                $this->config->get('app.middleware', []),
                function (Request $request): Response {
                    // Caught here so error responses still pass back through global middleware (CORS).
                    try {
                        return $this->router->dispatch($request);
                    } catch (Throwable $e) {
                        return $this->renderException($e);
                    }
                },
                $this->router->middlewareAliases(),
            );
        } catch (Throwable $e) {
            return $this->renderException($e);
        }
    }

    public function run(): void
    {
        $this->handle(Request::capture())->send();

        // Hand the response to the client before running deferred work (PHP-FPM).
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }

        $this->terminate();
    }

    /**
     * Run $callback after the response has been sent, e.g. sending email.
     * This keeps responses fast, and keeps their timing from revealing what
     * the callback did (such as whether an account exists).
     */
    public function defer(\Closure $callback): void
    {
        $this->deferred[] = $callback;
    }

    /**
     * Run deferred callbacks. Called by run(); call it yourself after handle()
     * in tests or long-running workers.
     */
    public function terminate(): void
    {
        while ($callback = array_shift($this->deferred)) {
            try {
                $callback();
            } catch (Throwable $e) {
                Log::exception($e);
            }
        }
    }

    /**
     * The binary APP_KEY used to sign URLs. Generate one with `php simple key:generate`.
     */
    public function key(): string
    {
        $key = (string) $this->config->get('app.key', '');

        if ($key === '') {
            throw new \RuntimeException('APP_KEY is not set. Run `php simple key:generate`.');
        }

        return str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : $key;
    }

    public function renderException(Throwable $e): Response
    {
        if ($e instanceof HttpException) {
            return Response::error($e->getMessage(), $e->status, $e->errors);
        }

        Log::exception($e);

        if (!$this->debug()) {
            return Response::error('Server Error', 500);
        }

        return Response::error($e->getMessage(), 500, [
            'exception' => $e::class,
            'file' => $e->getFile() . ':' . $e->getLine(),
            'trace' => array_slice(explode("\n", $e->getTraceAsString()), 0, 15),
        ]);
    }

    private function registerErrorHandling(): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', '0');

        // Turn warnings/notices into exceptions so they end up in the JSON error response.
        set_error_handler(function (int $level, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $level)) {
                return false;
            }
            throw new ErrorException($message, 0, $level, $file, $line);
        });

        // Last resort for anything thrown outside handle() (e.g. while booting).
        set_exception_handler(function (Throwable $e): void {
            if (PHP_SAPI === 'cli') {
                Log::exception($e);
                fwrite(STDERR, $e::class . ': ' . $e->getMessage() . PHP_EOL);
                exit(1);
            }
            $this->renderException($e)->send();
        });
    }
}
