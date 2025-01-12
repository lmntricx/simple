<?php
return [
    'GET' => [
        '/' => 'HomeController@index',
        '/login' => 'AuthController@loginForm',
        '/register' => 'AuthController@registerForm',
        '/logout' => 'AuthController@logout',
        '/dashboard' => 'DashboardController@index'
    ],
    'POST' => [
        '/login' => 'AuthController@login',
        '/register' => 'AuthController@register',
    ],
];


