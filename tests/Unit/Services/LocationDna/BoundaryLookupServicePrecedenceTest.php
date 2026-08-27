<?php

namespace Tests\Unit\Services\LocationDna;

use App\Contracts\BoundaryAdapterInterface;
use App\Services\LocationDna\BoundaryLookupService;
use Tests\TestCase;

/**
 * The canonical Location DNA precedence, for the named-boundary tiers this
 * service owns:
 *
 *     Polygon > Radius > ZIP > City > County
 *
 * Two rules are being pinned here, and both were previously unproven — this
 * service shipped with no test file at all.
 *
 *   1. ORDER. ZIP outranks City, City outranks County. The service previously
 *      resolved city first, so a buyer who listed both a ZIP and a city had the
 *      city boundary rendered and the (more specific) ZIP silently ignored.
 *
 *   2. FALL-THROUGH. Presence does not consume precedence. A tier wins only if
 *      it actually resolves to a usable boundary. Previously the first non-empty
 *      tier was selected unconditionally, so a ZIP the adapter could not resolve
 *      returned an empty fallback payload while a perfectly good city boundary
 *      sat unused one branch below.
 *
 * STATE IS NOT A TIER. It is narrowing context passed to the adapter to
 * disambiguate a name, and it must never be returned as a winning preference.
 *
 * CIRCLE IS NOT A TIER. A radius search is the circle. Polygons and radii are
 * resolved on the front end, so for this service they short-circuit the named
 * lookup entirely rather than selecting a named tier.
 *
 * The adapter is a recording fake: no network, no database, no Census call.
 */
class BoundaryLookupServicePrecedenceTest extends TestCase
{
    /** A ring shaped like the adapter contract: one boundary's coordinate rings. */
    public const USABLE_RINGS = [[[[-82.7, 27.7], [-82.6, 27.7], [-82.6, 27.8], [-82.7, 27.8]]]];

    /**
     * Recording fake. `$resolvable` lists the names that return usable rings;
     * every other name returns [] — the adapter's documented "not found" value.
     *
     * @param  list<string>|null  $resolvable  null = every name resolves
     */
    private function adapter(?array $resolvable = null): BoundaryAdapterInterface
    {
        return new class($resolvable) implements BoundaryAdapterInterface
        {
            /** @var list<array{type: string, names: array, state: ?string}> */
            public array $calls = [];

            public function __construct(private ?array $resolvable)
            {
            }

            public function lookup(string $type, array $names, ?string $stateAbbrev): array
            {
                $this->calls[] = ['type' => $type, 'names' => $names, 'state' => $stateAbbrev];

                return array_map(function ($name) {
                    if ($this->resolvable === null || in_array($name, $this->resolvable, true)) {
                        return BoundaryLookupServicePrecedenceTest::USABLE_RINGS;
                    }

                    return [];
                }, array_values($names));
            }

            /** @return list<string> the tiers consulted, in the order consulted */
            public function tiersTried(): array
            {
                return array_map(static fn (array $c): string => $c['type'], $this->calls);
            }

            public function winningTier(): ?string
            {
                return $this->calls === [] ? null : end($this->calls)['type'];
            }
        };
    }

    // ── order ───────────────────────────────────────────────────────────────

    /** Case 2: ZIP + city + county all present and all resolvable → ZIP wins. */
    public function test_zip_outranks_city_and_county(): void
    {
        $adapter = $this->adapter();
        $service = new BoundaryLookupService($adapter);

        $result = $service->resolve(
            ['zip_codes' => ['33701'], 'cities' => ['Tampa']],
            ['zip_codes' => ['33701'], 'cities' => ['Tampa'], 'counties' => ['Pinellas'], 'states' => ['FL']]
        );

        $this->assertSame('zip', $adapter->winningTier(), 'ZIP must outrank City and County');
        $this->assertSame(['zip'], $adapter->tiersTried(), 'A resolvable ZIP must end the cascade immediately');
        $this->assertFalse($result['fallback']);
        $this->assertNotEmpty($result['geojson_polygons']);
    }

    /** Case 3: city + county, no ZIP → city wins. */
    public function test_city_outranks_county(): void
    {
        $adapter = $this->adapter();
        $service = new BoundaryLookupService($adapter);

        $result = $service->resolve(
            ['cities' => ['Tampa']],
            ['cities' => ['Tampa'], 'counties' => ['Pinellas'], 'states' => ['FL']]
        );

        $this->assertSame('city', $adapter->winningTier(), 'City must outrank County');
        $this->assertFalse($result['fallback']);
    }

    /** Case 4: county only → county wins. */
    public function test_county_wins_when_it_is_the_only_named_tier(): void
    {
        $adapter = $this->adapter();
        $service = new BoundaryLookupService($adapter);

        $result = $service->resolve([], ['counties' => ['Pinellas'], 'states' => ['FL']]);

        $this->assertSame('county', $adapter->winningTier());
        $this->assertFalse($result['fallback']);
    }

    // ── fall-through: presence does not consume precedence ───────────────────

    /** Case 5: ZIP present but unresolvable, city resolvable → city wins. */
    public function test_an_unresolvable_zip_falls_through_to_a_valid_city(): void
    {
        $adapter = $this->adapter(resolvable: ['Tampa']);
        $service = new BoundaryLookupService($adapter);

        $result = $service->resolve(
            ['zip_codes' => ['00000'], 'cities' => ['Tampa']],
            ['counties' => ['Pinellas'], 'states' => ['FL']]
        );

        $this->assertSame(['zip', 'city'], $adapter->tiersTried(), 'ZIP must be tried first, then fall through');
        $this->assertSame('city', $adapter->winningTier(), 'An unusable ZIP must not suppress a valid City');
        $this->assertFalse($result['fallback']);
        $this->assertNotEmpty($result['geojson_polygons']);
    }

