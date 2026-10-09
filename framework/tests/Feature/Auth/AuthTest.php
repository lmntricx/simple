<?php

namespace Simple\Tests\Feature\Auth;

use Simple\Auth\Auth;
use Simple\Auth\EmailVerification;
use Simple\Auth\Hash;
use Simple\Auth\PasswordBroker;
use Simple\Auth\PersonalAccessToken;
use Simple\Http\Request;
use Simple\Http\Response;
use Simple\Http\TrustedProxies;
use Simple\Http\UrlSigner;
use Simple\Tests\Fixtures\Models\AuthUser;
use Simple\Tests\TestCase;

/**
 * The framework's auth building blocks, without any app controllers.
 */
class AuthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $router = $this->app->router;
        $router->get('/me', fn (Request $r) => Response::success($r->user()))->middleware('auth');
        $router->post('/write', fn () => 'ok')->middleware('auth', 'ability:posts:write');
        $router->get('/verified-only', fn () => 'ok')->middleware('auth', 'verified');
        $router->post('/login', fn () => 'ok')->middleware('throttle:3,1');
        $router->get('/verify/{id}/{hash}', fn (Request $r, string $id, string $hash) => EmailVerification::verify($r, $id, $hash)
            ? Response::success(null, 'verified')
            : Response::error('invalid', 403));
    }

    // ---- Tokens & middleware -------------------------------------------------

    public function test_tokens_are_hashed_and_authenticate_requests(): void
    {
        $user = AuthUser::make();
        $new = $user->createToken('phone');
        [$id, $secret] = explode('|', $new->plainTextToken);

        $this->assertSame(hash('sha256', $secret), PersonalAccessToken::find((int) $id)->getAttribute('token'));

        $this->withToken($new->plainTextToken)->getJson('/me')->assertOk()->assertJsonPath('data.email', 'ada@x.io');
        $this->assertNotNull(PersonalAccessToken::find((int) $id)->last_used_at);
    }

    public function test_bad_and_expired_tokens_are_rejected(): void
    {
        $user = AuthUser::make();
        $valid = $user->createToken('t')->plainTextToken;
        $id = explode('|', $valid)[0];

        foreach (['', 'garbage', "$id|" . str_repeat('0', 40), '999|' . str_repeat('a', 40)] as $bad) {
            $this->withToken($bad)->getJson('/me')->assertUnauthorized();
        }

        $expired = $user->createToken('old', expiresAt: new \DateTime('-1 minute'))->plainTextToken;
        $this->withToken($expired)->getJson('/me')->assertUnauthorized();

        $this->withToken(null)->getJson('/me')->assertUnauthorized()->assertHeader('WWW-Authenticate', 'Bearer');
    }

    public function test_abilities(): void
    {
        $user = AuthUser::make();

        $this->actingAs($user, ['posts:write'])->postJson('/write')->assertOk();
        $this->actingAs($user, ['posts:read'])->postJson('/write')->assertForbidden();
        $this->actingAs($user)->postJson('/write')->assertOk();
    }

    public function test_auth_state_is_reset_between_requests(): void
    {
        $this->actingAs(AuthUser::make())->getJson('/me')->assertOk();
        $this->withToken(null)->getJson('/me')->assertUnauthorized();
        $this->assertNull(Auth::user());
    }

    public function test_attempt_checks_credentials(): void
    {
        AuthUser::make('ada@x.io', 'right-password');

        $this->assertNotNull(Auth::attempt('ada@x.io', 'right-password'));
        $this->assertNull(Auth::attempt('ada@x.io', 'wrong-password'));
        $this->assertNull(Auth::attempt('nobody@x.io', 'right-password'));
    }

    public function test_dummy_hash_is_valid_at_the_configured_cost(): void
    {
        $this->assertSame('2y', password_get_info(Hash::dummy())['algo']);
        $this->assertStringStartsWith('$2y$04$', Hash::dummy(), 'BCRYPT_ROUNDS=4 in tests');
    }

    // ---- Throttling ----------------------------------------------------------

    public function test_throttle_per_client_ip_behind_trusted_proxy(): void
    {
        TrustedProxies::set(['10.0.0.0/8']);
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2']);
        $client = fn (string $ip) => ['X-Forwarded-For' => $ip];

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/login', [], $client('203.0.113.1'))->assertOk();
        }
        $this->postJson('/login', [], $client('203.0.113.1'))->assertStatus(429)->assertHeader('Retry-After');
        $this->postJson('/login', [], $client('203.0.113.2'))->assertOk();
    }

    // ---- Email verification ------------------------------------------------

    public function test_signed_verification_links(): void
    {
        $user = AuthUser::make();
        $this->app->config->set('auth.verified_redirect', null);
        $link = substr(EmailVerification::url($user, '/verify'), strlen('http://simple.test'));

        $this->getJson($link . 'x')->assertForbidden();                            // tampered signature
        $this->getJson(preg_replace('#/verify/\d+/#', '/verify/999/', $link))->assertForbidden();
        $this->assertNull(AuthUser::find($user->id)->email_verified_at);

        $this->getJson($link)->assertOk();
        $this->assertNotNull(AuthUser::find($user->id)->email_verified_at);
    }

    public function test_verified_middleware(): void
    {
        $user = AuthUser::make();
        $this->actingAs($user)->getJson('/verified-only')->assertForbidden();

        $user->forceFill(['email_verified_at' => date('Y-m-d H:i:s')])->save();
        $this->actingAs($user)->getJson('/verified-only')->assertOk();
    }

    public function test_expired_signed_urls_are_rejected(): void
    {
        $url = UrlSigner::sign('/verify/1/abc', [], time() - 1);
        $request = new Request('GET', '/verify/1/abc', $this->query($url));

        $this->assertFalse(UrlSigner::isValid($request));
        $this->assertTrue(UrlSigner::isValid(new Request('GET', '/verify/1/abc', $this->query(UrlSigner::sign('/verify/1/abc', [], time() + 60)))));
    }

    private function query(string $url): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        return $query;
    }

    // ---- Password reset ------------------------------------------------------

    public function test_password_reset_tokens(): void
    {
        $user = AuthUser::make('ada@x.io', 'old-password');
        $apiToken = $user->createToken('phone')->plainTextToken;

        $token = PasswordBroker::createToken($user);
        $this->assertNotNull($token);
        $this->assertNull(PasswordBroker::createToken($user), 'throttled');

        $this->assertNull(PasswordBroker::reset('ada@x.io', 'wrong-token', 'new-password'));
        $this->assertNotNull(PasswordBroker::reset('ADA@x.io', $token, 'new-password'));

        $this->assertNotNull(Auth::attempt('ada@x.io', 'new-password'));
        $this->assertNull(PasswordBroker::reset('ada@x.io', $token, 'again'), 'single use');
        $this->withToken($apiToken)->getJson('/me')->assertUnauthorized();
    }
}
