<?php
// app/Middleware/AuthMiddleware.php
namespace app\Middleware;

use app\Helpers\ResponseHelper;

class AuthMiddleware
{
    private static function loadEnv($path)
    {
        if (!file_exists($path)) {
            return false;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (str_starts_with(trim($line), "#")) {
                continue; // skip comments
            }
            [$name, $value] = explode("=", $line, 2);
            $name = trim($name);
            $value = trim($value);
            putenv("$name=$value");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
        return true;
    }

    public static function check(): void
    {
        // Start session for authentication check

        if (
            !isset($_SESSION["is_logged_in"]) ||
            $_SESSION["is_logged_in"] !== true
        ) {
            $envFile = __DIR__ . "/.env";

            if (!self::loadEnv($envFile)) {
                ResponseHelper::error(
                    message: "Please setup system first",
                    code: 401,
                );
            } else {
                // Redirect to login page if not authenticated
                ResponseHelper::error(
                    message: "Unauthorised access",
                    code: 401,
                );
                exit();
            }
        }

        $envFile = __DIR__ . "/.env";
    }
}
