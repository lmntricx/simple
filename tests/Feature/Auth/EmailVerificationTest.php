<?php

namespace Tests\Feature\Auth;

use Simple\Auth\EmailVerification;
use Simple\Http\Response;
use Simple\Http\UrlSigner;

class EmailVerificationTest extends AuthTestCase
{
    public function test_registering_sends_a_signed_link_and_starts_unverified(): void
    {
        $this->register('Vera', 'vera@x.io');

        $messages = $this->mail->sentTo('vera@x.io');
        $this->assertCount(1, $messages);
        $this->assertStringContainsString('Verify', $messages[0]->subject);
        $this->assertStringContainsString('<a href="http://simple.test/auth/verify-email/', $messages[0]->html);
        $this->assertNull($this->user('vera@x.io')->email_verified_at);
    }

    public function test_the_link_verifies_the_email(): void
    {
        $this->register('Vera', 'vera@x.io');

        $this->getJson($this->linkFromEmail('vera@x.io'))->assertOk();
        $this->assertNotNull($this->user('vera@x.io')->email_verified_at);
    }

    public function test_tampered_or_expired_links_are_rejected(): void
    {
        [, $veraId] = $this->register('Vera', 'vera@x.io');
        [, $tomId] = $this->register('Tom', 'tom@x.io');
        $link = $this->linkFromEmail('tom@x.io');
        $path = explode('?', $link)[0];

        $this->getJson($this->withQueryParam($link, 'signature', str_repeat('0', 64)))->assertForbidden();
        $this->getJson($this->withQueryParam($link, 'expires', (string) (time() + 99999)))->assertForbidden();
        $this->getJson(str_replace("/verify-email/$tomId/", "/verify-email/$veraId/", $link))->assertForbidden();
        $this->getJson($path)->assertForbidden();

        $expired = UrlSigner::sign($path, [], time() - 1);
        $this->getJson(substr($expired, strlen(config('app.url'))))->assertForbidden();

        $this->assertNull($this->user('tom@x.io')->email_verified_at);
    }

    public function test_verified_middleware(): void
    {
        $this->app->router->get('/test-verified', fn () => Response::success('ok'))->middleware('auth', 'verified');
        [$token] = $this->register();
        $this->withToken($token);

        $this->getJson('/test-verified')->assertForbidden();

        $this->getJson($this->linkFromEmail('ada@x.io'))->assertOk();
        $this->getJson('/test-verified')->assertOk();
    }

    public function test_resend_verification_email(): void
    {
        [$token] = $this->register();
        $this->withToken($token);
        $this->mail->clear();

        $this->postJson('/auth/email/verification-notification')->assertOk();
        $this->assertCount(1, $this->mail->sentTo('ada@x.io'));

        $this->getJson($this->linkFromEmail('ada@x.io'))->assertOk();
        $this->mail->clear();

        $this->postJson('/auth/email/verification-notification')->assertJsonPath('message', 'Email already verified');
        $this->assertCount(0, $this->mail->messages);
    }

    public function test_changing_email_requires_verifying_again_and_old_links_stop_working(): void
    {
        [$token, $id] = $this->register('Vera', 'vera@x.io');
        $this->getJson($this->linkFromEmail('vera@x.io'))->assertOk();
        $oldLink = EmailVerification::url($this->user('vera@x.io'));

        $this->withToken($token)->patchJson("/users/$id", ['email' => 'Vera.New@X.io'])->assertOk();

        $user = $this->user('vera.new@x.io');
        $this->assertNotNull($user, 'stored lowercase');
        $this->assertNull($user->email_verified_at);

        $this->getJson(substr($oldLink, strlen(config('app.url'))))->assertForbidden();
        $this->getJson($this->linkFromEmail('vera.new@x.io'))->assertOk();
    }

    public function test_redirects_to_the_frontend_when_configured(): void
    {
        $this->register();
        $this->app->config->set('auth.verified_redirect', 'https://app.test/verified');
        $link = $this->linkFromEmail('ada@x.io');

        $this->getJson($this->withQueryParam($link, 'signature', 'x'))
            ->assertStatus(302)
            ->assertHeader('Location', 'https://app.test/verified?verified=0');

        $this->getJson($link)
            ->assertStatus(302)
            ->assertHeader('Location', 'https://app.test/verified?verified=1');
    }

    public function test_links_respect_the_route_prefix(): void
    {
        $this->app->config->set('app.route_prefix', 'api');
        $this->register();
        $user = $this->user('ada@x.io');

        $this->assertStringStartsWith('http://simple.test/api/auth/verify-email/', EmailVerification::url($user));
    }

    public function test_a_failing_mail_server_does_not_break_registration(): void
    {
        \Simple\Mail\Mail::useTransport(new class implements \Simple\Mail\Transport\Transport {
            public function send(\Simple\Mail\Message $message): void
            {
                throw new \RuntimeException('SMTP down');
            }
        });

        $this->postJson('/auth/register', ['name' => 'Ned', 'email' => 'ned@x.io', 'password' => 'password123'])
            ->assertCreated();
    }
}
