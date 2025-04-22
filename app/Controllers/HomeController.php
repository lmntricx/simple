<?php
namespace app\Controllers;

use app\Middleware;
//use app\Services;

class HomeController {
    public function __construct() {
//        header("Content-type:application/json");
        // Check if the user is logged in
        // Middleware\AuthMiddleware::check();
    }

    public function index() {
//        $server = array(
//            "Status"=>"Success",
//            "IpAddress"=>$_SERVER['REMOTE_ADDR'],
//            "Response Code"=>200,
////            "Host"=>$_SERVER['REMOTE_HOST'],
//            "Port"=>$_SERVER['REMOTE_PORT'],
//            "Message"=>"The API is healthy"
//        );
//
//        echo(json_encode($server));

        include "app/views/header.php";
        include "app/views/home.php";
        include "app/views/footer.php";
    }
}
