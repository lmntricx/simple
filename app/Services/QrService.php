<?php

namespace app\Services;

use app\Models;

class QrService
{
    public static function create_qr(): bool
    {
        // get data from the post requets
        $name = $_POST["name"];
        $type = $_POST["type"];
        $content_url = $_POST["content_url"];
        $expiration = $_POST["expiration"];

        $user = Models\User::findByEmail($name);
        $user_id = $user["id"];

        // now record data
        return Models\Qr::create(
            $type,
            $name,
            $content_url,
            $expiration,
            $user_id,
        );
        // return true;
    }

    private function creditRefere(): void {}

    public static function userLogin(): bool
    {
        $user_data = [];

        $user_data["email"] = $_POST["email"];
        $user_data["password"] = password_hash(
            $_POST["password"],
            PASSWORD_BCRYPT,
        );

        $user = Models\User::findByEmail($user_data["email"]);
        if ($user) {
            $user_password = $user["password_hash"];
            if (password_verify($_POST["password"], $user_password)) {
                $_SESSION["is_logged_in"] = true;
                $_SESSION["user_id"] = $user["id"];
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }
}
