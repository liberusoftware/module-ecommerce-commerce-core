<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Liberu\Ecommerce\CommerceCore\Models\Channel;
use Liberu\Ecommerce\CommerceCore\Models\ChannelDomain;

it('normalises a host to one comparable shape', function (string $given, string $expected) {
    expect(ChannelDomain::normalise($given))->toBe($expected);
})->with([
    'already normal' => ['example.com', 'example.com'],
    'uppercase' => ['EXAMPLE.com', 'example.com'],
    'port' => ['example.com:8000', 'example.com'],
    'uppercase and port' => ['  EXAMPLE.com:8000 ', 'example.com'],
    'empty' => ['', ''],
    'whitespace only' => ['   ', ''],
]);

it('normalises on write, so two spellings of a hostname are one row', function () {
    $domain = ChannelDomain::factory()->create(['host' => 'SHOP.Example.com:8443']);

    expect($domain->fresh()->host)->toBe('shop.example.com');
});

it('rejects a second channel claiming the same hostname', function () {
    ChannelDomain::factory()->create(['host' => 'shop.example.com']);

    ChannelDomain::factory()->create(['host' => 'SHOP.example.com']);
})->throws(QueryException::class);

it('answers for the channel it belongs to', function () {
    $channel = Channel::factory()->create();
    $domain = ChannelDomain::factory()->create(['channel_id' => $channel->id]);

    expect($domain->channel->is($channel))->toBeTrue();
});