    /** Case 6: city present but unresolvable, county resolvable → county wins. */
    public function test_an_unresolvable_city_falls_through_to_a_valid_county(): void
    {
        $adapter = $this->adapter(resolvable: ['Pinellas']);
        $service = new BoundaryLookupService($adapter);

        $result = $service->resolve(
            ['cities' => ['Nowhere']],
            ['counties' => ['Pinellas'], 'states' => ['FL']]
        );

        $this->assertSame(['city', 'county'], $adapter->tiersTried());
        $this->assertSame('county', $adapter->winningTier(), 'An unusable City must not suppress a valid County');
        $this->assertFalse($result['fallback']);
    }

    /** Every named tier present but none resolvable → all tried, honest fallback. */
    public function test_all_tiers_unresolvable_reports_fallback_after_trying_every_tier(): void
    {
        $adapter = $this->adapter(resolvable: []);
        $service = new BoundaryLookupService($adapter);

        $result = $service->resolve(
            ['zip_codes' => ['00000'], 'cities' => ['Nowhere']],
            ['counties' => ['Nowhere County'], 'states' => ['FL']]
        );

        $this->assertSame(['zip', 'city', 'county'], $adapter->tiersTried(), 'Every tier must be attempted');
        $this->assertTrue($result['fallback']);
        $this->assertSame([], $result['geojson_polygons']);
    }

    // ── state is not a tier ─────────────────────────────────────────────────

    /** Case 7: state alone yields no winner and no lookup — it is not a tier. */
    public function test_state_alone_is_not_a_precedence_tier(): void
    {
        $adapter = $this->adapter();
        $service = new BoundaryLookupService($adapter);

        $result = $service->resolve([], ['states' => ['FL']]);

        $this->assertSame([], $adapter->tiersTried(), 'State must never trigger a boundary lookup of its own');
        $this->assertTrue($result['fallback']);
        $this->assertSame([], $result['geojson_polygons']);
    }

    /** State is narrowing context: it reaches the adapter, but only as a filter. */
    public function test_state_is_passed_to_the_adapter_as_narrowing_context(): void
    {
        $adapter = $this->adapter();
        $service = new BoundaryLookupService($adapter);

        $service->resolve(['zip_codes' => ['33701']], ['states' => ['FL']]);

        $this->assertSame('zip', $adapter->winningTier());
        $this->assertSame('FL', $adapter->calls[0]['state'], 'State must narrow the lookup');
    }

    // ── polygon / radius short-circuit ───────────────────────────────────────

    /** Case 8: a drawn polygon outranks every named tier. */
    public function test_polygon_outranks_every_named_tier(): void
    {
        $adapter = $this->adapter();
        $service = new BoundaryLookupService($adapter);

        $result = $service->resolve(
            ['polygons' => [['path' => [['lat' => 27.7, 'lng' => -82.7]]]], 'zip_codes' => ['33701'], 'cities' => ['Tampa']],
            ['counties' => ['Pinellas'], 'states' => ['FL']]
        );

        $this->assertSame([], $adapter->tiersTried(), 'A polygon must short-circuit named-boundary lookup');
        $this->assertTrue($result['fallback'], 'Polygons are rendered from preferences, not looked up');
    }

    /** Case 9: a radius outranks every named tier when no polygon is present. */
    public function test_radius_outranks_every_named_tier(): void
    {
        $adapter = $this->adapter();
        $service = new BoundaryLookupService($adapter);

        $result = $service->resolve(
            [
                'radius_searches' => [['center' => ['lat' => 27.7, 'lng' => -82.7], 'radius_miles' => 5]],
                'zip_codes'       => ['33701'],
                'cities'          => ['Tampa'],
            ],
            ['counties' => ['Pinellas'], 'states' => ['FL']]
        );

        $this->assertSame([], $adapter->tiersTried(), 'A radius must short-circuit named-boundary lookup');
        $this->assertTrue($result['fallback']);
    }

    // ── same-tier: every member of the winning tier is preserved ─────────────

    public function test_all_members_of_the_winning_tier_are_preserved(): void
    {
        $adapter = $this->adapter();
        $service = new BoundaryLookupService($adapter);

        $result = $service->resolve(
            ['zip_codes' => ['33701', '33702', '33703']],
            ['states' => ['FL']]
        );

        $this->assertSame(['33701', '33702', '33703'], $adapter->calls[0]['names'], 'No ZIP may be dropped');
        $this->assertCount(3, $result['geojson_polygons'], 'Every resolved member of the winning tier is returned');
    }

    public function test_partially_resolvable_winning_tier_keeps_the_members_that_resolved(): void
    {
        $adapter = $this->adapter(resolvable: ['33702']);
        $service = new BoundaryLookupService($adapter);

        $result = $service->resolve(['zip_codes' => ['33701', '33702']], ['cities' => ['Tampa'], 'states' => ['FL']]);

        // One ZIP resolved, so the ZIP tier IS usable and must win outright.
        $this->assertSame(['zip'], $adapter->tiersTried(), 'A partially usable tier still wins — no fall-through');
        $this->assertCount(1, $result['geojson_polygons']);
        $this->assertFalse($result['fallback']);
    }
}
