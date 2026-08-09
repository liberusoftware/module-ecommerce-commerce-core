<?php

namespace Liberu\Ecommerce\CommerceCore\Enums;

/**
 * A store's lifecycle, and the only transitions that exist.
 *
 * The transitions are data rather than a pile of `if`s so that both the domain
 * action and any presentation package asking *what can I offer this operator*
 * read the same source. A surface that hard-codes its own list drifts, and the
 * drift shows up as a button that 500s.
 */
enum StoreStatus: string
{
    /** Created, not yet serving. Nothing resolves to it. */
    case Draft = 'draft';

    /** Serving shoppers. */
    case Active = 'active';

    /** Temporarily stopped — non-payment, investigation — and reversible. */
    case Suspended = 'suspended';

    /** Closed for good. Terminal: an archived store is never reopened, it is replaced. */
    case Archived = 'archived';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Active, self::Archived],
            self::Active => [self::Suspended, self::Archived],
            self::Suspended => [self::Active, self::Archived],
            self::Archived => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /** Whether a storefront on this store answers at all. */
    public function isServing(): bool
    {
        return $this === self::Active;
    }

    public function isTerminal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
