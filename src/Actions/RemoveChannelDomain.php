<?php

namespace Liberu\Ecommerce\CommerceCore\Actions;

use Illuminate\Support\Facades\DB;
use Liberu\Ecommerce\CommerceCore\Events\ChannelDomainRemoved;
use Liberu\Ecommerce\CommerceCore\Events\PrimaryDomainChanged;
use Liberu\Ecommerce\CommerceCore\Models\ChannelDomain;

/**
 * Release a hostname.
 *
 * Removing the primary promotes the oldest survivor rather than leaving the
 * channel primary-less. Same reason the first domain is auto-primary: a channel
 * with domains and no primary canonicalises on whatever sorts first.
 */
final class RemoveChannelDomain
{
    public function handle(ChannelDomain $domain): void
    {
        DB::transaction(function () use ($domain) {
            $channelId = (int) $domain->channel_id;
            $host = $domain->host;
            $wasPrimary = $domain->is_primary;

            $domain->delete();

            ChannelDomainRemoved::dispatch($channelId, $host);

            if (! $wasPrimary) {
                return;
            }

            $successor = ChannelDomain::query()
                ->where('channel_id', $channelId)
                ->orderBy('id')
                ->first();

            if ($successor === null) {
                return;
            }

            $successor->forceFill(['is_primary' => true])->save();

            PrimaryDomainChanged::dispatch($successor, $host);
        });
    }
}
