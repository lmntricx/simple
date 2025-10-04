<?php
class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        $this->connection = new PDO(
            'mysql:host=' . env('DB_HOST') . ';dbname=' . env('DB_NAME'),
            env('DB_USER'),
            env('DB_PASS')
        );
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance->connection;
    }
}
