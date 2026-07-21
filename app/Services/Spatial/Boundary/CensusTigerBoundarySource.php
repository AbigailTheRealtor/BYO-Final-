<?php

namespace App\Services\Spatial\Boundary;

use App\Services\Spatial\BoundaryGeometry;
use App\Services\Spatial\BoundaryNormalizationResult;
use App\Services\Spatial\BoundaryRecord;
use App\Services\Spatial\BoundarySource;

/**
 * Spatial Intelligence Platform — Phase 2 Batch 2D Part C3b (Census TIGER boundary import authoring).
 *
 * Shared base for the four Census TIGER/Line boundary importers (county, place, ZCTA, school
 * district). Each concrete subclass is a thin {@see BoundarySource} that fixes its `sourceKey`,
 * `kind`, the GEOID key candidates, and its `attrs` payload; the normalization loop, geometry
 * handling, and reject accounting live here once (decision D-C3b-1). Mirrors
 * {@see PadUsBoundarySource}; reuses the C3a boundary framework unchanged.
 *
 * Contract (SSOT §7.2; owner decisions):
 *   • external_ref = the TIGER GEOID (a stable, ZERO-PADDED code, e.g. county '12057'). GEOID is read
 *     straight from the extract — never rebuilt from the live map adapter's unpadded FIPS.
 *   • geometry = canonical GeoJSON MultiPolygon (a valid Polygon is wrapped deterministically).
 *   • attrs carries `source = 'census_tiger'` and no `acres` (so the C3a acceptance acres check is a
 *     benign no-op for TIGER records).
 *   • Rejections: missing GEOID → rejected_invalid_field; invalid/unwrappable geometry →
 *     rejected_invalid_geometry. Duplicates are NOT merged (hard-fail at acceptance).
 *
 * Pure and deterministic — no DB, no network, no TIGER download.
 *
 * @see \Tests\Unit\Spatial\CensusTigerBoundarySourceTest
 */
abstract class CensusTigerBoundarySource implements BoundarySource
{
    protected readonly BoundaryGeometry $geometry;

    public function __construct(?BoundaryGeometry $geometry = null)
    {
        $this->geometry = $geometry ?? new BoundaryGeometry();
    }

    /**
     * GEOID key candidates (first present, non-blank wins). Zero-padded stable code.
     *
     * @return list<string>
     */
    abstract protected function refKeys(): array;

    /**
     * The per-layer attrs payload. Fixed key order for byte-stable JSON; carries no `acres`.
     *
     * @return array<string,mixed>
     */
    abstract protected function attrs(array $raw): array;

    public function normalize(iterable $rawRows): BoundaryNormalizationResult
    {
        $records = [];
        $total = 0;
        $rejGeometry = 0;
        $rejField = 0;
        $reasons = [];

        foreach ($rawRows as $raw) {
            $total++;

            $ref = $this->firstOf($raw, $this->refKeys());
            if ($ref === null) {
                $rejField++;
                $reasons['invalid_missing_geoid'] = ($reasons['invalid_missing_geoid'] ?? 0) + 1;
                continue;
            }

            $geometry = $this->geometry->normalizeToMultiPolygon($raw['geometry'] ?? null);
            if ($geometry === null) {
                $rejGeometry++;
                $reasons['invalid_geometry'] = ($reasons['invalid_geometry'] ?? 0) + 1;
                continue;
            }

            $records[] = new BoundaryRecord(
                kind: $this->kind(),
                external_ref: $ref,
                geometry: $geometry,
                attrs: $this->attrs($raw),
            );
        }

        return new BoundaryNormalizationResult(
            records: $records,
            totalInput: $total,
            rejectedInvalidGeometry: $rejGeometry,
            rejectedInvalidField: $rejField,
            rejectReasons: $reasons,
        );
    }

    /** @param list<string> $keys */
    protected function firstOf(array $raw, array $keys): ?string
    {
        foreach ($keys as $k) {
            if (array_key_exists($k, $raw) && $raw[$k] !== null) {
                $s = trim((string) $raw[$k]);
                if ($s !== '') {
                    return $s;
                }
            }
        }

        return null;
    }
}
