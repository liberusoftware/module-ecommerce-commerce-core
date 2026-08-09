<?php

namespace Liberu\Ecommerce\CommerceCore\Actions;

use Illuminate\Support\Facades\DB;
use Liberu\Ecommerce\CommerceCore\Events\PrimaryDomainChanged;
use Liberu\Ecommerce\CommerceCore\Models\ChannelDomain;

final class PromoteDomainToPrimary
{
    public function handle(ChannelDomain $domain): ChannelDomain
    {
        return DB::transaction(function () use ($domain) {
            $previous = ChannelDomain::query()
                ->where('channel_id', $domain->channel_id)
                ->where('is_primary', true)
                ->first();

            if ($previous?->is($domain)) {
                return $domain;
            }

            ChannelDomain::query()
                ->where('channel_id', $domain->channel_id)
                ->update(['is_primary' => false]);

            $domain->forceFill(['is_primary' => true])->save();

            PrimaryDomainChanged::dispatch($domain, $previous?->host);

            return $domain;
        });
    }
}
