<?php
namespace app\Controllers;

// use app\Middleware;
use app\Helpers\ResponseHelper;
use app\Models\Qr;
use app\Services\QrService;
use Swoole\Http\Response;

class HomeController
{
    public function __construct()
    {
        // header("Content-type:application/json");
        // Check if the user is logged in
        // Middleware\AuthMiddleware::check();
        ResponseHelper::post_method_check();
    }

    public function index(): void
    {
        $data = Qr::allCodes();

        ResponseHelper::success($data);
    }

    public function single_qr(): void
    {
        if (!isset($_GET["id"])) {
            ResponseHelper::error(message: "Please supply qr code id");
        }

        $data = Qr::single_qr($_GET["id"]);
        if ($data) {
            ResponseHelper::success($data);
        } else {
            ResponseHelper::error(
                message: "QR Code with that id does not exist",
            );
        }
    }

    // create a new qr code
    public function create_qr(): void
    {
        if (
            !isset($_POST["name"]) ||
            !isset($_POST["type"]) ||
            !isset($_POST["content_url"]) ||
            !isset($_POST["options"]) ||
            !isset($_POST["expiration_date"])
        ) {
            $status = "failed";
            $message = "Please fill in all info needed to create new qr code";
            $status_code = 400;
            $data = [];
            ResponseHelper::error("error", $status_code, $data);
        }

        $result = QrService::create_qr();
        if ($result) {
            ResponseHelper::success();
        } else {
            ResponseHelper::error();
        }
    }

    //Edit existing code.
    public function edit_qr(): void {}
}
