<?php

namespace Liberu\Ecommerce\CommerceCore\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Liberu\Ecommerce\CommerceCore\Models\Channel;
use Liberu\Ecommerce\CommerceCore\Models\Store;

/**
 * Authorization by id, for consumers that may not hold a model.
 *
 * The policies are the authority and this changes none of them — it resolves
 * the subject and asks the gate. It exists because the alternative for an
 * adapter forbidden from importing `Models\` is to make its own authorization
 * decision, which is exactly what #839's exclusions forbid: *business
 * authorization solely in UI/API presentation layers*.
 *
 * A missing subject is denied rather than reported. "You may not see it" and
 * "it is not there" are the same answer to anyone who is not entitled to it,
 * and distinguishing them leaks which ids exist.
 */
final class CommerceAccess
{
    public function toStore(Authenticatable $actor, string $ability, ?int $storeId = null): bool
    {
        if ($storeId === null) {
            return Gate::forUser($actor)->allows($ability, Store::class);
        }

        $store = Store::query()->find($storeId);

        return $store !== null && Gate::forUser($actor)->allows($ability, $store);
    }

    public function toChannel(Authenticatable $actor, string $ability, ?int $channelId = null): bool
    {
        if ($channelId === null) {
            return Gate::forUser($actor)->allows($ability, Channel::class);
        }

        $channel = Channel::query()->with('store')->find($channelId);

        return $channel !== null && Gate::forUser($actor)->allows($ability, $channel);
    }
}
