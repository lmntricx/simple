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
        ResponseHelper::post_method_check();
    }

    public function index(): void
    {
        echo "arg1";
    }

    public function sign_in(): void
    {
        if (!isset($_POST["user_name"]) or !isset($_POST["password"])) {
            $status = "failed";
            $message = "Please input real user credentials";
            $status_code = 400;
            $data = [];
            ResponseHelper::error("error", $status_code, $data);
        }

        if (BoardingService::userLogin()) {
            ResponseHelper::success();
        }
    }

    public function sign_up(): void {}
}
