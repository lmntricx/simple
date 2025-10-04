<?php

namespace app\Services;

use app\Models;
use Exception;

class QrService
{
    public static function create_qr(): array
    {
        try {
            // ---------------------------
            // 1️⃣ Get data from POST/FormData
            // ---------------------------
            $short_code = $_POST["name"] ?? null;
            $type = $_POST["type"] ?? null;
            $redirect_url = $_POST["redirect_url"] ?? null;
            // $expiration_date = $_POST["date"] ?? null;
            $expiration_date = !empty($_POST['expiration_date']) ? $_POST['expiration_date'] : NULL;


            // Validate required fields
            if (!$short_code || !$type || !$redirect_url) {
                return ["success" => false, "message" => "Missing required fields"];
            }

            // ---------------------------
            // 2️⃣ Create QR record in database
            // ---------------------------
            $qr_id = Models\Qr::create(
                $type,
                $short_code,
                $redirect_url,
                $expiration_date
            );

            if (!$qr_id['success']) {
                return ["success" => false, "message" => $qr_id['message']];
            }

            // ---------------------------
            // 3️⃣ Handle uploaded image
            // ---------------------------
            $basePath = dirname(__DIR__, 2);
            if (isset($_FILES["image"]) && $_FILES["image"]["error"] === UPLOAD_ERR_OK) {
                // Build the correct path to /api/public/uploads/qrcodes/
                $uploadDir = $basePath . "/public/uploads/qrcodes/";

                // Ensure directory exists
                if (!is_dir($uploadDir)) {
                    print("UPLOAD DIR: " . $uploadDir);

                    if (!mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
                        return [
                            "status" => "error",
                            "message" => "Failed to create upload directory: $uploadDir",
                            "data" => []
                        ];
                    }
                }

                $filename = $qr_id['inserted_id'] . "_" . basename($_FILES["image"]["name"]);
                $targetPath = $uploadDir . $filename;

                if (move_uploaded_file($_FILES["image"]["tmp_name"], $targetPath)) {
                    $imageData = json_encode(["image_path" => "/uploads/qrcodes/" . $filename]);

                    Models\Qr::edit_qr_image($qr_id['inserted_id'], ["custom_design" => $imageData]);
                
                } else {
                    return [
                        "status" => "error",
                        "message" => "QR created but failed to save image. Check directory permissions.",
                        "data" => []
                    ];
                }
            }


            // ---------------------------
            // 4️⃣ Return success
            // ---------------------------
            return ["success" => true, "qr_id" => $qr_id];
        } catch (\Exception $e) {
            // Optional: log $e->getMessage()
            return ["success" => false, "message" => "Server error"];
        }
    }

    public static function edit_qr(): bool
    {
        // Get data from the POST request
        $qr_id = isset($_POST['id']) ? (int) $_POST['id'] : null;
        $short_code = $_POST['short_code'] ?? null;
        $type = $_POST['type'] ?? null;
        $redirect_url = $_POST['redirect_url'] ?? null;
        $isActive = isset($_POST['isActive']) ? filter_var($_POST['isActive'], FILTER_VALIDATE_BOOLEAN) : false;

        // Validate required fields
        if (!$qr_id || !$short_code || !$type || !$redirect_url) {
            // print("We are here");
            return false;
        }

        // Call model method
        return Models\Qr::edit_qr($qr_id, $short_code, $type, $redirect_url, $isActive);
    }


    private static function getUserIP(): string
    {
        // Check for shared internet connections (e.g., Cloudflare, proxy)
        if (!empty($_SERVER["HTTP_CLIENT_IP"])) {
            $ip = $_SERVER["HTTP_CLIENT_IP"];
        } elseif (!empty($_SERVER["HTTP_X_FORWARDED_FOR"])) {
            // Handle multiple IPs if forwarded through proxies.
            $ipList = explode(",", $_SERVER["HTTP_X_FORWARDED_FOR"]);
            $ip = trim($ipList[0]); // Get the first real IP.
        } else {
            $ip = $_SERVER["REMOTE_ADDR"]; // Default method.
        }
        return $ip;
    }

