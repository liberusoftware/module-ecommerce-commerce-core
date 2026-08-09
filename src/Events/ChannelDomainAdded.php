<?php

namespace Liberu\Ecommerce\CommerceCore\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Liberu\Ecommerce\CommerceCore\Models\ChannelDomain;

final class ChannelDomainAdded
{
    use Dispatchable;

    public function __construct(public readonly ChannelDomain $domain) {}
}
