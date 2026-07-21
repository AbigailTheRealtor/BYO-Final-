<?php

namespace App\Services\Spatial\Boundary;

/**
 * Spatial Intelligence Platform — Phase 2 Batch 2D Part C3b.
 *
 * Census TIGER/Line place (incorporated place / CDP) boundaries → `boundaries.kind = 'place'`.
 * external_ref = place GEOID (STATEFP+PLACEFP, 7 digits). See {@see CensusTigerBoundarySource}.
 */
final class CensusPlaceBoundarySource extends CensusTigerBoundarySource
{
    private const NAME_KEYS     = ['name', 'NAME'];
    private const BASENAME_KEYS = ['basename', 'BASENAME'];
    private const STATEFP_KEYS  = ['statefp', 'STATEFP', 'state', 'STATE'];

    public function sourceKey(): string
    {
        return 'tiger_place';
    }

    public function kind(): string
    {
        return 'place';
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
            'basename'   => $this->firstOf($raw, self::BASENAME_KEYS),
            'state_fips' => $this->firstOf($raw, self::STATEFP_KEYS),
            'source'     => 'census_tiger',
        ];
    }
}
