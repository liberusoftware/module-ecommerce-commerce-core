<?php

declare(strict_types=1);

use Liberu\Ecommerce\CommerceCore\Models\Channel;
use Liberu\Ecommerce\CommerceCore\Models\ChannelDomain;
use Liberu\Ecommerce\CommerceCore\Models\Store;
use Liberu\Ecommerce\CommerceCore\Services\ChannelResolver;
use Liberu\Ecommerce\CommerceCore\Services\StoreContext;
use Liberu\PackageTestbench\TestUser;

/** Put a resolved channel on the request, the way `ResolveChannel` middleware does. */
function onStorefrontOf(Store $store): Channel
{
    $channel = Channel::factory()->create(['store_id' => $store->id]);
    ChannelDomain::factory()->create(['channel_id' => $channel->id]);
    request()->attributes->set(ChannelResolver::ATTRIBUTE, $channel);

    return $channel;
}

/** Act as a panel user working in a team, the way Jetstream's team switcher leaves them. */
function inPanelOfTeam(int $teamId): TestUser
{
    $user = TestUser::factory()->create();
    $user->current_team_id = $teamId;

    test()->actingAs($user);

    return $user;
}

describe('reads', function () {
    it('narrows to the store the storefront belongs to', function () {
        $store = Store::factory()->create();
        $other = Store::factory()->create();
        onStorefrontOf($store);

        $query = Channel::query();
        StoreContext::applyTo($query, 'store_id');

        expect($query->getQuery()->wheres)->toHaveCount(1)
            ->and($query->pluck('store_id')->all())->not->toContain($other->id);
    });

    it('narrows a panel user to every store their team owns', function () {
        $mine = Store::factory()->count(2)->create(['team_id' => 7]);
        Store::factory()->create(['team_id' => 8]);
        inPanelOfTeam(7);

        $query = Store::query();
        StoreContext::applyTo($query, 'id');

        expect($query->pluck('id')->all())->toBe($mine->pluck('id')->all());
    });

    it('leaves a query alone off a storefront and off a panel', function () {
        Store::factory()->count(2)->create(['team_id' => 7]);

        $query = Store::query();
        StoreContext::applyTo($query, 'id');

        // Scoping to nothing here would mean an empty catalogue in a console
        // command or a queued job, not a safe one.
        expect($query->getQuery()->wheres)->toBe([])
            ->and($query->count())->toBe(2);
    });

    it('leaves a query alone for a panel user whose team owns no store', function () {
        Store::factory()->create(['team_id' => 8]);
        inPanelOfTeam(7);

        $query = Store::query();
        StoreContext::applyTo($query, 'id');

        expect($query->getQuery()->wheres)->toBe([]);
    });

    it('suspends the scope inside acrossAllStores, and restores it after', function () {
        $store = Store::factory()->create();
        Store::factory()->create();
        onStorefrontOf($store);

        $inside = StoreContext::acrossAllStores(function () {
            $query = Store::query();
            StoreContext::applyTo($query, 'id');

            return $query->count();
        });

        $after = Store::query();
        StoreContext::applyTo($after, 'id');

        expect($inside)->toBe(2)
            ->and($after->count())->toBe(1);
    });

    it('restores the scope even when the callback throws', function () {
        $store = Store::factory()->create();
        Store::factory()->create();
        onStorefrontOf($store);

        try {
            StoreContext::acrossAllStores(fn () => throw new RuntimeException('boom'));
        } catch (RuntimeException) {
            // The point is what the scope looks like afterwards.
        }

        $query = Store::query();
        StoreContext::applyTo($query, 'id');

        expect($query->count())->toBe(1);
    });

    it('nests without the inner call ending the outer one', function () {
        $store = Store::factory()->create();
        Store::factory()->create();
        onStorefrontOf($store);

        $outer = StoreContext::acrossAllStores(function () {
            StoreContext::acrossAllStores(fn () => null);

            $query = Store::query();
            StoreContext::applyTo($query, 'id');

            return $query->count();
        });

        expect($outer)->toBe(2);
    });
});

describe('writes', function () {
    it('stamps the storefront store', function () {
        $store = Store::factory()->create(['team_id' => 7]);
        Store::factory()->create(['team_id' => 7]);
        onStorefrontOf($store);

        expect(StoreContext::forWrites())->toBe($store->id)
            ->and(StoreContext::teamForWrites())->toBe(7);
    });

    it('prefers the panel team to the deployment-wide shortcut', function () {
        $mine = Store::factory()->create(['team_id' => 7]);
        Store::factory()->create(['team_id' => 8]);
        inPanelOfTeam(7);

        expect(StoreContext::forWrites())->toBe($mine->id)
            ->and(StoreContext::teamForWrites())->toBe(7);
    });

    it('falls back to the only store on a single-store deployment', function () {
        $only = Store::factory()->create(['team_id' => 7]);

        expect(StoreContext::forWrites())->toBe($only->id);
    });

    it('stamps nothing when several stores could answer and nobody said which', function () {
        Store::factory()->count(2)->create(['team_id' => 7]);

        expect(StoreContext::forWrites())->toBeNull()
            ->and(StoreContext::teamForWrites())->toBeNull();
    });

    it('stamps nothing rather than borrowing another team’s store', function () {
        Store::factory()->create(['team_id' => 8]);
        Store::factory()->create(['team_id' => 9]);
        inPanelOfTeam(7);

        expect(StoreContext::forWrites())->toBeNull();
    });

    it('stamps the panel team even when their team owns no store yet', function () {
        Store::factory()->create(['team_id' => 8]);
        Store::factory()->create(['team_id' => 9]);
        inPanelOfTeam(7);

        expect(StoreContext::teamForWrites())->toBe(7);
    });

    it('leaves the team null when the resolved store belongs to nobody', function () {
        $store = Store::factory()->create(['team_id' => null]);
        onStorefrontOf($store);

        expect(StoreContext::forWrites())->toBe($store->id)
            ->and(StoreContext::teamForWrites())->toBeNull();
    });
});
