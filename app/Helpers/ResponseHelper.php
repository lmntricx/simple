<?php
namespace app\Helpers;

class ResponseHelper
{
    public static function post_method_check(): void
    {
        if ($_SERVER["REQUEST_METHOD"] != "POST") {
            $status = "failed";
            $message = "Unacceptable request method";
            $status_code = 405;
            $data = [];
            self::json($status, $message, $data, $status_code);
        }
    }

    public static function get_method_check(): void
    {
        if ($_SERVER["REQUEST_METHOD"] != "GET") {
            $status = "failed";
            $message = "Unacceptable request method";
            $status_code = 405;
            $data = [];
            self::json($status, $message, $data, $status_code);
        }
    }

    public static function json(
        $status = "success",
        $message = "",
        $data = [],
        $code = 200,
    ): void {
        // ================================
        // CORS Headers
        // ================================
        // Allow requests from your React frontend
        header("Access-Control-Allow-Origin: http://localhost:5173");
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization");
        header("Access-Control-Allow-Credentials: true"); // required for PHP sessions

        // Handle preflight OPTIONS request
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }
        // ================================

        http_response_code($code);
        header("Content-Type: application/json");

        echo json_encode([
            "status" => $status,
            "message" => $message,
            "data" => $data,
        ]);
        exit();
    }

    public static function success(
        $data = [],
        $message = "Success",
        $code = 200,
    ): void {
        self::json("success", $message, $data, $code);
    }

    public static function not_found(
        $data = [],
        $message = "Not found",
        $code = 404,
    ): void {
        self::json("Not found", $message, $data, $code);
    }

    public static function error(
        $message = "Error",
        $code = 400,
        $errors = [],
    ): void {
        self::json("error", $message, $errors, $code);
    }
}
