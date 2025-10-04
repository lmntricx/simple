<?php

namespace app\Controllers;

use app\Middleware;
use app\Services;

class DashboardController
{
    public function __construct() {
        // Check if the user is logged in
        Middleware\AuthMiddleware::check();
    }

    public function index() {
        include "app/views/dashboard/header.php";
        include "app/views/dashboard/home.php";
        include "app/views/footer.php";
    }

}