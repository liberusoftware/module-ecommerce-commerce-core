<?php

namespace Liberu\Ecommerce\CommerceCore\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Liberu\Ecommerce\CommerceCore\Models\Channel;

/**
 * A channel is governed by its store.
 *
 * Delegating rather than repeating the team check means a change to what
 * ownership means happens once. It also closes the gap where a channel outlives
 * the rule its store is under — an archived store's channels stop being
 * editable because the store's own policy says so.
 */
class ChannelPolicy
{
    public function __construct(private readonly StorePolicy $stores) {}

    public function viewAny(Authenticatable $actor): bool
    {
        return $this->stores->viewAny($actor);
    }

    public function view(Authenticatable $actor, Channel $channel): bool
    {
        return $this->stores->view($actor, $channel->store);
    }

    // No `create`: Laravel hands a create check the class name rather than a
    // model, so there would be no store to ask. Creating a channel is an edit
    // to its store, and StorePolicy::update() is what answers that.

    public function update(Authenticatable $actor, Channel $channel): bool
    {
        return $this->stores->update($actor, $channel->store);
    }

    public function delete(Authenticatable $actor, Channel $channel): bool
    {
        return $this->stores->update($actor, $channel->store);
    }

    public function manageDomains(Authenticatable $actor, Channel $channel): bool
    {
        return $this->stores->update($actor, $channel->store);
    }
}
