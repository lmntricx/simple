<?php

return [
    // The model that logs in. It must `use Simple\Auth\HasApiTokens`.
    'model' => App\Models\User::class,

    // Column checked together with "password" by Auth::attempt().
    'username' => 'email',

    // Minutes until a token expires, counted from creation. null = never.
    // Individual tokens can also be given their own expiry in createToken().
    'expiration' => env('AUTH_TOKEN_EXPIRATION') !== null ? (int) env('AUTH_TOKEN_EXPIRATION') : null,

    // ---- Email verification ----

    // Minutes a verification link stays valid.
    'verification_expire' => 60,

    // Where to send the browser after a verification link is clicked, e.g. your
    // frontend's "email verified" page. "?verified=1" (or "?verified=0" for an
    // invalid/expired link) is appended. null = respond with JSON instead.
    'verified_redirect' => env('EMAIL_VERIFIED_REDIRECT'),

    // ---- Password reset ----

    // Your frontend's reset-password page. "?token=...&email=..." is appended;
    // the page should POST them with the new password to /auth/reset-password.
    'password_reset_url' => env('PASSWORD_RESET_URL', rtrim((string) env('APP_URL', 'http://localhost'), '/') . '/reset-password'),

    // Minutes a reset token stays valid.
    'password_reset_expire' => 60,

    // Seconds before another reset email can be sent to the same address.
    'password_reset_throttle' => 60,
];
