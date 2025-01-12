<?php

namespace core;

use PDO;

class Database
{
    private static array $instances = []; // Array to hold multiple database connections

    public static function getInstance(string $dbName = 'guardian_office', string $host = 'localhost', string $user = 'root', string $password = ''): PDO
    {
        $key = "$host:$dbName"; // Unique key for each database connection

        if (!isset(self::$instances[$key])) {
            self::$instances[$key] = new PDO("mysql:host=$host;dbname=$dbName", $user, $password);
            self::$instances[$key]->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }

        return self::$instances[$key];
    }
}
