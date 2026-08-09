<?php

namespace Liberu\Ecommerce\CommerceCore\Actions;

use Liberu\Ecommerce\CommerceCore\Enums\ChannelStatus;
use Liberu\Ecommerce\CommerceCore\Events\ChannelCreated;
use Liberu\Ecommerce\CommerceCore\Models\Channel;
use Liberu\Ecommerce\CommerceCore\Models\Store;

final class CreateChannel
{
    public function handle(Store $store, string $name, string $theme = 'theme-ecommerce', ?string $currency = null, ?string $locale = null): Channel
    {
        $channel = $store->channels()->create([
            'name' => $name,
            'theme' => $theme,
            'status' => ChannelStatus::Draft,
            // Null, not the store's value. A channel that overrides nothing must
            // keep following its store rather than freezing a copy of it.
            'currency' => $currency,
            'locale' => $locale,
        ]);

        ChannelCreated::dispatch($channel);

        return $channel;
    }
}
