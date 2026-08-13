<?php

namespace Tests\Unit\Services\Location\AddressCorpus;

use App\Services\Location\AddressCorpus\GeoJsonSourceReader;
use RuntimeException;
use Tests\TestCase;

/**
 * The streaming reader, and the three things it must refuse.
 *
 * The refusals matter more than the streaming. A reader that loads too much is
 * slow; a reader that accepts State Plane feet as degrees is silently, hugely
 * wrong, and every downstream check passes — the coordinate is finite, the
 * address parses, the row imports. Only the property ends up on the wrong
 * continent.
 */
class GeoJsonSourceReaderTest extends TestCase
{
    private function fixture(string $name): string
    {
        return base_path("tests/fixtures/address-corpus/{$name}");
    }

    private function reader(string $name): GeoJsonSourceReader
    {
        return new GeoJsonSourceReader($this->fixture($name));
    }

    /** @return list<array<string, mixed>> */
    private function rows(string $name): array
    {
        return iterator_to_array($this->reader($name)->rows(), false);
    }

    // ── happy path ──────────────────────────────────────────────────────────

    public function test_it_streams_every_feature(): void
    {
        $this->assertCount(10, $this->rows('ng911-pinellas-sample.geojson'));
        $this->assertCount(6, $this->rows('ng911-hillsborough-sample.geojson'));
    }

    public function test_properties_and_geometry_arrive_as_one_flat_row(): void
    {
        $first = $this->rows('ng911-pinellas-sample.geojson')[0];

        $this->assertSame('PIN-0001', $first['SITEADDID']);
        $this->assertSame('E Madison St', $first['FULLNAME']);
        $this->assertEqualsWithDelta(27.9475, $first[GeoJsonSourceReader::LATITUDE], 0.00001);
        $this->assertEqualsWithDelta(-82.4570, $first[GeoJsonSourceReader::LONGITUDE], 0.00001);
    }

    public function test_the_reader_yields_rather_than_materialising(): void
    {
        // Not a memory benchmark — a structural assertion. A reader that decoded
        // the file and returned an array would satisfy every other test here and
        // fail on the first real county export.
        $generator = $this->reader('ng911-pinellas-sample.geojson')->rows();

        $this->assertInstanceOf(\Generator::class, $generator);
        $this->assertSame('PIN-0001', $generator->current()['SITEADDID']);
    }

    public function test_a_valid_source_reports_a_usable_schema(): void
    {
        $schema = $this->reader('ng911-hillsborough-sample.geojson')->assertSchema();

        $this->assertTrue($schema['ok']);
        $this->assertContains('SITEADDID', $schema['header']);
        $this->assertContains('ADDRNUM', $schema['header']);
        $this->assertNotContains(GeoJsonSourceReader::LATITUDE, $schema['header']);
    }

    // ── the refusals ────────────────────────────────────────────────────────

    public function test_a_declared_non_wgs84_crs_is_refused(): void
    {
        // Refused from the envelope, before a single feature is read — even
        // though this fixture's coordinates happen to be degrees. What the file
        // *declares* about itself is the thing being trusted or not.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/non-WGS84|outSR=4326/i');

        iterator_to_array($this->reader('ng911-declared-crs.geojson')->rows(), false);
    }

    public function test_projected_coordinates_are_refused_even_with_no_declared_crs(): void
    {
        // The case that actually happens. An ArcGIS export in EPSG:2237 carries
        // no `crs` member, so the envelope is indistinguishable from a WGS84
        // one; only the magnitude of the first coordinate gives it away.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/WGS84 degree range|2237|reproject/i');

        iterator_to_array($this->reader('ng911-stateplane.geojson')->rows(), false);
    }

    public function test_non_point_geometry_is_refused(): void
    {
        // A parcel polygon in an address feed is exactly how a parcel centroid
        // ends up labelled as an address point. There is no silent centroiding.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Point features only|Unsupported geometry/i');

        iterator_to_array($this->reader('ng911-polygon.geojson')->rows(), false);
    }

    public function test_a_bare_feature_is_not_a_feature_collection(): void
    {
        $schema = $this->reader('ng911-not-a-featurecollection.json')->assertSchema();

        $this->assertFalse($schema['ok']);
        $this->assertNotEmpty($schema['missing_required']);
    }

    public function test_a_missing_file_is_refused_at_construction(): void
    {
        $this->expectException(RuntimeException::class);

        new GeoJsonSourceReader($this->fixture('does-not-exist.geojson'));
    }

    public function test_the_declared_crs_failure_is_reported_through_assert_schema(): void
    {
        // The command renders a schema failure; it must not have to catch an
        // exception to learn about a CRS problem.
        $schema = $this->reader('ng911-declared-crs.geojson')->assertSchema();

        $this->assertFalse($schema['ok']);
        $this->assertStringContainsStringIgnoringCase('wgs84', implode(' ', $schema['missing_required']));
    }

    public function test_string_contents_do_not_end_a_feature_early(): void
    {
        // A brace or a quote inside a street name would break a naive
        // depth-counter and silently truncate the corpus.
        $path = sys_get_temp_dir() . '/ng911-braces-' . getmypid() . '.geojson';

        file_put_contents($path, json_encode([
            'type'     => 'FeatureCollection',
            'features' => [
                ['type' => 'Feature',
                 'properties' => ['SITEADDID' => 'B-1', 'FULLNAME' => 'Odd } Name "quoted" St', 'ADDRNUM' => '1'],
                 'geometry'   => ['type' => 'Point', 'coordinates' => [-82.45, 27.94]]],
                ['type' => 'Feature',
                 'properties' => ['SITEADDID' => 'B-2', 'FULLNAME' => 'Second St', 'ADDRNUM' => '2'],
                 'geometry'   => ['type' => 'Point', 'coordinates' => [-82.46, 27.95]]],
            ],
        ]));

        $rows = iterator_to_array((new GeoJsonSourceReader($path))->rows(), false);

        @unlink($path);

        $this->assertCount(2, $rows);
        $this->assertSame('Odd } Name "quoted" St', $rows[0]['FULLNAME']);
        $this->assertSame('B-2', $rows[1]['SITEADDID']);
    }
}
