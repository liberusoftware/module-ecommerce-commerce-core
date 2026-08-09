<?php

declare(strict_types=1);

use Liberu\Ecommerce\CommerceCore\Contracts\ResolvesCommercialContext;
use Liberu\Ecommerce\CommerceCore\Models\Channel;
use Liberu\Ecommerce\CommerceCore\Models\ChannelDomain;
use Liberu\Ecommerce\CommerceCore\Models\Store;
use Liberu\Ecommerce\CommerceCore\Services\ChannelResolver;
use Liberu\Ecommerce\CommerceCore\Values\CommercialContext;

it('inherits from the store when the channel overrides nothing', function () {
    $store = Store::factory()->create(['currency' => 'GBP', 'locale' => 'en_GB', 'timezone' => 'Europe/London', 'team_id' => 7]);
    $channel = Channel::factory()->create(['store_id' => $store->id]);

    $context = CommercialContext::forChannel($channel);

    expect($context->currency)->toBe('GBP')
        ->and($context->locale)->toBe('en_GB')
        ->and($context->timezone)->toBe('Europe/London')
        ->and($context->teamId)->toBe(7)
        ->and($context->channelId)->toBe($channel->id)
        ->and($context->isResolved())->toBeTrue();
});

it('lets the channel override currency and locale, but not the store timezone', function () {
    $store = Store::factory()->create(['currency' => 'GBP', 'locale' => 'en_GB', 'timezone' => 'Europe/London']);
    $channel = Channel::factory()->create(['store_id' => $store->id, 'currency' => 'EUR', 'locale' => 'fr']);

    $context = CommercialContext::forChannel($channel);

    expect($context->currency)->toBe('EUR')
        ->and($context->locale)->toBe('fr')
        // The timezone is the merchant's operating day — when their reports
        // close and their cutoffs fall — not a per-storefront presentation
        // choice, so a channel has no say in it.
        ->and($context->timezone)->toBe('Europe/London');
});

it('answers for a store with no channel in sight', function () {
    $store = Store::factory()->create(['currency' => 'GBP']);

    $context = CommercialContext::forStore($store);

    expect($context->channelId)->toBeNull()
        ->and($context->storeId)->toBe($store->id)
        ->and($context->currency)->toBe('GBP');
});

it('carries a null team for a store that belongs to nobody', function () {
    expect(CommercialContext::forStore(Store::factory()->create(['team_id' => null]))->teamId)->toBeNull()
        ->and(CommercialContext::forChannel(Channel::factory()->create())->teamId)->toBeNull();
});

it('reports the deployment defaults when nothing resolves', function () {
    config()->set('commerce-core.default_currency', 'GBP');
    config()->set('app.locale', 'en_GB');
    config()->set('app.timezone', 'Europe/London');

    $context = CommercialContext::unresolved();

    expect($context->isResolved())->toBeFalse()
        ->and($context->storeId)->toBeNull()
        ->and($context->currency)->toBe('GBP')
        ->and($context->locale)->toBe('en_GB')
        ->and($context->timezone)->toBe('Europe/London');
});

it('serialises to the shape a job payload can carry', function () {
    $store = Store::factory()->create(['team_id' => 7, 'currency' => 'GBP']);

    $context = CommercialContext::forStore($store);

    expect($context->toArray())->toBe([
        'store_id' => $store->id,
        'channel_id' => null,
        'team_id' => 7,
        'currency' => 'GBP',
        'locale' => 'en',
        'timezone' => 'UTC',
    ])->and(json_decode(json_encode($context), true))->toBe($context->toArray());
});

describe('resolution', function () {
    it('answers from the storefront the request arrived on', function () {
        $store = Store::factory()->create(['currency' => 'GBP']);
        $channel = Channel::factory()->create(['store_id' => $store->id]);
        ChannelDomain::factory()->create(['channel_id' => $channel->id]);
        request()->attributes->set(ChannelResolver::ATTRIBUTE, $channel);

        $context = app(ResolvesCommercialContext::class)->current();

        expect($context->channelId)->toBe($channel->id)
            ->and($context->currency)->toBe('GBP');
    });

    it('falls back to the only store on a single-store deployment', function () {
        $store = Store::factory()->create(['currency' => 'GBP']);

        $context = app(ResolvesCommercialContext::class)->current();

        expect($context->storeId)->toBe($store->id)
            ->and($context->channelId)->toBeNull();
    });

    it('reports unresolved rather than guessing between several stores', function () {
        Store::factory()->count(2)->create();

        expect(app(ResolvesCommercialContext::class)->current()->isResolved())->toBeFalse();
    });
});
