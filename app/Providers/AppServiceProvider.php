<?php

namespace App\Providers;

use Simple\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Tell the container how to build things it can't work out on its own,
     * usually interfaces and classes that need configuration values:
     *
     *   $this->app->singleton(PaymentGateway::class, fn () => new StripeGateway(config('services.stripe.key')));
     *
     * Classes whose constructors only take other classes need no registration.
     */
    public function register(): void
    {
    }

    public function boot(): void
    {
    }
}
