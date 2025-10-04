<?php
return [
    "GET" => [
        "/" => "HomeController@index",
        "/scanned" => "ScanController@qr_scanned",
        "/single_qr" => "HomeController@single_qr",

        "/dashboard" => "HomeController@dash_stats",
        "/weekly_usage" => "HomeController@weekly_usage",
        "/qrcodes/search" => "HomeController@search",
        "/users" => "HomeController@get_users",

        "/analytics" => "HomeController@get_analytics",
        "/expiring_qrcodes" => "HomeController@expiring_qrcodes",

    ],
    "POST" => [
        "/sign-in" => "BoardingController@sign_in",
        "/set-up" => "BoardingController@setup_system",
        "/new_qr" => "HomeController@create_qr",
        "/qrcodes" => "HomeController@index",
        "/delete_qr" => "HomeController@delete_qr",
        "/create_qr" => "HomeController@create_qr",
        "/edit_qr" => "HomeController@edit_qr",
        "/scanned" => "ScanController@qr_scanned",

        "/qr/analytics" => "HomeController@qr_analytics",

        "/users/create" => "HomeController@create_user",
        "/users/edit" => "HomeController@edit_user",
        "/users/delete" => "HomeController@delete_user",
    ],
];
