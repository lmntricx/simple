<?php

/**
 * Authentication tests, run through the full HTTP stack (routes, middleware,
 * controllers) against an in-memory SQLite database:
 *   php tests/auth.php
 */

/** @var \Simple\Application $app */
$app = require __DIR__ . '/helpers.php';

use App\Models\User;
use Simple\Auth\Auth;
use Simple\Auth\PersonalAccessToken;
use Simple\Database\Migrator;
use Simple\Http\Request;
use Simple\Http\Response;
use Simple\Support\RateLimiter;

(new Migrator(base_path('database/migrations')))->migrate();

// Keep rate-limit counters out of storage/ and isolated per run.
RateLimiter::$directory = sys_get_temp_dir() . '/simple_test_ratelimits_' . uniqid();

/**
 * Send a request through the app. Returns [status, decoded JSON, Response].
 */
function call(string $method, string $path, array $body = [], ?string $token = null, string $ip = '10.0.0.1'): array
{
    global $app;
    $headers = ['content-type' => 'application/json'];
    if ($token !== null) {
        $headers['authorization'] = "Bearer $token";
    }

    [$path, $query] = array_pad(explode('?', $path, 2), 2, '');
    parse_str($query, $queryParams);

    $response = $app->handle(new Request($method, $path, $queryParams, $body, $headers, [], ['REMOTE_ADDR' => $ip]));
    $app->terminate(); // run deferred work (emails)

    return [$response->status(), json_decode($response->content(), true), $response];
}

function register(string $name, string $email, string $password = 'password123'): array
{
    [$status, $json] = call('POST', '/auth/register', compact('name', 'email', 'password'), ip: uniqid());
    eq(201, $status, 'register status');
    return [$json['data']['token'], $json['data']['user']['id']];
}

section('Register & login');

test('register returns user + token, never the password', function () {
    [$status, $json] = call('POST', '/auth/register', ['name' => 'Ada', 'email' => 'ada@x.io', 'password' => 'password123']);
    eq(201, $status);
    eq('Ada', $json['data']['user']['name']);
    eq(false, array_key_exists('password', $json['data']['user']));
    eq('Bearer', $json['data']['token_type']);
    eq(1, preg_match('/^\d+\|[0-9a-f]{40}$/', $json['data']['token']));
});

test('register validates input and rejects duplicate emails', function () {
    [$status, $json] = call('POST', '/auth/register', ['name' => 'X', 'email' => 'ada@x.io', 'password' => 'short']);
    eq(422, $status);
    eq(true, isset($json['errors']['email'], $json['errors']['password']));
});

test('password is stored hashed', function () {
    $hash = User::firstWhere('email', 'ada@x.io')->getAttribute('password');
    eq(true, str_starts_with($hash, '$2y$'));
    eq(true, password_verify('password123', $hash));
});

test('login with correct credentials returns a token', function () {
    [$status, $json] = call('POST', '/auth/login', ['email' => 'ada@x.io', 'password' => 'password123', 'device_name' => 'phone']);
    eq(200, $status);
    eq('phone', PersonalAccessToken::find((int) explode('|', $json['data']['token'])[0])->name);
});

test('login with wrong password or unknown email gives the same 401', function () {
    [$s1, $j1] = call('POST', '/auth/login', ['email' => 'ada@x.io', 'password' => 'wrong-password']);
    [$s2, $j2] = call('POST', '/auth/login', ['email' => 'nobody@x.io', 'password' => 'wrong-password']);
    eq(401, $s1);
    eq(401, $s2);
    eq($j1, $j2, 'responses must not reveal whether the email exists');
});

test('token is stored hashed, not in plain text', function () {
    [, $json] = call('POST', '/auth/login', ['email' => 'ada@x.io', 'password' => 'password123']);
    [$id, $secret] = explode('|', $json['data']['token']);
    $stored = PersonalAccessToken::find((int) $id)->getAttribute('token');
    eq(hash('sha256', $secret), $stored);
    eq(false, str_contains(json_encode(PersonalAccessToken::find((int) $id)), $secret));
});

section('Protected routes');

test('no token => 401 with WWW-Authenticate', function () {
    [$status, , $response] = call('GET', '/auth/me');
    eq(401, $status);
    eq('Bearer', $response->headers()['WWW-Authenticate'] ?? null);
});

