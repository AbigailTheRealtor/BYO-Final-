<?php

namespace App\Support\Listing;

use App\Services\ListingImport\Mls\MlsSupplementalDetails;

/**
 * BathroomTotal — the ONE reading of "how many bathrooms" from MLS/RESO components.
 *
 * The canonical total is RESO BathroomsTotalDecimal, counted in halves: one full and one half
 * bathroom is 1.5 (CanonicalListingVocabulary::PROPERTY_BATHROOMS). The feed also sends
 * BathroomsTotalInteger, which is a ROUNDED count — 2 for that same home — and MLS import used
 * to store it as the listing's bathrooms, so a page showing "Full Bathrooms 1 / Half Bathrooms 1"
 * also said "Bathrooms 2". Every consumer that needs a total from MLS components — import,
 * the canonical MLS adapter, and the read path for listings imported before this existed —
 * resolves it here, so they cannot disagree.
 *
 * PRECEDENCE, each step only when the one before is ABSENT:
 *   1. BathroomsTotalDecimal. When the feed sent one it decides alone — a value that is not a
 *      positive count in halves is unknown, never overruled by the components;
 *   2. BathroomsFull + ½ × BathroomsHalf, when Full is known, the Half count is known (or the
 *      integer equals Full, which rules halves out), and no three-quarter or quarter bathroom
 *      is reported (their weighting is not a convention this application holds);
 *   3. BathroomsTotalInteger, only when the Half count is explicitly zero and nothing reports a
 *      partial bathroom — then the integer is exact rather than rounded.
 * Zero, negative, non-numeric or finer-than-halves is UNKNOWN (null), never a fact: a
 * commercial record's "0" is the feed's placeholder, not a building with no bathrooms.
 *
 * Pure: no container, no I/O.
 */
final class BathroomTotal
{
    /** Bridge keys the rule reads, shared by the raw record and the stored MLS Details rows. */
    public const DECIMAL       = 'BathroomsTotalDecimal';
    public const INTEGER       = 'BathroomsTotalInteger';
    public const FULL          = 'BathroomsFull';
    public const HALF          = 'BathroomsHalf';
    public const THREE_QUARTER = 'BathroomsThreeQuarter';
    public const ONE_QUARTER   = 'BathroomsOneQuarter';
    public const PARTIAL       = 'BathroomsPartial';

    /** The canonical total from a raw RESO/Bridge record (keys as the feed sends them). */
    public static function fromRecord(array $raw): ?float
    {
        return self::resolve(
            $raw[self::DECIMAL] ?? null,
            $raw[self::INTEGER] ?? null,
            $raw[self::FULL] ?? null,
            $raw[self::HALF] ?? null,
            $raw[self::THREE_QUARTER] ?? null,
            $raw[self::ONE_QUARTER] ?? null,
            $raw[self::PARTIAL] ?? null,
        );
    }

    /**
     * The canonical total from the stored MLS Details rows (the facts the listing page renders
     * as "Full Bathrooms" / "Half Bathrooms"), for a listing imported before import stored it.
     */
    public static function fromMlsDetails(MlsSupplementalDetails $details): ?float
    {
        $values = [];
        foreach ($details->group('facts') as $section) {
            foreach ((array) ($section['rows'] ?? []) as $row) {
                $key = (string) ($row['key'] ?? '');
                if (in_array($key, [self::DECIMAL, self::INTEGER, self::FULL, self::HALF, self::THREE_QUARTER, self::ONE_QUARTER, self::PARTIAL], true)) {
                    $values[$key] = $row['value'] ?? null;
                }
            }
        }

        return $values === [] ? null : self::fromRecord($values);
    }

