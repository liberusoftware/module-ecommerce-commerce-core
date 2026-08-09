<?php

namespace Liberu\Ecommerce\CommerceCore\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Liberu\Ecommerce\CommerceCore\Models\ChannelDomain;

/**
 * Every canonical URL the storefront generates changes with this, which is why
 * it is its own event rather than a flavour of {@see ChannelDomainAdded}.
 */
final class PrimaryDomainChanged
{
    use Dispatchable;

    public function __construct(
        public readonly ChannelDomain $domain,
        public readonly ?string $previousHost,
    ) {}
}
