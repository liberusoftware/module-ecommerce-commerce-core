<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Liberu\Ecommerce\CommerceCore\Actions\AllocateOrderNumber;
use Liberu\Ecommerce\CommerceCore\Contracts\AllocatesOrderNumbers;
use Liberu\Ecommerce\CommerceCore\Events\OrderNumberAllocated;
use Liberu\Ecommerce\CommerceCore\Models\OrderNumberSequence;
use Liberu\Ecommerce\CommerceCore\Models\Store;

it('starts a store at one, padded', function () {
    Event::fake([OrderNumberAllocated::class]);
    $store = Store::factory()->create();

    expect((new AllocateOrderNumber())->handle($store))->toBe('000001');

    Event::assertDispatched(OrderNumberAllocated::class, fn (OrderNumberAllocated $e) => $e->number === '000001' && $e->sequenceValue === 1);
});

it('never hands the same number out twice', function () {
    $store = Store::factory()->create();

    $numbers = collect(range(1, 25))->map(fn () => (new AllocateOrderNumber())->handle($store));

    expect($numbers->unique())->toHaveCount(25)
        ->and($numbers->first())->toBe('000001')
        ->and($numbers->last())->toBe('000025');
});

it('keeps each store on its own series', function () {
    $first = Store::factory()->create();
    $second = Store::factory()->create();

    (new AllocateOrderNumber())->handle($first);
    (new AllocateOrderNumber())->handle($first);

    expect((new AllocateOrderNumber())->handle($second))->toBe('000001');
});

it('keeps each prefix on its own series within a store', function () {
    $store = Store::factory()->create();

    (new AllocateOrderNumber())->handle($store, 'WEB-');
    (new AllocateOrderNumber())->handle($store, 'WEB-');

    expect((new AllocateOrderNumber())->handle($store, 'WEB-'))->toBe('WEB-000003')
        ->and((new AllocateOrderNumber())->handle($store, 'B2B-'))->toBe('B2B-000001')
        ->and($store->orderSequences()->count())->toBe(2);
});

it('honours a sequence somebody configured rather than the defaults', function () {
    $store = Store::factory()->create();
    OrderNumberSequence::query()->create([
        'store_id' => $store->id,
        'prefix' => 'INV/',
        'next_number' => 4021,
        'pad_to' => 8,
    ]);

    expect((new AllocateOrderNumber())->handle($store, 'INV/'))->toBe('INV/00004021');
});

it('does not truncate a number that has outgrown its padding', function () {
    $store = Store::factory()->create();
    OrderNumberSequence::query()->create(['store_id' => $store->id, 'prefix' => '', 'next_number' => 12345678, 'pad_to' => 6]);

    expect((new AllocateOrderNumber())->handle($store))->toBe('12345678');
});

it('spends the number whether or not an order is ever placed against it', function () {
    $store = Store::factory()->create();

    (new AllocateOrderNumber())->handle($store);

    expect($store->orderSequences()->first()->next_number)->toBe(2);
});

it('is reachable through the contract, so orders never import the action', function () {
    $store = Store::factory()->create();

    expect(app(AllocatesOrderNumbers::class)->handle($store))->toBe('000001');
});

it('belongs to its store', function () {
    $store = Store::factory()->create();
    (new AllocateOrderNumber())->handle($store);

    expect($store->orderSequences()->first()->store->is($store))->toBeTrue();
});
