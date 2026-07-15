<?php

namespace Tests\Support\LocationDna;

use App\Contracts\NearbyPoiFetcherInterface;

/**
 * FixtureNearbyPoiFetcher — a test-only NearbyPoiFetcherInterface that replays a fixed set of
 * raw candidate rows, ignoring the query.
 *
 * It exists so PoiBaselineDiffHarness's dual-run driver (`diffFetchers`) can be exercised over
 * the frozen golden-master candidates without a live provider. It is a TEST ARTIFACT ONLY: it is
 * never bound in the container and never reachable from any production path. Google is neither
 * privileged nor contacted.
 *
 * @see \App\Services\LocationDna\PoiBaselineDiffHarness::diffFetchers()
 */
final class FixtureNearbyPoiFetcher implements NearbyPoiFetcherInterface
{
    /** @param array<int, array> $candidates Raw, provider-native candidate rows to replay. */
    public function __construct(private readonly array $candidates)
    {
    }

    /**
     * {@inheritDoc}
     *
     * Returns the injected candidates verbatim, regardless of coordinate/meta. Never throws.
     */
    public function fetchNearby(float $lat, float $lng, array $meta): array
    {
        return $this->candidates;
    }
}
