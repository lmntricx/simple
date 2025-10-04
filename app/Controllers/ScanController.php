<?php
namespace app\Controllers;

use app\Middleware;
use app\Helpers\ResponseHelper;
use app\Models\Qr;
use app\Models\User;
use app\Services\BoardingService;
use app\Services\QrService;
use app\Services\UserServices;
use Swoole\Http\Response;
use app\Managers\SchemaManager;

class ScanController
{
    public function __construct() {
        // Check if the user is logged in
        // Middleware\AuthMiddleware::check();
    }

    public function index() {
        ResponseHelper::success();
    }

    public function qr_scanned(): void
    {
        ResponseHelper::post_method_check();

        if (!isset($_POST["id"])) {
            ResponseHelper::error(message: "Please supply valid qr code id");
        }

        $result = QrService::qr_scanned();

        if ($result) {
            // forward user to the url encoded in the QR Code..
            $data = [];
            $data['redirect_url'] = $result;

            ResponseHelper::success(data: $data);
        } else {
            ResponseHelper::not_found(message: "QR code with id not found");
            
        }
    }

}