<?php

namespace Tests\Unit\Services\LocationDna;

use App\Contracts\PoiLookupAdapterInterface;
use App\Services\LocationDna\PoiDistanceLookupService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * PoiDistanceLookupServiceRichShapeRegressionTest
 *
 * Stage D regression guard. GooglePlacesPoiAdapter now returns the richer 9-key
 * item shape (adding 'confidence' + 'last_refreshed'). This proves that feeding
 * PoiDistanceLookupService those richer items leaves its behavior UNCHANGED:
 *   - results are still sorted ascending by distance_miles (ordering intact);
 *   - the service passes items through untouched (extra keys neither break it
 *     nor are stripped);
 *   - the 4-key output contract is intact.
 *
 * No real Google Places API calls are made (mocked adapter).
 */
class PoiDistanceLookupServiceRichShapeRegressionTest extends TestCase
{
    private const POINT_GEOMETRY = ['type' => 'point', 'lat' => 27.9506, 'lng' => -82.4572];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'location_dna.poi.max_radius_miles'      => 25,
            'location_dna.poi.category_result_limit' => 5,
            'location_dna.poi.cache_ttl'             => 86400,
            'location_dna.poi.timeout'               => 5,
            'services.google.places_key'             => 'test-places-key',
            'cache.default'                          => 'array',
        ]);

        Cache::flush();
    }

    /** A 9-key item matching the post-Stage-D GooglePlacesPoiAdapter output. */
    private function richItem(string $name, float $distanceMiles, ?float $confidence): array
    {
        return [
            'category'       => 'schools',
            'name'           => $name,
            'address'        => '123 Main St',
            'latitude'       => 27.97,
            'longitude'      => -82.46,
            'distance_miles' => $distanceMiles,
            'source'         => 'google_places',
            'confidence'     => $confidence,
            'last_refreshed' => '2026-07-05T12:00:00+00:00',
        ];
    }

    public function test_order_is_unchanged_with_rich_nine_key_items(): void
    {
        $adapter = $this->createMock(PoiLookupAdapterInterface::class);
        // Returned out of distance order to prove the service still sorts ascending.
        $adapter->method('search')->willReturn([
            $this->richItem('Far School',  5.0, 0.9),
            $this->richItem('Near School', 0.5, 0.5),
            $this->richItem('Mid School',  2.5, 0.75),
        ]);

        $result = (new PoiDistanceLookupService($adapter))
            ->lookup(self::POINT_GEOMETRY, ['schools']);

        $distances = array_column($result['results'], 'distance_miles');
        $sorted    = $distances;
        sort($sorted);

        $this->assertSame($sorted, $distances, 'Results must remain sorted ascending by distance_miles');
        $this->assertSame([0.5, 2.5, 5.0], $distances);
    }

    public function test_new_keys_pass_through_without_being_stripped_or_breaking(): void
    {
        $adapter = $this->createMock(PoiLookupAdapterInterface::class);
        $adapter->method('search')->willReturn([
            $this->richItem('Near School', 0.5, 0.5),
        ]);

        $result = (new PoiDistanceLookupService($adapter))
            ->lookup(self::POINT_GEOMETRY, ['schools']);

        // 4-key output contract intact.
        $this->assertSame(['results', 'error', 'source_lat', 'source_lng'], array_keys($result));
        $this->assertNull($result['error']);

        $item = $result['results'][0];
        // The richer envelope keys survive pass-through unchanged.
        $this->assertArrayHasKey('confidence', $item);
        $this->assertArrayHasKey('last_refreshed', $item);
        $this->assertSame(0.5, $item['confidence']);
        $this->assertSame('2026-07-05T12:00:00+00:00', $item['last_refreshed']);
        // Original keys unchanged.
        $this->assertSame('google_places', $item['source']);
        $this->assertSame(0.5, $item['distance_miles']);
    }
}
