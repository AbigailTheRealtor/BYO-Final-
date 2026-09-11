<?php

namespace App\Support\Listing;

/**
 * Whether a stored listing flag is set — one answer for every reader.
 *
 * WHY THIS EXISTS
 * ---------------
 * `is_sold` records a completed BidYourOffer transaction on the four role
 * listing tables, and those tables do not agree on how to store it:
 *
 *     seller_agent_auctions, buyer_agent_auctions      varchar, default '0'
 *     landlord_agent_auctions, tenant_agent_auctions   boolean, default false
 *
 * Nor do the writers. Laravel binds PHP true/false as 1/0; the Seller wizards
 * assign 0; BuyerOfferListing and the Hire Buyer wizard assign the literal
 * string 'false', which a varchar column keeps verbatim; and the two tenant
 * wizard components assign 'false' to the buyer and seller rows they save, so
 * seller_agent_auctions can hold it too. None of the four models casts the
 * column, so a reader receives whichever of those arrived.
 *
 * The four role models' status accessors, both Agent hubs and the DNA relevance
 * resolver already answered with the same strict list below. The shared Agent
 * listing page used a plain `(bool)` cast — and `(bool) 'false'` is true, so a
 * Buyer listing saved with 'false' was announced there as an accepted
 * transaction while the hub beside it called it open. The same stored value must
 * mean the same thing everywhere, so the list lives here, once.
 *
 * WHY NOT A MODEL CAST
 * --------------------
 * Eloquent's `boolean` cast is `(bool) $value` — the very reading being removed.
 * Casting the column would make the models agree with the defect rather than the
 * page agree with the models.
 *
 * It reads and never rewrites. Normalising historical rows would be a data
 * change, and it is not this class's decision.
 *
 * No container, no config: the models call it, and a model must be usable
 * without a booted application.
 */
final class ListingFlag
{
    /**
     * The only stored values that count as set. Everything else — false, 0,
     * '0', '', null and the string 'false' — does not.
     *
     * @var list<bool|int|string>
     */
    public const TRUE_VALUES = [true, 1, '1', 'true'];

    public static function isTrue(mixed $value): bool
    {
        return in_array($value, self::TRUE_VALUES, true);
    }
}
