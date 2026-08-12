<?php

namespace app\ORM;

use PDO;
use PDOException;

class SchemaBuilder
{
    public static function createTableIfNotExists(PDO $connection, string $table, callable $schemaCallback): void
    {
        if (self::tableExists($connection, $table)) {
            return;
        }

        $blueprint = new Blueprint();
        $schemaCallback($blueprint);

        if (empty($blueprint->getColumns())) {
            return;
        }

        $driver = $connection->getAttribute(PDO::ATTR_DRIVER_NAME);
        $sql = $blueprint->toSql($table, $driver);

        $connection->exec($sql);
    }

    public static function tableExists(PDO $connection, string $table): bool
    {
        $driver = $connection->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $stmt = $connection->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
            $stmt->execute([$table]);
            return (bool) $stmt->fetchColumn();
        }

        if ($driver === 'sqlite') {
            $stmt = $connection->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name = ?");
            $stmt->execute([$table]);
            return (bool) $stmt->fetchColumn();
        }

        try {
            $stmt = $connection->prepare("SELECT 1 FROM {$table} LIMIT 1");
            $stmt->execute();
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
}