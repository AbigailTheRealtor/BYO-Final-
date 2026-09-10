<?php

namespace App\Support\Listing;

/**
 * The Stellar/Bridge listing status vocabulary, and what this application is
 * entitled to claim about it.
 *
 * WHY THIS CLASS EXISTS RATHER THAN A MATCH STATEMENT SOMEWHERE
 * ------------------------------------------------------------
 * Before the 2026-09-10 lifecycle probe, the only status literal anywhere in
 * this codebase was the `StandardStatus eq 'Active'` clause in the two OData
 * filter builders, and every one of the seven per-type fixtures is `Active`. A
 * status map written from memory would therefore have been a map of RESO's
 * published vocabulary rather than of the strings this dataset actually emits —
 * and the difference is not academic, as the probe showed.
 *
 * THE PROBE, AND WHAT IT ESTABLISHED (2026-09-10, `mls:probe-lifecycle`)
 * ---------------------------------------------------------------------
 * One narrow request per candidate status against the live dataset:
 *
 *   CONFIRMED — a record came back:
 *     Active, Pending, Closed, Active Under Contract, Coming Soon
 *
 *   UNCONFIRMED — the dataset returned nothing for it:
 *     Expired, Withdrawn, Canceled, Cancelled, Temporarily Off Market
 *
 * Unconfirmed is NOT the same as non-existent. An IDX feed commonly withholds
 * off-market records under its licence, so "no Expired record today" may mean
 * the status exists and we are not licensed to see it, or that none currently
 * exists. Both readings are consistent with the evidence, so both sets are
 * recognised here and neither is treated as an error.
 *
 * TWO VOCABULARIES, NOT ONE — and this is the trap.
 * -------------------------------------------------
 * `StandardStatus` and `MlsStatus` are different fields with different values on
 * the SAME record. The probe caught two disagreements directly:
 *
 *     StandardStatus 'Closed'                ↔ MlsStatus 'Sold'
 *     StandardStatus 'Active Under Contract' ↔ MlsStatus 'Pending'
 *
 * `StandardStatus` is the RESO-normalised field and is the one the owner's
 * transition list is written in (Active → Pending, Active → Expired, Pending →
 * Closed), so it is the authoritative market status here. `MlsStatus` is
 * preserved verbatim alongside it as Stellar's own local wording, never mapped
 * onto the other and never used as a fallback: a listing whose StandardStatus
 * is missing is a listing whose status we do not know, and guessing it from
 * 'Sold' would be inventing the very normalisation RESO already performed.
 *
 * NOTHING HERE MAPS A STATUS ONTO A BidYourOffer STATUS STRING.
 * ------------------------------------------------------------
 * The owner's rule is "preserve the exact confirmed Stellar status strings", so
 * the status passes through verbatim as the listing's market status. This class
 * only answers questions ABOUT a status — is it one we recognise, does it mean
 * the property has left the market — and never rewrites one.
 */
final class MlsSourceStatus
{
    /**
     * Statuses a live request against this dataset actually returned.
     *
     * @var list<string>
     */
    public const PROBE_CONFIRMED = [
        'Active',
        'Pending',
        'Closed',
        'Active Under Contract',
        'Coming Soon',
    ];

    /**
     * Statuses the owner's transition contract names, which the probe could not
     * confirm in this dataset.
     *
     * Recognised so that one arriving tomorrow is handled rather than flagged as
     * unknown — the owner has stated these transitions must be followed. Kept in
     * a separate constant from PROBE_CONFIRMED so nobody later reads this file
     * as evidence that the dataset emits them. It is not.
     *
     * @var list<string>
     */
    public const OWNER_DECLARED = [
        'Expired',
        'Withdrawn',
        'Canceled',
        'Cancelled',
        'Temporarily Off Market',
    ];

    /**
     * Statuses meaning the property is no longer openly on the market.
     *
     * Exposed for callers that need the distinction. Phase 1A deliberately wires
     * this to NOTHING destructive — in particular it does not trigger
     * MlsListingGallerySync::detachAll(). Withdrawing photographs from a closed
     * or withdrawn listing is a licensing decision about republication, not a
     * mechanical consequence of a status string, and it has not been taken.
     *
     * @var list<string>
     */
    private const OFF_MARKET = [
        'Closed',
        'Expired',
        'Withdrawn',
        'Canceled',
        'Cancelled',
        'Temporarily Off Market',
    ];

    /** Trimmed, or null when there is no usable status at all. */
    public static function normalize(mixed $status): ?string
    {
        if (! is_string($status)) {
            return null;
        }

        $trimmed = trim($status);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * Is this a status we have either observed or been told to expect?
     *
     * Case-sensitive on purpose. The feed's own casing is what gets stored and
     * displayed, and a case-insensitive match here would quietly accept
     * 'ACTIVE' as recognised while the listing went on to display 'ACTIVE'.
     */
    public static function isRecognised(mixed $status): bool
    {
        $normalized = self::normalize($status);

        if ($normalized === null) {
            return false;
        }

        return in_array($normalized, self::PROBE_CONFIRMED, true)
            || in_array($normalized, self::OWNER_DECLARED, true);
    }

    /** Was this status actually observed in the live dataset? */
    public static function isProbeConfirmed(mixed $status): bool
    {
        $normalized = self::normalize($status);

        return $normalized !== null && in_array($normalized, self::PROBE_CONFIRMED, true);
    }

    /**
     * Has the property left the open market?
     *
     * An unrecognised status answers false: we do not know what it means, and
     * inferring "off market" from an unfamiliar word is exactly the silent
     * misreading the unknown-status rule exists to prevent.
     */
    public static function isOffMarket(mixed $status): bool
    {
        $normalized = self::normalize($status);

        return $normalized !== null && in_array($normalized, self::OFF_MARKET, true);
    }

    /** Every status this class recognises, confirmed and declared together. */
    public static function recognised(): array
    {
        return array_merge(self::PROBE_CONFIRMED, self::OWNER_DECLARED);
    }
}
