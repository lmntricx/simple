<?php

namespace app\Services;

// use core\Database;
use app\Models\User;

class UserServices
{
    public static function update_user(): bool
    {
        $user_id = $_POST["id"];
        $user_name = $_POST["user_name"];
        $password = $_POST["user_password"];
        $email = $_POST["user_email"];

        $password =
            mb_strlen($password) > 2
                ? password_hash($password, PASSWORD_DEFAULT)
                : "";

        $result = User::update_user($user_id, $user_name, $password, $email);

        return $result;
    }
}
