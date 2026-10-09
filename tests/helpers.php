<?php

/**
 * Minimal test runner shared by the test files. Boots the app on an in-memory
 * SQLite database.
 */

putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE_PATH=:memory:');
putenv('APP_KEY=base64:' . base64_encode(str_repeat('k', 32)));
putenv('APP_URL=http://simple.test');
putenv('MAIL_DRIVER=array');

$app = require __DIR__ . '/../bootstrap/app.php';
$mail = Simple\Mail\Mail::fake();
restore_error_handler();
set_error_handler(function ($level, $message, $file, $line) {
    if (!(error_reporting() & $level)) {
        return false; // silenced with @
    }
    throw new ErrorException($message, 0, $level, $file, $line);
});

$passed = $failed = 0;

function test(string $name, Closure $fn): void
{
    global $passed, $failed;
    try {
        $fn();
        $passed++;
        echo "  \033[32m✓\033[0m $name\n";
    } catch (Throwable $e) {
        $failed++;
        echo "  \033[31m✗ $name\033[0m\n      " . $e::class . ': ' . $e->getMessage() . "\n";
    }
}

function eq(mixed $expected, mixed $actual, string $msg = ''): void
{
    if ($expected !== $actual) {
        throw new Exception(($msg ? "$msg: " : '') . 'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function throws(string $class, Closure $fn): void
{
    try {
        $fn();
    } catch (Throwable $e) {
        if ($e instanceof $class) {
            return;
        }
        throw new Exception("expected $class, got " . $e::class . ': ' . $e->getMessage());
    }
    throw new Exception("expected $class, nothing thrown");
}

function section(string $name): void
{
    echo "\n$name\n";
}

function finish(): never
{
    global $passed, $failed;
    echo "\n" . ($failed ? "\033[31m" : "\033[32m") . "$passed passed, $failed failed\033[0m\n";
    exit($failed ? 1 : 0);
}

return $app;
