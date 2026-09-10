<?php

namespace App\Support\Listing;

/**
 * What a listing-detail page prints when it says "Listing Status".
 *
 * WHY THIS EXISTS
 * ---------------
 * The status question was already answered, correctly, by the model:
 * {@see \App\Models\SellerAgentAuction::getStatusAttribute()} resolves
 * `is_sold`, then the MLS market status via {@see MlsLinkedListingStatus},
 * then the stored `listing_status`, then `expiration_date`. The seller's
 * published page did not consult it. It read the raw `listing_status` meta
 * value out of the EAV blob and printed that.
 *
 * On an MLS-linked listing those two disagree, and visibly so on one screen:
 * the hero pill printed the stored 'Active' while the MLS Details section
 * below it printed Stellar's `StandardStatus` of 'Pending' under the label
 * "Status". The page contradicted itself, and the half a reader trusts most —
 * the big pill at the top — was the stale half.
 *
 * THE SPLIT, AND WHY IT IS NOT SIMPLY `$auction->status`
 * ------------------------------------------------------
 * Reading the accessor unconditionally would also change two things that were
 * never wrong, because the accessor is total where the display is not:
 *
 *   · it ends in `return 'Active'`, so a listing that has never had a status
 *     of any kind would start announcing one. The page's rule today is that
 *     an absent value renders no row at all, and inventing 'Active' for a
 *     manual listing is the same class of mistake as inventing it for an MLS
 *     listing whose first sync has not run — asserting something nobody told
 *     us.
 *
 *   · it derives 'Expired' from `expiration_date`. For a manual listing that
 *     is correct and is the platform's own lifecycle; it is deliberately left
 *     alone. This class changes what the page reads, not what the platform
 *     decides.
 *
 * So the effective status is consulted exactly where the feed owns the answer —
 * an MLS-linked listing with a source status stored — and every other listing
 * keeps, byte for byte, the value the page printed before.
 *
 * WHAT IT DELIBERATELY DOES NOT DO
 * --------------------------------
 * It does not read `StandardStatus` itself. Re-deriving the market status in a
 * presentation helper would be a second status mapping sitting beside
 * MlsLinkedListingStatus, and two mappings are how a page and its model come to
 * disagree — which is the defect being fixed here, rebuilt one layer up. The
 * *presence* of a market status selects the branch; the model supplies the
 * value, including `is_sold` winning over it.
 *
 * It writes nothing. Status is read here and only here.
 */
final class ListingStatusDisplay
{
    /**
     * The status string this listing's detail page should display, or null when
     * it should display no status row at all.
     *
     * The listing is the single source: its meta is read back off the model
     * rather than accepted alongside it, so the branch decision and the value
     * cannot be taken from two different snapshots of the same row.
     */
    public static function for(object $listing): ?string
    {
        $meta = self::meta($listing);

        // Is this a status the feed owns? Presence only — the value comes from
        // the model, which applies is_sold ahead of it.
        if (MlsLinkedListingStatus::marketStatus($meta) === null) {
            return self::text($meta['listing_status'] ?? null);
        }

        return self::text($listing->status ?? null);
    }

    /**
     * @return array<string,mixed>
     */
    private static function meta(object $listing): array
    {
        if (! isset($listing->get)) {
            return [];
        }

        $bag = $listing->get;

        return method_exists($bag, 'toArray') ? $bag->toArray() : [];
    }

    /**
     * Blank, whitespace and non-scalar all read as "no status", matching the
     * row helper the seller page already uses — an empty value has never
     * rendered a row and must not start now.
     */
    private static function text(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
