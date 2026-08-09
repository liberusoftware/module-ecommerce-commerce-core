<?php

namespace Liberu\Ecommerce\CommerceCore\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * An allocation is spent whether or not an order is ever placed against it —
 * gaps in a numbering series are normal and re-using a number is not. The event
 * is what lets a consumer reconcile the two without reading this module's table.
 */
final class OrderNumberAllocated
{
    use Dispatchable;

    public function __construct(
        public readonly int $storeId,
        public readonly string $number,
        public readonly int $sequenceValue,
    ) {}
}
