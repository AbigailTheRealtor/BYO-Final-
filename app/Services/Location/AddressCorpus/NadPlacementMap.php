<?php

namespace App\Services\Location\AddressCorpus;

use App\Services\Location\Coordinates\CoordinatePrecision;

/**
 * NAD `Placement` → {@see CoordinatePrecision}, and the deliberate refusal to
 * guess.
 *
 * NOTHING IS INFERRED FROM THE PRESENCE OF A COORDINATE
 * ----------------------------------------------------
 * A row with a perfectly valid latitude and longitude and no `Placement` value
 * is a row that did not say how the point was placed. It could be a surveyed
 * rooftop or a parcel centroid; the coordinate itself cannot tell you which, and
 * the difference decides whether the point may drive flood-boundary work.
 *
 * So an unmapped value returns **null**, not a tier. Null does not mean
 * "unknown precision, treat as coarse" — that decision has an owner and has not
 * been made. It means this class declined, and the caller must carry the raw
 * value forward so the decision can be made from evidence.
 *
 * `Placement` is only `Commonly Used` in the NAD schema, so the share of Florida
 * rows carrying nothing is an open question that the dry run exists to answer.
 * Once the real distribution is known, the mapping below gets confirmed or
 * corrected by someone looking at actual counts.
 *
 * THE SEEDED ENTRIES ARE PROPOSALS
 * --------------------------------
 * Only `Structure - Rooftop` appears in the published NAD schema (as the
 * `Placement` field's example). The rest are seeded from the NENA vocabulary the
 * schema is built on, matched **exactly** after case and whitespace folding —
 * never by substring, because "Parcel" appearing inside a longer phrase is not
 * evidence that the phrase means a parcel centroid.
 *
 * Anything not listed here is reported as unrecognised, with its count, rather
 * than being quietly folded into the nearest-looking tier.
 */
final class NadPlacementMap
{
    /**
     * Proposed mapping, pending confirmation against the observed distribution.
     *
     * Keys are already normalized (see {@see self::normalize()}).
     *
     * @var array<string, CoordinatePrecision>
     */
    public const PROPOSED = [
        // Documented in the NAD schema as the Placement example.
        'structure - rooftop'  => CoordinatePrecision::Rooftop,
        'rooftop'              => CoordinatePrecision::Rooftop,

        // A mapped building entrance is its own tier in CoordinatePrecision.
        'structure - entrance' => CoordinatePrecision::Entrance,
        'structure - doorway'  => CoordinatePrecision::Entrance,
        'entrance'             => CoordinatePrecision::Entrance,

        // The building's centre locates the structure but makes no claim about
        // the roof, which is exactly what Parcel means in this codebase.
        'structure - center'   => CoordinatePrecision::Parcel,
        'structure - centroid' => CoordinatePrecision::Parcel,

        'parcel - center'      => CoordinatePrecision::Parcel,
        'parcel - centroid'    => CoordinatePrecision::Parcel,
        'parcel centroid'      => CoordinatePrecision::Parcel,
    ];

    /**
     * The precision a raw Placement value proposes, or null when this class
     * declines to say.
     *
     * Null covers three distinct situations the caller must keep apart in its
     * counts: the field was absent, the field was empty, and the field held
     * something not in the table above.
     */
    public static function proposedPrecision(?string $raw): ?CoordinatePrecision
    {
        $key = self::normalize($raw);

        if ($key === '') {
            return null;
        }

        return self::PROPOSED[$key] ?? null;
    }

    /** True when the value is one this class recognises. */
    public static function isRecognised(?string $raw): bool
    {
        return self::proposedPrecision($raw) !== null;
    }

    /**
     * Case- and whitespace-folded form used for lookup.
     *
     * Submitters vary the spacing around the dash ("Structure-Rooftop",
     * "Structure - Rooftop"), which is formatting rather than meaning.
     */
    public static function normalize(?string $raw): string
    {
        if ($raw === null) {
            return '';
        }

        $value = strtolower(trim($raw));
        $value = preg_replace('/\s*-\s*/', ' - ', $value) ?? $value;

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }
}
