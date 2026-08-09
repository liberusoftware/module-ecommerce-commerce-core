<?php

namespace Liberu\Ecommerce\CommerceCore\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Carries the hostname, not the model: the row is gone by the time a listener
 * runs, and a deleted model is a trap for anyone who tries to reload it.
 */
final class ChannelDomainRemoved
{
    use Dispatchable;

    public function __construct(
        public readonly int $channelId,
        public readonly string $host,
    ) {}
}
