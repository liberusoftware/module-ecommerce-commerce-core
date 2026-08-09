<?php

declare(strict_types=1);

use Liberu\Ecommerce\CommerceCore\Models\Channel;
use Liberu\Ecommerce\CommerceCore\Models\ChannelDomain;
use Liberu\Ecommerce\CommerceCore\Services\ChannelResolver;

it('resolves a hostname to the channel that answers on it', function () {
    $channel = Channel::factory()->create();
    ChannelDomain::factory()->create(['channel_id' => $channel->id, 'host' => 'shop.example.com']);

    expect((new ChannelResolver)->resolve('shop.example.com')->is($channel))->toBeTrue();
});

it('resolves a hostname however it is spelled', function (string $host) {
    $channel = Channel::factory()->create();
    ChannelDomain::factory()->create(['channel_id' => $channel->id, 'host' => 'shop.example.com']);

    expect((new ChannelResolver)->resolve($host)->is($channel))->toBeTrue();
})->with(['SHOP.Example.com', 'shop.example.com:8000', ' shop.example.com ']);

it('resolves nothing for a hostname no channel claims', function () {
    ChannelDomain::factory()->create(['host' => 'shop.example.com']);

    expect((new ChannelResolver)->resolve('other.example.com'))->toBeNull();
});

it('resolves nothing for an empty hostname, without querying for it', function (string $host) {
    // A domain row with an empty host cannot exist — the mutator would normalise
    // to '' and the column is unique — but the guard is what stops an empty Host
    // header matching whatever sorts first if one ever did.
    expect((new ChannelResolver)->resolve($host))->toBeNull();
})->with(['', '   ', ':8000']);

it('eager-loads the store, so a storefront does not pay a second query for it', function () {
    $channel = Channel::factory()->create();
    ChannelDomain::factory()->create(['channel_id' => $channel->id, 'host' => 'shop.example.com']);

    expect((new ChannelResolver)->resolve('shop.example.com')->relationLoaded('store'))->toBeTrue();
});

it('reports no current channel off a resolved host', function () {
    expect(ChannelResolver::current())->toBeNull();
});

it('reports the channel the request carries', function () {
    $channel = Channel::factory()->create();
    request()->attributes->set(ChannelResolver::ATTRIBUTE, $channel);

    expect(ChannelResolver::current()->is($channel))->toBeTrue();
});
