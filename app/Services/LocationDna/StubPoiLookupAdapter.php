<?php

namespace App\Services\LocationDna;

use App\Contracts\PoiLookupAdapterInterface;

class StubPoiLookupAdapter implements PoiLookupAdapterInterface
{
    /**
     * Always returns an empty result set.
     *
     * Used as the safe default when no provider API key is configured,
     * ensuring the service never errors at boot or during tests.
     *
     * {@inheritDoc}
     */
    public function search(float $lat, float $lng, string $category, int $radiusMiles, int $limit): array
    {
        return [];
    }
}
