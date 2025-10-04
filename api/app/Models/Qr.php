<?php

namespace app\Models;

use MongoDB\Driver\Exception\Exception;
use core\Database;
use mysql_xdevapi\DatabaseObject;

class Qr
{
    public static function allCodes(): false|\PDO|array
    {
        try {
            $db = Database::getInstance();
            $stmt = $db->prepare("SELECT * FROM qrcodes ORDER BY id");
            $stmt->execute();

            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return false;
        }
    }

    public static function single_qr(string $qr_id = ""): array|false
    {
        try {
            $db = Database::getInstance();
            $query = "SELECT * FROM qrcodes WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$qr_id]);

            return $stmt->fetch(\PDO::FETCH_ASSOC); // array or false
        } catch (\Exception $e) {
            return false;
        }
    }


    public static function delete_qr($qr_id = ""): bool|\PDO|array
    {
        try {
            $db = Database::getInstance();
            $query = "DELETE FROM qrcodes WHERE id=?";
            $stmt = $db->prepare($query);
            $stmt->execute([$qr_id]);

            return true;
        } catch (Exception $e) {
            return false;
        }
    }


    public static function create(
        string $type,
        string $short_code,
        string $redirect_url,
        $expiration_date = null
    ): array {
        try {
            // Validate required fields
            if (!$type || !$short_code || !$redirect_url) {
                return [
                    "success" => false,
                    "message" => "Missing required fields: type, short_code, or redirect_url",
                ];
            }

            $db = Database::getInstance();

            $stmt = $db->prepare(
                'INSERT INTO qrcodes (type, short_code, redirect_url, expiration_date, created_by)
                 VALUES (?, ?, ?, ?, ?)'
            );

            $default_user_id = 1;

            $stmt->execute([
                $type,
                $short_code,
                $redirect_url,
                $expiration_date,
                $default_user_id,
            ]);

            $insertedId = (int) $db->lastInsertId();

            return [
                "success" => true,
                "message" => "QR code created successfully",
                "inserted_id" => $insertedId,
            ];
        } catch (\PDOException $e) {
            return [
                "success" => false,
                "message" => "Database error: " . $e->getMessage(),
            ];
        } catch (\Exception $e) {
            return [
                "success" => false,
                "message" => "Server error: " . $e->getMessage(),
            ];
        }
    }

