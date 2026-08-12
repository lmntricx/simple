<?php

namespace app\Controllers;

use app\Helpers\ResponseHelper;

class MainController
{
    private ResponseHelper $response;

    public function __construct()
    {
        $this->response = new ResponseHelper();
    }

    public function index(): void
    {
        $this->response->success(['message' => 'Simple API is working'], 'Welcome');
    }
}
