<?php

namespace App\Support\Listing;

use App\Services\ListingImport\QuickImport\MlsQuickImportDraftWriter as Meta;

/**
 * Whether a listing's market status is Stellar's to decide, and what it is.
 *
 * THE RULE, FROM THE OWNER'S LIVE-SYNC CONTRACT
 * ---------------------------------------------
 *     IF MLS-linked:  market status = the current Stellar status
 *     ELSE:           the existing BidYourOffer status logic applies, unchanged
 *
 * And, stated as a prohibition, because this is the half that was actually
 * wrong: for an MLS-linked listing BidYourOffer must not override Stellar using
 * `expiration_date`, a manually selected `listing_status`, a bidding timer or an
 * auction timer.
 *
 * WHAT THIS FIXES
 * ---------------
 * `expiration_date` is a date a user types into a form. On every listing model
 * it independently computed 'Expired' once that date passed:
 *
 *     if ($expirationDate && now()->gte(parse($expirationDate))) return 'Expired';
 *
 * On an MLS-linked listing that is a BidYourOffer timer silently contradicting
 * the MLS. A property Stellar still lists as Active would read 'Expired' on this
 * platform because of a date typed weeks earlier, and nothing on the page would
 * explain why. That check is now reached only by listings that own their own
 * lifecycle — manual ones.
 *
 * WHY THIS IS A SHARED CLASS AND NOT A COPY IN EACH MODEL
 * ------------------------------------------------------
 * Seller and Landlord both need it and their status accessors are already
 * near-identical copies. A second copy is how the two roles come to disagree
 * about whether a listing has expired — which, for the seller and the landlord
 * looking at the same feed, is not a cosmetic difference.
 *
 * WHAT IT DELIBERATELY DOES NOT OVERRIDE
 * --------------------------------------
 * `is_sold` still wins, and is checked by the caller BEFORE this class is
 * consulted. That flag records a completed BidYourOffer transaction — an
 * accepted bid, a hired agent — and the owner's protected list names bids,
 * offers, counters and history as user-owned. A closed deal on this platform is
 * not something an MLS status string should be able to reopen.
 */
final class MlsLinkedListingStatus
{
    /**
     * Is this listing linked to an MLS source record?
     *
     * The same test {@see \App\Services\ListingImport\Mls\MlsListingDetailsReader::isMlsImported()}
     * applies — either identifier is enough, because a listing imported before
     * ListingKey was persisted still carries its MLS number.
     *
     * @param  array<string,mixed>  $meta
     */
    public static function isLinked(array $meta): bool
    {
        return self::filled($meta[Meta::META_LISTING_KEY] ?? null)
            || self::filled($meta[Meta::META_MLS_NUMBER] ?? null);
    }

    /**
     * The Stellar status this listing must report, or null when BidYourOffer's
     * own lifecycle logic should run instead.
     *
     * Null is returned in two distinct situations, and both correctly fall
     * through to the platform's own rules:
     *
     *   · the listing is not MLS-linked — a manual listing, whose expiration
     *     date and chosen status are exactly what should govern it;
     *   · it is MLS-linked but no source status has ever been stored — an
     *     import that predates sync, or one whose first sync has not run.
     *     Inventing 'Active' for it would assert something the feed has not
     *     told us.
     *
     * StandardStatus is preferred over MlsStatus: it is the RESO-normalised
     * field, it is the vocabulary the owner's transition list is written in, and
     * the two genuinely disagree on real records. The value is returned
     * VERBATIM — unrecognised statuses included — because the contract is to
     * preserve the exact Stellar string, and a status we do not recognise is
     * still what the MLS says.
     *
     * @param  array<string,mixed>  $meta
     */
    public static function marketStatus(array $meta): ?string
    {
        if (! self::isLinked($meta)) {
            return null;
        }

        $standard = MlsSourceStatus::normalize($meta[Meta::META_STANDARD_STATUS] ?? null);

        if ($standard !== null) {
            return $standard;
        }

        return MlsSourceStatus::normalize($meta[Meta::META_SOURCE_STATUS] ?? null);
    }

    private static function filled(mixed $value): bool
    {
        return is_string($value) ? trim($value) !== '' : ! empty($value);
    }
}
