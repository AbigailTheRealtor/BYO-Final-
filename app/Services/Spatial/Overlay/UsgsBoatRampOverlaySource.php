<?php

namespace App\Services\Spatial\Overlay;

use App\Services\Spatial\AuthorityOverlayNormalizationResult;
use App\Services\Spatial\AuthorityOverlaySource;
use App\Services\Spatial\AuthorityRecord;

/**
 * Spatial Intelligence Platform — Phase 2 Batch 2D Part C2 (authority-overlay importers).
 *
 * Reference BASE-source importer: USGS Boat Ramp Locations (CC0) → {@see AuthorityRecord}. Boat
 * ramps have no Overture registry to link against, so at Class-2 these records become `places` rows
 * DIRECTLY (source='usgs', category_key='boat_ramp') — target() === 'place'. Ranking is by
 * MEMBERSHIP in the registry (SSOT §9.1 "Boat ramps → USGS membership"), so there is no numeric
 * metric: authority_metric stays NULL and metricDomain() is null.
 *
 * Contract (SSOT §8.1 row 18 / §14.1; owner decisions):
 *   • authority_source = 'usgs'; authority_ref = the USGS ramp id — the registry natural key.
 *   • authority_metric = NULL (membership source; presence is the signal).
 *   • Only structurally-invalid rows (missing id / name / coordinates) are rejected; no metric
 *     domain applies.
 *
 * Pure and deterministic — no DB, no network.
 *
 * @see \Tests\Unit\Spatial\UsgsBoatRampOverlaySourceTest
 */
final class UsgsBoatRampOverlaySource implements AuthorityOverlaySource
{
    private const REF_KEYS  = ['id', 'ramp_id', 'objectid', 'usgs_id'];
    private const NAME_KEYS = ['name', 'ramp_name', 'facility_name'];
    private const LON_KEYS  = ['lon', 'longitude', 'lng', 'x'];
    private const LAT_KEYS  = ['lat', 'latitude', 'y'];

    public function sourceKey(): string
    {
        return 'usgs';
    }

    public function target(): string
    {
        return 'place';
    }

    public function metricLabel(): ?string
    {
        return null;
    }

    public function metricDomain(): ?array
    {
        return null;
    }

    public function normalize(iterable $rawRows): AuthorityOverlayNormalizationResult
    {
        $records = [];
        $total = 0;
        $rejInvalid = 0;
        $reasons = [];

        foreach ($rawRows as $raw) {
            $total++;

            $ref  = $this->firstOf($raw, self::REF_KEYS);
            $name = $this->firstOf($raw, self::NAME_KEYS);
            [$lon, $lat] = $this->coordinates($raw);

            if ($ref === null || $name === null || $lon === null || $lat === null) {
                $rejInvalid++;
                $reasons['invalid_missing_field'] = ($reasons['invalid_missing_field'] ?? 0) + 1;
                continue;
            }

            $records[] = new AuthorityRecord(
                authority_source: $this->sourceKey(),
                authority_ref: $ref,
                name: $name,
                lon: $lon,
                lat: $lat,
                authority_metric: null,
            );
        }

        return new AuthorityOverlayNormalizationResult(
            records: $records,
            totalInput: $total,
            rejectedInvalid: $rejInvalid,
            rejectedOutOfDomain: 0,
            rejectReasons: $reasons,
        );
    }

    /** @param list<string> $keys */
    private function firstOf(array $raw, array $keys): ?string
    {
        foreach ($keys as $k) {
            if (array_key_exists($k, $raw)) {
                $s = $this->str($raw[$k]);
                if ($s !== null) {
                    return $s;
                }
            }
        }

        return null;
    }

    /** @return array{0: float|null, 1: float|null} [lon, lat] */
    private function coordinates(array $raw): array
    {
        return [$this->num($raw, self::LON_KEYS), $this->num($raw, self::LAT_KEYS)];
    }

    /** @param list<string> $keys */
    private function num(array $raw, array $keys): ?float
    {
        foreach ($keys as $k) {
            if (array_key_exists($k, $raw) && is_numeric($raw[$k])) {
                return (float) $raw[$k];
            }
        }

        return null;
    }

    private function str(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $s = trim((string) $v);

        return $s === '' ? null : $s;
    }
}
