<?php

namespace Liberu\Ecommerce\CommerceCore\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Liberu\Ecommerce\CommerceCore\Enums\Capability;

final class StoreCapabilityChanged
{
    use Dispatchable;

    public function __construct(
        public readonly int $storeId,
        public readonly Capability $capability,
        public readonly bool $enabled,
    ) {}
}
