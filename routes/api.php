<?php

use App\Controllers\HomeController;
use App\Controllers\UserController;
use Simple\Http\Router;

return function (Router $router) {
    $router->get('/', [HomeController::class, 'index']);

    $router->resource('users', UserController::class);

    // Groups share a prefix and/or middleware:
    // $router->group(['prefix' => 'admin', 'middleware' => ['auth']], function (Router $router) {
    //     $router->get('/stats', [AdminController::class, 'stats']);
    // });
};
