<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Liberu\Ecommerce\CommerceCore\Actions\SetStoreCapability;
use Liberu\Ecommerce\CommerceCore\Actions\SetStoreSetting;
use Liberu\Ecommerce\CommerceCore\Enums\Capability;
use Liberu\Ecommerce\CommerceCore\Events\StoreCapabilityChanged;
use Liberu\Ecommerce\CommerceCore\Events\StoreSettingChanged;
use Liberu\Ecommerce\CommerceCore\Models\Store;

describe('settings', function () {
    it('stores a structured value and reports what it replaced', function () {
        Event::fake([StoreSettingChanged::class]);
        $store = Store::factory()->create();

        (new SetStoreSetting())->handle($store, 'checkout.terms', ['url' => '/terms', 'required' => true]);

        expect($store->settings()->first()->value)->toBe(['url' => '/terms', 'required' => true]);
        Event::assertDispatched(StoreSettingChanged::class, fn (StoreSettingChanged $e) => $e->from === null && $e->key === 'checkout.terms');
    });

    it('reports the previous value, which is the question an audit actually asks', function () {
        $store = Store::factory()->create();
        (new SetStoreSetting())->handle($store, 'shipping.free_over', 50);
        Event::fake([StoreSettingChanged::class]);

        (new SetStoreSetting())->handle($store, 'shipping.free_over', 75);

        Event::assertDispatched(StoreSettingChanged::class, fn (StoreSettingChanged $e) => $e->from === 50 && $e->to === 75);
    });

    it('says nothing when a form posts back a value that has not changed', function () {
        $store = Store::factory()->create();
        (new SetStoreSetting())->handle($store, 'shipping.free_over', 50);
        Event::fake([StoreSettingChanged::class]);

        (new SetStoreSetting())->handle($store, 'shipping.free_over', 50);

        Event::assertNotDispatched(StoreSettingChanged::class);
        expect($store->settings()->count())->toBe(1);
    });

    it('keeps one row per key per store', function () {
        $store = Store::factory()->create();
        $other = Store::factory()->create();

        (new SetStoreSetting())->handle($store, 'k', 'a');
        (new SetStoreSetting())->handle($store, 'k', 'b');
        (new SetStoreSetting())->handle($other, 'k', 'c');

        expect($store->settings()->count())->toBe(1)
            ->and($store->settings()->first()->value)->toBe('b')
            ->and($other->settings()->first()->value)->toBe('c');
    });

    it('forgets a setting, so the reader falls back to its own default', function () {
        Event::fake([StoreSettingChanged::class]);
        $store = Store::factory()->create();
        (new SetStoreSetting())->handle($store, 'k', 'a');

        (new SetStoreSetting())->forget($store, 'k');

        expect($store->settings()->count())->toBe(0);
        Event::assertDispatched(StoreSettingChanged::class, fn (StoreSettingChanged $e) => $e->from === 'a' && $e->to === null);
    });

    it('forgets a setting that was never set without complaining', function () {
        Event::fake([StoreSettingChanged::class]);

        (new SetStoreSetting())->forget(Store::factory()->create(), 'never-set');

        Event::assertNotDispatched(StoreSettingChanged::class);
    });

    it('belongs to its store', function () {
        $store = Store::factory()->create();
        $setting = (new SetStoreSetting())->handle($store, 'k', 'a');

        expect($setting->store->is($store))->toBeTrue();
    });
});

describe('capabilities', function () {
    it('is off before anybody decides', function () {
        expect(Store::factory()->create()->allows(Capability::GuestCheckout))->toBeFalse()
            ->and(Capability::GuestCheckout->defaultEnabled())->toBeFalse()
            ->and(Capability::MultiCurrency->label())->toBe('Multi Currency');
    });

    it('is on once somebody turns it on', function () {
        Event::fake([StoreCapabilityChanged::class]);
        $store = Store::factory()->create();

        (new SetStoreCapability())->handle($store, Capability::GuestCheckout, true);

        expect($store->fresh()->allows(Capability::GuestCheckout))->toBeTrue()
            ->and($store->fresh()->allows(Capability::Backorders))->toBeFalse();

        Event::assertDispatched(StoreCapabilityChanged::class, fn (StoreCapabilityChanged $e) => $e->enabled === true);
    });

    it('records a decision to keep the default, because that is not the same as no decision', function () {
        Event::fake([StoreCapabilityChanged::class]);
        $store = Store::factory()->create();

        (new SetStoreCapability())->handle($store, Capability::Backorders, false);

        expect($store->capabilities()->count())->toBe(1);
        Event::assertDispatched(StoreCapabilityChanged::class);
    });

    it('says nothing when the decision has not changed', function () {
        $store = Store::factory()->create();
        (new SetStoreCapability())->handle($store, Capability::GuestCheckout, true);
        Event::fake([StoreCapabilityChanged::class]);

        (new SetStoreCapability())->handle($store, Capability::GuestCheckout, true);

        Event::assertNotDispatched(StoreCapabilityChanged::class);
        expect($store->capabilities()->count())->toBe(1);
    });

    it('answers from a loaded relation without a second query', function () {
        $store = Store::factory()->create();
        (new SetStoreCapability())->handle($store, Capability::MultiCurrency, true);

        $loaded = Store::query()->with('capabilities')->find($store->id);

        expect($loaded->allows(Capability::MultiCurrency))->toBeTrue()
            ->and($loaded->allows(Capability::GuestCheckout))->toBeFalse();
    });

    it('belongs to its store', function () {
        $store = Store::factory()->create();
        $grant = (new SetStoreCapability())->handle($store, Capability::GuestCheckout, true);

        expect($grant->store->is($store))->toBeTrue();
    });
});
