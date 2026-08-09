<?php

namespace Liberu\Ecommerce\CommerceCore\Services;

use Liberu\Ecommerce\CommerceCore\Contracts\ResolvesCommercialContext;
use Liberu\Ecommerce\CommerceCore\Models\Store;
use Liberu\Ecommerce\CommerceCore\Values\CommercialContext;

/**
 * Which store, and in what terms.
 *
 * Three sources, in the order that can actually answer:
 *
 * 1. the channel the request resolved to — a shopper is on a storefront;
 * 2. the store `StoreContext` picks for writes — a panel user, or a
 *    single-store deployment;
 * 3. nothing, which is the honest answer in a queued job on a multi-store
 *    deployment and is why {@see CommercialContext::unresolved()} exists rather
 *    than this returning null.
 *
 * Never null, because every caller would otherwise write the same fallback and
 * a third of them would write it differently.
 */
final class CommercialContextResolver implements ResolvesCommercialContext
{
    public function current(): CommercialContext
    {
        if ($channel = ChannelResolver::current()) {
            return CommercialContext::forChannel($channel->loadMissing('store'));
        }

        $storeId = StoreContext::forWrites();

        if ($storeId !== null && ($store = Store::query()->find($storeId))) {
            return CommercialContext::forStore($store);
        }

        return CommercialContext::unresolved();
    }
}
