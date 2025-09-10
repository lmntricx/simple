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
                username VARCHAR(100) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                email VARCHAR(150) NOT NULL UNIQUE,
                role ENUM('admin', 'user') DEFAULT 'admin',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",

            // Table: personal info
            "CREATE TABLE IF NOT EXISTS qrcodes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                type ENUM('static', 'dynamic') NOT NULL,
                short_code VARCHAR(50) NOT NULL UNIQUE,
                original_url TEXT,
                redirect_url TEXT,
                custom_design JSON NULL,
                expiration_date TIMESTAMP NULL,
                password VARCHAR(255) NULL,
                created_by INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
            )",

            // Table: banking info
            "CREATE TABLE IF NOT EXISTS scan_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                qr_id INT NOT NULL,
                scanned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                ip_address VARCHAR(45),
                user_agent TEXT,
                device_type VARCHAR(50),
                os VARCHAR(50),
                browser VARCHAR(50),
                location_country VARCHAR(100),
                location_city VARCHAR(100),
                FOREIGN KEY (qr_id) REFERENCES qrcodes(id) ON DELETE CASCADE
            )",

            // Table: locale
            "CREATE TABLE IF NOT EXISTS qr_versions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                qr_id INT NOT NULL,
                old_url TEXT,
                new_url TEXT,
                changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                changed_by INT,
                FOREIGN KEY (qr_id) REFERENCES qrcodes(id) ON DELETE CASCADE,
                FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
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
