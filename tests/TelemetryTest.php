<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use Liberu\Ecommerce\CommerceCore\Actions\AddChannelDomain;
use Liberu\Ecommerce\CommerceCore\Actions\AllocateOrderNumber;
use Liberu\Ecommerce\CommerceCore\Actions\ChangeChannelStatus;
use Liberu\Ecommerce\CommerceCore\Actions\ChangeStoreStatus;
use Liberu\Ecommerce\CommerceCore\Actions\CreateChannel;
use Liberu\Ecommerce\CommerceCore\Actions\CreateStore;
use Liberu\Ecommerce\CommerceCore\Actions\PromoteDomainToPrimary;
use Liberu\Ecommerce\CommerceCore\Actions\RemoveChannelDomain;
use Liberu\Ecommerce\CommerceCore\Actions\SetStoreCapability;
use Liberu\Ecommerce\CommerceCore\Actions\SetStoreSetting;
use Liberu\Ecommerce\CommerceCore\Enums\Capability;
use Liberu\Ecommerce\CommerceCore\Enums\ChannelStatus;
use Liberu\Ecommerce\CommerceCore\Enums\StoreStatus;
use Liberu\Ecommerce\CommerceCore\Models\Channel;
use Liberu\Ecommerce\CommerceCore\Models\Store;

/**
 * Capture what the logger wrote, in order.
 *
 * The reader is a long closure with an explicit `use (&$records)` rather than an
 * arrow function: `fn` captures by value at the point it is defined, so it would
 * hand back the empty array this starts as and never see anything the listener
 * appended.
 */
function captureLog(): Closure
{
    $records = [];

    Log::listen(function ($record) use (&$records) {
        $records[] = ['level' => $record->level, 'message' => $record->message, 'context' => $record->context];
    });

    return function () use (&$records): array {
        return $records;
    };
}

beforeEach(function () {
    config()->set('commerce-core.telemetry.enabled', true);
    config()->set('commerce-core.telemetry.channel', null);
});

it('writes nothing at all until a deployment asks for it', function () {
    config()->set('commerce-core.telemetry.enabled', false);
    $records = captureLog();

    (new CreateStore())->handle('Acme');

    expect($records())->toBe([]);
});

it('records each domain event under a stable name a query can key on', function (Closure $act, string $event) {
    $records = captureLog();

    $act();

    expect(collect($records())->pluck('context.event'))->toContain('commerce-core.'.$event);
})->with([
    // One level of closure, not two. Pest hands a closure inside a dataset row
    // through untouched rather than calling it, so a closure returning a
    // closure meant the test invoked the outer one and asserted on an action
    // that never ran.
    'store created' => [fn () => (new CreateStore())->handle('Acme'), 'store.created'],
    'store status' => [fn () => (new ChangeStoreStatus())->handle(Store::factory()->draft()->create(), StoreStatus::Active), 'store.status_changed'],
    'channel created' => [fn () => (new CreateChannel())->handle(Store::factory()->create(), 'Web'), 'channel.created'],
    'channel status' => [fn () => (new ChangeChannelStatus())->handle(Channel::factory()->draft()->create(), ChannelStatus::Active), 'channel.status_changed'],
    'domain added' => [fn () => (new AddChannelDomain())->handle(Channel::factory()->create(), 'a.example.com'), 'channel_domain.added'],
    'capability' => [fn () => (new SetStoreCapability())->handle(Store::factory()->create(), Capability::GuestCheckout, true), 'store_capability.changed'],
    'setting' => [fn () => (new SetStoreSetting())->handle(Store::factory()->create(), 'k', 'a'), 'store_setting.changed'],
    'order number' => [fn () => (new AllocateOrderNumber())->handle(Store::factory()->create()), 'order_number.allocated'],
]);

it('raises the level when a store stops serving, and not when it starts', function () {
    $store = Store::factory()->draft()->create();
    $records = captureLog();

    (new ChangeStoreStatus())->handle($store, StoreStatus::Active);
    (new ChangeStoreStatus())->handle($store->fresh(), StoreStatus::Suspended);

    $levels = collect($records())->where('context.event', 'commerce-core.store.status_changed')->pluck('level')->all();

    expect($levels)->toBe(['info', 'warning']);
});

it('raises the level when a channel stops serving', function () {
    $channel = Channel::factory()->create();
    $records = captureLog();

    (new ChangeChannelStatus())->handle($channel, ChannelStatus::Disabled);

    expect(collect($records())->firstWhere('context.event', 'commerce-core.channel.status_changed')['level'])->toBe('warning');
});

it('flags the two records that move traffic', function () {
    $channel = Channel::factory()->create();
    (new AddChannelDomain())->handle($channel, 'old.example.com');
    $second = (new AddChannelDomain())->handle($channel, 'new.example.com');
    $records = captureLog();

    (new PromoteDomainToPrimary())->handle($second);
    (new RemoveChannelDomain())->handle($second->fresh());

    $flagged = collect($records())->whereIn('context.event', [
        'commerce-core.channel_domain.primary_changed',
        'commerce-core.channel_domain.removed',
    ]);

    expect($flagged)->toHaveCount(3)
        ->and($flagged->pluck('level')->unique()->all())->toBe(['warning']);
});

it('never writes a setting’s value, only whether there was one', function () {
    $store = Store::factory()->create();
    (new SetStoreSetting())->handle($store, 'payments.secret', 'sk_live_dont_log_me');
    $records = captureLog();

    (new SetStoreSetting())->handle($store, 'payments.secret', 'sk_live_still_dont');

    $record = collect($records())->firstWhere('context.event', 'commerce-core.store_setting.changed');

    expect(json_encode($record))->not->toContain('sk_live')
        ->and($record['context']['key'])->toBe('payments.secret')
        ->and($record['context']['had_previous_value'])->toBeTrue()
        ->and($record['context']['cleared'])->toBeFalse();
});

it('says when a setting was cleared rather than replaced', function () {
    $store = Store::factory()->create();
    (new SetStoreSetting())->handle($store, 'k', 'a');
    $records = captureLog();

    (new SetStoreSetting())->forget($store, 'k');

    expect(collect($records())->firstWhere('context.event', 'commerce-core.store_setting.changed')['context']['cleared'])->toBeTrue();
});

it('carries the identifiers an operator searches by', function () {
    $store = Store::factory()->ownedBy(7)->create();
    $records = captureLog();

    (new AllocateOrderNumber())->handle($store, 'WEB-');

    $record = collect($records())->firstWhere('context.event', 'commerce-core.order_number.allocated');

    expect($record['context']['store_id'])->toBe($store->id)
        ->and($record['context']['number'])->toBe('WEB-000001')
        ->and($record['context']['sequence_value'])->toBe(1);
});
