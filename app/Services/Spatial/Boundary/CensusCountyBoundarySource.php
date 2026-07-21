<?php

namespace App\Services\Spatial\Boundary;

/**
 * Spatial Intelligence Platform — Phase 2 Batch 2D Part C3b.
 *
 * Census TIGER/Line county boundaries → `boundaries.kind = 'county'`. external_ref = county GEOID
 * (STATEFP+COUNTYFP, 5 digits). See {@see CensusTigerBoundarySource}.
 */
final class CensusCountyBoundarySource extends CensusTigerBoundarySource
{
    private const NAME_KEYS     = ['name', 'NAME'];
    private const NAMELSAD_KEYS = ['namelsad', 'NAMELSAD'];
    private const STATEFP_KEYS  = ['statefp', 'STATEFP', 'state', 'STATE'];

    public function sourceKey(): string
    {
        return 'tiger_county';
    }

    public function kind(): string
    {
        return 'county';
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
            'namelsad'   => $this->firstOf($raw, self::NAMELSAD_KEYS),
            'state_fips' => $this->firstOf($raw, self::STATEFP_KEYS),
            'source'     => 'census_tiger',
        ];
    }
}
