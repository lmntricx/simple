<?php

return [
    'model' => Simple\Tests\Fixtures\Models\AuthUser::class,
    'username' => 'email',
    'expiration' => null,
    'bcrypt_rounds' => (int) env('BCRYPT_ROUNDS', 4),
    'verification_expire' => 60,
    'verified_redirect' => null,
    'password_reset_url' => 'http://simple.test/reset-password',
    'password_reset_expire' => 60,
    'password_reset_throttle' => 60,
];