test('malformed and forged tokens => 401', function () {
    [$token] = register('Mal', 'mal@x.io');
    [$id] = explode('|', $token);
    foreach (['garbage', '|', "$id|", "$id|" . str_repeat('0', 40), 'abc|def', "999999|" . str_repeat('a', 40)] as $bad) {
        eq(401, call('GET', '/auth/me', token: $bad)[0], "token [$bad]");
    }
});

test('valid token => /auth/me returns the user and updates last_used_at', function () {
    [$token, $id] = register('Bob', 'bob@x.io');
    [$status, $json] = call('GET', '/auth/me', token: $token);
    eq(200, $status);
    eq('bob@x.io', $json['data']['email']);
    eq(true, PersonalAccessToken::find((int) explode('|', $token)[0])->last_used_at !== null);
});

test('authenticated user does not leak into the next request', function () {
    [$token] = register('Leak', 'leak@x.io');
    call('GET', '/auth/me', token: $token);
    eq(401, call('GET', '/auth/me')[0]);
    eq(null, Auth::user());
});

test('expired token => 401', function () {
    $user = User::firstWhere('email', 'bob@x.io');
    $token = $user->createToken('old', expiresAt: new DateTime('-1 minute'))->plainTextToken;
    eq(401, call('GET', '/auth/me', token: $token)[0]);
});

test('global expiration from config', function () {
    $user = User::firstWhere('email', 'bob@x.io');
    $new = $user->createToken('aging');
    $new->accessToken->forceFill(['created_at' => date('Y-m-d H:i:s', time() - 3600)])->save();

    config_set('auth.expiration', 30);
    eq(401, call('GET', '/auth/me', token: $new->plainTextToken)[0], 'older than 30 minutes');
    config_set('auth.expiration', 120);
    eq(200, call('GET', '/auth/me', token: $new->plainTextToken)[0], 'younger than 120 minutes');
    config_set('auth.expiration', null);
});

section('Logout');

test('logout revokes only the current token', function () {
    [$t1] = register('Cy', 'cy@x.io');
    [, $json] = call('POST', '/auth/login', ['email' => 'cy@x.io', 'password' => 'password123'], ip: uniqid());
    $t2 = $json['data']['token'];

    eq(200, call('POST', '/auth/logout', token: $t1)[0]);
    eq(401, call('GET', '/auth/me', token: $t1)[0], 'revoked token');
    eq(200, call('GET', '/auth/me', token: $t2)[0], 'other token still valid');
});

test('logout-all revokes every token for that user only', function () {
    [$a1] = register('Dee', 'dee@x.io');
    [, $json] = call('POST', '/auth/login', ['email' => 'dee@x.io', 'password' => 'password123'], ip: uniqid());
    $a2 = $json['data']['token'];
    [$other] = register('Eve', 'eve@x.io');

    [$status, $json] = call('POST', '/auth/logout-all', token: $a1);
    eq(200, $status);
    eq(2, $json['data']['revoked']);
    eq(401, call('GET', '/auth/me', token: $a2)[0]);
    eq(200, call('GET', '/auth/me', token: $other)[0], "another user's token");
});

section('Authorization');

test('users can update themselves but not others', function () {
    [$token, $id] = register('Fay', 'fay@x.io');
    [, $otherId] = register('Gus', 'gus@x.io');

    eq(200, call('PATCH', "/users/$id", ['name' => 'Fay 2'], $token)[0]);
    eq('Fay 2', User::find($id)->name);
    eq(403, call('PATCH', "/users/$otherId", ['name' => 'hacked'], $token)[0]);
    eq('Gus', User::find($otherId)->name);
    eq(403, call('DELETE', "/users/$otherId", token: $token)[0]);
});

test('deleting your account removes your tokens', function () {
    [$token, $id] = register('Hal', 'hal@x.io');
    eq(200, call('DELETE', "/users/$id", token: $token)[0]);
    eq(0, PersonalAccessToken::where('user_id', $id)->count());
    eq(401, call('GET', '/auth/me', token: $token)[0]);
});

test('user list requires auth', function () {
    eq(401, call('GET', '/users')[0]);
});

section('Token abilities');

