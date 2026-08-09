<?php

namespace Liberu\Ecommerce\CommerceCore\Actions;

use Illuminate\Support\Facades\DB;
use Liberu\Ecommerce\CommerceCore\Events\ChannelDomainAdded;
use Liberu\Ecommerce\CommerceCore\Events\PrimaryDomainChanged;
use Liberu\Ecommerce\CommerceCore\Exceptions\DomainAlreadyClaimed;
use Liberu\Ecommerce\CommerceCore\Models\Channel;
use Liberu\Ecommerce\CommerceCore\Models\ChannelDomain;

/**
 * Claim a hostname for a channel.
 *
 * The first domain a channel gets is its primary whether or not the caller
 * asked: a channel with domains and no primary generates canonicals from
 * whatever sorts first, which is a silent SEO fault rather than a loud one.
 */
final class AddChannelDomain
{
    public function handle(Channel $channel, string $host, bool $primary = false): ChannelDomain
    {
        $host = ChannelDomain::normalise($host);

        return DB::transaction(function () use ($channel, $host, $primary) {
            if (ChannelDomain::query()->where('host', $host)->lockForUpdate()->exists()) {
                throw DomainAlreadyClaimed::for($host);
            }

            $isFirst = ! $channel->domains()->exists();
            $previousPrimary = $isFirst ? null : $channel->domains()->where('is_primary', true)->first();

            $domain = $channel->domains()->create([
                'host' => $host,
                'is_primary' => $primary || $isFirst,
            ]);

            if ($primary && ! $isFirst) {
                $channel->domains()->whereKeyNot($domain->getKey())->update(['is_primary' => false]);
            }

            ChannelDomainAdded::dispatch($domain);

            if ($domain->is_primary) {
                PrimaryDomainChanged::dispatch($domain, $previousPrimary?->host);
            }

            return $domain;
        });
    }
}
