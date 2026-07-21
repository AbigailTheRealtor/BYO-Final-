<?php

namespace App\Services\Spatial\Boundary;

use App\Services\Spatial\BoundaryGeometry;
use App\Services\Spatial\BoundaryNormalizationResult;
use App\Services\Spatial\BoundaryRecord;
use App\Services\Spatial\BoundarySource;

/**
 * Spatial Intelligence Platform — Phase 2 Batch 2D Part C3a (PAD-US boundary import authoring).
 *
 * Reference boundary importer: USGS PAD-US 4.1 protected areas → {@see BoundaryRecord} with
 * `kind = 'protected_area'`. At Class-2 these load into `boundaries`, then `boundaries_parts` is
 * derived via `ST_Subdivide` (E-24). PAD-US is polygon geometry, so it belongs with C3 boundaries,
 * not the C2 point overlays.
 *
 * Contract (SSOT §7.2/§8.1; owner decisions):
 *   • external_ref = the PAD-US unit id; kind = 'protected_area' (fixed).
 *   • geometry = canonical GeoJSON MultiPolygon (a valid Polygon is wrapped deterministically).
 *   • ACREAGE lives ONLY in `attrs['acres']` — never `places.authority_metric`, never a place row,
 *     never duplicated. A row whose acreage is absent / non-numeric is KEPT with `acres = null`
 *     (identity + geometry remain authoritative); routing acreage into park ranking is a separate
 *     future decision.
 *   • Rejections: missing unit id → rejected_invalid_field; invalid/unwrappable geometry →
 *     rejected_invalid_geometry. Duplicates are NOT merged here (hard-fail at acceptance).
 *
 * Pure and deterministic — no DB, no network, no PAD-US download.
 *
 * @see \Tests\Unit\Spatial\PadUsBoundarySourceTest
 */
final class PadUsBoundarySource implements BoundarySource
{
    private const REF_KEYS  = ['unit_id', 'padus_id', 'objectid', 'id'];
    private const NAME_KEYS = ['unit_nm', 'name', 'loc_nm'];
    private const ACRE_KEYS = ['gis_acres', 'acres', 'area_acres'];

    private readonly BoundaryGeometry $geometry;

    public function __construct(?BoundaryGeometry $geometry = null)
    {
        $this->geometry = $geometry ?? new BoundaryGeometry();
    }

    public function sourceKey(): string
    {
        return 'padus';
    }

    public function kind(): string
    {
        return 'protected_area';
    }

    public function normalize(iterable $rawRows): BoundaryNormalizationResult
    {
        $records = [];
        $total = 0;
        $rejGeometry = 0;
        $rejField = 0;
        $reasons = [];

        foreach ($rawRows as $raw) {
            $total++;

            $ref = $this->firstOf($raw, self::REF_KEYS);
            if ($ref === null) {
                $rejField++;
                $reasons['invalid_missing_unit_id'] = ($reasons['invalid_missing_unit_id'] ?? 0) + 1;
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

    /**
     * PAD-US attrs payload. Fixed key order for byte-stable JSON. Acreage stored ONLY here.
     *
     * @return array<string,mixed>
     */
    private function attrs(array $raw): array
    {
        return [
            'acres'  => $this->acres($raw),
            'name'   => $this->firstOf($raw, self::NAME_KEYS),
            'source' => 'padus',
        ];
    }

    private function acres(array $raw): ?float
    {
        foreach (self::ACRE_KEYS as $k) {
            if (array_key_exists($k, $raw) && $raw[$k] !== null && is_numeric($raw[$k])) {
                return (float) $raw[$k];
            }
        }

        return null;
    }

    /** @param list<string> $keys */
    private function firstOf(array $raw, array $keys): ?string
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
