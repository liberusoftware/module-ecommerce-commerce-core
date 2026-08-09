<?php

namespace Liberu\Ecommerce\CommerceCore\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Liberu\Ecommerce\CommerceCore\Enums\StoreStatus;
use Liberu\Ecommerce\CommerceCore\Models\Store;

/**
 * Who may act on a store.
 *
 * Tenancy is the whole policy: a store belongs to one team, and an actor works
 * in one team at a time. The team is read off the actor rather than off a
 * Filament panel so this answers the same way in a console command, a job and
 * an API request — the places a panel-shaped check silently allows everything.
 *
 * An unowned store (`team_id` null) is nobody's, and nobody may act on it. That
 * is deliberately stricter than the read scope, which leaves such rows visible:
 * seeing an orphan is how it gets fixed, editing one is how it gets stolen.
 */
class StorePolicy
{
    public function viewAny(Authenticatable $actor): bool
    {
        return $this->teamOf($actor) !== null;
    }

    public function view(Authenticatable $actor, Store $store): bool
    {
        return $this->ownsIt($actor, $store);
    }

    public function create(Authenticatable $actor): bool
    {
        return $this->teamOf($actor) !== null;
    }

    public function update(Authenticatable $actor, Store $store): bool
    {
        // An archived store is a record, not a resource. Editing one rewrites
        // history that invoices and orders already point at.
        return $this->ownsIt($actor, $store) && $store->status !== StoreStatus::Archived;
    }

    public function delete(Authenticatable $actor, Store $store): bool
    {
        // Deleting cascades to channels, domains, settings and sequences. A
        // store that ever traded is archived instead, which is why only a draft
        // may go.
        return $this->ownsIt($actor, $store) && $store->status === StoreStatus::Draft;
    }

    public function changeStatus(Authenticatable $actor, Store $store): bool
    {
        return $this->ownsIt($actor, $store) && ! $store->status->isTerminal();
    }

    public function manageSettings(Authenticatable $actor, Store $store): bool
    {
        return $this->update($actor, $store);
    }

    private function ownsIt(Authenticatable $actor, Store $store): bool
    {
        $teamId = $this->teamOf($actor);

        return $teamId !== null && $store->team_id !== null && (int) $store->team_id === $teamId;
    }

    private function teamOf(Authenticatable $actor): ?int
    {
        $teamId = $actor->current_team_id ?? null;

        return $teamId === null ? null : (int) $teamId;
    }
}
