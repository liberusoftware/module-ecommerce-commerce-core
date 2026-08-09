<?php

namespace Liberu\Ecommerce\CommerceCore\Contracts;

use Liberu\Ecommerce\CommerceCore\Models\Store;

/**
 * Order numbering, for the module that owns orders.
 *
 * An interface because the consumer is another package: `ecommerce-orders`
 * must be able to allocate without importing this module's action class, and
 * without knowing that a sequence table exists at all.
 */
interface AllocatesOrderNumbers
{
    public function handle(Store $store, string $prefix = ''): string;
}
