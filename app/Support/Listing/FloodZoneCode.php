<?php

namespace App\Support\Listing;

/**
 * The one decision about whether a stored value is a usable FEMA flood-zone designation.
 *
 * WHY THIS EXISTS. `flood_zone_code` is populated from three directions that disagree about
 * what may go in it:
 *
 *   - the Seller / Landlord form, whose own select offers `Unknown` and `Other` as choices,
 *     and whose `Other` branch unlocks a free-text box;
 *   - the MLS text importer, whose normalizer uppercased whatever it was handed, so
 *     "Zone AE", "N/A" and "AE - high risk" all became "flood zone codes";
 *   - the same importer's "Flood Insurance Required" branch, which wrote the literal string
 *     `yes` into the ZONE CODE field — a boolean answer stored as a designation.
 *
 * So the column holds designations, sentinels, prose and booleans, and no reader could tell
 * them apart. This class is what tells them apart, once, for every reader.
 *
 * THE RULE IS RECOGNITION, NOT EXTRACTION. A value is either already a designation or it is
 * not one. Nothing here parses "Zone AE" down to "AE", and nothing infers a zone from a
 * Yes/No flood flag: both would be this class inventing a FEMA determination the record does
 * not actually make, which is the precise failure it exists to stop. An unusable value is
 * null — not a guess, not a partial match, not the raw string passed through.
 *
 * THE SENTINEL LIST IS LOAD-BEARING, not decoration. `NO`, `NA`, `NONE`, `TBD` and `YES` all
 * satisfy the shape rule — one to four alphanumerics — so the pattern alone would accept
 * every one of them as a zone. They are rejected by name, before the shape is considered.
 *
 * This class decides nothing about VISIBILITY. Whether a usable code may be published is
 * SnapshotFactVisibility's decision and is unchanged by this file.
 */
final class FloodZoneCode
{
    /**
     * Values that occupy the field without being a designation.
     *
     * `UNKNOWN`, `OTHER` and `FLOOD` are longer than the shape rule allows and would be
     * rejected anyway; they are named here so the list reads as the complete set of things
     * this field is known to contain instead of a list of pattern near-misses.
     */
    public const SENTINELS = [
        'YES', 'NO', 'UNKNOWN', 'OTHER', 'FLOOD', 'N/A', 'NA', 'NONE', 'TBD',
    ];

    /** One to four letters or digits — "X", "AE", "VE", "A99", "AR". */
    private const SHAPE = '/^[A-Z0-9]{1,4}$/';

    /**
     * The canonical designation, or null when the value is not one.
     *
     * @return string|null uppercase designation; null for blank, boolean, sentinel,
     *                     mis-shaped, or any prose
     */
    public static function canonical(mixed $value): ?string
    {
        // A boolean is a flood FLAG, never a zone. Rejected before the string cast, which
        // would otherwise turn true into "1" — a value that satisfies the shape rule.
        if (!is_scalar($value) || is_bool($value)) {
            return null;
        }

        $candidate = strtoupper(trim((string) $value));

        if ($candidate === '' || in_array($candidate, self::SENTINELS, true)) {
            return null;
        }

        // A purely numeric value is not a designation. Every FEMA zone begins with a letter
        // (A, AE, AH, AO, AR, A99, V, VE, X, D, B, C), and "1" / "0" is how a boolean flood
        // FLAG arrives from a feed that stores booleans as integers — the same "a flag is
        // not a zone" rule as the is_bool() rejection above, one encoding further on. It is
        // excluded here rather than by the shape rule, which must keep accepting the digits
        // inside A99 and AR12.
        if (preg_match('/^[0-9]+$/', $candidate) === 1) {
            return null;
        }

        return preg_match(self::SHAPE, $candidate) === 1 ? $candidate : null;
    }

    public static function isUsable(mixed $value): bool
    {
        return self::canonical($value) !== null;
    }
}
