<?php

namespace Liberu\Ecommerce\CommerceCore\Data;

use JsonSerializable;
use Liberu\Ecommerce\CommerceCore\Enums\Capability;
use Liberu\Ecommerce\CommerceCore\Enums\StoreStatus;
use Liberu\Ecommerce\CommerceCore\Models\Store;

/**
 * A store as anything outside this module is allowed to see it.
 *
 * The read model exists because of a boundary rule with teeth: an `-api`
 * package may not import a `Models\` class at all. Without something like this
 * the adapter has nothing to serialise, and the rule gets waived instead of
 * met. It is also the thing that makes the API contract stable — a column
 * rename inside this package is not a breaking change to a consumer that never
 * saw the column.
 *
 * @param  list<string>  $capabilities
 */
final readonly class StoreData implements JsonSerializable
{
    /** @param list<string> $capabilities */
    public function __construct(
        public int $id,
        public ?int $teamId,
        public string $name,
        public string $slug,
        public StoreStatus $status,
        public string $currency,
        public string $locale,
        public string $timezone,
        public ?string $archivedAt,
        public array $capabilities,
    ) {}

    public static function from(Store $store): self
    {
        return new self(
            id: (int) $store->id,
            teamId: $store->team_id === null ? null : (int) $store->team_id,
            name: $store->name,
            slug: $store->slug,
            status: $store->status,
            currency: $store->currency,
            locale: $store->locale,
            timezone: $store->timezone,
            archivedAt: $store->archived_at?->toIso8601String(),
            capabilities: array_values(array_map(
                fn (Capability $capability): string => $capability->value,
                array_filter(Capability::cases(), $store->allows(...)),
            )),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'team_id' => $this->teamId,
            'name' => $this->name,
            'slug' => $this->slug,
            'status' => $this->status->value,
            'currency' => $this->currency,
            'locale' => $this->locale,
            'timezone' => $this->timezone,
            'archived_at' => $this->archivedAt,
            'capabilities' => $this->capabilities,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
