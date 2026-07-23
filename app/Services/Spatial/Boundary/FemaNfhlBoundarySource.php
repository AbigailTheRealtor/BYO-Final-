<?php

namespace App\Services\Spatial\Boundary;

use App\Services\Spatial\BoundaryGeometry;
use App\Services\Spatial\BoundaryNormalizationResult;
use App\Services\Spatial\BoundaryRecord;
use App\Services\Spatial\BoundarySource;

/**
 * Spatial Intelligence Platform — Phase 2 Batch 2D Part C3c (FEMA NFHL boundary import authoring).
 *
 * Shared base for the two FEMA National Flood Hazard Layer boundary importers — the hazard areas
 * (`S_FLD_HAZ_AR` → `flood_zone`) and the effective FIRM panel footprint (`S_FIRM_PAN` →
 * `flood_coverage`). Each concrete subclass is a thin {@see BoundarySource} that fixes its
 * `sourceKey`, `kind`, its external_ref key candidates, its missing-ref reject reason, and its
 * `attrs` payload; the normalization loop, geometry handling, and reject accounting live here once
 * (decision D-C3c-1). Mirrors {@see CensusTigerBoundarySource} (C3b) and reuses the C3a boundary
 * framework unchanged.
 *
 * Why BOTH layers (SSOT §10 / E-11 / INV-5): a hazard polygon alone cannot distinguish "outside the
 * SFHA" from "FEMA never mapped here". Only the coverage footprint makes "not mapped" renderable as
 * something other than "no flood risk". The two layers are therefore peers, not a primary and an
 * afterthought — but the downstream resolution that consumes them is NOT part of this slice.
 *
 * Contract (SSOT §7.2/§10; owner decisions):
 *   • external_ref is the SOURCE-NATIVE key, never a composite: `FLD_AR_ID` for flood_zone,
 *     `FIRM_PAN` for flood_coverage (D-C3c-2). No invented composite identifiers, ever.
 *   • geometry = canonical GeoJSON MultiPolygon (a valid Polygon is wrapped deterministically).
 *   • attrs carries `source = 'fema_nfhl'` and no `acres` (so the C3a acceptance acres check is a
 *     benign no-op for FEMA records). Attribute values are passed through UNPARSED — `SFHA_TF` stays
 *     the raw 'T'/'F' token and `EFF_DATE` stays the raw date string; coercion is a later decision.
 *   • Rejections: missing external_ref → rejected_invalid_field (with a per-layer reason token,
 *     D-C3c-3); invalid/unwrappable geometry → rejected_invalid_geometry. Duplicates are NOT merged
 *     (hard-fail at acceptance — `ref_present` and `ref_unique` both remain enforced).
 *   • Whether real NFHL extracts actually populate these keys uniquely nationwide is a REAL-DATA
 *     question deferred to Class-2 (D-C3c-4); offline authoring proves the contract on synthetic
 *     fixtures only.
 *
 * Pure and deterministic — no DB, no network, no FEMA download. Unrelated to the live
 * {@see \App\Services\LocationDna\FemaFloodZoneAdapter}, which is untouched by this slice.
 *
 * @see \Tests\Unit\Spatial\FemaNfhlBoundarySourceTest
 */
abstract class FemaNfhlBoundarySource implements BoundarySource
{
    protected readonly BoundaryGeometry $geometry;

    public function __construct(?BoundaryGeometry $geometry = null)
    {
        $this->geometry = $geometry ?? new BoundaryGeometry();
    }

    /**
     * external_ref key candidates (first present, non-blank wins). Source-native, never composite.
     *
     * @return list<string>
     */
    abstract protected function refKeys(): array;

    /**
     * The counted reject reason token for a row with no usable external_ref (D-C3c-3). Per-layer
     * because the two FEMA layers key on genuinely different fields — a shared token would erase
     * which key was missing.
     */
    abstract protected function missingRefReason(): string;

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
                $reason = $this->missingRefReason();
                $reasons[$reason] = ($reasons[$reason] ?? 0) + 1;
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
