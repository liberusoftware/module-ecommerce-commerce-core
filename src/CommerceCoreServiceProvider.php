<?php

namespace Liberu\Ecommerce\CommerceCore;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Liberu\Ecommerce\CommerceCore\Actions\AllocateOrderNumber;
use Liberu\Ecommerce\CommerceCore\Contracts\AllocatesOrderNumbers;
use Liberu\Ecommerce\CommerceCore\Contracts\ResolvesCommercialContext;
use Liberu\Ecommerce\CommerceCore\Models\Channel;
use Liberu\Ecommerce\CommerceCore\Models\Store;
use Liberu\Ecommerce\CommerceCore\Policies\ChannelPolicy;
use Liberu\Ecommerce\CommerceCore\Policies\StorePolicy;
use Liberu\Ecommerce\CommerceCore\Services\CommercialContextResolver;

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

        // Bound to the interfaces rather than left to autowiring, so a consumer
        // in another package type-hints the contract and never the class.
        $this->app->bind(ResolvesCommercialContext::class, CommercialContextResolver::class);
        $this->app->bind(AllocatesOrderNumbers::class, AllocateOrderNumber::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Registered here rather than left to Laravel's convention: the
        // convention maps `App\Models\X` to `App\Policies\XPolicy`, and this
        // module's models are in neither namespace.
        Gate::policy(Store::class, StorePolicy::class);
        Gate::policy(Channel::class, ChannelPolicy::class);

        $this->publishes([
            __DIR__.'/../config/commerce-core.php' => config_path('commerce-core.php'),
        ], 'commerce-core-config');
    }
}
