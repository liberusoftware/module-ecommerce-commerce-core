<?php

namespace Liberu\Ecommerce\CommerceCore\Data;

use JsonSerializable;
use Liberu\Ecommerce\CommerceCore\Models\ChannelDomain;

final readonly class ChannelDomainData implements JsonSerializable
{
    public function __construct(
        public int $id,
        public int $channelId,
        public string $host,
        public bool $isPrimary,
    ) {}

    public static function from(ChannelDomain $domain): self
    {
        return new self(
            id: (int) $domain->id,
            channelId: (int) $domain->channel_id,
            host: $domain->host,
            isPrimary: (bool) $domain->is_primary,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'channel_id' => $this->channelId,
            'host' => $this->host,
            'is_primary' => $this->isPrimary,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
