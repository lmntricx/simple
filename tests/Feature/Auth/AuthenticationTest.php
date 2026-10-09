<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Simple\Auth\Auth;
use Simple\Auth\PersonalAccessToken;
use Simple\Http\Response;

class AuthenticationTest extends AuthTestCase
{
    // ---- Register & login ---------------------------------------------------

    public function test_register_returns_user_and_token_without_password(): void
    {
        $response = $this->postJson('/auth/register', ['name' => 'Ada', 'email' => 'ada@x.io', 'password' => 'password123'])
            ->assertCreated()
            ->assertJsonPath('data.user.name', 'Ada')
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonMissingPath('data.user.password');

        $this->assertMatchesRegularExpression('/^\d+\|[0-9a-f]{40}$/', $response->json('data.token'));
    }

    public function test_register_validates_and_rejects_duplicate_emails(): void
    {
        $this->register();

        $this->postJson('/auth/register', ['name' => 'X', 'email' => 'ADA@x.io', 'password' => 'short'])
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_password_and_email_are_normalised_on_storage(): void
    {
        $this->register('Ada', '  Ada@X.io ');
        $user = $this->user('ada@x.io');

        $this->assertNotNull($user);
        $this->assertStringStartsWith('$2y$', $user->getAttribute('password'));
        $this->assertTrue($user->verifyPassword('password123'));
    }

    public function test_login_returns_a_named_token(): void
    {
        $this->register();
        $token = $this->postJson('/auth/login', ['email' => 'ada@x.io', 'password' => 'password123', 'device_name' => 'phone'])
            ->assertOk()
            ->json('data.token');

        $this->assertSame('phone', PersonalAccessToken::find((int) explode('|', $token)[0])->name);
    }

    public function test_wrong_password_and_unknown_email_get_identical_responses(): void
    {
        $this->register();
        $wrongPassword = $this->postJson('/auth/login', ['email' => 'ada@x.io', 'password' => 'wrong-password'])->assertUnauthorized();
        $unknownEmail = $this->postJson('/auth/login', ['email' => 'nobody@x.io', 'password' => 'wrong-password'])->assertUnauthorized();

        $this->assertSame($wrongPassword->json(), $unknownEmail->json());
    }

    public function test_tokens_are_stored_hashed(): void
    {
        [$token] = $this->register();
        [$id, $secret] = explode('|', $token);
        $stored = PersonalAccessToken::find((int) $id);

        $this->assertSame(hash('sha256', $secret), $stored->getAttribute('token'));
        $this->assertStringNotContainsString($secret, json_encode($stored));
    }

    // ---- Protected routes ---------------------------------------------------

    public function test_missing_token_is_rejected(): void
    {
        $this->getJson('/auth/me')->assertUnauthorized()->assertHeader('WWW-Authenticate', 'Bearer');
    }

    public static function badTokens(): array
    {
        return [
            'garbage' => ['garbage'],
            'separator only' => ['|'],
            'id only' => ['{id}|'],
            'wrong secret' => ['{id}|' . str_repeat('0', 40)],
            'non-numeric id' => ['abc|def'],
            'unknown id' => ['999999|' . str_repeat('a', 40)],
        ];
    }

    #[DataProvider('badTokens')]
    public function test_malformed_and_forged_tokens_are_rejected(string $bad): void
    {
        [$token] = $this->register();
        $id = explode('|', $token)[0];

        $this->withToken(str_replace('{id}', $id, $bad))->getJson('/auth/me')->assertUnauthorized();
    }

    public function test_valid_token_authenticates_and_records_last_use(): void
    {
        [$token] = $this->register();

        $this->withToken($token)->getJson('/auth/me')->assertOk()->assertJsonPath('data.email', 'ada@x.io');
        $this->assertNotNull(PersonalAccessToken::find((int) explode('|', $token)[0])->last_used_at);
    }

    public function test_authenticated_user_does_not_leak_into_the_next_request(): void
    {
        [$token] = $this->register();
        $this->withToken($token)->getJson('/auth/me')->assertOk();

        $this->withToken(null)->getJson('/auth/me')->assertUnauthorized();
        $this->assertNull(Auth::user());
    }

    public function test_expired_token_is_rejected(): void
    {
        $this->register();
        $token = $this->user('ada@x.io')->createToken('old', expiresAt: new \DateTime('-1 minute'))->plainTextToken;

        $this->withToken($token)->getJson('/auth/me')->assertUnauthorized();
    }

    public function test_global_token_expiration(): void
    {
        $this->register();
        $new = $this->user('ada@x.io')->createToken('aging');
        $new->accessToken->forceFill(['created_at' => date('Y-m-d H:i:s', time() - 3600)])->save();
        $this->withToken($new->plainTextToken);

        $this->app->config->set('auth.expiration', 30);
        $this->getJson('/auth/me')->assertUnauthorized();

        $this->app->config->set('auth.expiration', 120);
        $this->getJson('/auth/me')->assertOk();
    }

    // ---- Logout -------------------------------------------------------------

    public function test_logout_revokes_only_the_current_token(): void
    {
        [$first] = $this->register();
        $second = $this->login('ada@x.io');

        $this->withToken($first)->postJson('/auth/logout')->assertOk();
        $this->withToken($first)->getJson('/auth/me')->assertUnauthorized();
        $this->withToken($second)->getJson('/auth/me')->assertOk();
    }

    public function test_logout_all_revokes_every_token_for_that_user_only(): void
    {
        [$first] = $this->register('Ada', 'ada@x.io');
        $second = $this->login('ada@x.io');
        [$other] = $this->register('Eve', 'eve@x.io');

        $this->withToken($first)->postJson('/auth/logout-all')->assertOk()->assertJsonPath('data.revoked', 2);
        $this->withToken($second)->getJson('/auth/me')->assertUnauthorized();
        $this->withToken($other)->getJson('/auth/me')->assertOk();
    }

    // ---- Authorization ------------------------------------------------------

    public function test_users_can_update_themselves_but_not_others(): void
    {
        [$token, $id] = $this->register('Fay', 'fay@x.io');
        [, $otherId] = $this->register('Gus', 'gus@x.io');
        $this->withToken($token);

        $this->patchJson("/users/$id", ['name' => 'Fay 2'])->assertOk();
        $this->assertSame('Fay 2', User::find($id)->name);

        $this->patchJson("/users/$otherId", ['name' => 'hacked'])->assertForbidden();
        $this->deleteJson("/users/$otherId")->assertForbidden();
        $this->assertSame('Gus', User::find($otherId)->name);
    }

    public function test_deleting_your_account_removes_your_tokens(): void
    {
        [$token, $id] = $this->register();

        $this->withToken($token)->deleteJson("/users/$id")->assertOk();
        $this->assertSame(0, PersonalAccessToken::where('user_id', $id)->count());
        $this->getJson('/auth/me')->assertUnauthorized();
    }

    public function test_user_routes_require_authentication(): void
    {
        $this->getJson('/users')->assertUnauthorized();
        $this->getJson('/users/1')->assertUnauthorized();
    }

    public function test_unknown_user_id_is_404_not_500(): void
    {
        $this->register();
        $this->actingAs($this->user('ada@x.io'));

        $this->getJson('/users/999')->assertNotFound();
        $this->getJson('/users/abc')->assertNotFound();
    }

    // ---- Abilities ----------------------------------------------------------

    public function test_ability_middleware(): void
    {
        $this->app->router->post('/test-abilities', fn () => Response::success('ok'))->middleware('auth', 'ability:posts:write');
        $this->register();
        $user = $this->user('ada@x.io');

        $this->actingAs($user, ['posts:write'])->postJson('/test-abilities')->assertOk();
        $this->actingAs($user)->postJson('/test-abilities')->assertOk();
        $this->actingAs($user, ['posts:read'])->postJson('/test-abilities')->assertForbidden();
        $this->withToken(null)->postJson('/test-abilities')->assertUnauthorized();
    }

    public function test_token_can(): void
    {
        $this->register();
        $user = $this->user('ada@x.io');
        $token = $user->createToken('r', ['posts:read']);

        Auth::login($user, $token->accessToken);
        $this->assertTrue($user->tokenCan('posts:read'));
        $this->assertFalse($user->tokenCan('posts:write'));

        Auth::forget();
        $this->assertFalse($user->tokenCan('posts:read'));
    }

    // ---- Rate limiting ------------------------------------------------------

    public function test_login_is_throttled_per_ip(): void
    {
        $this->register();
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.50']);

        for ($i = 1; $i <= 10; $i++) {
            $last = $this->postJson('/auth/login', ['email' => 'ada@x.io', 'password' => 'nope-nope'])->assertUnauthorized();
        }
        $last->assertHeader('X-RateLimit-Remaining', '0');

        $blocked = $this->postJson('/auth/login', ['email' => 'ada@x.io', 'password' => 'password123'])->assertStatus(429);
        $this->assertGreaterThan(0, (int) $blocked->header('Retry-After'));

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.51'])
            ->postJson('/auth/login', ['email' => 'ada@x.io', 'password' => 'password123'])
            ->assertOk();
    }

    public function test_throttling_uses_the_real_client_ip_behind_a_trusted_proxy(): void
    {
        $this->app->config->set('app.trusted_proxies', ['10.0.0.0/8']);
        \Simple\Http\TrustedProxies::set(['10.0.0.0/8']);

        // Every request arrives from the same load balancer IP...
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2']);

        // ...one client uses up its limit...
        for ($i = 0; $i < 11; $i++) {
            $this->postJson('/auth/login', ['email' => 'x@x.io', 'password' => 'x'], ['X-Forwarded-For' => '203.0.113.1']);
        }
        $this->postJson('/auth/login', ['email' => 'x@x.io', 'password' => 'x'], ['X-Forwarded-For' => '203.0.113.1'])->assertStatus(429);

        // ...and a different client behind the same proxy is unaffected.
        $this->postJson('/auth/login', ['email' => 'x@x.io', 'password' => 'x'], ['X-Forwarded-For' => '203.0.113.2'])->assertUnauthorized();

        \Simple\Http\TrustedProxies::set([]);
    }

    // ---- CLI ----------------------------------------------------------------

    public function test_auth_prune_deletes_only_expired_tokens(): void
    {
        $this->register();
        $user = $this->user('ada@x.io');
        $user->createToken('expired', expiresAt: new \DateTime('-1 day'));
        $user->createToken('valid', expiresAt: new \DateTime('+1 day'));

        $this->runCommand(['auth:prune']);

        $this->assertSame(0, PersonalAccessToken::where('expires_at', '<=', date('Y-m-d H:i:s'))->count());
        $this->assertSame(2, PersonalAccessToken::where('user_id', $user->id)->count(), 'register token + valid token');
    }

    private function runCommand(array $args): int
    {
        return (new \Simple\Console\Kernel($this->app, fopen('php://memory', 'w')))->handle(['simple', ...$args]);
    }
}
