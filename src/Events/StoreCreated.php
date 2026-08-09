<?php

namespace Liberu\Ecommerce\CommerceCore\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Liberu\Ecommerce\CommerceCore\Models\Store;

/**
 * Past tense, and carrying the model rather than an id.
 *
 * A listener inside this module wants the model; one in another module wants
 * `$store->id` and is told by ADR 0007 to key on it rather than to type-hint
 * the class. Carrying the model serves both; carrying only the id serves
 * neither without a query.
 */
final class StoreCreated
{
    use Dispatchable;

    public function __construct(public readonly Store $store) {}
}
