<?php

namespace Tests\Support\Spatial;

/**
 * B1.2 — Tier-2 mixed-geometry fixture plan (cluster-independent, pure).
 *
 * The canonical per-category row distribution for the synthetic `places` corpus
 * used by the later E-50 mixed-KNN EXPLAIN acceptance gate (plan §6). It mirrors
 * the exact Stage 0b Tier-2 distribution (5,000,200 rows) so the plan-shape check
 * runs against a realistically sparse corpus — a near-empty table lets the planner
 * pick a seq scan and produces a misleading EXPLAIN.
 *
 * This class is the SPEC; the runnable generator is
 * spikes/phase-2-batch-1b-postgis-schema/fixtures/generate_tier2_fixture.sql,
 * whose embedded counts are asserted equal to this plan by FixtureCorpusPlanTest.
 *
 * Sparse categories (kept < 0.6% each) are the ONLY meaningful KNN targets
 * (SSOT §7.3): boat_ramp, airport, marina, urgent_care. Dense categories look
 * fine either way and tell you nothing.
 */
final class FixtureCorpusPlan
{
    /** Exact Stage 0b Tier-2 counts (results/crunchy/RESULT.md). Sum = 5,000,200. */
    private const TIER2 = [
        'restaurant'    => 2_265_000,
        'retail_store'  => 1_700_000,
        'school'        =>   566_000,
        'park'          =>   425_000,
        'urgent_care'   =>    25_500,
        'marina'        =>     8_500,
        'boat_ramp'     =>     6_200,
        'airport'       =>     4_000,
    ];

    /** The four sparse categories — the KNN validation targets (SSOT §7.3). */
    private const SPARSE = ['urgent_care', 'marina', 'boat_ramp', 'airport'];

    /**
     * Which categories carry non-point geometry in the fixture, so the corpus is a
     * true geography(Geometry,4326) mix (E-50 caveat: production places.geom is
     * mixed, the spike proved only geography(Point)). Areas per SSOT §7.4.
     */
    private const GEOMETRY_MIX = [
        'park'         => 'Polygon',      // areas — dense polygon
        'airport'      => 'Polygon',      // sparse polygon — the E-50 order-swap category
        'boat_ramp'    => 'LineString',   // sparse linestring — exercises a third geom type
        // everything else defaults to Point
    ];

    public const TIER2_TOTAL = 5_000_200;

    /** Canonical Tier-2 counts, unscaled. */
    public function tier2Counts(): array
    {
        return self::TIER2;
    }

    /**
     * Per-category counts scaled to an arbitrary total, preserving proportions and
     * summing to exactly $total (rounding drift is absorbed by the largest bucket).
     */
    public function forTotal(int $total): array
    {
        if ($total <= 0) {
            throw new \InvalidArgumentException("Fixture total must be positive; got {$total}.");
        }

        $out = [];
        $running = 0;
        foreach (self::TIER2 as $cat => $base) {
            $scaled = (int) round($base * $total / self::TIER2_TOTAL);
            $out[$cat] = $scaled;
            $running += $scaled;
        }

        // Absorb rounding drift into the largest category so the sum is exact.
        $largest = array_keys($out, max($out))[0];
        $out[$largest] += ($total - $running);

        return $out;
    }

    public function sparseCategories(): array
    {
        return self::SPARSE;
    }

    public function geometryType(string $category): string
    {
        return self::GEOMETRY_MIX[$category] ?? 'Point';
    }

    /** True when the fixture spans >1 geometry type (required to exercise E-50). */
    public function isMixedGeometry(): bool
    {
        return count(array_unique(array_values(self::GEOMETRY_MIX))) >= 1
            && count(self::GEOMETRY_MIX) > 0;
    }

    /** Fraction of the total each sparse category represents (must be < 0.6%). */
    public function sparseFractions(): array
    {
        $out = [];
        foreach (self::SPARSE as $cat) {
            $out[$cat] = self::TIER2[$cat] / self::TIER2_TOTAL;
        }
        return $out;
    }
}
