<?php

declare(strict_types=1);

use Liberu\Ecommerce\CommerceCore\Models\Channel;
use Liberu\Ecommerce\CommerceCore\Models\ChannelDomain;
use Liberu\Ecommerce\CommerceCore\Models\Store;
use Liberu\PackageTestbench\TestUser;

it('canonicalises on the domain flagged primary, not the first one added', function () {
    $channel = Channel::factory()->create();
    ChannelDomain::factory()->create(['channel_id' => $channel->id, 'host' => 'www.example.com']);
    $primary = ChannelDomain::factory()->primary()->create(['channel_id' => $channel->id, 'host' => 'example.com']);

    expect($channel->primaryDomain()->is($primary))->toBeTrue();
});

it('falls back to the only domain there is when none is flagged', function () {
    $channel = Channel::factory()->create();
    $only = ChannelDomain::factory()->create(['channel_id' => $channel->id]);

    expect($channel->primaryDomain()->is($only))->toBeTrue();
});

it('has no primary domain before any domain is added', function () {
    expect(Channel::factory()->create()->primaryDomain())->toBeNull();
});

it('belongs to a store, which has many channels', function () {
    $store = Store::factory()->create();
    $channel = Channel::factory()->create(['store_id' => $store->id]);

    expect($channel->store->is($store))->toBeTrue()
        ->and($store->channels->pluck('id')->all())->toBe([$channel->id]);
});

it('resolves the host team model from configuration rather than importing it', function () {
    config()->set('commerce-core.team_model', TestUser::class);

    expect(Store::factory()->create()->team()->getRelated())
        ->toBeInstanceOf(TestUser::class);
});
