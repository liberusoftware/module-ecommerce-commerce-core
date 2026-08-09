<?php

namespace Liberu\Ecommerce\CommerceCore\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Liberu\Ecommerce\CommerceCore\Models\Channel;

final class ChannelCreated
{
    use Dispatchable;

    public function __construct(public readonly Channel $channel) {}
}