test('ability middleware checks token abilities', function () {
    global $app;
    $app->router->group(['middleware' => ['auth', 'ability:posts:write']], function ($router) {
        $router->post('/test-abilities', fn () => Response::success('ok'));
    });

    $user = User::firstWhere('email', 'bob@x.io');
    $writer = $user->createToken('w', ['posts:write'])->plainTextToken;
    $reader = $user->createToken('r', ['posts:read'])->plainTextToken;
    $all = $user->createToken('all')->plainTextToken;

    eq(200, call('POST', '/test-abilities', token: $writer)[0]);
    eq(200, call('POST', '/test-abilities', token: $all)[0]);
    eq(403, call('POST', '/test-abilities', token: $reader)[0]);
    eq(401, call('POST', '/test-abilities')[0]);
});

test('tokenCan()', function () {
    $user = User::firstWhere('email', 'bob@x.io');
    $token = $user->createToken('r', ['posts:read']);
    Auth::login($user, $token->accessToken);
    eq(true, $user->tokenCan('posts:read'));
    eq(false, $user->tokenCan('posts:write'));
    Auth::forget();
    eq(false, $user->tokenCan('posts:read'));
});

section('Rate limiting');

test('login is throttled after 10 attempts per minute per IP', function () {
    $ip = '192.0.2.50';
    for ($i = 1; $i <= 10; $i++) {
        [$status, , $response] = call('POST', '/auth/login', ['email' => 'ada@x.io', 'password' => 'nope-nope'], ip: $ip);
        eq(401, $status, "attempt $i");
    }
    eq('0', $response->headers()['X-RateLimit-Remaining']);

    [$status, , $response] = call('POST', '/auth/login', ['email' => 'ada@x.io', 'password' => 'password123'], ip: $ip);
    eq(429, $status, '11th attempt, even with the right password');
    eq(true, (int) $response->headers()['Retry-After'] > 0);

    eq(200, call('POST', '/auth/login', ['email' => 'ada@x.io', 'password' => 'password123'], ip: '192.0.2.51')[0], 'other IP');
});

/**
 * The first link in the last email sent to $email, as an app-relative path + query.
 */
function linkFromEmail(string $email): string
{
    global $mail;
    $messages = $mail->sentTo($email);
    eq(true, $messages !== [], "an email was sent to $email");
    preg_match('#https?://[^\s]+#', end($messages)->text, $m);
    return substr($m[0], strlen('http://simple.test'));
}

function tamper(string $link, string $param, string $value): string
{
    [$path, $query] = explode('?', $link, 2);
    parse_str($query, $params);
    $params[$param] = $value;
    return $path . '?' . http_build_query($params);
}

section('Email verification');

test('registering sends a signed verification link; the user starts unverified', function () {
    global $mail;
    $mail->clear();
    register('Vera', 'vera@x.io');

    $messages = $mail->sentTo('vera@x.io');
    eq(1, count($messages));
    eq(true, str_contains($messages[0]->subject, 'Verify'));
    eq(true, str_contains($messages[0]->html, '<a href="http://simple.test/auth/verify-email/'));
    eq(null, User::firstWhere('email', 'vera@x.io')->email_verified_at);
});

test('the verification link verifies the email', function () {
    [$status] = call('GET', linkFromEmail('vera@x.io'));
    eq(200, $status);
    eq(true, User::firstWhere('email', 'vera@x.io')->email_verified_at !== null);
});

test('tampered, re-pointed or expired links are rejected', function () {
    global $mail;
    register('Tom', 'tom@x.io');
    $link = linkFromEmail('tom@x.io');
    $tomId = User::firstWhere('email', 'tom@x.io')->id;
    $veraId = User::firstWhere('email', 'vera@x.io')->id;

    eq(403, call('GET', tamper($link, 'signature', str_repeat('0', 64)))[0], 'forged signature');
    eq(403, call('GET', tamper($link, 'expires', (string) (time() + 99999)))[0], 'extended expiry');
    eq(403, call('GET', str_replace("/verify-email/$tomId/", "/verify-email/$veraId/", $link))[0], 'other user id');
    eq(403, call('GET', explode('?', $link)[0])[0], 'no signature at all');

    // A genuinely signed link that has expired.
    $expired = Simple\Http\UrlSigner::sign(explode('?', $link)[0], [], time() - 1);
    eq(403, call('GET', substr($expired, strlen('http://simple.test')))[0], 'expired');

    eq(null, User::find($tomId)->email_verified_at);
});

