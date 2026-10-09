<?php

namespace Tests\Feature\Auth;

use Simple\Auth\PasswordBroker;
use Simple\Database\Connection;
use Simple\Database\QueryBuilder;

class PasswordResetTest extends AuthTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->register('Ada', 'ada@x.io');
        $this->register('Bob', 'bob@x.io');
        $this->mail->clear();
    }

    /** @return array{token: string, email: string} */
    private function requestReset(string $email = 'ada@x.io'): array
    {
        $this->postJson('/auth/forgot-password', ['email' => $email])->assertOk();
        parse_str(parse_url($this->linkFromEmail(strtolower($email)), PHP_URL_QUERY), $query);
        return $query;
    }

    private function resetTokens(): QueryBuilder
    {
        return new QueryBuilder(Connection::get(), 'password_reset_tokens');
    }

    public function test_same_response_for_known_and_unknown_emails(): void
    {
        $known = $this->postJson('/auth/forgot-password', ['email' => 'ADA@x.io'])->assertOk();
        $unknown = $this->postJson('/auth/forgot-password', ['email' => 'nobody@x.io'])->assertOk();

        $this->assertSame($known->json(), $unknown->json());
        $this->assertCount(1, $this->mail->sentTo('ada@x.io'), 'known email (any case) gets a link');
        $this->assertCount(0, $this->mail->sentTo('nobody@x.io'));
    }

    public function test_reset_link_points_at_the_frontend(): void
    {
        $this->requestReset();
        $this->assertStringStartsWith(
            'http://simple.test/reset-password?token=',
            preg_match('#https?://\S+#', $this->mail->last()->text, $m) ? $m[0] : '',
        );
    }

    public function test_token_is_stored_hashed(): void
    {
        $query = $this->requestReset();
        $this->assertSame(hash('sha256', $query['token']), $this->resetTokens()->where('email', 'ada@x.io')->first()['token']);
    }

    public function test_requests_for_the_same_address_are_throttled(): void
    {
        $this->requestReset();
        $this->postJson('/auth/forgot-password', ['email' => 'ada@x.io'])->assertOk();

        $this->assertCount(1, $this->mail->sentTo('ada@x.io'), 'second request within 60s sends nothing');
    }

    public function test_wrong_token_fails_and_changes_nothing(): void
    {
        $this->requestReset();
        $this->postJson('/auth/reset-password', ['email' => 'ada@x.io', 'token' => str_repeat('a', 64), 'password' => 'new-password-1'])
            ->assertUnprocessable();

        $this->assertTrue($this->user('ada@x.io')->verifyPassword('password123'));
    }

    public function test_token_only_works_for_its_own_email(): void
    {
        $query = $this->requestReset('ada@x.io');
        $this->postJson('/auth/reset-password', ['email' => 'bob@x.io', 'token' => $query['token'], 'password' => 'new-password-1'])
            ->assertUnprocessable();
    }

    public function test_reset_changes_password_revokes_tokens_and_is_single_use(): void
    {
        $apiToken = $this->user('ada@x.io')->createToken('phone')->plainTextToken;
        $query = $this->requestReset();

        $this->postJson('/auth/reset-password', ['email' => $query['email'], 'token' => $query['token'], 'password' => 'new-password-1'])
            ->assertOk();

        $ada = $this->user('ada@x.io');
        $this->assertTrue($ada->verifyPassword('new-password-1'));
        $this->assertFalse($ada->verifyPassword('password123'));

        $this->withToken($apiToken)->getJson('/auth/me')->assertUnauthorized();
        $this->withToken(null);

        $this->postJson('/auth/reset-password', ['email' => $query['email'], 'token' => $query['token'], 'password' => 'another-pass-2'])
            ->assertUnprocessable();
        $this->postJson('/auth/login', ['email' => 'ada@x.io', 'password' => 'new-password-1'])->assertOk();
    }

    public function test_expired_tokens_are_rejected_and_pruned(): void
    {
        $token = PasswordBroker::createToken($this->user('bob@x.io'));
        $this->resetTokens()->where('email', 'bob@x.io')->update(['created_at' => date('Y-m-d H:i:s', time() - 61 * 60)]);

        $this->assertFalse(PasswordBroker::tokenIsValid('bob@x.io', $token));
        $this->assertSame(1, PasswordBroker::prune());
    }

    public function test_new_password_is_validated(): void
    {
        $this->postJson('/auth/reset-password', ['email' => 'ada@x.io', 'token' => 'x', 'password' => 'short'])
            ->assertJsonValidationErrors(['password']);
    }
}
