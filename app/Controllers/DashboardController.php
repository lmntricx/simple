<?php
namespace app\Controllers;

use app\Middleware\AuthMiddleware;

class DashboardController {
    public function __construct() {
        // Check if the user is logged in
        AuthMiddleware::check();
    }

    public function index() {

//        echo("Elbow grease");
        include 'app/Views/dashboard/header.php';
        include 'app/Views/dashboard/home.php';
//        include 'app/Views/dashboard/footer.php';
    }
}
