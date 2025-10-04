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
                email VARCHAR(150),
                role ENUM('admin', 'user') DEFAULT 'admin',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_login TIMESTAMP NULL DEFAULT NULL
            )",

            // Table: qrcodes
            "CREATE TABLE IF NOT EXISTS qrcodes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                type ENUM('static', 'dynamic') NOT NULL,
                short_code VARCHAR(50) NOT NULL,
                original_url TEXT,
                redirect_url TEXT,
                custom_design JSON NULL,
                is_active BOOLEAN DEFAULT TRUE,
                expiration_date TIMESTAMP NULL,
                password VARCHAR(255) NULL,
                created_by INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
            )",

            // Table: scan_logs
            "CREATE TABLE IF NOT EXISTS scan_logs (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                qr_id INT NOT NULL,
                scanned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                ip_address VARCHAR(45),
                user_agent TEXT,
                device_type VARCHAR(50),
                os VARCHAR(50),
                browser VARCHAR(50),
                location_country VARCHAR(100) NULL,
                location_city VARCHAR(100) NULL,
                FOREIGN KEY (qr_id) REFERENCES qrcodes(id) ON DELETE CASCADE
            ) ENGINE=InnoDB",

            // Table: qr_versions
            "CREATE TABLE IF NOT EXISTS qr_versions (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                qr_id INT NOT NULL,
                old_url TEXT,
                new_url TEXT,
                changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                changed_by INT NULL,
                FOREIGN KEY (qr_id) REFERENCES qrcodes(id) ON DELETE CASCADE,
                FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB",

            // Table: ip2location
            "CREATE TABLE IF NOT EXISTS ip2location (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                start_ip BIGINT UNSIGNED NOT NULL,
                end_ip BIGINT UNSIGNED NOT NULL,
                country CHAR(2),
                region VARCHAR(100),
                city VARCHAR(100),
                latitude DECIMAL(9,6),
                longitude DECIMAL(9,6),
                INDEX (start_ip),
                INDEX (end_ip)
            ) ENGINE=InnoDB"
        ];

        // Execute the queries
        foreach ($queries as $query) {
            try {
                $this->db->exec($query);
            } catch (\PDOException $e) {
                echo "Error: " . $e->getMessage();
            }
        }
    }

    /**
     * Import IP data from CSV file into ip2location table
     */
    public function importIpData(string $csvFile): void
    {
        if (!file_exists($csvFile)) {
            throw new \Exception("CSV file not found: $csvFile");
        }

        $handle = fopen($csvFile, "r");
        if (!$handle) {
            throw new \Exception("Unable to open file: $csvFile");
        }

        $this->db->beginTransaction();

        $insertQuery = $this->db->prepare("
            INSERT INTO ip2location 
            (start_ip, end_ip, country, region, city, latitude, longitude)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        while (($data = fgetcsv($handle, 1000, "\t")) !== FALSE) {
            if (count($data) < 8) continue;

            list($startIp, $endIp, $region, $country, $province, $city, $lat, $lon) = $data;

            $insertQuery->execute([
                sprintf('%u', ip2long($startIp)),
                sprintf('%u', ip2long($endIp)),
                $country,
                $province ?: null,
                $city ?: null,
                $lat ?: null,
                $lon ?: null
            ]);
        }

        fclose($handle);
        $this->db->commit();
    }

    /**
     * Lookup location by IP address
     */
    public function findLocationByIp(string $ip): ?array
    {
        $ipInt = sprintf('%u', ip2long($ip));

        $query = $this->db->prepare("
            SELECT country, region, city, latitude, longitude
            FROM ip2location
            WHERE start_ip <= :ip AND end_ip >= :ip
            LIMIT 1
        ");
        $query->execute(['ip' => $ipInt]);

        return $query->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function migrate(): void
    {
        // Placeholder for future migrations
    }
}
