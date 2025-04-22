<?php

namespace app\Models;

use core\Database;
use mysql_xdevapi\DatabaseObject;

class User
{
    public static function findByEmail($email):false|\PDO|array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);

        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public static function create($data):false|\PDOStatement
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('INSERT INTO users (referral_code, first_name, last_name, email, phone_number, password_hash) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$data['referralCode'],$data['firstName'],$data['lastName'],$data['email'],$data['phoneNumber'],$data['password']]);

        return $stmt;
    }
}
