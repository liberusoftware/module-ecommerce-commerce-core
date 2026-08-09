<?php

namespace Liberu\Ecommerce\CommerceCore\Actions;

use Liberu\Ecommerce\CommerceCore\Events\StoreSettingChanged;
use Liberu\Ecommerce\CommerceCore\Models\Store;
use Liberu\Ecommerce\CommerceCore\Models\StoreSetting;

/**
 * Write one setting, and say what it was.
 *
 * Silent when the value has not changed: a settings form posts every field it
 * renders, and an event per rendered field turns the audit trail into noise
 * that hides the one field somebody actually edited.
 */
final class SetStoreSetting
{
    public function handle(Store $store, string $key, mixed $value): StoreSetting
    {
        $setting = StoreSetting::query()->firstOrNew([
            'store_id' => $store->getKey(),
            'key' => $key,
        ]);

        $previous = $setting->exists ? $setting->value : null;

        if ($setting->exists && $previous === $value) {
            return $setting;
        }

        $setting->value = $value;
        $setting->save();

        StoreSettingChanged::dispatch((int) $store->getKey(), $key, $previous, $value);

        return $setting;
    }

    /** Remove a setting, so the reader falls back to its own default again. */
    public function forget(Store $store, string $key): void
    {
        $setting = StoreSetting::query()
            ->where('store_id', $store->getKey())
            ->where('key', $key)
            ->first();

        if ($setting === null) {
            return;
        }

        $previous = $setting->value;
        $setting->delete();

        StoreSettingChanged::dispatch((int) $store->getKey(), $key, $previous, null);
    }
}
