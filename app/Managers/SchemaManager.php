<?php
namespace app\Managers;

use core\Database;
use PDO;

class SchemaManager
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function createTables(): void
    {
        // Define tables and schema setup
        $queries = [
            // Table: users
            "CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                referral_code VARCHAR(255) NOT NULL,
                first_name VARCHAR(255) NOT NULL,
                last_name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL UNIQUE,
                phone_number VARCHAR(255) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL UNIQUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",

            // Table: personal info
            "CREATE TABLE IF NOT EXISTS personal_info (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL UNIQUE,
                id_number VARCHAR(255) NOT NULL UNIQUE,
                
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )",

            // Table: banking info
            "CREATE TABLE IF NOT EXISTS banking_info (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                bank_name VARCHAR(255) NOT NULL,
                account_number VARCHAR(255) NOT NULL,
                account_type VARCHAR(255) NOT NULL,
                account_holder VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )",

            // Table: locale
            "CREATE TABLE IF NOT EXISTS locale_info (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                country VARCHAR(255) NOT NULL,
                state VARCHAR(255) NOT NULL,
                city VARCHAR(255) NOT NULL,
                town VARCHAR(255) NOT NULL,
                stree_address  VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )"
        ];

        // Execute the queries
        foreach ($queries as $query) {
            try {
                $this->db->exec($query);
            } catch (\PDOException $e) {
                echo 'Error: ' . $e->getMessage();
            }
        }
    }

    public function migrate(): void
    {
        // Add migration methods here if you need more control over migrations in the future
        // For example, creating indexes, altering columns, etc.
        $migrations = [
            // Example: Adding a new column to users table
            "ALTER TABLE users ADD COLUMN last_login TIMESTAMP NULL DEFAULT NULL"
        ];

        foreach ($migrations as $query) {
            try {
                $this->db->exec($query);
            } catch (\PDOException $e) {
                echo 'Error: ' . $e->getMessage();
            }
        }
    }
}
