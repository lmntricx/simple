<?php

namespace app\Models;

use MongoDB\Driver\Exception\Exception;
use core\Database;
use mysql_xdevapi\DatabaseObject;

class Logs
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

    public static function record_scan_transaction(
        $qr_id,
        $user_ip,
        $agent_data,
        $browser,
        $os,
        $device,
    ): bool|\PDO|array {
        try {
            $db = Database::getInstance();
            $stmt = $db->prepare(
                "INSERT INTO scan_logs (qr_id, ip_address, user_agent, device_type, os, browser) VALUES (?, ?, ?, ?, ?, ?)",
            );
            $stmt->execute([
                $qr_id,
                $user_ip,
                $agent_data,
                $device,
                $os,
                $browser,
            ]);

            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
