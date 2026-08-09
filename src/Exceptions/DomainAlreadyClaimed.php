<?php

namespace Liberu\Ecommerce\CommerceCore\Exceptions;

use DomainException;

/**
 * A hostname another channel already answers on.
 *
 * Caught rather than left to the unique index because the index reports a
 * constraint name and this reports the hostname — and because the check has to
 * happen inside the same transaction as the insert either way, so there is no
 * saving in skipping it.
 */
final class DomainAlreadyClaimed extends DomainException
{
    public static function for(string $host): self
    {
        return new self("The hostname [{$host}] is already claimed by another channel.");
    }
}
