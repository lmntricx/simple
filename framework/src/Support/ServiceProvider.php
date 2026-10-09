<?php

namespace Simple\Support;

use Simple\Application;

/**
 * Register your services with the container. List providers in config/app.php.
 *
 *   class AppServiceProvider extends ServiceProvider {
 *       public function register(): void {
 *           $this->app->singleton(PaymentGateway::class, StripeGateway::class);
 *       }
 *   }
 */
abstract class ServiceProvider
{
    public function __construct(protected Application $app)
    {
    }

    /**
     * Bind things into the container. Don't use other services here; they may not be registered yet.
     */
    public function register(): void
    {
    }

    /**
     * Runs after every provider has registered.
     */
    public function boot(): void
    {
    }
}
