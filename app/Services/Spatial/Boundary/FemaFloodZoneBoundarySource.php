<?php

namespace App\Services\Spatial\Boundary;

/**
 * Spatial Intelligence Platform — Phase 2 Batch 2D Part C3c.
 *
 * FEMA NFHL flood hazard areas (`S_FLD_HAZ_AR`) → `boundaries.kind = 'flood_zone'`.
 * external_ref = `FLD_AR_ID`, the layer's own hazard-area identifier (D-C3c-2) — never a composite
 * of DFIRM_ID + zone + panel. See {@see FemaNfhlBoundarySource}.
 *
 * attrs mirrors SSOT §10's named fields (`FLD_ZONE`, `ZONE_SUBTY`, `SFHA_TF`) plus `DFIRM_ID` for
 * provenance. Values are raw passthrough: `sfha` keeps FEMA's 'T'/'F' token rather than becoming a
 * boolean, because zone D (undetermined) and zone A without a BFE both need their own downstream
 * treatment and a premature boolean would flatten them.
 */
final class FemaFloodZoneBoundarySource extends FemaNfhlBoundarySource
{
    private const ZONE_KEYS    = ['fld_zone', 'FLD_ZONE'];
    private const SUBTYPE_KEYS = ['zone_subty', 'ZONE_SUBTY'];
    private const SFHA_KEYS    = ['sfha_tf', 'SFHA_TF'];
    private const DFIRM_KEYS   = ['dfirm_id', 'DFIRM_ID'];

    public function sourceKey(): string
    {
        return 'fema_flood_zone';
    }

    public function kind(): string
    {
        return 'flood_zone';
    }

    /** @return list<string> */
    protected function refKeys(): array
    {
        return ['fld_ar_id', 'FLD_AR_ID'];
    }

    protected function missingRefReason(): string
    {
        return 'invalid_missing_fld_ar_id';
    }

    protected function attrs(array $raw): array
    {
        return [
            'flood_zone'   => $this->firstOf($raw, self::ZONE_KEYS),
            'zone_subtype' => $this->firstOf($raw, self::SUBTYPE_KEYS),
            'sfha'         => $this->firstOf($raw, self::SFHA_KEYS),
            'dfirm_id'     => $this->firstOf($raw, self::DFIRM_KEYS),
            'source'       => 'fema_nfhl',
        ];
    }
}
