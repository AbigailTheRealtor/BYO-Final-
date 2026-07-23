<?php

namespace App\Services\Spatial\Boundary;

/**
 * Spatial Intelligence Platform — Phase 2 Batch 2D Part C3c.
 *
 * FEMA NFHL effective FIRM panel footprint (`S_FIRM_PAN`) → `boundaries.kind = 'flood_coverage'`.
 * external_ref = `FIRM_PAN`, the printed panel identifier (D-C3c-2) — never DFIRM_ID+PANEL+SUFFIX
 * glued together. See {@see FemaNfhlBoundarySource}.
 *
 * This is the layer that makes "not mapped" honest (SSOT §10, INV-5): without the coverage footprint,
 * "outside a hazard area", "never mapped", and "the lookup failed" are indistinguishable. The
 * resolution logic that actually draws that distinction is downstream and NOT part of this slice.
 *
 * `eff_date` is a raw passthrough string — FEMA's effective date is not parsed, normalized, or
 * compared here; effective-vs-preliminary selection is a Class-2 data decision.
 */
final class FemaFloodCoverageBoundarySource extends FemaNfhlBoundarySource
{
    private const DFIRM_KEYS    = ['dfirm_id', 'DFIRM_ID'];
    private const PANEL_KEYS    = ['panel', 'PANEL'];
    private const SUFFIX_KEYS   = ['suffix', 'SUFFIX'];
    private const PANELTYP_KEYS = ['panel_typ', 'PANEL_TYP'];
    private const EFFDATE_KEYS  = ['eff_date', 'EFF_DATE'];

    public function sourceKey(): string
    {
        return 'fema_flood_coverage';
    }

    public function kind(): string
    {
        return 'flood_coverage';
    }

    /** @return list<string> */
    protected function refKeys(): array
    {
        return ['firm_pan', 'FIRM_PAN'];
    }

    protected function missingRefReason(): string
    {
        return 'invalid_missing_firm_pan';
    }

    protected function attrs(array $raw): array
    {
        return [
            'dfirm_id'   => $this->firstOf($raw, self::DFIRM_KEYS),
            'panel'      => $this->firstOf($raw, self::PANEL_KEYS),
            'suffix'     => $this->firstOf($raw, self::SUFFIX_KEYS),
            'panel_type' => $this->firstOf($raw, self::PANELTYP_KEYS),
            'eff_date'   => $this->firstOf($raw, self::EFFDATE_KEYS),
            'source'     => 'fema_nfhl',
        ];
    }
}
