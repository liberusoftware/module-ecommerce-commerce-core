<?php

namespace Liberu\Ecommerce\CommerceCore\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * The old value rides along because this is the module's audit evidence for a
 * setting: an event saying only what a value became cannot answer "what was it
 * before somebody broke it", which is the question actually asked.
 */
final class StoreSettingChanged
{
    use Dispatchable;

    public function __construct(
        public readonly int $storeId,
        public readonly string $key,
        public readonly mixed $from,
        public readonly mixed $to,
    ) {}
}
