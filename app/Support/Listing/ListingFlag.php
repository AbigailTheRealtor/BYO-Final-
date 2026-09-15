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
 * `is_approved` is the same kind of flag and gets the same answer. The four role
 * models used to cast it `boolean` — which is how the string 'false' in the
 * Seller / Buyer varchar columns read as approved — and now read the raw value
 * through this class instead: {@see \App\Models\Concerns\HasApprovalFlag}.
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

    /**
     * TRUE_VALUES as a varchar column stores them: PHP true and 1 are written as '1'.
     *
     * @return list<string>
     */
    public static function storedTrueValues(): array
    {
        $stored = array_map(
            static fn ($value): string => is_bool($value) ? ($value ? '1' : '0') : (string) $value,
            self::TRUE_VALUES
        );

        return array_values(array_unique($stored));
    }

    /**
     * Constrain a query to rows whose $column is set — the query side of isTrue().
     *
     * A query never passes through the model, so the model's reading does not reach it.
     * `where($column, true)` binds the integer 1 and matches '1' alone, while both Buyer
     * publish paths store the string 'true': a row isTrue() calls set, and that where()
     * never finds. An exact list of the stored forms matches both, coerces nothing, and
     * reads the same on SQLite and PostgreSQL.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder
     */
    public static function whereTrue($query, string $column)
    {
        return $query->whereIn($column, self::storedTrueValues());
    }

    /**
     * Constrain a query to rows whose $column is NOT set: every other value, NULL
     * included — a bare NOT IN would drop NULL rows silently.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder
     */
    public static function whereNotTrue($query, string $column)
    {
        return $query->where(function ($q) use ($column) {
            $q->whereNotIn($column, self::storedTrueValues())->orWhereNull($column);
        });
    }
}
