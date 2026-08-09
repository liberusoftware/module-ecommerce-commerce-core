<?php

declare(strict_types=1);

use Liberu\Ecommerce\CommerceCore\Models\Channel;
use Liberu\Ecommerce\CommerceCore\Models\Store;
use Liberu\PackageTestbench\TestUser;

/** An actor working in a team, the way the team switcher leaves them. */
function actorInTeam(?int $teamId): TestUser
{
    $user = TestUser::factory()->create();
    $user->current_team_id = $teamId;

    return $user;
}

it('lets a merchant act on their own store', function () {
    $actor = actorInTeam(7);
    $store = Store::factory()->ownedBy(7)->create();

    expect($actor->can('view', $store))->toBeTrue()
        ->and($actor->can('update', $store))->toBeTrue()
        ->and($actor->can('changeStatus', $store))->toBeTrue()
        ->and($actor->can('manageSettings', $store))->toBeTrue()
        ->and($actor->can('viewAny', Store::class))->toBeTrue()
        ->and($actor->can('create', Store::class))->toBeTrue();
});

it('refuses another merchant’s store outright', function () {
    $actor = actorInTeam(7);
    $theirs = Store::factory()->ownedBy(8)->create();

    expect($actor->can('view', $theirs))->toBeFalse()
        ->and($actor->can('update', $theirs))->toBeFalse()
        ->and($actor->can('delete', $theirs))->toBeFalse()
        ->and($actor->can('changeStatus', $theirs))->toBeFalse();
});

it('refuses a store that belongs to nobody, which is stricter than the read scope on purpose', function () {
    $actor = actorInTeam(7);
    $orphan = Store::factory()->create(['team_id' => null]);

    expect($actor->can('view', $orphan))->toBeFalse()
        ->and($actor->can('update', $orphan))->toBeFalse();
});

it('refuses an actor with no team at all', function () {
    $actor = actorInTeam(null);
    $store = Store::factory()->ownedBy(7)->create();

    expect($actor->can('viewAny', Store::class))->toBeFalse()
        ->and($actor->can('create', Store::class))->toBeFalse()
        ->and($actor->can('view', $store))->toBeFalse();
});

it('will not let an archived store be edited or moved again', function () {
    $actor = actorInTeam(7);
    $archived = Store::factory()->ownedBy(7)->archived()->create();

    expect($actor->can('view', $archived))->toBeTrue()
        ->and($actor->can('update', $archived))->toBeFalse()
        ->and($actor->can('changeStatus', $archived))->toBeFalse();
});

it('allows deleting only a store that never traded', function () {
    $actor = actorInTeam(7);

    expect($actor->can('delete', Store::factory()->ownedBy(7)->draft()->create()))->toBeTrue()
        ->and($actor->can('delete', Store::factory()->ownedBy(7)->create()))->toBeFalse();
});

it('governs a channel by its store, so nothing has to repeat the team check', function () {
    $actor = actorInTeam(7);
    $mine = Channel::factory()->create(['store_id' => Store::factory()->ownedBy(7)]);
    $theirs = Channel::factory()->create(['store_id' => Store::factory()->ownedBy(8)]);

    expect($actor->can('viewAny', Channel::class))->toBeTrue()
        ->and($actor->can('view', $mine))->toBeTrue()
        ->and($actor->can('update', $mine))->toBeTrue()
        ->and($actor->can('delete', $mine))->toBeTrue()
        ->and($actor->can('manageDomains', $mine))->toBeTrue()
        ->and($actor->can('view', $theirs))->toBeFalse()
        ->and($actor->can('manageDomains', $theirs))->toBeFalse();
});

it('stops a channel outliving the rule its store is under', function () {
    $actor = actorInTeam(7);
    $channel = Channel::factory()->create(['store_id' => Store::factory()->ownedBy(7)->archived()]);

    expect($actor->can('view', $channel))->toBeTrue()
        ->and($actor->can('update', $channel))->toBeFalse()
        ->and($actor->can('manageDomains', $channel))->toBeFalse();
});
