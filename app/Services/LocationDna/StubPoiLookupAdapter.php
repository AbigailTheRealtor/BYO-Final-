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
     * The interface contract now specifies a 9-key item shape (adding the
     * canonical 'confidence' and 'last_refreshed' envelope keys — Stage D). This
     * stub emits no items, so it satisfies "every item carries 9 keys" vacuously;
     * it deliberately does NOT fabricate items or quality signal.
     *
     * {@inheritDoc}
     */
    public function search(float $lat, float $lng, string $category, int $radiusMiles, int $limit): array
    {
        return [];
    }
}
