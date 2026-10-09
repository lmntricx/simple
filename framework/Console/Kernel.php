<?php

namespace Simple\Console;

use Simple\Application;
use Simple\Auth\PasswordBroker;
use Simple\Auth\PersonalAccessToken;
use Simple\Database\Schema\Schema;
use Simple\Database\Migrator;
use Simple\Support\Str;

/**
 * php simple <command> [arguments]
 */
class Kernel
{
    use QueueCommands;
    use ScheduleCommands;

    private const COMMANDS = [
        'serve' => 'Start the PHP development server  [--port=8000]',
        'routes' => 'List registered routes',
        'migrate' => 'Run pending migrations',
        'migrate:rollback' => 'Undo the last batch of migrations',
        'migrate:status' => 'Show which migrations have run',
        'db:seed' => 'Run database/seeders/DatabaseSeeder  [--class=Name]',
        'db:fresh' => 'Drop all tables, re-run migrations [--seed] [--force in production]',
        'queue:work' => 'Process queued jobs   [--queue=high,default] [--once] [--stop-when-empty] [--max-jobs=N] [--max-time=S]',
        'queue:failed' => 'List failed jobs',
        'queue:retry' => 'Retry a failed job                <id|all>',
        'queue:forget' => 'Delete a failed job               <id>',
        'queue:flush' => 'Delete all failed jobs',
        'queue:clear' => 'Delete pending jobs on a queue    [--queue=default]',
        'schedule:run' => 'Run due scheduled tasks (call every minute from cron)',
        'schedule:list' => 'List scheduled tasks and their next run',
        'schedule:work' => 'Run the scheduler in the foreground (development)',
        'auth:prune' => 'Delete expired API tokens and password reset tokens',
        'key:generate' => 'Set APP_KEY in .env              [--show to print only, --force to replace]',
        'make:migration' => 'Create a migration            <name>, e.g. create_posts_table',
        'make:controller' => 'Create a controller           <Name>',
        'make:model' => 'Create a model                <Name> [-m to add a migration]',
        'make:middleware' => 'Create a middleware           <Name>',
        'make:factory' => 'Create a model factory        <Model>, e.g. Post',
        'make:seeder' => 'Create a seeder               <Name>, e.g. PostSeeder',
        'make:job' => 'Create a queued job           <Name>, e.g. SendInvoice',
    ];

    /** @var resource */
    private $out;
    /** @var resource */
    private $err;

    /**
     * @param resource|null $out where output goes (default STDOUT); errors go there too when given
     */
    public function __construct(private Application $app, $out = null)
    {
        $this->out = $out ?? STDOUT;
        $this->err = $out ?? STDERR;
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
            'db:seed' => $this->seed($options),
            'db:fresh' => $this->fresh($options),
            'queue:work' => $this->queueWork($options),
            'queue:failed' => $this->queueFailed(),
            'queue:retry' => $this->queueRetry($args[0] ?? null),
            'queue:forget' => $this->queueForget($args[0] ?? null),
            'queue:flush' => $this->queueFlush(),
            'queue:clear' => $this->queueClear($options),
            'schedule:run' => $this->scheduleRun(),
            'schedule:list' => $this->scheduleList(),
            'schedule:work' => $this->scheduleWork(),
            'auth:prune' => $this->pruneTokens(),
            'key:generate' => $this->generateKey($options),
            'make:migration' => $this->makeMigration($args[0] ?? null),
            'make:controller' => $this->make('controller', $args[0] ?? null),
            'make:model' => $this->makeModel($args[0] ?? null, in_array('-m', $options, true)),
            'make:middleware' => $this->make('middleware', $args[0] ?? null),
            'make:factory' => $this->makeFactory($args[0] ?? null),
            'make:seeder' => $this->make('seeder', $args[0] ?? null),
            'make:job' => $this->make('job', $args[0] ?? null),
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

        $this->line("Deleted $deleted expired API token(s).");

        if (Schema::hasTable('password_reset_tokens')) {
            $this->line('Deleted ' . PasswordBroker::prune() . ' expired password reset token(s).');
        }

        return 0;
    }

    private function generateKey(array $options): int
    {
        $key = 'base64:' . base64_encode(random_bytes(32));

        if (in_array('--show', $options, true)) {
            $this->line($key);
            return 0;
        }

        $file = $this->app->basePath . '/.env';
        $env = is_file($file) ? file_get_contents($file) : '';

        if (preg_match('/^APP_KEY=(.+)$/m', $env, $m) && trim($m[1]) !== '' && !in_array('--force', $options, true)) {
            return $this->fail('APP_KEY is already set. Changing it invalidates every signed link already sent '
                . '(e.g. email verification). Use --force to replace it.');
        }

        $env = preg_match('/^APP_KEY=.*$/m', $env)
            ? preg_replace('/^APP_KEY=.*$/m', 'APP_KEY=' . $key, $env)
            : rtrim($env, "\r\n") . ($env === '' ? '' : PHP_EOL) . 'APP_KEY=' . $key . PHP_EOL;

        file_put_contents($file, $env);
        $this->line('APP_KEY set in .env');

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

        $folder = [
            'controller' => 'app/Controllers',
            'model' => 'app/Models',
            'middleware' => 'app/Middleware',
            'seeder' => 'database/seeders',
            'job' => 'app/Jobs',
        ][$type];
        $path = "{$this->app->basePath}/$folder/$name.php";

        return $this->write($path, $this->stub($type, ['{{class}}' => $name]));
    }

    private function makeFactory(?string $model): int
    {
        if (!$model || !preg_match('/^[A-Z][A-Za-z0-9]*$/', $model)) {
            return $this->fail('Usage: php simple make:factory <Model>  (e.g. Post)');
        }

        $path = "{$this->app->basePath}/database/factories/{$model}Factory.php";

        return $this->write($path, $this->stub('factory', ['{{model}}' => $model]));
    }

    private function seed(array $options): int
    {
        $class = 'DatabaseSeeder';
        foreach ($options as $option) {
            if (str_starts_with($option, '--class=')) {
                $class = substr($option, 8);
            }
        }

        $class = str_contains($class, '\\') ? $class : "Database\\Seeders\\$class";
        if (!class_exists($class)) {
            return $this->fail("Seeder [$class] not found.");
        }

        $this->app->make($class)->run();
        $this->line("Seeded: $class");

        return 0;
    }

    private function fresh(array $options): int
    {
        if ($this->app->config->get('app.env') === 'production' && !in_array('--force', $options, true)) {
            return $this->fail('db:fresh deletes all data. Refusing in production without --force.');
        }

        Schema::dropAllTables();
        $this->line('Dropped all tables.');

        $code = $this->migrate();

        return $code === 0 && in_array('--seed', $options, true) ? $this->seed($options) : $code;
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

    /**
     * Value of "--name=value" in $options, or null.
     */
    private function option(array $options, string $name): ?string
    {
        foreach ($options as $option) {
            if (str_starts_with($option, "--$name=")) {
                return substr($option, strlen($name) + 3);
            }
        }
        return null;
    }

    private function line(string $text): void
    {
        fwrite($this->out, $text . PHP_EOL);
    }

    private function fail(string $message): int
    {
        fwrite($this->err, $message . PHP_EOL);
        return 1;
    }
}
