<?php

namespace core;

use PDO;

class Database
{
    private static array $instances = []; // Array to hold multiple database connections
    private string $dbName = ""; # your database name
    private string $dbHost = "localhost"; # your database host
    protected static string $dbPassword = "root"; # your database password

    public static function getInstance(string $dbName = 'database', string $host = 'localhost', string $user = 'root', string $password = "root"): PDO
    {
        $key = "$host:$dbName"; // Unique key for each database connection

        if (!isset(self::$instances[$key])) {
            self::$instances[$key] = new PDO("mysql:host=$host;dbname=$dbName", $user, $password);
            self::$instances[$key]->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }

        return self::$instances[$key];
    }
}
