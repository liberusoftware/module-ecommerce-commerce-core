<?php

namespace Liberu\Ecommerce\CommerceCore\Enums;

/**
 * What a store is allowed to do.
 *
 * An enum rather than free strings, because a capability is a switch other
 * modules branch on — checkout asks about guest checkout, pricing asks about
 * multi-currency — and a typo in a free string reads as "off" rather than as an
 * error. The set grows by release, which is exactly the review a new switch on
 * every merchant's storefront deserves.
 *
 * The default is what a store gets before anybody decides, and it is off for
 * every capability that widens what a shopper may do. A merchant turns a
 * capability on; nobody has it turned on for them.
 */
enum Capability: string
{
    /** Shoppers may check out without an account. */
    case GuestCheckout = 'guest_checkout';

    /** Prices may be presented in more than the store's own currency. */
    case MultiCurrency = 'multi_currency';

    /** The store trades with business customers, with the tax handling that implies. */
    case BusinessToBusiness = 'business_to_business';

    /** Orders may be placed against stock the store does not yet hold. */
    case Backorders = 'backorders';

    public function defaultEnabled(): bool
    {
        return false;
    }

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }
}