    /**
     * The listing's bathrooms as every READER must see them — the public page, its hero and Ask
     * AI all call this on the same decoded meta array, so they cannot disagree.
     *
     * When the listing's stored MLS Details carry bathroom components that determine a total
     * (the rows the page prints as "Full Bathrooms" / "Half Bathrooms"), that total governs:
     * a missing stored total is filled, and a stored total that CONTRADICTS the components —
     * the rounded "2" imports wrote before BathroomTotal existed, for one full and one half — is
     * replaced by the component total (1.5). A stored total that agrees is left exactly as it
     * is, and a listing without MLS components (every manual listing) is returned unchanged.
     *
     * READ-TIME ONLY: nothing is written back. An old import reads correctly without being
     * re-imported, and no stored row is rewritten by this.
     *
     * @param  array<string, mixed>  $meta  decoded listing meta
     * @return array<string, mixed>
     */
    public static function projectListingMeta(array $meta): array
    {
        $total = self::componentTotal($meta);
        if ($total === null) {
            return $meta;
        }

        $stored = $meta['bathrooms'] ?? null;
        if (is_string($stored) && strcasecmp(trim($stored), 'Other') === 0) {
            $stored = $meta['other_bathrooms'] ?? ($meta['custom_bathrooms'] ?? null);
        }
        if (self::normalize($stored) === $total) {
            return $meta;
        }

        $meta['bathrooms'] = self::format($total);

        return $meta;
    }

    /** The total the listing's stored MLS Details components determine, or null. */
    public static function componentTotal(array $meta): ?float
    {
        $blob = $meta[\App\Services\ListingImport\QuickImport\MlsQuickImportDraftWriter::META_PROPERTY_DETAILS] ?? null;
        if ($blob === null || $blob === '' || $blob === []) {
            return null;
        }

        return self::fromMlsDetails(MlsSupplementalDetails::fromStored($blob));
    }

    public static function resolve(
        mixed $decimal,
        mixed $integer,
        mixed $full,
        mixed $half,
        mixed $threeQuarter = null,
        mixed $oneQuarter = null,
        mixed $partial = null,
    ): ?float {
        // A decimal the feed SENT is its own statement of the total. When it cannot be read as a
        // count in halves (2.25 reports a quarter bathroom; "two" is not a number) the answer is
        // unknown — the components are not allowed to overrule the feed's own total.
        if (!self::absent($decimal)) {
            return self::normalize($decimal);
        }

        $full         = self::count($full);
        $half         = self::count($half);
        $integer      = self::count($integer);
        $threeQuarter = self::count($threeQuarter);
        $oneQuarter   = self::count($oneQuarter);
        $partial      = self::count($partial);
        $quartered    = ($threeQuarter ?? 0) > 0 || ($oneQuarter ?? 0) > 0;

        // Full + ½ × Half — only when the half count is KNOWN (or the integer equals the full
        // count, which rules halves out). A missing half count is not zero half bathrooms.
        if ($full !== null && !$quartered && ($half !== null || ($integer !== null && $integer === $full))) {
            return self::normalize($full + 0.5 * ($half ?? 0));
        }

        // The integer is exact only when no partial bathroom can be hiding in it: the half count
        // is explicitly zero and nothing reports a quarter or partial bathroom. Otherwise it is
        // the rounded count this class exists to keep out of a listing.
        $noPartials = $half === 0 && !$quartered && ($partial ?? 0) === 0;

        return $noPartials ? self::normalize($integer) : null;
    }

    /** A positive bathroom count in halves, or null. Accepts the numeric strings the feed and meta hold. */
    public static function normalize(mixed $value): ?float
    {
        if (is_string($value)) {
            $value = trim($value);
        }
        if (!is_int($value) && !is_float($value) && !(is_string($value) && is_numeric($value))) {
            return null;
        }
        $value = (float) $value;

        return ($value > 0 && fmod($value * 2, 1.0) === 0.0) ? $value : null;
    }

    /** "1.5" / "2" — the form's own option spelling (config property_types.bathroom_options). */
    public static function format(float $total): string
    {
        return floor($total) === $total ? (string) (int) $total : rtrim(rtrim(number_format($total, 1, '.', ''), '0'), '.');
    }

    private static function absent(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }

    private static function count(mixed $value): ?int
    {
        if (is_string($value)) {
            $value = trim($value);
        }
        if (is_int($value) && $value >= 0) {
            return $value;
        }
        if ((is_float($value) || (is_string($value) && is_numeric($value))) && (float) $value >= 0 && floor((float) $value) === (float) $value) {
            return (int) $value;
        }

        return null;
    }
}
