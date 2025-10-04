<?php

namespace app\Controllers;

use app\Services\BoardingService;
use app\Helpers\ResponseHelper;

class BoardingController
{
    public function __construct()
    {
        //        session_start();
        //        header("Content-type:application/json");
        // ResponseHelper::post_method_check();

    }

    public function index(): void
    {
        ResponseHelper::success();
    }

    public function sign_in(): void
    {
        $input = json_decode(file_get_contents("php://input"), true);
        $email = $input['email'] ?? null;
        $password = $input['password'] ?? null;

        if (!$email || !$password) {
            ResponseHelper::error("Please input real user credentials",400);
        }

        // ResponseHelper::not_found();

        $user_data = BoardingService::userLogin($email, $password);
        if (!$user_data) {
            $response_data = [];
            $response_data["message"] = "User not found";
            ResponseHelper::not_found($user_data, $response_data['message']);
        } else {
            
            ResponseHelper::success($user_data);
        }
    }

    public function setup_system(): void
    {
        if (
            !isset($_POST["db_name"]) ||
            !isset($_POST["db_password"]) ||
            !isset($_POST["db_user"]) ||
            !isset($_POST["user_name"]) ||
            !isset($_POST["user_password"])
        ) {
            ResponseHelper::error(message: "Missing form values", code: 400);
        }

        $database_setup = BoardingService::setup_system();
        if ($database_setup) {
            // record first user info
            $result = BoardingService::registerUser();
            if ($result) {
                ResponseHelper::success(
                    message: "Successfully registered a new user",
                );
            } else {
                ResponseHelper::error(
                    message: "Failed to create a new user",
                    code: 403,
                );
            }
        } else {
            ResponseHelper::error(
                message: "Failed to create new database for the system",
                code: 500,
            );
        }
    }

    public function sign_out(): void {
        unset($_SESSION['variable']);
        session_destroy();
        ResponseHelper::success();
    }
}
