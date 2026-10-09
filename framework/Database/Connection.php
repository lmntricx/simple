<?php

namespace Simple\Database;

use InvalidArgumentException;
use PDO;

/**
 * Lazily opens and caches one PDO per named connection (see config/database.php).
 */
final class Connection
{
    private static array $config = ['default' => null, 'connections' => []];

    /** @var array<string, PDO> */
    private static array $pdos = [];

    public static function configure(array $config): void
    {
        self::$config = $config + self::$config;
        self::$pdos = [];
    }

    public static function get(?string $name = null): PDO
    {
        $name ??= self::$config['default'];

        return self::$pdos[$name] ??= self::open($name);
    }

    /**
     * Register an already-open PDO, e.g. an in-memory SQLite database in tests.
     */
    public static function set(string $name, PDO $pdo): void
    {
        self::$pdos[$name] = $pdo;
    }

    /**
     * Run $callback in a transaction: committed if it returns, rolled back if it throws.
     *
     *   Connection::transaction(function () use ($order) { $order->save(); ... });
     */
    public static function transaction(\Closure $callback, ?string $name = null): mixed
    {
        $pdo = self::get($name);

        // Already inside a transaction: just join it.
        if ($pdo->inTransaction()) {
            return $callback($pdo);
        }

        $pdo->beginTransaction();

        try {
            $result = $callback($pdo);
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function driver(PDO $pdo): string
    {
        return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    private static function open(?string $name): PDO
    {
        $config = self::$config['connections'][$name] ?? null;

        if (!is_array($config)) {
            throw new InvalidArgumentException("Database connection [$name] is not configured.");
        }

        $driver = $config['driver'] ?? 'mysql';

        $dsn = match ($driver) {
            'mysql' => sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $config['host'] ?? '127.0.0.1',
                $config['port'] ?? 3306,
                $config['database'] ?? '',
                $config['charset'] ?? 'utf8mb4',
            ),
            'pgsql' => sprintf(
                'pgsql:host=%s;port=%s;dbname=%s',
                $config['host'] ?? '127.0.0.1',
                $config['port'] ?? 5432,
                $config['database'] ?? '',
            ),
            'sqlite' => 'sqlite:' . ($config['database'] ?? ':memory:'),
            default => throw new InvalidArgumentException("Unsupported database driver [$driver]."),
        };

        $pdo = new PDO($dsn, $config['username'] ?? null, $config['password'] ?? null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        if ($driver === 'sqlite') {
            $pdo->exec('PRAGMA foreign_keys = ON');
        }

        return $pdo;
    }
}
