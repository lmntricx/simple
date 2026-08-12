<?php

return [
    'GET' => [
        '/' => 'MainController@index',
        '/users' => 'UserController@index',
        '/users/{id}' => 'UserController@show',
    ],
    'POST' => [
        '/' => 'MainController@index',
        '/users' => 'UserController@store',
    ],
    'PUT' => [
        '/users/{id}' => 'UserController@update',
    ],
    'DELETE' => [
        '/users/{id}' => 'UserController@destroy',
    ],
];
