<?php

return [
    // The model that logs in. It must `use Simple\Auth\HasApiTokens`.
    'model' => App\Models\User::class,

    // Column checked together with "password" by Auth::attempt().
    'username' => 'email',

    // Minutes until a token expires, counted from creation. null = never.
    // Individual tokens can also be given their own expiry in createToken().
    'expiration' => env('AUTH_TOKEN_EXPIRATION') !== null ? (int) env('AUTH_TOKEN_EXPIRATION') : null,
];
