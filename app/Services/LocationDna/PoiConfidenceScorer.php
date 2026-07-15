<?php

namespace App\Services\LocationDna;

/**
 * PoiConfidenceScorer — canonical POI confidence (docs/canonical-field-mapping-spec.md §2).
 *
 * WHY THIS EXISTS
 * ---------------
 * Batch 3 extracts the confidence formula that lived inline in
 * {@see GooglePlacesPoiAdapter::search()} so ONE formula produces both the adapter's
 * per-item envelope confidence AND the persisted `property_location_pois.confidence`
 * written by the single POI writer in {@see LocationDnaPoiDistanceService}. Before this,
 * only the adapter path derived confidence; the persistence path wrote NULL. Extracting
 * it keeps the two byte-identical by construction rather than by hope.
 *
 * THE MODEL (spec §2, "Rated commercial POI")
 * -------------------------------------------
 *   - Unrated (rating === null): structural existence confidence 0.5 — the provider
 *     returned the POI but supplied no quality signal, and none is fabricated.
 *   - Rated: a floor of 0.6 rising toward 0.9 as review volume climbs, saturating at
 *     REVIEW_SATURATION reviews. An absent/negative review count counts as 0 reviews.
 *
 *     0 reviews → 0.6 · 100 reviews → 0.75 · 200+ reviews → 0.9 · rounded to 3 dp.
 *
 * Pure, stateless, deterministic: no I/O, no framework, safe to `new` anywhere.
 */
final class PoiConfidenceScorer
{
    /** Existence-only confidence for a POI the provider returned but did not rate. */
    private const CONFIDENCE_STRUCTURAL = 0.5;

    /** Floor confidence for a rated POI (0 reviews). */
    private const CONFIDENCE_RATED_BASE = 0.6;

    /** Additional confidence a rated POI earns as review volume saturates. */
    private const CONFIDENCE_RATED_SPAN = 0.3;

    /** Review count at which rated confidence reaches its CONFIDENCE_RATED_BASE + SPAN ceiling. */
    private const REVIEW_SATURATION = 200;

    /**
     * Derive a 0.0–1.0 confidence from a POI's rating signal.
     *
     * @param  ?float $rating       Provider rating, or null when the provider did not rate the POI.
     * @param  int    $reviewCount  Provider review count; a negative count is treated as 0.
     * @return ?float               0.5 when unrated; otherwise 0.6–0.9 scaled by review volume.
     */
    public function score(?float $rating, int $reviewCount): ?float
    {
        if ($rating === null) {
            return self::CONFIDENCE_STRUCTURAL;
        }

        $reviewFactor = min(1.0, max(0, $reviewCount) / self::REVIEW_SATURATION);

        return round(
            self::CONFIDENCE_RATED_BASE + (self::CONFIDENCE_RATED_SPAN * $reviewFactor),
            3
        );
    }
}
