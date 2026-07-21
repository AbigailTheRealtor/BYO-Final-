<?php

namespace App\Services\Spatial;

/**
 * Spatial Intelligence Platform — Phase 2 Batch 2D Part C1 (cross-source authority linking).
 *
 * Pure, deterministic implementation of the SSOT §8.2 cross-source dedup rule — the offline
 * counterpart of the Class-2 `ST_DWithin(150 m)` + `pg_trgm` SQL join. For each authority record it
 * finds candidate `places` within the radius AND with normalised-name similarity ≥ the floor, then
 * classifies:
 *
 *   • LINKED    — exactly one candidate → an automatic `spatial_name` link.
 *   • UNLINKED  — zero candidates → nothing written (may be reconsidered at a later refresh).
 *   • AMBIGUOUS — two or more candidates → NOT auto-linked; surfaced for human review (decision D3).
 *                 No tie-break is invented; "human-review the ambiguous tail" (SSOT §8.2).
 *
 * LINK, NOT MERGE (SSOT §7.2/§8.2): a link references a place by its natural key
 * (source, source_ref); no `places` row is ever mutated, deleted, or collapsed. Inputs are readonly
 * DTOs — this class returns new structures and mutates nothing.
 *
 * NO CATEGORY GATE (decision D4): the SSOT rule is spatial + name only; category compatibility is
 * out of scope for C1.
 *
 * No DB, no network, no PostGIS.
 *
 * @see \App\Services\Spatial\NameNormalizer
 * @see \Tests\Unit\Spatial\AuthorityLinkMatcherTest
 */
final class AuthorityLinkMatcher
{
    private const EARTH_RADIUS_M = 6371000.0;

    private readonly NameNormalizer $normalizer;
    private readonly float $radiusMeters;
    private readonly float $similarityMin;

    public function __construct(
        ?NameNormalizer $normalizer = null,
        ?float $radiusMeters = null,
        ?float $similarityMin = null,
    ) {
        $this->normalizer    = $normalizer ?? new NameNormalizer();
        $this->radiusMeters  = $radiusMeters ?? (float) config('spatial_authority.match_radius_m', 150);
        $this->similarityMin = $similarityMin ?? (float) config('spatial_authority.name_similarity_min', 0.60);
    }

    /**
     * @param  list<AuthorityRecord>       $authority
     * @param  list<NormalizedPlaceRecord> $places
     * @return array{
     *   linked: list<array{authority: AuthorityRecord, place: NormalizedPlaceRecord, score: float, method: string}>,
     *   unlinked: list<AuthorityRecord>,
     *   ambiguous: list<array{authority: AuthorityRecord, candidates: list<NormalizedPlaceRecord>}>
     * }
     */
    public function match(array $authority, array $places): array
    {
        $linked = [];
        $unlinked = [];
        $ambiguous = [];

        foreach ($authority as $record) {
            $candidates = [];

            foreach ($places as $place) {
                $distance = $this->haversineMeters($record->lat, $record->lon, $place->lat, $place->lon);
                if ($distance > $this->radiusMeters) {
                    continue;
                }
                $similarity = $this->normalizer->similarity($record->name, $place->name);
                if ($similarity < $this->similarityMin) {
                    continue;
                }
                $candidates[] = ['place' => $place, 'score' => $similarity];
            }

            // Deterministic order for stable ambiguous reporting + reproducible output.
            usort($candidates, static fn (array $a, array $b): int =>
                [$a['place']->source, $a['place']->source_ref] <=> [$b['place']->source, $b['place']->source_ref]);

            $count = count($candidates);
            if ($count === 0) {
                $unlinked[] = $record;
            } elseif ($count === 1) {
                $linked[] = [
                    'authority' => $record,
                    'place'     => $candidates[0]['place'],
                    'score'     => round($candidates[0]['score'], 3),
                    'method'    => 'spatial_name',
                ];
            } else {
                $ambiguous[] = [
                    'authority'  => $record,
                    'candidates' => array_map(static fn (array $c): NormalizedPlaceRecord => $c['place'], $candidates),
                ];
            }
        }

        return ['linked' => $linked, 'unlinked' => $unlinked, 'ambiguous' => $ambiguous];
    }

    public function radiusMeters(): float
    {
        return $this->radiusMeters;
    }

    public function similarityMin(): float
    {
        return $this->similarityMin;
    }

    /** Great-circle distance in metres (haversine). */
    public function haversineMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return self::EARTH_RADIUS_M * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
