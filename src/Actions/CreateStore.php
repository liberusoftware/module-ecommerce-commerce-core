<?php

namespace Liberu\Ecommerce\CommerceCore\Actions;

use Illuminate\Support\Str;
use Liberu\Ecommerce\CommerceCore\Enums\StoreStatus;
use Liberu\Ecommerce\CommerceCore\Events\StoreCreated;
use Liberu\Ecommerce\CommerceCore\Models\Store;

/**
 * The one way a store comes into existence.
 *
 * A slug is derived rather than asked for, and made unique by suffix rather
 * than by rejection: the operator naming a second "Outlet" wants a second
 * store, not a validation error about a field they were never shown.
 */
final class CreateStore
{
    public function handle(string $name, ?int $teamId = null, ?string $currency = null, ?string $locale = null, ?string $timezone = null): Store
    {
        $store = Store::query()->create([
            'team_id' => $teamId,
            'name' => $name,
            'slug' => $this->uniqueSlug($name),
            'status' => StoreStatus::Draft,
            'currency' => $currency ?? config('commerce-core.default_currency'),
            'locale' => $locale ?? config('app.locale'),
            'timezone' => $timezone ?? config('app.timezone'),
        ]);

        StoreCreated::dispatch($store);

        return $store;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'store';
        $slug = $base;

        for ($suffix = 2; Store::query()->where('slug', $slug)->exists(); $suffix++) {
            $slug = $base.'-'.$suffix;
        }

        return $slug;
    }
}
