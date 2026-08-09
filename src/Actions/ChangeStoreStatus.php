<?php

namespace Liberu\Ecommerce\CommerceCore\Actions;

use Liberu\Ecommerce\CommerceCore\Enums\StoreStatus;
use Liberu\Ecommerce\CommerceCore\Events\StoreStatusChanged;
use Liberu\Ecommerce\CommerceCore\Exceptions\InvalidStatusTransition;
use Liberu\Ecommerce\CommerceCore\Models\Store;

/**
 * Every lifecycle move a store makes, in one place.
 *
 * One action rather than activate/suspend/archive: the rule being enforced is
 * the transition table, and three actions means three chances to consult a
 * different copy of it.
 */
final class ChangeStoreStatus
{
    public function handle(Store $store, StoreStatus $to): Store
    {
        $from = $store->status;

        // Idempotent rather than an error. A retried job, a double-clicked
        // button and a webhook redelivery all arrive here asking for a state
        // the store is already in, and none of them is a fault.
        if ($from === $to) {
            return $store;
        }

        if (! $from->canTransitionTo($to)) {
            throw InvalidStatusTransition::between('store', $from->value, $to->value);
        }

        $store->forceFill([
            'status' => $to,
            'archived_at' => $to === StoreStatus::Archived ? now() : null,
        ])->save();

        StoreStatusChanged::dispatch($store, $from, $to);

        return $store;
    }
}