    public static function edit_qr(
        $qr_id,
        $short_code,
        $type,
        $redirect_url,
        bool $isActive
    ): bool {
        try {
            $db = Database::getInstance();

            $stmt = $db->prepare("
                UPDATE qrcodes
                SET short_code = ?, type = ?, redirect_url = ?, is_active = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $short_code,
                $type,
                $redirect_url,
                $isActive ? 1 : 0,
                $qr_id,
            ]);

            return true;
        } catch (\Exception $e) {
            print($e->getMessage());
            return false;
        }
    }

    public static function edit_qr_image(
        $qr_id,
        $image_name
    ): bool {
        try {
            $db = Database::getInstance();

            $stmt = $db->prepare("
                UPDATE qrcodes
                SET custom_design = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $image_name,
                $qr_id
            ]);

            return true;
        } catch (\Exception $e) {
            print($e->getMessage());
            return false;
        }
    }

    // now we deal with stats
    public static function get_dashboard_stats(): bool|array
    {
        try {
            // Get your database instance (singleton wrapper)
            $db = Database::getInstance();

            $sql = "
                SELECT
                    COUNT(*) AS total_scans,
                    COUNT(DISTINCT device_type) AS unique_devices,
                    COUNT(DISTINCT qr_id) AS unique_urls
                FROM scan_logs
            ";

            $stmt = $db->prepare($sql);
            $stmt->execute();

            // Use global PDO class
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            return $result ?: [];
        } catch (\Exception $e) {
            // Optional: log $e->getMessage() somewhere
            return false;
        }
    }


    // ✅ Weekly usage stats
    public static function get_weekly_usage(): bool|array
    {
        try {
            // Get your database instance (singleton wrapper)
            $db = Database::getInstance();

            $sql = "
                SELECT 
                    DATE(scanned_at) AS day,
                    COUNT(*) AS total_scans
                FROM scan_logs
                WHERE scanned_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                GROUP BY DATE(scanned_at)
                ORDER BY day ASC
            ";

            $stmt = $db->prepare($sql);
            $stmt->execute();

            // Use global PDO class
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return $rows ?: [];
        } catch (\Exception $e) {
            // Optional: log $e->getMessage() for debugging
            return false;
        }
    }

    public static function get_analytics_data(): false|array
    {
        try {
            $db = Database::getInstance();

            // Weekly scans (last 7 days)
            $stmt = $db->prepare("
                SELECT DATE(scanned_at) as day, COUNT(*) as total_scans
                FROM scan_logs
                WHERE scanned_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                GROUP BY DATE(scanned_at)
                ORDER BY day ASC
            ");
            $stmt->execute();
            $weekly = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Monthly scans (last 12 months)
            $stmt = $db->prepare("
                SELECT DATE_FORMAT(scanned_at, '%Y-%m') as month, COUNT(*) as total_scans
                FROM scan_logs
                WHERE scanned_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                GROUP BY month
                ORDER BY month ASC
            ");
            $stmt->execute();
            $monthly = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Totals
            $stmt = $db->prepare("
                SELECT COUNT(*) as total_scans, 
                       COUNT(DISTINCT device_type) as unique_devices,
                       COUNT(DISTINCT qr_id) as unique_urls
                FROM scan_logs
            ");
            $stmt->execute();
            $totals = $stmt->fetch(\PDO::FETCH_ASSOC);

            // 🔹 Top QR Codes by scan count
            $stmt = $db->prepare("
                SELECT q.short_code, COUNT(s.id) as scans
                FROM scan_logs s
                JOIN qrcodes q ON s.qr_id = q.id
                GROUP BY s.qr_id
                ORDER BY scans DESC
                LIMIT 5
            ");
            $stmt->execute();
            $topQRCodes = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // 🔹 Device breakdown
            $stmt = $db->prepare("
                SELECT device_type, COUNT(*) as count
                FROM scan_logs
                GROUP BY device_type
                ORDER BY count DESC
            ");
            $stmt->execute();
            $devices = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // 🔹 OS breakdown
            $stmt = $db->prepare("
                SELECT os, COUNT(*) as count
                FROM scan_logs
                GROUP BY os
                ORDER BY count DESC
            ");
            $stmt->execute();
            $os = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // 🔹 Browser breakdown
            $stmt = $db->prepare("
                SELECT browser, COUNT(*) as count
                FROM scan_logs
                GROUP BY browser
                ORDER BY count DESC
            ");
            $stmt->execute();
            $browsers = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // 🔹 Top countries
            $stmt = $db->prepare("
                SELECT location_country as country, COUNT(*) as count
                FROM scan_logs
                WHERE location_country IS NOT NULL
                GROUP BY location_country
                ORDER BY count DESC
                LIMIT 5
            ");
            $stmt->execute();
            $countries = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // 🔹 Top cities
            $stmt = $db->prepare("
                SELECT location_city as city, COUNT(*) as count
                FROM scan_logs
                WHERE location_city IS NOT NULL
                GROUP BY location_city
                ORDER BY count DESC
                LIMIT 5
            ");
            $stmt->execute();
            $cities = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return [
                "weekly" => $weekly,
                "monthly" => $monthly,
                "totals" => $totals,
                "top_qrcodes" => $topQRCodes,
                "devices" => $devices,
                "os" => $os,
                "browsers" => $browsers,
                "countries" => $countries,
                "cities" => $cities,
            ];
        } catch (\Exception $e) {
            return false;
        }
    }


    public static function search_qrcodes(string $query): array|false
    {
        try {
            $db = Database::getInstance();

            // Use LIKE for partial matches, search name/short_code/redirect_url
            $sql = "SELECT id, short_code, type, redirect_url, custom_design, expiration_date,
                           CASE WHEN expiration_date IS NULL OR expiration_date > NOW() THEN 1 ELSE 0 END AS isActive
                    FROM qrcodes
                    WHERE short_code LIKE ? OR redirect_url LIKE ? OR type LIKE ?
                    ORDER BY created_at DESC
                    LIMIT 30"; // optional limit for performance

            $stmt = $db->prepare($sql);
            $likeQuery = '%' . $query . '%';
            $stmt->execute([$likeQuery, $likeQuery, $likeQuery]);

            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Cast isActive to boolean
            foreach ($rows as &$row) {
                $row['isActive'] = (bool)$row['isActive'];
            }

            return $rows ?: [];
        } catch (\Exception $e) {
            // Optional: log $e->getMessage()
            return false;
        }
    }

    // In QrService.php or relevant model
    public static function get_expiring_qr_codes(int $daysUntilExpiry = 14): false|array
    {
        try {
            $db = Database::getInstance();
            $stmt = $db->prepare("
                SELECT id, short_code, type, expiration_date, is_active
                FROM qrcodes
                WHERE expiration_date IS NOT NULL
                  AND expiration_date <= DATE_ADD(CURDATE(), INTERVAL :days DAY)
                ORDER BY expiration_date ASC
            ");
            $stmt->bindValue(":days", $daysUntilExpiry, \PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return false;
        }
    }

    // Get all data for a single QR code
    public static function get_qr_code_analytics(int $qrId): false|array
    {
        try {
            $db = Database::getInstance();

            // QR Code basic info
            $stmt = $db->prepare("SELECT * FROM qrcodes WHERE id = :qrId");
            $stmt->execute([':qrId' => $qrId]);
            $qrInfo = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$qrInfo) return false;

            // Scan logs
            $stmt = $db->prepare("SELECT * FROM scan_logs WHERE qr_id = :qrId ORDER BY scanned_at DESC");
            $stmt->execute([':qrId' => $qrId]);
            $scanLogs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Versions
            $stmt = $db->prepare("SELECT * FROM qr_versions WHERE qr_id = :qrId ORDER BY changed_at DESC");
            $stmt->execute([':qrId' => $qrId]);
            $versions = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return [
                'qr' => $qrInfo,
                'scans' => $scanLogs,
                'versions' => $versions
            ];
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function deactivate_qr(int $qrId): bool
    {
        try {
            $db = Database::getInstance();
            $stmt = $db->prepare("UPDATE qrcodes SET is_active = 0 WHERE id = :qrId");
            return $stmt->execute([':qrId' => $qrId]);
        } catch (\Exception $e) {
            return false;
        }
    }





}
