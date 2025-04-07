<?php
namespace app\Controllers;

use app\Middleware;
//use app\Services;

class HomeController {
    public function __construct() {
        // Check if the user is logged in
        // Middleware\AuthMiddleware::check();
    }

    public function index() {
        include 'app/Views/header.php';
        include 'app/Views/home.php';
        include 'app/Views/footer.php';
    }
}
