<?php

namespace App\Services\Spatial\Boundary;

/**
 * Spatial Intelligence Platform — Phase 2 Batch 2D Part C3b.
 *
 * Census TIGER/Line Unified School District boundaries → `boundaries.kind = 'school_district'`.
 * external_ref = district GEOID (STATEFP+UNSDLEA). See {@see CensusTigerBoundarySource}.
 *
 * SCOPE (R10): this imports TIGER district BOUNDARIES only (current, launch-safe). NCES SABS
 * attendance zones (frozen 2015-16) are a SEPARATE dataset and are NOT part of C3b.
 */
final class CensusSchoolDistrictBoundarySource extends CensusTigerBoundarySource
{
    private const NAME_KEYS    = ['name', 'NAME'];
    private const STATEFP_KEYS = ['statefp', 'STATEFP', 'state', 'STATE'];

    public function sourceKey(): string
    {
        return 'tiger_school_district';
    }

    public function kind(): string
    {
        return 'school_district';
    }

    /** @return list<string> */
    protected function refKeys(): array
    {
        return ['geoid', 'GEOID'];
    }

    protected function attrs(array $raw): array
    {
        return [
            'name'       => $this->firstOf($raw, self::NAME_KEYS),
            'state_fips' => $this->firstOf($raw, self::STATEFP_KEYS),
            'source'     => 'census_tiger',
        ];
    }
}