    private static function getBrowser($userAgent): string
    {
        if (strpos($userAgent, "Firefox") !== false) {
            return "Firefox";
        } elseif (
            strpos($userAgent, "Chrome") !== false &&
            strpos($userAgent, "Chromium") === false
        ) {
            return "Chrome";
        } elseif (
            strpos($userAgent, "Safari") !== false &&
            strpos($userAgent, "Chrome") === false
        ) {
            return "Safari";
        } elseif (strpos($userAgent, "Edge") !== false) {
            return "Edge";
        } elseif (
            strpos($userAgent, "OPR") !== false ||
            strpos($userAgent, "Opera") !== false
        ) {
            return "Opera";
        } elseif (
            strpos($userAgent, "MSIE") !== false ||
            strpos($userAgent, "Trident") !== false
        ) {
            return "Internet Explorer";
        }
        return "Unknown Browser";
    }

    private static function getOS(string $userAgent): string
    {
        // Android with version
        if (preg_match("/Android\s+([\d\.]+)/i", $userAgent, $matches)) {
            return "Android " . $matches[1];
        }

        // iPhone with iOS version
        if (preg_match("/iPhone.*OS\s([\d_]+)/i", $userAgent, $matches)) {
            return "iOS " . str_replace("_", ".", $matches[1]);
        }

        // iPad with iOS version
        if (preg_match("/iPad.*OS\s([\d_]+)/i", $userAgent, $matches)) {
            return "iPadOS " . str_replace("_", ".", $matches[1]);
        }

        // Windows
        if (preg_match("/Windows NT ([\d\.]+)/i", $userAgent, $matches)) {
            return "Windows " . $matches[1];
        }

        // Mac OS
        if (preg_match("/Macintosh/i", $userAgent)) {
            return "Mac OS";
        }

        // Linux
        if (preg_match("/Linux/i", $userAgent)) {
            return "Linux";
        }

        return "Unknown OS";
    }

    private static function getDevice(string $userAgent): string
    {
        // iPhone
        if (stripos($userAgent, "iPhone") !== false) {
            return "iPhone";
        }

        // iPad
        if (stripos($userAgent, "iPad") !== false) {
            return "iPad";
        }

        // Android with model (e.g., SM-G973F)
        if (preg_match("/Android.*;\s?([^;]*Build)/i", $userAgent, $matches)) {
            $model = trim(str_replace("Build", "", $matches[1]));
            return $model ?: "Android Device";
        }

        // Generic Mobile/Tablet detection
        if (preg_match("/mobile/i", $userAgent)) {
            return "Mobile";
        }
        if (preg_match("/tablet/i", $userAgent)) {
            return "Tablet";
        }

        return "Desktop";
    }


    public static function qr_scanned(): bool|string|array
    {
        try {
            $qr_code_id = $_POST["id"];

            // get device and geolocation details if possibles.
            $user_ip = self::getUserIP();
            $agent_data = $_SERVER["HTTP_USER_AGENT"];
            $browser_agent = self::getBrowser($agent_data);
            $user_os = self::getOS($agent_data);
            $user_device = self::getDevice($agent_data);


            $qr_data = Models\Qr::single_qr($qr_code_id);
            if (!empty($qr_data)) {
                $redirect_url = $qr_data["redirect_url"];

                // record user device info.
                $result = Models\Logs::record_scan_transaction(
                    $qr_code_id,
                    $user_ip,
                    $agent_data,
                    $browser_agent,
                    $user_os,
                    $user_device,
                );

                // print($redirect_url);

                return $redirect_url;
            } else {
                return false;
            }
        } catch (Exception $e) {
            print($e->getMessage());
            return false;
        }
    }
}
