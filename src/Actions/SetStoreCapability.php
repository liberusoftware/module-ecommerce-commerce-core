<?php

namespace Liberu\Ecommerce\CommerceCore\Actions;

use Liberu\Ecommerce\CommerceCore\Enums\Capability;
use Liberu\Ecommerce\CommerceCore\Events\StoreCapabilityChanged;
use Liberu\Ecommerce\CommerceCore\Models\Store;
use Liberu\Ecommerce\CommerceCore\Models\StoreCapability;

final class SetStoreCapability
{
    public function handle(Store $store, Capability $capability, bool $enabled): StoreCapability
    {
        $grant = StoreCapability::query()->firstOrNew([
            'store_id' => $store->getKey(),
            'capability' => $capability->value,
        ]);

        // A row that exists and already says this is not a change. A row that
        // does not exist is, even when the requested value matches the default:
        // "nobody decided" and "somebody decided the default" are different
        // facts, and only the second survives a change to the default.
        if ($grant->exists && $grant->enabled === $enabled) {
            return $grant;
        }

        $grant->enabled = $enabled;
        $grant->save();

        StoreCapabilityChanged::dispatch((int) $store->getKey(), $capability, $enabled);

        return $grant;
    }
}
