<?php

namespace app\Controllers;

use app\Services\boardingService;

class BoardingController
{
    public function __construct() {
//        session_start();
//        header("Content-type:application/json");
    }

    public function index(): void{
        $server = array(
            "Status"=>"Success",
            "IpAddress"=>$_SERVER['REMOTE_ADDR'],
            "Response Code"=>200,
            "Port"=>$_SERVER['REMOTE_PORT'],
            "Message"=>"The API is healthy"
        );

        echo(json_encode($server));
    }

    public function sign_in_form(): void {
        include "app/views/boarding/header.php";
        include "app/views/boarding/sign_in.php";
        include "app/views/boarding/footer.php";
    }

    public function sign_up_form(): void {

        include "app/views/boarding/header.php";
        include "app/views/boarding/sign_up.php";
        include "app/views/boarding/footer.php";
    }

    public  function sign_in():void {
        if($_SERVER['REQUEST_METHOD'] === 'POST') {
            if(boardingService::userLogin()){
                header("Location:/dashboard");
            }else{
                header("Location:/sign-in?e=invalid");
            }
        }else{
            header("Location:/sign-in");
        }
    }

    public function sign_up(): void {
        if($_SERVER['REQUEST_METHOD'] === 'POST') {
            if(boardingService::registerUser()){
                header("Location:/dashboard");
            }else{
                header("Location:/sign-up");
            }
        }else{
            header("Location:/sign-up");
        }
    }

}