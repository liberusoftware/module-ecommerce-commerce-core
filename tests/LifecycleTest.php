<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Liberu\Ecommerce\CommerceCore\Actions\ChangeChannelStatus;
use Liberu\Ecommerce\CommerceCore\Actions\ChangeStoreStatus;
use Liberu\Ecommerce\CommerceCore\Actions\CreateChannel;
use Liberu\Ecommerce\CommerceCore\Actions\CreateStore;
use Liberu\Ecommerce\CommerceCore\Enums\ChannelStatus;
use Liberu\Ecommerce\CommerceCore\Enums\StoreStatus;
use Liberu\Ecommerce\CommerceCore\Events\ChannelCreated;
use Liberu\Ecommerce\CommerceCore\Events\ChannelStatusChanged;
use Liberu\Ecommerce\CommerceCore\Events\StoreCreated;
use Liberu\Ecommerce\CommerceCore\Events\StoreStatusChanged;
use Liberu\Ecommerce\CommerceCore\Exceptions\InvalidStatusTransition;
use Liberu\Ecommerce\CommerceCore\Models\Channel;
use Liberu\Ecommerce\CommerceCore\Models\Store;

describe('store status', function () {
    it('admits only the transitions the table declares', function (StoreStatus $from, StoreStatus $to, bool $allowed) {
        expect($from->canTransitionTo($to))->toBe($allowed);
    })->with([
        'draft opens' => [StoreStatus::Draft, StoreStatus::Active, true],
        'draft abandoned' => [StoreStatus::Draft, StoreStatus::Archived, true],
        'draft cannot suspend' => [StoreStatus::Draft, StoreStatus::Suspended, false],
        'active suspends' => [StoreStatus::Active, StoreStatus::Suspended, true],
        'active closes' => [StoreStatus::Active, StoreStatus::Archived, true],
        'active cannot un-open' => [StoreStatus::Active, StoreStatus::Draft, false],
        'suspended resumes' => [StoreStatus::Suspended, StoreStatus::Active, true],
        'archived is terminal' => [StoreStatus::Archived, StoreStatus::Active, false],
    ]);

    it('knows archived is the end of the road', function () {
        expect(StoreStatus::Archived->isTerminal())->toBeTrue()
            ->and(StoreStatus::Active->isTerminal())->toBeFalse()
            ->and(StoreStatus::Active->isServing())->toBeTrue()
            ->and(StoreStatus::Suspended->isServing())->toBeFalse()
            ->and(StoreStatus::Draft->label())->toBe('Draft');
    });
});

describe('channel status', function () {
    it('admits only the transitions the table declares', function (ChannelStatus $from, ChannelStatus $to, bool $allowed) {
        expect($from->canTransitionTo($to))->toBe($allowed);
    })->with([
        'draft opens' => [ChannelStatus::Draft, ChannelStatus::Active, true],
        'draft cannot be disabled before it opens' => [ChannelStatus::Draft, ChannelStatus::Disabled, false],
        'active disables' => [ChannelStatus::Active, ChannelStatus::Disabled, true],
        'disabled resumes' => [ChannelStatus::Disabled, ChannelStatus::Active, true],
        'disabled cannot go back to draft' => [ChannelStatus::Disabled, ChannelStatus::Draft, false],
    ]);

    it('reports whether it serves, and how it is labelled', function () {
        expect(ChannelStatus::Active->isServing())->toBeTrue()
            ->and(ChannelStatus::Disabled->isServing())->toBeFalse()
            ->and(ChannelStatus::Disabled->label())->toBe('Disabled');
    });
});

