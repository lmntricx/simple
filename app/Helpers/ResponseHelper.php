<?php
// app/Helpers/ResponseHelper.php
namespace app\Helpers;

class ResponseHelper
{

    public static function post_method_check(): void
    {
        if($_SERVER['REQUEST_METHOD'] != 'POST') {
            $status = "failed";
            $message = "Unacceptable request method";
            $status_code = 405;
            $data = [];
            self::json($status, $message, $data, $status_code);
        }
    }

    public static function json($status = "success", $message = "", $data = [], $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode([
            'status'  => $status,
            'message' => $message,
            'data'    => $data
        ]);
        exit();
    }

    public static function success($data = [], $message = "Success", $code = 200): void
    {
        self::json("success", $message, $data, $code);
    }

    public static function error($message = "Error", $code = 400, $errors = []): void
    {
        self::json("error", $message, $errors, $code);
    }
}
