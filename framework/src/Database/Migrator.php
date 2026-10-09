<?php

namespace Simple\Database;

use PDO;
use RuntimeException;
use Simple\Database\Schema\Blueprint;
use Simple\Database\Schema\Schema;

/**
 * Runs database/migrations/*.php in filename order and records them in a
 * "migrations" table, grouped into batches so a rollback undoes the last run.
 */
class Migrator
{
    private const TABLE = 'migrations';

    public function __construct(private string $path, private ?PDO $pdo = null)
    {
        $this->pdo ??= Connection::get();
    }

    /**
     * @return string[] names of migrations that ran
     */
    public function migrate(): array
    {
        $this->ensureTable();

        $ran = array_column($this->query()->get(), 'migration');
        $pending = array_diff(array_keys($this->files()), $ran);

        if ($pending === []) {
            return [];
        }

        $batch = (int) ($this->pdo->query('SELECT MAX(batch) FROM ' . self::TABLE)->fetchColumn() ?: 0) + 1;

        foreach ($pending as $name) {
            $this->atomically(function () use ($name, $batch) {
                $this->load($name)->up();
                $this->query()->insert(['migration' => $name, 'batch' => $batch]);
            });
        }

        return array_values($pending);
    }

    /**
     * @return string[] names of migrations that were rolled back
     */
    public function rollback(): array
    {
        $this->ensureTable();

        $batch = (int) $this->pdo->query('SELECT MAX(batch) FROM ' . self::TABLE)->fetchColumn();
        if ($batch === 0) {
            return [];
        }

        $names = array_column(
            $this->query()->where('batch', $batch)->orderBy('migration', 'DESC')->get(),
            'migration',
        );

        foreach ($names as $name) {
            $this->atomically(function () use ($name) {
                $this->load($name)->down();
                $this->query()->where('migration', $name)->delete();
            });
        }

        return $names;
    }

    /**
     * SQLite and PostgreSQL can roll back schema changes, so a migration that
     * fails halfway leaves nothing behind. MySQL commits every DDL statement
     * immediately, so there a failed migration may need manual cleanup.
     */
    private function atomically(\Closure $callback): void
    {
        if (Connection::driver($this->pdo) === 'mysql') {
            $callback();
            return;
        }

        $this->pdo->beginTransaction();
        try {
            $callback();
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * @return array<string, ?int> migration name => batch (null when pending)
     */
    public function status(): array
    {
        $this->ensureTable();
        $ran = array_column($this->query()->get(), 'batch', 'migration');

        $status = [];
        foreach (array_keys($this->files()) as $name) {
            $status[$name] = isset($ran[$name]) ? (int) $ran[$name] : null;
        }
        return $status;
    }

    /** @return array<string, string> name => path */
    private function files(): array
    {
        $files = [];
        foreach (glob($this->path . '/*.php') ?: [] as $file) {
            $files[basename($file, '.php')] = $file;
        }
        ksort($files);
        return $files;
    }

    private function load(string $name): Migration
    {
        $file = $this->files()[$name] ?? throw new RuntimeException("Migration file [$name] is missing.");
        $migration = require $file;

        if (!$migration instanceof Migration) {
            throw new RuntimeException("Migration [$name] must return an instance of " . Migration::class);
        }

        return $migration;
    }

    private function ensureTable(): void
    {
        if (!Schema::hasTable(self::TABLE, $this->pdo)) {
            Schema::create(self::TABLE, function (Blueprint $table) {
                $table->id();
                $table->string('migration');
                $table->integer('batch');
            }, $this->pdo);
        }
    }

    private function query(): QueryBuilder
    {
        return new QueryBuilder($this->pdo, self::TABLE);
    }
}
