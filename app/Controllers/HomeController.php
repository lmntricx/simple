<?php
namespace app\Controllers;

use app\Middleware\AuthMiddleware;

class HomeController {
    public function __construct() {
        // Check if the user is logged in
        // AuthMiddleware::check();
    }

    public function index() {
        include 'app/Views/header.php';
        include 'app/Views/home.php';
        include 'app/Views/footer.php';
    }
}
