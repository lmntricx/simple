<?php

use App\Controllers\AuthController;
use App\Controllers\HomeController;
use App\Controllers\UserController;
use Simple\Http\Router;

return function (Router $router) {
    $router->get('/', [HomeController::class, 'index']);

    // Public: rate limited to slow down password guessing.
    $router->group(['prefix' => 'auth', 'middleware' => ['throttle:10,1']], function (Router $router) {
        $router->post('/register', [AuthController::class, 'register']);
        $router->post('/login', [AuthController::class, 'login']);
    });

    // Requires "Authorization: Bearer {token}".
    $router->group(['middleware' => ['auth']], function (Router $router) {
        $router->get('/auth/me', [AuthController::class, 'me']);
        $router->post('/auth/logout', [AuthController::class, 'logout']);
        $router->post('/auth/logout-all', [AuthController::class, 'logoutAll']);

        $router->get('/users', [UserController::class, 'index']);
        $router->get('/users/{id}', [UserController::class, 'show']);
        $router->put('/users/{id}', [UserController::class, 'update']);
        $router->patch('/users/{id}', [UserController::class, 'update']);
        $router->delete('/users/{id}', [UserController::class, 'destroy']);
    });

    // Token abilities: ->middleware('auth', 'ability:posts:write')
};