describe('creating', function () {
    it('creates a store in draft, with the deployment defaults', function () {
        Event::fake([StoreCreated::class]);
        config()->set('commerce-core.default_currency', 'GBP');

        $store = (new CreateStore())->handle('Acme Supplies', teamId: 7);

        expect($store->status)->toBe(StoreStatus::Draft)
            ->and($store->slug)->toBe('acme-supplies')
            ->and($store->currency)->toBe('GBP')
            ->and($store->team_id)->toBe(7);

        Event::assertDispatched(StoreCreated::class, fn (StoreCreated $e) => $e->store->is($store));
    });

    it('suffixes a slug rather than rejecting a repeated name', function () {
        $first = (new CreateStore())->handle('Outlet');
        $second = (new CreateStore())->handle('Outlet');
        $third = (new CreateStore())->handle('Outlet');

        expect([$first->slug, $second->slug, $third->slug])->toBe(['outlet', 'outlet-2', 'outlet-3']);
    });

    it('still produces a slug for a name that slugs to nothing', function () {
        expect((new CreateStore())->handle('日本')->slug)->toBe('store');
    });

    it('creates a channel in draft, overriding nothing by default', function () {
        Event::fake([ChannelCreated::class]);
        $store = Store::factory()->create();

        $channel = (new CreateChannel())->handle($store, 'Web');

        expect($channel->status)->toBe(ChannelStatus::Draft)
            ->and($channel->currency)->toBeNull()
            ->and($channel->locale)->toBeNull();

        Event::assertDispatched(ChannelCreated::class);
    });
});

describe('changing status', function () {
    it('moves a store and announces both ends of the move', function () {
        Event::fake([StoreStatusChanged::class]);
        $store = Store::factory()->draft()->create();

        (new ChangeStoreStatus())->handle($store, StoreStatus::Active);

        expect($store->fresh()->status)->toBe(StoreStatus::Active);
        Event::assertDispatched(
            StoreStatusChanged::class,
            fn (StoreStatusChanged $e) => $e->from === StoreStatus::Draft && $e->to === StoreStatus::Active,
        );
    });

    it('stamps archived_at on the way out, and clears it on the way back', function () {
        $store = Store::factory()->create();

        (new ChangeStoreStatus())->handle($store, StoreStatus::Archived);
        expect($store->fresh()->archived_at)->not->toBeNull();

        $suspended = Store::factory()->create();
        (new ChangeStoreStatus())->handle($suspended, StoreStatus::Suspended);
        expect($suspended->fresh()->archived_at)->toBeNull();
    });

    it('is silent when the store is already there', function () {
        Event::fake([StoreStatusChanged::class]);
        $store = Store::factory()->create();

        (new ChangeStoreStatus())->handle($store, StoreStatus::Active);

        Event::assertNotDispatched(StoreStatusChanged::class);
    });

    it('refuses a transition the table does not admit', function () {
        (new ChangeStoreStatus())->handle(Store::factory()->archived()->create(), StoreStatus::Active);
    })->throws(InvalidStatusTransition::class, 'A store cannot move from [archived] to [active].');

    it('moves a channel, and refuses what the table does not admit', function () {
        Event::fake([ChannelStatusChanged::class]);
        $channel = Channel::factory()->draft()->create();

        (new ChangeChannelStatus())->handle($channel, ChannelStatus::Active);

        expect($channel->fresh()->status)->toBe(ChannelStatus::Active);
        Event::assertDispatched(ChannelStatusChanged::class);

        expect(fn () => (new ChangeChannelStatus())->handle($channel, ChannelStatus::Draft))
            ->toThrow(InvalidStatusTransition::class);
    });

    it('is silent when the channel is already there', function () {
        Event::fake([ChannelStatusChanged::class]);

        (new ChangeChannelStatus())->handle(Channel::factory()->create(), ChannelStatus::Active);

        Event::assertNotDispatched(ChannelStatusChanged::class);
    });
});

describe('serving', function () {
    it('serves only when both the channel and its store do', function (string $storeState, string $channelState, bool $serving) {
        $store = Store::factory()->{$storeState}()->create();
        $channel = Channel::factory()->{$channelState}()->create(['store_id' => $store->id]);

        expect($channel->isServing())->toBe($serving)
            ->and(Channel::query()->serving()->count())->toBe($serving ? 1 : 0);
    })->with([
        'both open' => ['active', 'active', true],
        'store suspended' => ['suspended', 'active', false],
        'channel disabled' => ['active', 'disabled', false],
        'neither' => ['draft', 'draft', false],
    ]);

    it('scopes stores to the ones serving', function () {
        Store::factory()->create();
        Store::factory()->suspended()->create();

        expect(Store::query()->serving()->count())->toBe(1);
    });
});
