<?php
return [
    'GET' => [
        '/' => 'HomeController@index',
        '/sign-in' => 'BoardingController@sign_in_form',
        '/sign-up' => 'BoardingController@sign_up_form',
        '/dashboard' => 'DashboardController@index'
    ],
    'POST' => [
        '/sign-in' => 'BoardingController@sign_in',
        '/sign-up' => 'BoardingController@sign_up',
    ],
];


