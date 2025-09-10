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

    public static function single_qr($qr_id = ""): false|\PDO|array
    {
        try {
            $db = Database::getInstance();
            $query = "SELECT * FROM qr_codes WHERE id=?";
            $stmt = $db->prepare($query);
            $stmt->execute([$qr_id]);

            return $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return false;
        }
    }

    public static function create(
        $enum,
        $shortcode,
        $redirect_url,
        $expiration_date,
        $creator_id,
    ): false|\PDOStatement {
        try {
            $db = Database::getInstance();
            $stmt = $db->prepare('INSERT INTO qrcodes (type, shortcode, redirect_url, expiration_date, created_by)
                VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([
                $enum,
                $shortcode,
                $redirect_url,
                $expiration_date,
                $creator_id,
            ]);

            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
