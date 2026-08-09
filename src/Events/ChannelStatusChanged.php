<?php

namespace Liberu\Ecommerce\CommerceCore\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Liberu\Ecommerce\CommerceCore\Enums\ChannelStatus;
use Liberu\Ecommerce\CommerceCore\Models\Channel;

final class ChannelStatusChanged
{
    use Dispatchable;

    public function __construct(
        public readonly Channel $channel,
        public readonly ChannelStatus $from,
        public readonly ChannelStatus $to,
    ) {}
}