test('verified middleware blocks unverified users', function () {
    global $app;
    $app->router->group(['middleware' => ['auth', 'verified']], function ($router) {
        $router->get('/test-verified', fn () => Response::success('ok'));
    });

    [, , $response] = call('POST', '/auth/login', ['email' => 'tom@x.io', 'password' => 'password123'], ip: uniqid());
    $tomToken = json_decode($response->content(), true)['data']['token'];
    [, $json] = call('POST', '/auth/login', ['email' => 'vera@x.io', 'password' => 'password123'], ip: uniqid());

    eq(403, call('GET', '/test-verified', token: $tomToken)[0], 'unverified');
    eq(200, call('GET', '/test-verified', token: $json['data']['token'])[0], 'verified');
});

test('resend verification email (and not when already verified)', function () {
    global $mail;
    $tom = User::firstWhere('email', 'tom@x.io');
    $token = $tom->createToken('t')->plainTextToken;

    $mail->clear();
    [$status, $json] = call('POST', '/auth/email/verification-notification', token: $token);
    eq(200, $status);
    eq(1, count($mail->sentTo('tom@x.io')));

    eq(200, call('GET', linkFromEmail('tom@x.io'))[0]);

    $mail->clear();
    [, $json] = call('POST', '/auth/email/verification-notification', token: $token);
    eq('Email already verified', $json['message']);
    eq(0, count($mail->messages));
});

test('changing your email un-verifies it, sends a new link, and old links stop working', function () {
    global $mail;
    $vera = User::firstWhere('email', 'vera@x.io');
    $token = $vera->createToken('t')->plainTextToken;
    $oldLink = Simple\Auth\EmailVerification::url($vera);

    $mail->clear();
    eq(200, call('PATCH', "/users/{$vera->id}", ['email' => 'Vera.New@X.io'], $token)[0]);
    $vera = User::find($vera->id);
    eq('vera.new@x.io', $vera->email, 'stored lowercase');
    eq(null, $vera->email_verified_at);

    eq(403, call('GET', substr($oldLink, strlen('http://simple.test')))[0], 'link for the old address');
    eq(200, call('GET', linkFromEmail('vera.new@x.io'))[0]);
});

test('verification redirects to the frontend when configured', function () {
    register('Rae', 'rae@x.io');
    config_set('auth.verified_redirect', 'https://app.test/verified');

    [$status, , $response] = call('GET', linkFromEmail('rae@x.io'));
    eq(302, $status);
    eq('https://app.test/verified?verified=1', $response->headers()['Location']);

    [, , $response] = call('GET', tamper(linkFromEmail('rae@x.io'), 'signature', 'x'));
    eq('https://app.test/verified?verified=0', $response->headers()['Location']);

    config_set('auth.verified_redirect', null);
});

section('Password reset');

test('forgot-password gives the same response for known and unknown emails', function () {
    global $mail;
    $mail->clear();
    [$s1, $j1] = call('POST', '/auth/forgot-password', ['email' => 'ADA@x.io'], ip: uniqid());
    [$s2, $j2] = call('POST', '/auth/forgot-password', ['email' => 'nobody@x.io'], ip: uniqid());

    eq(200, $s1);
    eq([$s1, $j1], [$s2, $j2]);
    eq(1, count($mail->sentTo('ada@x.io')), 'known email (case-insensitive) gets a link');
    eq(0, count($mail->sentTo('nobody@x.io')));
});

test('the reset token is stored hashed', function () {
    parse_str(parse_url(linkFromEmail('ada@x.io'), PHP_URL_QUERY), $q);
    $row = (new Simple\Database\QueryBuilder(Simple\Database\Connection::get(), 'password_reset_tokens'))->where('email', 'ada@x.io')->first();
    eq(hash('sha256', $q['token']), $row['token']);
});

test('reset emails to the same address are throttled', function () {
    global $mail;
    $before = count($mail->sentTo('ada@x.io'));
    call('POST', '/auth/forgot-password', ['email' => 'ada@x.io'], ip: uniqid());
    eq($before, count($mail->sentTo('ada@x.io')), 'second request within 60s sends nothing');
});

