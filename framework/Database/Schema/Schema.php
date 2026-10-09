<?php

namespace Simple\Database\Schema;

use Closure;
use PDO;
use Simple\Database\Connection;

/**
 * Schema::create('posts', function (Blueprint $table) {
 *     $table->id();
 *     $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
 *     $table->string('title');
 *     $table->timestamps();
 * });
 */
final class Schema
{
    public static function create(string $table, Closure $callback, ?PDO $pdo = null): void
    {
        $pdo ??= Connection::get();
        $blueprint = new Blueprint($table);
        $callback($blueprint);

        foreach ($blueprint->toSql(Connection::driver($pdo)) as $statement) {
            $pdo->exec($statement);
        }
    }

    public static function dropIfExists(string $table, ?PDO $pdo = null): void
    {
        $pdo ??= Connection::get();
        $q = Connection::driver($pdo) === 'mysql' ? '`' : '"';
        $pdo->exec("DROP TABLE IF EXISTS {$q}{$table}{$q}");
    }

    public static function hasTable(string $table, ?PDO $pdo = null): bool
    {
        $pdo ??= Connection::get();

        $sql = match (Connection::driver($pdo)) {
            'mysql' => 'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
            'pgsql' => "SELECT 1 FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = ?",
            default => "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ?",
        };

        $statement = $pdo->prepare($sql);
        $statement->execute([$table]);

        return (bool) $statement->fetchColumn();
    }
}
