<?php

namespace App\Services\Spatial\Boundary;

/**
 * Spatial Intelligence Platform — Phase 2 Batch 2D Part C3b.
 *
 * Census TIGER/Line ZIP Code Tabulation Area boundaries → `boundaries.kind = 'zcta'`. external_ref =
 * the 5-digit ZCTA code (2020 vintage: ZCTA5CE20 / GEOID). ZCTAs carry NO name, so `attrs.name` is
 * null. See {@see CensusTigerBoundarySource}.
 */
final class CensusZctaBoundarySource extends CensusTigerBoundarySource
{
    public function sourceKey(): string
    {
        return 'tiger_zcta';
    }

    public function kind(): string
    {
        return 'zcta';
    }

    /** @return list<string> */
    protected function refKeys(): array
    {
        return ['geoid', 'GEOID', 'geoid20', 'GEOID20', 'zcta5ce20', 'ZCTA5CE20', 'zcta5', 'ZCTA5'];
    }

    protected function attrs(array $raw): array
    {
        return [
            'name'   => null, // ZCTAs have no name (SSOT §7.2)
            'source' => 'census_tiger',
        ];
    }
}
