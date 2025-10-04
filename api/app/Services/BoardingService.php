<?php

namespace app\Services;

use core\Database;
use app\Models;
use Exception;

use app\Managers\SchemaManager;

class BoardingService
{
    private static function addToDatabase(): bool
    {
        return true;
    }

    private function creditReferer($refererId): void {}

    public static function userLogin($email, $password ): array|bool
    {
        $user_data = [];

        $user_data["email"] = $email;
        // $user_data["password"] = password_hash(
        //     $password,
        //     PASSWORD_BCRYPT,
        // );
        $user_data["password"] = $password; // password_hash($_POST["password"],PASSWORD_BCRYPT,);

        $user = Models\User::findByEmail($email);
        if ($user) {
            $user_password = $user["password_hash"];

            // if($user_password == $password){
            //     $_SESSION["is_logged_in"] = true;
            //     $_SESSION["user_id"] = $user["id"];
                
            //     $user_data["user_id"] = $user["id"];
            //     $user_data["is_logged_in"] = true;
            //     return $user_data;
            // } else {
            //     return false;
            // }

            if (password_verify($password, $user_password)) {
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

    public static function setup_system(): bool
    {
        try {
            $envFile = __DIR__ . "/.env";

            $dbHost = "localhost";
            $dbName = $_POST["db_name"];
            $dbUser = $_POST["db_user"];
            $dbPass = $_POST["db_password"];

            $content = "DB_HOST=$dbHost\nDB_NAME=$dbName\nDB_USER=$dbUser\nDB_PASS=$dbPass\n";
            file_put_contents($envFile, $content);

            // Create database schema if necessary
            $schemaManager = new SchemaManager();
            $schemaManager->createTables();

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public static function registerUser(): bool
    {
        $username = $_POST["user_name"];
        $password = password_hash($_POST["user_password"], PASSWORD_DEFAULT);
        $result = Models\User::create(
            user: $username,
            passsword_hash: $password,
            role: "admin",
        );

        return $result;
    }
}
