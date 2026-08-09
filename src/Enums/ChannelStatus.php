<?php

namespace Liberu\Ecommerce\CommerceCore\Enums;

/**
 * A channel's lifecycle.
 *
 * Shorter than a store's on purpose: a channel is a way in, not a business.
 * Turning one off is reversible and leaves the store trading through its other
 * channels, so there is no terminal state to model — removing a channel is a
 * delete, and the hostnames go with it.
 */
enum ChannelStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Disabled = 'disabled';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Active],
            self::Active => [self::Disabled],
            self::Disabled => [self::Active],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isServing(): bool
    {
        return $this === self::Active;
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
