<?php

namespace core;

use PDO;
use PDOException;

class Database
{
    private static array $instances = [];
    private static ?array $globalConfig = null;

    public static function setConfig(array $config): void
    {
        self::$globalConfig = $config;
    }

    public static function getInstance(array $config = null): PDO
    {
        $config = $config ?? self::$globalConfig ?? (defined('DB_CONFIG') ? DB_CONFIG : null);

        if (!is_array($config) || empty($config)) {
            throw new \RuntimeException('Database configuration is not available.');
        }

        $key = md5(json_encode($config));

        if (!isset(self::$instances[$key])) {
            $dsn = sprintf(
                '%s:host=%s;port=%s;dbname=%s;charset=%s',
                $config['driver'] ?? 'mysql',
                $config['host'] ?? '127.0.0.1',
                $config['port'] ?? '3306',
                $config['database'] ?? '',
                $config['charset'] ?? 'utf8mb4'
            );

            try {
                $pdo = new PDO(
                    $dsn,
                    $config['username'] ?? '',
                    $config['password'] ?? ''
                );
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                throw new \RuntimeException('Database connection failed: ' . $e->getMessage());
            }

            self::$instances[$key] = $pdo;
        }

        return self::$instances[$key];
    }
}
