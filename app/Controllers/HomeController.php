<?php

namespace App\Controllers;

use Simple\Http\Request;
use Simple\Http\Response;

class HomeController
{
    public function index(Request $request): Response
    {
        return Response::success([
            'app' => config('app.name'),
            'version' => \Simple\Application::VERSION,
        ], 'It works');
    }
}
