<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Tests\TestCase;

abstract class AuthTestCase extends TestCase
{
    /**
     * Register through the API. Returns [plain-text token, user id].
     */
    protected function register(string $name = 'Ada', string $email = 'ada@x.io', string $password = 'password123'): array
    {
        $response = $this->postJson('/auth/register', compact('name', 'email', 'password'))->assertCreated();

        return [$response->json('data.token'), $response->json('data.user.id')];
    }

    protected function login(string $email, string $password = 'password123'): string
    {
        return $this->postJson('/auth/login', compact('email', 'password'))->assertOk()->json('data.token');
    }

    protected function user(string $email): ?User
    {
        return User::firstWhere('email', $email);
    }

    /**
     * App-relative path + query of the first link in the last email sent to $email.
     */
    protected function linkFromEmail(string $email): string
    {
        $messages = $this->mail->sentTo($email);
        $this->assertNotEmpty($messages, "No email was sent to $email.");

        preg_match('#https?://[^\s]+#', end($messages)->text, $m);

        return substr($m[0], strlen(config('app.url')));
    }

    protected function withQueryParam(string $link, string $param, string $value): string
    {
        [$path, $query] = explode('?', $link, 2);
        parse_str($query, $params);
        $params[$param] = $value;

        return $path . '?' . http_build_query($params);
    }
}
