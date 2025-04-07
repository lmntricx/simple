<?php

namespace app\Controllers;

use app\Services;

class BoardingController
{
    public function __construct() {
        header("Content-type:application/json");
    }

    public function index() {
        $server = array(
            "Status"=>"Success",
            "IpAddress"=>$_SERVER['REMOTE_ADDR'],
            "Response Code"=>200,
            "Port"=>$_SERVER['REMOTE_PORT'],
            "Message"=>"The API is healthy"
        );

        echo(json_encode($server));
    }

}