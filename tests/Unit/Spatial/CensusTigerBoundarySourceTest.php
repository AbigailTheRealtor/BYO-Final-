<?php

namespace Tests\Unit\Spatial;

use App\Services\Spatial\Boundary\CensusCountyBoundarySource;
use App\Services\Spatial\Boundary\CensusPlaceBoundarySource;
use App\Services\Spatial\Boundary\CensusSchoolDistrictBoundarySource;
use App\Services\Spatial\Boundary\CensusZctaBoundarySource;
use Tests\TestCase;

/**
 * Batch 2D Part C3b — the four Census TIGER boundary source adapters (shared base, D-C3b-1).
 * Pure; no DB, no network, no download.
 */
class CensusTigerBoundarySourceTest extends TestCase
{
    private function polygon(float $lon = -82.0, float $lat = 27.0): array
    {
        return ['type' => 'Polygon', 'coordinates' => [[[$lon, $lat], [$lon, $lat + 0.01], [$lon + 0.01, $lat + 0.01], [$lon + 0.01, $lat], [$lon, $lat]]]];
    }

    /** @test */
    public function each_adapter_declares_its_source_key_and_kind(): void
    {
        $this->assertSame(['tiger_county', 'county'], [(new CensusCountyBoundarySource())->sourceKey(), (new CensusCountyBoundarySource())->kind()]);
        $this->assertSame(['tiger_place', 'place'], [(new CensusPlaceBoundarySource())->sourceKey(), (new CensusPlaceBoundarySource())->kind()]);
        $this->assertSame(['tiger_zcta', 'zcta'], [(new CensusZctaBoundarySource())->sourceKey(), (new CensusZctaBoundarySource())->kind()]);
        $this->assertSame(['tiger_school_district', 'school_district'], [(new CensusSchoolDistrictBoundarySource())->sourceKey(), (new CensusSchoolDistrictBoundarySource())->kind()]);
    }

    /** @test */
    public function county_normalizes_geoid_wraps_polygon_and_builds_attrs(): void
    {
        $result = (new CensusCountyBoundarySource())->normalize([
            ['geoid' => '12057', 'name' => 'Hillsborough County', 'namelsad' => 'Hillsborough County', 'statefp' => '12', 'geometry' => $this->polygon()],
        ]);

        $this->assertCount(1, $result->records);
        $r = $result->records[0];
        $this->assertSame('county', $r->kind);
        $this->assertSame('12057', $r->external_ref);
        $this->assertSame('MultiPolygon', $r->geometry['type']);
        $this->assertSame(['name' => 'Hillsborough County', 'namelsad' => 'Hillsborough County', 'state_fips' => '12', 'source' => 'census_tiger'], $r->attrs);
        $this->assertArrayNotHasKey('acres', $r->attrs);
        $this->assertTrue($result->isFullyAccounted());
    }

    /** @test */
    public function zcta_resolves_alternate_geoid_keys_and_has_a_null_name(): void
    {
        $source = new CensusZctaBoundarySource();

        $byGeoid20 = $source->normalize([['GEOID20' => '33702', 'geometry' => $this->polygon()]]);
        $this->assertSame('33702', $byGeoid20->records[0]->external_ref);

        $byZcta5 = $source->normalize([['ZCTA5' => '33701', 'geometry' => $this->polygon()]]);
        $this->assertSame('33701', $byZcta5->records[0]->external_ref);
        $this->assertNull($byZcta5->records[0]->attrs['name']);
        $this->assertSame('census_tiger', $byZcta5->records[0]->attrs['source']);
    }

    /** @test */
    public function place_carries_basename_and_school_district_carries_name(): void
    {
        $place = (new CensusPlaceBoundarySource())->normalize([
            ['geoid' => '1200001', 'name' => 'Synthetic City city', 'basename' => 'Synthetic City', 'statefp' => '12', 'geometry' => $this->polygon()],
        ]);
        $this->assertSame('Synthetic City', $place->records[0]->attrs['basename']);

        $sd = (new CensusSchoolDistrictBoundarySource())->normalize([
            ['geoid' => '1200060', 'name' => 'Synthetic Unified School District', 'statefp' => '12', 'geometry' => $this->polygon()],
        ]);
        $this->assertSame('Synthetic Unified School District', $sd->records[0]->attrs['name']);
        $this->assertArrayNotHasKey('acres', $sd->records[0]->attrs);
    }

    /** @test */
    public function a_missing_geoid_is_rejected_invalid_field(): void
    {
        $result = (new CensusCountyBoundarySource())->normalize([
            ['name' => 'No-GEOID County', 'geometry' => $this->polygon()],
        ]);

        $this->assertCount(0, $result->records);
        $this->assertSame(1, $result->rejectedInvalidField);
        $this->assertSame(0, $result->rejectedInvalidGeometry);
        $this->assertTrue($result->isFullyAccounted());
    }

    /** @test */
    public function an_unclosed_ring_is_rejected_invalid_geometry(): void
    {
        $open = ['type' => 'Polygon', 'coordinates' => [[[-80.3, 25.7], [-80.3, 25.71], [-80.29, 25.71], [-80.29, 25.7]]]];
        $result = (new CensusCountyBoundarySource())->normalize([
            ['geoid' => '12086', 'geometry' => $open],
        ]);

        $this->assertCount(0, $result->records);
        $this->assertSame(1, $result->rejectedInvalidGeometry);
    }

    /** @test */
    public function normalization_preserves_input_order_and_is_deterministic(): void
    {
        $rows = [
            ['geoid' => '12103', 'name' => 'B', 'statefp' => '12', 'geometry' => $this->polygon()],
            ['geoid' => '12057', 'name' => 'A', 'statefp' => '12', 'geometry' => $this->polygon()],
        ];
        $result = (new CensusCountyBoundarySource())->normalize($rows);
        $this->assertSame(['12103', '12057'], array_map(fn ($r) => $r->external_ref, $result->records));
    }
}
