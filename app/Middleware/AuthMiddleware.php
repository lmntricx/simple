<?php
// app/Middleware/AuthMiddleware.php
namespace app\Middleware;

class AuthMiddleware
{
    public static function check(): void
    {
//        session_start();  // Start session for authentication check

        if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
            // Redirect to login page if not authenticated
            header('Location: /login');
            exit();
        }
    }
}
