<?php

namespace Simple\Tests\Fixtures;

use Simple\Support\ServiceProvider;

/**
 * Registered in the fixture app's config/app.php, to test service providers.
 */
class FixtureServiceProvider extends ServiceProvider
{
    public static bool $booted = false;

    public function register(): void
    {
        $this->app->instance('fixture.registered', true);
    }

    public function boot(): void
    {
        self::$booted = $this->app->has('fixture.registered');
    }
}
