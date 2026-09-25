<?php

namespace App\Helpers;

/**
 * Phase 5 (A5.29 / A5.30) — canonical contingency option sets + legacy display mapping.
 *
 * Seller and Buyer answer the contingency questions from DIFFERENT perspectives:
 *   - Seller: "will I accept an offer that carries this contingency?"
 *       Accepted / Not Accepted / Negotiable / Not Applicable
 *   - Buyer:  "does my offer include this contingency?"
 *       Appraisal/Financing: Included / Waived / Negotiable / Not Applicable
 *       Sale-of-Buyer's-Property: Included / Not Included / Negotiable / Not Applicable
 *
 * Legacy values that pre-date the new option sets are mapped to their canonical
 * equivalent for DISPLAY and EDIT only. Stored values are never rewritten by this
 * helper — callers preserve the raw value (an untouched save keeps the original;
 * normalisation only happens if the user actively picks a new option).
 *
 * Legacy → canonical (owner-approved mapping, Phase 5):
 *   Seller:  Required → Accepted, Preferred Waived → Negotiable
 *   Buyer (appraisal/financing): Yes → Included, No → Waived,
 *                                "Not Applicable (Cash)" → Not Applicable
 *   Buyer (home sale): Yes → Included, No → Not Included
 */
class ContingencyOptionHelper
{
    /** Seller appraisal / financing / sale-of-buyer's-property contingency options. */
    public const SELLER = ['Accepted', 'Not Accepted', 'Negotiable', 'Not Applicable'];

    /** Buyer appraisal / financing contingency options. */
    public const BUYER_APPRAISAL_FINANCING = ['Included', 'Waived', 'Negotiable', 'Not Applicable'];

    /** Buyer sale-of-buyer's-property (home sale) contingency options. */
    public const BUYER_HOME_SALE = ['Included', 'Not Included', 'Negotiable', 'Not Applicable'];

    /** Map a stored Seller contingency value to its canonical display label. */
    public static function sellerDisplay(?string $value): string
    {
        return match ($value) {
            'Required'         => 'Accepted',
            'Preferred Waived' => 'Negotiable',
            default            => (string) $value,
        };
    }

    /** Map a stored Buyer appraisal/financing contingency value to its canonical display label. */
    public static function buyerAppraisalFinancingDisplay(?string $value): string
    {
        return match ($value) {
            'Yes'                   => 'Included',
            'No'                    => 'Waived',
            'Not Applicable (Cash)' => 'Not Applicable',
            default                 => (string) $value,
        };
    }

    /**
     * Does a buyer appraisal / financing / inspection contingency carry a period on the buyer
     * page? Only when it displays as Included or Negotiable. One rule for the page and for Ask
     * AI, so a period is never stated where the page would not print it.
     */
    public static function buyerShowsPeriod(?string $value): bool
    {
        return in_array(self::buyerAppraisalFinancingDisplay((string) $value), ['Included', 'Negotiable'], true);
    }

    /** Is the buyer page's Home Sale Contingency block shown for this stored value? */
    public static function buyerHomeSaleShown(?string $value): bool
    {
        return in_array(self::buyerHomeSaleDisplay((string) $value), ['Included', 'Negotiable'], true);
    }

    /** Map a stored Buyer home-sale contingency value to its canonical display label. */
    public static function buyerHomeSaleDisplay(?string $value): string
    {
        return match ($value) {
            'Yes' => 'Included',
            'No'  => 'Not Included',
            default => (string) $value,
        };
    }
}
