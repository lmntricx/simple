<?php

namespace Simple\Console;

use Simple\Application;
use Simple\Auth\PersonalAccessToken;
use Simple\Database\Migrator;
use Simple\Support\Str;

/**
 * php simple <command> [arguments]
 */
class Kernel
{
    private const COMMANDS = [
        'serve' => 'Start the PHP development server  [--port=8000]',
        'routes' => 'List registered routes',
        'migrate' => 'Run pending migrations',
        'migrate:rollback' => 'Undo the last batch of migrations',
        'migrate:status' => 'Show which migrations have run',
        'auth:prune' => 'Delete expired API tokens',
        'make:migration' => 'Create a migration            <name>, e.g. create_posts_table',
        'make:controller' => 'Create a controller           <Name>',
        'make:model' => 'Create a model                <Name> [-m to add a migration]',
        'make:middleware' => 'Create a middleware           <Name>',
    ];

    public function __construct(private Application $app)
    {
    }

    public function handle(array $argv): int
    {
        $command = $argv[1] ?? 'help';
        $args = array_values(array_filter(array_slice($argv, 2), fn ($a) => !str_starts_with($a, '-')));
        $options = array_values(array_filter(array_slice($argv, 2), fn ($a) => str_starts_with($a, '-')));

        return match ($command) {
            'serve' => $this->serve($options),
            'routes' => $this->routes(),
            'migrate' => $this->migrate(),
            'migrate:rollback' => $this->rollback(),
            'migrate:status' => $this->status(),
            'auth:prune' => $this->pruneTokens(),
            'make:migration' => $this->makeMigration($args[0] ?? null),
            'make:controller' => $this->make('controller', $args[0] ?? null),
            'make:model' => $this->makeModel($args[0] ?? null, in_array('-m', $options, true)),
            'make:middleware' => $this->make('middleware', $args[0] ?? null),
            'help', '--help', '-h', 'list' => $this->help(),
            default => $this->fail("Unknown command [$command]. Run `php simple help`."),
        };
    }

    private function help(): int
    {
        $this->line('Simple Framework ' . Application::VERSION);
        $this->line('');
        $this->line('Usage: php simple <command> [arguments]');
        $this->line('');
        foreach (self::COMMANDS as $name => $description) {
            $this->line(sprintf('  %-18s %s', $name, $description));
        }
        return 0;
    }

    private function serve(array $options): int
    {
        $port = 8000;
        foreach ($options as $option) {
            if (str_starts_with($option, '--port=')) {
                $port = (int) substr($option, 7);
            }
        }

        $public = $this->app->basePath . '/public';
        $this->line("Simple development server started: http://127.0.0.1:$port");
        passthru(sprintf(
            '%s -S 127.0.0.1:%d -t %s %s',
            escapeshellarg(PHP_BINARY),
            $port,
            escapeshellarg($public),
            escapeshellarg($public . '/index.php'),
        ), $code);

        return $code;
    }

    private function routes(): int
    {
        foreach ($this->app->router->routes() as $route) {
            $action = is_array($route->action) ? implode('@', $route->action) : 'Closure';
            $middleware = $route->getMiddleware() ? ' [' . implode(', ', $route->getMiddleware()) . ']' : '';
            $this->line(sprintf('  %-7s %-30s %s%s', $route->method, $route->uri, $action, $middleware));
        }
        return 0;
    }

    private function migrate(): int
    {
        $ran = $this->migrator()->migrate();
        $this->line($ran ? 'Migrated:' : 'Nothing to migrate.');
        foreach ($ran as $name) {
            $this->line("  $name");
        }
        return 0;
    }

    private function rollback(): int
    {
        $rolledBack = $this->migrator()->rollback();
        $this->line($rolledBack ? 'Rolled back:' : 'Nothing to roll back.');
        foreach ($rolledBack as $name) {
            $this->line("  $name");
        }
        return 0;
    }

    private function status(): int
    {
        foreach ($this->migrator()->status() as $name => $batch) {
            $this->line(sprintf('  %-8s %s', $batch === null ? 'Pending' : "Ran ($batch)", $name));
        }
        return 0;
    }

    private function pruneTokens(): int
    {
        $now = date('Y-m-d H:i:s');
        $deleted = PersonalAccessToken::query()->whereNotNull('expires_at')->where('expires_at', '<=', $now)->delete();

        $minutes = config('auth.expiration');
        if ($minutes) {
            $cutoff = date('Y-m-d H:i:s', time() - (int) $minutes * 60);
            $deleted += PersonalAccessToken::query()->where('created_at', '<=', $cutoff)->delete();
        }

        $this->line("Deleted $deleted expired token(s).");
        return 0;
    }

    private function migrator(): Migrator
    {
        return new Migrator($this->app->basePath . '/database/migrations');
    }

    private function makeMigration(?string $name, ?string $table = null): int
    {
        if (!$name) {
            return $this->fail('Usage: php simple make:migration create_posts_table');
        }

        $name = Str::snake($name);
        $path = $this->app->basePath . '/database/migrations/' . date('Y_m_d_His') . "_$name.php";

        // create_posts_table => Schema::create; add_slug_to_posts(_table) => Schema::table
        if ($table !== null || preg_match('/^create_(\w+?)_table$/', $name, $m)) {
            return $this->write($path, $this->stub('migration', ['{{table}}' => $table ?? $m[1]]));
        }

        $table = preg_match('/_(?:to|from|in|on)_(\w+?)(?:_table)?$/', $name, $m) ? $m[1] : 'table_name';

        return $this->write($path, $this->stub('migration-alter', ['{{table}}' => $table]));
    }

    private function makeModel(?string $name, bool $withMigration): int
    {
        $code = $this->make('model', $name);

        if ($code === 0 && $withMigration) {
            $table = Str::plural(Str::snake($name));
            $code = $this->makeMigration("create_{$table}_table", $table);
        }

        return $code;
    }

    private function make(string $type, ?string $name): int
    {
        if (!$name || !preg_match('/^[A-Z][A-Za-z0-9]*$/', $name)) {
            return $this->fail("Usage: php simple make:$type <Name>  (StudlyCase, e.g. Post)");
        }

        $folder = ['controller' => 'Controllers', 'model' => 'Models', 'middleware' => 'Middleware'][$type];
        $path = "{$this->app->basePath}/app/$folder/$name.php";

        return $this->write($path, $this->stub($type, ['{{class}}' => $name]));
    }

    private function stub(string $name, array $replace): string
    {
        return strtr(file_get_contents(__DIR__ . "/stubs/$name.stub"), $replace);
    }

    private function write(string $path, string $contents): int
    {
        if (file_exists($path)) {
            return $this->fail("Already exists: $path");
        }

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }

        file_put_contents($path, $contents);
        $this->line('Created: ' . substr($path, strlen($this->app->basePath) + 1));

        return 0;
    }

    private function line(string $text): void
    {
        fwrite(STDOUT, $text . PHP_EOL);
    }

    private function fail(string $message): int
    {
        fwrite(STDERR, $message . PHP_EOL);
        return 1;
    }
}
