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

// Create database schema if necessary
$schemaManager = new SchemaManager();
$schemaManager->createTables();


class HomeController
{
    public function __construct()
    {
        // header("Content-type:application/json");
        // Check if the user is logged in
        // Middleware\AuthMiddleware::check();
    }

    public function index(): void
    {
        ResponseHelper::get_method_check();

        $data = Qr::allCodes();

        ResponseHelper::success($data);
    }

    public function single_qr(): void
    {
        ResponseHelper::get_method_check();

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

    public function delete_qr(): void
    {
        ResponseHelper::post_method_check();

        if (!isset($_POST["id"])) {
            ResponseHelper::error(message: "Unkown qr code id");
        }

        $result = Qr::delete_qr($_GET["id"]);
        if ($result) {
            ResponseHelper::success();
        } else {
            ResponseHelper::error();
        }
    }

    public function edit_qr(): void
    {
        // Ensure this is a POST request
        ResponseHelper::post_method_check();

        // Required fields
        $requiredFields = ["id", "short_code", "type", "redirect_url", "isActive"];
        foreach ($requiredFields as $field) {
            if (!isset($_POST[$field])) {
                ResponseHelper::error(message: "Missing required field: $field");
                return;
            }
        }

        $qr_id = (int)$_POST["id"];
        $short_code = trim($_POST["short_code"]);
        $type = trim($_POST["type"]);
        $redirect_url = trim($_POST["redirect_url"]);
        $isActive = filter_var($_POST["isActive"], FILTER_VALIDATE_BOOLEAN);

        // Validate 'type' is allowed
        if (!in_array($type, ["static", "dynamic"])) {
            ResponseHelper::error(message: "Invalid QR code type");
            return;
        }

        // Call the service method to update the QR code
        $result = QrService::edit_qr($qr_id, $short_code, $type, $redirect_url, $isActive);

        if ($result) {
            ResponseHelper::success(message: "QR code updated successfully");
        } else {
            ResponseHelper::error(message: "Failed to update QR code");
        }
    }


    // create a new qr code
    public function create_qr(): void
    {
        ResponseHelper::post_method_check();

        if (
            !isset($_POST["name"]) ||
            !isset($_POST["type"]) ||
            !isset($_POST["redirect_url"])
        ) {
            $status = "failed";
            $message = "Please fill in all info needed to create new qr code";
            $status_code = 400;
            $data = [];
            ResponseHelper::error(message: $message, code: $status_code, errors: $data);
        }

        $result = QrService::create_qr();
        if ($result["success"]) {
            ResponseHelper::success(message: "Successfully created a qr code");
        } else {
            ResponseHelper::error(message: $result["message"]);
        }
    }


    public function get_users(): void
    {
        $users = User::getAll();
        ResponseHelper::success(data: $users);
    }

    public function create_user()
    {
        // Capture input (assuming JSON payload from frontend)
        $input = json_decode(file_get_contents("php://input"), true);

        $username = $input['username'] ?? null;
        $email    = $input['email'] ?? null;
        $password = $input['password'] ?? null;
        $role     = $input['role'] ?? 'user';

        if (!$username) {
            ResponseHelper::error(message: "Please provide proper user name or password");
        }

        try {
            $created = User::create($username, $email, $password, $role);

            if ($created) {
                ResponseHelper::success(message: "User created successfully");
            } else {
                ResponseHelper::error(message: "Failed to create user");
            }
        } catch (PDOException $e) {
            $message = $e->getMessage();
            $code = $e->getCode();
            ResponseHelper::error(message: $message);
        }
    }

    public function edit_user(): void
    {
        ResponseHelper::post_method_check();

        $id = $_POST['id'] ?? null;
        $username = $_POST['username'] ?? null;
        $email = $_POST['email'] ?? null;
        $password = $_POST['password'] ?? null;
        $role = $_POST['role'] ?? null;

        // print_r($_POST);

        if ($id == null) {
            ResponseHelper::error(message: "User ID is required ");
        }

        $result = User::edit((int)$id, $username, $email, $password, $role);
        if ($result) {
            ResponseHelper::success(message: "User updated successfully");
        } else {
            ResponseHelper::error(message: "Failed to update user");
        }
    }

    public function delete_user(): void
    {
        ResponseHelper::post_method_check();

        $id = $_POST['id'] ?? null;

        // print_r($_POST);

        if ($id == null) {
            ResponseHelper::error(message: "User ID is required ");
        }

        $result = User::delete((int)$id);
        if ($result) {
            ResponseHelper::success(message: "User deleted successfully");
        } else {
            ResponseHelper::error(message: "Failed to delete user");
        }
    }

    public function dash_stats(): void 
    {
        ResponseHelper::get_method_check();

        $dash_stats = Qr::get_dashboard_stats();

        if(!$dash_stats) {
            ResponseHelper::not_found(code: 404);
        }

        ResponseHelper::success(data: $dash_stats);
    }

    public function weekly_usage(): void 
    {
        ResponseHelper::get_method_check();

        $weekly_usage = Qr::get_weekly_usage();

        if(!$weekly_usage) {
            ResponseHelper::not_found(code: 404);
        }

        ResponseHelper::success(data: $weekly_usage);
    }

    public function get_analytics(): void
    {
        ResponseHelper::get_method_check();

        $result = Qr::get_analytics_data();

        if ($result) {
            ResponseHelper::success(data: $result);
        } else {
            ResponseHelper::error(message: "Failed to fetch analytics data");
        }
    }


    /**
     * Search QR codes for AJAX / frontend requests
     */
    public function search(): void
    {
        ResponseHelper::get_method_check();

        try {
            // Get search query from GET parameter
            $query = $_GET['q'] ?? '';

            if (!$query || strlen($query) < 3) {
                ResponseHelper::success();
            }

            // Call the model method
            $results = Qr::search_qrcodes($query);

            if ($results === false) {
                ResponseHelper::error(message: "failed to search");
            }

            // Return success response
            ResponseHelper::success(data: $results);
        } catch (\Exception $e) {
            ResponseHelper::error(message: "Server error");
        }
    }

    // In QrController.php
    public function expiring_qrcodes(): void
    {
        ResponseHelper::get_method_check();

        $daysUntilExpiry = isset($_GET["daysUntilExpiry"]) ? (int)$_GET["daysUntilExpiry"] : 14;

        $result = Qr::get_expiring_qr_codes($daysUntilExpiry);

        if ($result !== false) {
            ResponseHelper::success(data: $result);
        } else {
            ResponseHelper::error(message: "Failed to fetch expiring QR codes.");
        }
    }

    public function qr_analytics(): void
    {
        ResponseHelper::post_method_check();

        if (!isset($_POST['id'])) {
            ResponseHelper::error("Please provide a valid QR code ID");
        }

        $qrId = (int)$_POST['id'];
        $data = Qr::get_qr_code_analytics($qrId);

        if (!$data) {
            ResponseHelper::not_found("QR code not found");
        } else {
            ResponseHelper::success($data);
        }
    }

    public function deactivate_qr(): void
    {
        ResponseHelper::post_method_check();

        if (!isset($_POST['id'])) {
            ResponseHelper::error("QR ID is required");
        }

        $qrId = (int)$_POST['id'];
        $result = Qr::deactivate_qr($qrId);

        if ($result) {
            ResponseHelper::success(['message' => 'QR Code deactivated']);
        } else {
            ResponseHelper::error("Failed to deactivate QR code");
        }
    }




}
