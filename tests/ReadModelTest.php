<?php

declare(strict_types=1);

use Liberu\Ecommerce\CommerceCore\Actions\AddChannelDomain;
use Liberu\Ecommerce\CommerceCore\Actions\SetStoreCapability;
use Liberu\Ecommerce\CommerceCore\Data\ChannelData;
use Liberu\Ecommerce\CommerceCore\Data\StoreData;
use Liberu\Ecommerce\CommerceCore\Enums\Capability;
use Liberu\Ecommerce\CommerceCore\Enums\ChannelStatus;
use Liberu\Ecommerce\CommerceCore\Enums\StoreStatus;
use Liberu\Ecommerce\CommerceCore\Models\Channel;
use Liberu\Ecommerce\CommerceCore\Models\Store;
use Liberu\Ecommerce\CommerceCore\Queries\ChannelQuery;
use Liberu\Ecommerce\CommerceCore\Queries\StoreQuery;
use Liberu\Ecommerce\CommerceCore\Services\CommerceAccess;
use Liberu\PackageTestbench\TestUser;

describe('store data', function () {
    it('carries what a consumer is allowed to see, capabilities included', function () {
        $store = Store::factory()->ownedBy(7)->create(['name' => 'Acme', 'slug' => 'acme', 'currency' => 'GBP']);
        (new SetStoreCapability())->handle($store, Capability::GuestCheckout, true);

        $data = StoreData::from($store->fresh());

        expect($data->toArray())->toBe([
            'id' => $store->id,
            'team_id' => 7,
            'name' => 'Acme',
            'slug' => 'acme',
            'status' => 'active',
            'currency' => 'GBP',
            'locale' => 'en',
            'timezone' => 'UTC',
            'archived_at' => null,
            'capabilities' => ['guest_checkout'],
        ])->and(json_decode(json_encode($data), true))->toBe($data->toArray());
    });

    it('lists no capability for a store where nobody has turned one on', function () {
        expect(StoreData::from(Store::factory()->create())->capabilities)->toBe([]);
    });

    it('reports an archive date as an instant a client can parse', function () {
        $store = Store::factory()->archived()->create();

        expect(StoreData::from($store->fresh())->archivedAt)->toBeString()
            ->and(StoreData::from($store->fresh())->status)->toBe(StoreStatus::Archived);
    });
});

describe('channel data', function () {
    it('carries its hostnames and which one is canonical', function () {
        $channel = Channel::factory()->create(['name' => 'Web']);
        (new AddChannelDomain())->handle($channel, 'example.com');
        (new AddChannelDomain())->handle($channel, 'www.example.com');

        $data = ChannelData::from($channel->fresh());

        expect($data->primaryHost)->toBe('example.com')
            ->and($data->domains)->toHaveCount(2)
            ->and($data->domains[1]->host)->toBe('www.example.com')
            ->and($data->domains[1]->isPrimary)->toBeFalse()
            ->and($data->status)->toBe(ChannelStatus::Active)
            ->and($data->toArray()['domains'][0]['host'])->toBe('example.com')
            ->and(json_decode(json_encode($data), true))->toBe($data->toArray());
    });

    it('keeps an inherited currency null, because null is the fact', function () {
        $data = ChannelData::from(Channel::factory()->create(['store_id' => Store::factory()->create(['currency' => 'GBP'])]));

        expect($data->currency)->toBeNull()
            ->and($data->locale)->toBeNull()
            ->and($data->primaryHost)->toBeNull();
    });
});

describe('queries', function () {
    it('pages a team’s stores and nobody else’s', function () {
        Store::factory()->count(3)->ownedBy(7)->create();
        Store::factory()->ownedBy(8)->create();

        $page = (new StoreQuery())->paginate(teamId: 7, perPage: 2);

        expect($page->total())->toBe(3)
            ->and($page->items())->toHaveCount(2)
            ->and($page->items()[0])->toBeInstanceOf(StoreData::class);
    });

    it('pages every store when the caller explicitly asks for no team', function () {
        Store::factory()->ownedBy(7)->create();
        Store::factory()->ownedBy(8)->create();

        expect((new StoreQuery())->paginate(teamId: null)->total())->toBe(2);
    });

    it('finds a store by key and by slug, and reports absence as null', function () {
        $store = Store::factory()->create(['slug' => 'acme']);

        expect((new StoreQuery())->find($store->id)->slug)->toBe('acme')
            ->and((new StoreQuery())->findBySlug('acme')->id)->toBe($store->id)
            ->and((new StoreQuery())->find(9999))->toBeNull()
            ->and((new StoreQuery())->findBySlug('nope'))->toBeNull();
    });

    it('pages a store’s channels, and finds one by key', function () {
        $store = Store::factory()->create();
        $channel = Channel::factory()->create(['store_id' => $store->id]);
        Channel::factory()->create();

        expect((new ChannelQuery())->paginateForStore($store->id)->total())->toBe(1)
            ->and((new ChannelQuery())->find($channel->id)->id)->toBe($channel->id)
            ->and((new ChannelQuery())->find(9999))->toBeNull();
    });
});

describe('access by id', function () {
    it('answers the policy question without the caller holding a model', function () {
        $actor = TestUser::factory()->create();
        $actor->current_team_id = 7;
        $mine = Store::factory()->ownedBy(7)->create();
        $theirs = Store::factory()->ownedBy(8)->create();
        $channel = Channel::factory()->create(['store_id' => $mine->id]);

        $access = new CommerceAccess();

        expect($access->toStore($actor, 'view', $mine->id))->toBeTrue()
            ->and($access->toStore($actor, 'view', $theirs->id))->toBeFalse()
            ->and($access->toStore($actor, 'viewAny'))->toBeTrue()
            ->and($access->toChannel($actor, 'view', $channel->id))->toBeTrue()
            ->and($access->toChannel($actor, 'viewAny'))->toBeTrue();
    });

    it('denies a subject that is not there rather than saying so', function () {
        $actor = TestUser::factory()->create();
        $actor->current_team_id = 7;

        $access = new CommerceAccess();

        expect($access->toStore($actor, 'view', 9999))->toBeFalse()
            ->and($access->toChannel($actor, 'view', 9999))->toBeFalse();
    });
});
