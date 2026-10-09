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

    /**
     * Alter an existing table:
     *   Schema::table('posts', function (Blueprint $table) {
     *       $table->string('slug')->nullable();
     *       $table->dropColumn('legacy_field');
     *   });
     *
     * SQLite can't add a NOT NULL column without a default, so make new columns
     * nullable() or give them a default().
     */
    public static function table(string $table, Closure $callback, ?PDO $pdo = null): void
    {
        $pdo ??= Connection::get();
        $blueprint = new Blueprint($table, altering: true);
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

    /**
     * Drop every table in the database. Used by `db:fresh` and the test suite.
     */
    public static function dropAllTables(?PDO $pdo = null): void
    {
        $pdo ??= Connection::get();

        switch (Connection::driver($pdo)) {
            case 'mysql':
                $tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
                $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
                foreach ($tables as $table) {
                    $pdo->exec('DROP TABLE `' . str_replace('`', '``', $table) . '`');
                }
                $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
                break;

            case 'pgsql':
                $tables = $pdo->query('SELECT tablename FROM pg_tables WHERE schemaname = current_schema()')->fetchAll(PDO::FETCH_COLUMN);
                foreach ($tables as $table) {
                    $pdo->exec('DROP TABLE IF EXISTS "' . str_replace('"', '""', $table) . '" CASCADE');
                }
                break;

            default:
                $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);
                $pdo->exec('PRAGMA foreign_keys = OFF');
                foreach ($tables as $table) {
                    $pdo->exec('DROP TABLE "' . str_replace('"', '""', $table) . '"');
                }
                $pdo->exec('PRAGMA foreign_keys = ON');
        }
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
