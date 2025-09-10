<?php
return [
    'GET' => [
        '/' => 'HomeController@index',
        '/dashboard' => 'DashboardController@index'
    ],
    'POST' => [
        '/sign-in' => 'BoardingController@sign_in',
        '/sign-up' => 'BoardingController@sign_up',
        '/new_qr' => 'HomeController@create_qr',
        '/qrcodes' => 'HomeController@index',
    ],
];


