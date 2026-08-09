<?php

namespace Liberu\Ecommerce\CommerceCore\Data;

use JsonSerializable;
use Liberu\Ecommerce\CommerceCore\Enums\ChannelStatus;
use Liberu\Ecommerce\CommerceCore\Models\Channel;

final readonly class ChannelData implements JsonSerializable
{
    /** @param list<ChannelDomainData> $domains */
    public function __construct(
        public int $id,
        public int $storeId,
        public string $name,
        public string $theme,
        public ChannelStatus $status,
        public ?string $currency,
        public ?string $locale,
        public ?string $primaryHost,
        public array $domains,
    ) {}

    public static function from(Channel $channel): self
    {
        $domains = $channel->domains()->orderBy('id')->get();

        return new self(
            id: (int) $channel->id,
            storeId: (int) $channel->store_id,
            name: $channel->name,
            theme: $channel->theme,
            status: $channel->status,
            // Null rather than the store's value, because null is the fact:
            // "follows its store" and "happens to match its store today" are
            // different, and only one of them tracks a later change.
            currency: $channel->currency,
            locale: $channel->locale,
            primaryHost: $channel->primaryDomain()?->host,
            domains: $domains->map(ChannelDomainData::from(...))->all(),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'store_id' => $this->storeId,
            'name' => $this->name,
            'theme' => $this->theme,
            'status' => $this->status->value,
            'currency' => $this->currency,
            'locale' => $this->locale,
            'primary_host' => $this->primaryHost,
            'domains' => array_map(fn (ChannelDomainData $d): array => $d->toArray(), $this->domains),
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
