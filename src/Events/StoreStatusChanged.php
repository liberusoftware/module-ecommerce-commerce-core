<?php

namespace Liberu\Ecommerce\CommerceCore\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Liberu\Ecommerce\CommerceCore\Enums\StoreStatus;
use Liberu\Ecommerce\CommerceCore\Models\Store;

/**
 * Both ends of the move, because the interesting listeners are asymmetric:
 * "started serving" and "stopped serving" are different subscriptions, and a
 * listener given only the new status has to guess which one just happened.
 */
final class StoreStatusChanged
{
    use Dispatchable;

    public function __construct(
        public readonly Store $store,
        public readonly StoreStatus $from,
        public readonly StoreStatus $to,
    ) {}
}
