<?php

namespace app\Models;

use core\Database;
use Exception;
use mysql_xdevapi\DatabaseObject;

class User
{
    public static function findByEmail($email): false|\PDO|array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);

        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    // public static function create(
    //     $user,
    //     $passsword_hash,
    //     $role,
    // ): false|\PDOStatement {
    //     try {
    //         $db = Database::getInstance();
    //         $stmt = $db->prepare(
    //             "INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)",
    //         );
    //         $stmt->execute([$user, $passsword_hash, $role]);

    //         return true;
    //     } catch (Exception $e) {
    //         return false;
    //     }
    // }

    public static function delete($user_id): bool|\PDO|array
    {
        try {
            $db = Database::getInstance();
            $stmt = $db->prepare("DELETE FROM users WHERE id=?");
            $stmt->execute([$user_id]);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    // Fetch all users
    public static function getAll(): array
    {
        $db = Database::getInstance();
        $stmt = $db->query("SELECT id, username, email, role, created_at FROM users ORDER BY created_at DESC");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    // Create new user
    public static function create(string $username, string $email, string $password, string $role = 'user'): bool
    {
        $db = Database::getInstance();
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $db->prepare("
            INSERT INTO users (username, email, password_hash, role)
            VALUES (?, ?, ?, ?)
        ");

        return $stmt->execute([$username, $email, $passwordHash, $role]);
    }

    // Edit user
    public static function edit(int $id, ?string $username = null, ?string $email = null, ?string $password = null, ?string $role = null): bool
    {
        $db = Database::getInstance();
        $fields = [];
        $values = [];

        if ($username !== null) {
            $fields[] = "username = ?";
            $values[] = $username;
        }
        if ($email !== null) {
            $fields[] = "email = ?";
            $values[] = $email;
        }
        if ($password !== null) {
            $fields[] = "password_hash = ?";
            $values[] = password_hash($password, PASSWORD_DEFAULT);
        }
        if ($role !== null) {
            $fields[] = "role = ?";
            $values[] = $role;
        }

        if (empty($fields)) return false; // nothing to update

        $values[] = $id; // for WHERE clause
        $sql = "UPDATE users SET " . implode(", ", $fields) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        return $stmt->execute($values);
    }
}