test('reset with a wrong token fails and changes nothing', function () {
    [$status] = call('POST', '/auth/reset-password', ['email' => 'ada@x.io', 'token' => str_repeat('a', 64), 'password' => 'new-password-1'], ip: uniqid());
    eq(422, $status);
    eq(true, User::firstWhere('email', 'ada@x.io')->verifyPassword('password123'));
});

test('a token only works for its own email', function () {
    parse_str(parse_url(linkFromEmail('ada@x.io'), PHP_URL_QUERY), $q);
    eq(422, call('POST', '/auth/reset-password', ['email' => 'bob@x.io', 'token' => $q['token'], 'password' => 'new-password-1'], ip: uniqid())[0]);
});

test('reset sets the new password, revokes all tokens, and the token is single-use', function () {
    $ada = User::firstWhere('email', 'ada@x.io');
    $apiToken = $ada->createToken('phone')->plainTextToken;
    parse_str(parse_url(linkFromEmail('ada@x.io'), PHP_URL_QUERY), $q);

    [$status] = call('POST', '/auth/reset-password', ['email' => $q['email'], 'token' => $q['token'], 'password' => 'new-password-1'], ip: uniqid());
    eq(200, $status);

    $ada = User::firstWhere('email', 'ada@x.io');
    eq(true, $ada->verifyPassword('new-password-1'));
    eq(false, $ada->verifyPassword('password123'));
    eq(401, call('GET', '/auth/me', token: $apiToken)[0], 'old API tokens revoked');
    eq(422, call('POST', '/auth/reset-password', ['email' => $q['email'], 'token' => $q['token'], 'password' => 'another-pass-2'], ip: uniqid())[0], 'reused');
    eq(200, call('POST', '/auth/login', ['email' => 'ada@x.io', 'password' => 'new-password-1'], ip: uniqid())[0]);
});

test('expired reset tokens are rejected', function () {
    $broker = Simple\Auth\PasswordBroker::class;
    $bob = User::firstWhere('email', 'bob@x.io');
    $token = $broker::createToken($bob);
    (new Simple\Database\QueryBuilder(Simple\Database\Connection::get(), 'password_reset_tokens'))
        ->where('email', 'bob@x.io')->update(['created_at' => date('Y-m-d H:i:s', time() - 61 * 60)]);

    eq(false, $broker::tokenIsValid('bob@x.io', $token));
    eq(1, $broker::prune());
});

test('reset validates the new password', function () {
    [$status, $json] = call('POST', '/auth/reset-password', ['email' => 'ada@x.io', 'token' => 'x', 'password' => 'short'], ip: uniqid());
    eq(422, $status);
    eq(true, isset($json['errors']['password']));
});

section('Mail failures');

test('a failing mail server does not break registration', function () {
    Simple\Mail\Mail::useTransport(new class implements Simple\Mail\Transport\Transport {
        public function send(Simple\Mail\Message $message): void
        {
            throw new RuntimeException('SMTP down');
        }
    });

    [$status, $json] = call('POST', '/auth/register', ['name' => 'Ned', 'email' => 'ned@x.io', 'password' => 'password123'], ip: uniqid());
    eq(201, $status);
    eq(true, isset($json['data']['token']));

    global $mail;
    Simple\Mail\Mail::useTransport($mail);
});

section('CLI');

test('auth:prune deletes expired tokens only', function () {
    global $app;
    $user = User::firstWhere('email', 'bob@x.io');
    $user->createToken('expired', expiresAt: new DateTime('-1 day'));
    $before = PersonalAccessToken::query()->count();

    ob_start();
    (new Simple\Console\Kernel($app))->handle(['simple', 'auth:prune']);
    ob_end_clean();

    eq(0, PersonalAccessToken::where('expires_at', '<=', date('Y-m-d H:i:s'))->count());
    eq(true, PersonalAccessToken::query()->count() >= $before - 2);
});

// Clean up rate-limit files.
array_map('unlink', glob(RateLimiter::$directory . '/*') ?: []);
@rmdir(RateLimiter::$directory);

function config_set(string $key, mixed $value): void
{
    app()->config->set($key, $value);
}

finish();
