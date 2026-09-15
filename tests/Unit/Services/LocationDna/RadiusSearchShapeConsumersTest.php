<?php

namespace Tests\Unit\Services\LocationDna;

use App\Contracts\FloodZoneAdapterInterface;
use App\Contracts\SchoolDistrictAdapterInterface;
use App\Services\LocationDna\CommuteTimeLookupService;
use App\Services\LocationDna\FloodZoneLookupService;
use App\Services\LocationDna\LocationDnaEnrichmentRunner;
use App\Services\LocationDna\PoiDistanceLookupService;
use App\Services\LocationDna\SchoolDistrictLookupService;
use Mockery;
use Tests\TestCase;

/**
 * The radius searches the CURRENT widget saves are flat — `{address, lat, lng,
 * radius_miles}` — and three server-side consumers used to read only the nested
 * `{center: {lat, lng}}` shape. Every radius saved today was therefore skipped: no flood
 * overlay, no school-district overlay, no POI geometry. Nothing about the stored row was
 * wrong; the readers disagreed about it.
 *
 * Each consumer now reads through RadiusSearchRow. These cases feed each one the flat row
 * the widget writes and assert it is USED — and keep the nested row working beside it.
 */
class RadiusSearchShapeConsumersTest extends TestCase
{
    private const FLAT = ['address' => '315 E Madison St, Tampa, FL 33602', 'lat' => 27.9506, 'lng' => -82.4572, 'radius_miles' => 5];

    private const NESTED = ['center' => ['lat' => 27.9506, 'lng' => -82.4572], 'radius_miles' => 5, 'label' => 'Tampa'];

    private function noBoundary(): array
    {
        return ['geojson_polygons' => [], 'fallback' => true];
    }

    private function capturingAdapter(string $interface, ?array &$bbox): object
    {
        $adapter = Mockery::mock($interface);
        $adapter->shouldReceive('lookup')
            ->once()
            ->with(Mockery::on(function (array $received) use (&$bbox) {
                $bbox = $received;

                return true;
            }))
            ->andReturn([]);

        return $adapter;
    }

    /** @dataProvider shapes */
    public function test_flood_lookup_derives_its_area_from_either_radius_shape(array $row): void
    {
        $bbox = null;
        (new FloodZoneLookupService($this->capturingAdapter(FloodZoneAdapterInterface::class, $bbox)))
            ->resolve($this->noBoundary(), ['radius_searches' => [$row]]);

        $this->assertIsArray($bbox, 'The flood adapter must be asked about the radius area');
        [$minLng, $minLat, $maxLng, $maxLat] = $bbox;
        $this->assertTrue($minLat < 27.9506 && $maxLat > 27.9506 && $minLng < -82.4572 && $maxLng > -82.4572,
            'The bounding box must contain the radius centre');
    }

    /** @dataProvider shapes */
    public function test_school_district_lookup_derives_its_area_from_either_radius_shape(array $row): void
    {
        $bbox = null;
        (new SchoolDistrictLookupService($this->capturingAdapter(SchoolDistrictAdapterInterface::class, $bbox)))
            ->resolve($this->noBoundary(), ['radius_searches' => [$row]]);

        $this->assertIsArray($bbox, 'The school-district adapter must be asked about the radius area');
        [$minLng, $minLat, $maxLng, $maxLat] = $bbox;
        $this->assertTrue($minLat < 27.9506 && $maxLat > 27.9506 && $minLng < -82.4572 && $maxLng > -82.4572);
    }

    /** @dataProvider shapes */
    public function test_enrichment_hands_either_radius_shape_to_the_poi_lookup(array $row): void
    {
        $captured = null;

        $poi = Mockery::mock(PoiDistanceLookupService::class);
        $poi->shouldReceive('lookup')->andReturnUsing(function (array $geometry) use (&$captured) {
            $captured = $geometry;

            return ['results' => [], 'error' => null, 'source_lat' => null, 'source_lng' => null];
        });

        $flood = Mockery::mock(FloodZoneLookupService::class);
        $flood->shouldReceive('lookup')->andReturn(['zones' => [], 'error' => null]);
        $school = Mockery::mock(SchoolDistrictLookupService::class);
        $school->shouldReceive('lookup')->andReturn(['districts' => [], 'error' => null]);
        $commute = Mockery::mock(CommuteTimeLookupService::class);
        $commute->shouldReceive('lookup')->andReturn(['results' => [], 'error' => null]);

        (new LocationDnaEnrichmentRunner($flood, $school, $poi, $commute))
            ->run($this->noBoundary(), ['radius_searches' => [$row]]);

        $this->assertSame(
            ['type' => 'radius', 'lat' => 27.9506, 'lng' => -82.4572, 'radius_miles' => 5],
            $captured
        );
    }

    public static function shapes(): array
    {
        return [
            'flat — what the widget writes today' => [self::FLAT],
            'nested — older rows'                 => [self::NESTED],
        ];
    }

    public function test_an_unusable_radius_is_still_skipped_rather_than_guessed(): void
    {
        $adapter = Mockery::mock(FloodZoneAdapterInterface::class);
        $adapter->shouldNotReceive('lookup');

        $result = (new FloodZoneLookupService($adapter))->resolve($this->noBoundary(), [
            'radius_searches' => [['address' => 'Nowhere', 'lat' => null, 'lng' => null, 'radius_miles' => 5]],
        ]);

        $this->assertFalse($result['available']);
    }
}
