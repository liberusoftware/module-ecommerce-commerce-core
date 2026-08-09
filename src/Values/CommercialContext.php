<?php

namespace Liberu\Ecommerce\CommerceCore\Values;

use JsonSerializable;
use Liberu\Ecommerce\CommerceCore\Models\Channel;
use Liberu\Ecommerce\CommerceCore\Models\Store;

/**
 * The commercial answer to *who is this request for, and in what terms*.
 *
 * Immutable, and carries ids rather than models: it crosses module and job
 * boundaries, and a serialised Eloquent model arrives at the other end either
 * stale or dragging its whole relation graph into the payload.
 *
 * Currency, locale and timezone resolve channel-first, store-second. A channel
 * that overrides nothing holds null rather than a copy, so changing the store
 * changes the storefront — which a copy would silently stop doing.
 */
final readonly class CommercialContext implements JsonSerializable
{
    public function __construct(
        public ?int $storeId,
        public ?int $channelId,
        public ?int $teamId,
        public string $currency,
        public string $locale,
        public string $timezone,
    ) {}

    public static function forChannel(Channel $channel): self
    {
        $store = $channel->store;

        return new self(
            storeId: $store->id,
            channelId: $channel->id,
            teamId: $store->team_id === null ? null : (int) $store->team_id,
            currency: $channel->currency ?? $store->currency,
            locale: $channel->locale ?? $store->locale,
            timezone: $store->timezone,
        );
    }

    public static function forStore(Store $store): self
    {
        return new self(
            storeId: $store->id,
            channelId: null,
            teamId: $store->team_id === null ? null : (int) $store->team_id,
            currency: $store->currency,
            locale: $store->locale,
            timezone: $store->timezone,
        );
    }

    /**
     * What a console command, a queued job or an unresolved host gets.
     *
     * The application's own configuration rather than a hardcoded 'USD': a
     * deployment that trades in one currency has already said so once, and
     * saying it twice is how the two disagree.
     */
    public static function unresolved(): self
    {
        return new self(
            storeId: null,
            channelId: null,
            teamId: null,
            currency: (string) config('commerce-core.default_currency'),
            locale: (string) config('app.locale'),
            timezone: (string) config('app.timezone'),
        );
    }

    public function isResolved(): bool
    {
        return $this->storeId !== null;
    }

    /** @return array<string, int|string|null> */
    public function toArray(): array
    {
        return [
            'store_id' => $this->storeId,
            'channel_id' => $this->channelId,
            'team_id' => $this->teamId,
            'currency' => $this->currency,
            'locale' => $this->locale,
            'timezone' => $this->timezone,
        ];
    }

    /** @return array<string, int|string|null> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
