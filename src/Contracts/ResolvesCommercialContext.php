<?php

namespace Liberu\Ecommerce\CommerceCore\Contracts;

use Liberu\Ecommerce\CommerceCore\Values\CommercialContext;

/**
 * The seam other modules resolve commerce against.
 *
 * Published as an interface so a consumer type-hints this rather than the
 * concrete resolver — ADR 0007's rule for anything crossing a product boundary.
 * It is also what lets a test swap in a fixed context instead of building a
 * store, a channel and a hostname to get one.
 */
interface ResolvesCommercialContext
{
    /** The context for the current request, job or command. Never null. */
    public function current(): CommercialContext;
}
