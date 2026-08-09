<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Liberu\Ecommerce\CommerceCore\Actions\AddChannelDomain;
use Liberu\Ecommerce\CommerceCore\Actions\PromoteDomainToPrimary;
use Liberu\Ecommerce\CommerceCore\Actions\RemoveChannelDomain;
use Liberu\Ecommerce\CommerceCore\Events\ChannelDomainAdded;
use Liberu\Ecommerce\CommerceCore\Events\ChannelDomainRemoved;
use Liberu\Ecommerce\CommerceCore\Events\PrimaryDomainChanged;
use Liberu\Ecommerce\CommerceCore\Exceptions\DomainAlreadyClaimed;
use Liberu\Ecommerce\CommerceCore\Models\Channel;
use Liberu\Ecommerce\CommerceCore\Models\ChannelDomain;

it('makes the first hostname primary whether or not anybody asked', function () {
    Event::fake([ChannelDomainAdded::class, PrimaryDomainChanged::class]);
    $channel = Channel::factory()->create();

    $domain = (new AddChannelDomain())->handle($channel, 'Example.com');

    expect($domain->host)->toBe('example.com')
        ->and($domain->is_primary)->toBeTrue();

    Event::assertDispatched(ChannelDomainAdded::class);
    Event::assertDispatched(PrimaryDomainChanged::class, fn (PrimaryDomainChanged $e) => $e->previousHost === null);
});

it('leaves a later hostname secondary unless told otherwise', function () {
    $channel = Channel::factory()->create();
    (new AddChannelDomain())->handle($channel, 'example.com');

    $second = (new AddChannelDomain())->handle($channel, 'www.example.com');

    expect($second->is_primary)->toBeFalse();
});

it('demotes the incumbent when a new hostname arrives as primary', function () {
    $channel = Channel::factory()->create();
    $first = (new AddChannelDomain())->handle($channel, 'old.example.com');

    $second = (new AddChannelDomain())->handle($channel, 'new.example.com', primary: true);

    expect($second->is_primary)->toBeTrue()
        ->and($first->fresh()->is_primary)->toBeFalse()
        ->and($channel->primaryDomain()->is($second))->toBeTrue();
});

it('refuses a hostname another channel already answers on', function () {
    (new AddChannelDomain())->handle(Channel::factory()->create(), 'shop.example.com');

    (new AddChannelDomain())->handle(Channel::factory()->create(), 'SHOP.example.com:443');
})->throws(DomainAlreadyClaimed::class, 'The hostname [shop.example.com] is already claimed');

it('promotes a secondary, reporting what it displaced', function () {
    Event::fake([PrimaryDomainChanged::class]);
    $channel = Channel::factory()->create();
    (new AddChannelDomain())->handle($channel, 'old.example.com');
    $second = (new AddChannelDomain())->handle($channel, 'new.example.com');

    (new PromoteDomainToPrimary())->handle($second);

    expect($channel->primaryDomain()->is($second))->toBeTrue()
        ->and(ChannelDomain::query()->where('is_primary', true)->count())->toBe(1);

    Event::assertDispatched(PrimaryDomainChanged::class, fn (PrimaryDomainChanged $e) => $e->previousHost === 'old.example.com');
});

it('says nothing when promoting the domain that is already primary', function () {
    $channel = Channel::factory()->create();
    $only = (new AddChannelDomain())->handle($channel, 'example.com');
    Event::fake([PrimaryDomainChanged::class]);

    (new PromoteDomainToPrimary())->handle($only);

    Event::assertNotDispatched(PrimaryDomainChanged::class);
});

it('releases a hostname, and reports it by name because the row is gone', function () {
    Event::fake([ChannelDomainRemoved::class]);
    $channel = Channel::factory()->create();
    (new AddChannelDomain())->handle($channel, 'example.com');
    $second = (new AddChannelDomain())->handle($channel, 'www.example.com');

    (new RemoveChannelDomain())->handle($second);

    expect($channel->domains()->count())->toBe(1);
    Event::assertDispatched(ChannelDomainRemoved::class, fn (ChannelDomainRemoved $e) => $e->host === 'www.example.com');
});

it('promotes the oldest survivor when the primary is released', function () {
    $channel = Channel::factory()->create();
    $primary = (new AddChannelDomain())->handle($channel, 'old.example.com');
    $survivor = (new AddChannelDomain())->handle($channel, 'www.example.com');
    (new AddChannelDomain())->handle($channel, 'shop.example.com');

    (new RemoveChannelDomain())->handle($primary);

    expect($channel->primaryDomain()->is($survivor))->toBeTrue();
});

it('leaves nothing behind when the last hostname is released', function () {
    $channel = Channel::factory()->create();
    $only = (new AddChannelDomain())->handle($channel, 'example.com');

    (new RemoveChannelDomain())->handle($only);

    expect($channel->domains()->count())->toBe(0)
        ->and($channel->primaryDomain())->toBeNull();
});

it('frees the hostname for another channel once released', function () {
    $first = Channel::factory()->create();
    $domain = (new AddChannelDomain())->handle($first, 'shop.example.com');
    (new RemoveChannelDomain())->handle($domain);

    $second = (new AddChannelDomain())->handle(Channel::factory()->create(), 'shop.example.com');

    expect($second->exists)->toBeTrue();
});
