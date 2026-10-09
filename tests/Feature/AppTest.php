<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Checks this app's own wiring (routes, providers, schedule).
 */
class AppTest extends TestCase
{
    protected bool $migrate = false;

    public function test_home_route(): void
    {
        $this->getJson('/')->assertOk()->assertJsonPath('message', 'It works');
    }

    public function test_app_service_provider_is_registered(): void
    {
        $this->assertContains(\App\Providers\AppServiceProvider::class, $this->app->config->get('app.providers'));
    }

    public function test_expired_tokens_are_pruned_daily(): void
    {
        $out = fopen('php://memory', 'w+');
        (new \Simple\Console\Kernel($this->app, $out))->handle(['simple', 'schedule:list']);
        rewind($out);

        $this->assertMatchesRegularExpression('/0 0 \* \* \*\s+simple auth:prune/', stream_get_contents($out));
    }
}
