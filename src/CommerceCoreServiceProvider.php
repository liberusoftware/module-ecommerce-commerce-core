<?php

namespace Liberu\Ecommerce\CommerceCore;

use Illuminate\Support\ServiceProvider;

/**
 * Registered by `ModuleManagerServiceProvider` from `module.json`, never by
 * Composer discovery — the package ships no `extra.laravel.providers`, so an
 * install boots nothing until the deployment names the module in
 * `MODULES_ENABLED`.
 */
class CommerceCoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/commerce-core.php', 'commerce-core');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([
            __DIR__.'/../config/commerce-core.php' => config_path('commerce-core.php'),
        ], 'commerce-core-config');
    }
}
