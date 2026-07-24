<?php

namespace Tests\Feature\Spatial;

use Tests\TestCase;

/**
 * Batch 2D Part C3d-c (G1) — tiger_county_shp_to_ndjson.sh converts a Census TIGER county shapefile
 * into the FL-only, EPSG:4326 raw NDJSON that corpus:import-boundaries consumes. The contract is
 * asserted statically (always-on); the end-to-end conversion runs only where ogr2ogr + jq exist.
 */
class TigerShpToNdjsonConverterTest extends TestCase
{
    private function script(): string
    {
        return base_path('spikes/phase-2-batch-2d-part-c3b-tiger-boundary-import/bin/tiger_county_shp_to_ndjson.sh');
    }

    /** @test */
    public function the_converter_declares_its_offline_contract(): void
    {
        $src = (string) file_get_contents($this->script());

        // Dependencies required (fails non-zero if missing).
        $this->assertStringContainsString('command -v ogr2ogr', $src);
        $this->assertStringContainsString('command -v jq', $src);
        // Reproject to 4326 + filter STATEFP + stream GeoJSONSeq.
        $this->assertStringContainsString('-t_srs EPSG:4326', $src);
        $this->assertStringContainsString("STATEFP='", $src);
        $this->assertStringContainsString('GeoJSONSeq', $src);
        // Emits only the adapter's key set.
        $this->assertStringContainsString('geoid: .properties.GEOID', $src);
        $this->assertStringContainsString('statefp: .properties.STATEFP', $src);
        // Never downloads and never reads the spatial secret.
        $this->assertStringNotContainsString('curl', $src);
        $this->assertStringNotContainsString('wget', $src);
        $this->assertStringNotContainsString('SPATIAL_DATABASE_URL', $src);
    }

    /** @test */
    public function it_filters_to_florida_reprojects_and_keeps_string_codes(): void
    {
        if (!$this->hasBinary('ogr2ogr') || !$this->hasBinary('jq')) {
            $this->markTestSkipped('ogr2ogr and/or jq not available — skipping the live conversion.');
        }

        $dir = sys_get_temp_dir() . '/tiger_conv_' . getmypid();
        @mkdir($dir, 0775, true);
        $geojson = $dir . '/src.geojson';
        $shp = $dir . '/src.shp';
        $out = $dir . '/out.ndjson';

        // One Florida feature (STATEFP 12) + one California feature (STATEFP 06, must be filtered out).
        file_put_contents($geojson, json_encode([
            'type' => 'FeatureCollection',
            'features' => [
                ['type' => 'Feature', 'properties' => ['GEOID' => '12086', 'NAME' => 'Miami-Dade', 'NAMELSAD' => 'Miami-Dade County', 'STATEFP' => '12'],
                 'geometry' => ['type' => 'Polygon', 'coordinates' => [[[-80.9, 25.1], [-80.9, 25.2], [-80.8, 25.2], [-80.8, 25.1], [-80.9, 25.1]]]]],
                ['type' => 'Feature', 'properties' => ['GEOID' => '06037', 'NAME' => 'Los Angeles', 'NAMELSAD' => 'Los Angeles County', 'STATEFP' => '06'],
                 'geometry' => ['type' => 'Polygon', 'coordinates' => [[[-118.9, 34.1], [-118.9, 34.2], [-118.8, 34.2], [-118.8, 34.1], [-118.9, 34.1]]]]],
            ],
        ]));

        exec('ogr2ogr -f "ESRI Shapefile" ' . escapeshellarg($shp) . ' ' . escapeshellarg($geojson) . ' 2>&1', $mk, $mkCode);
        $this->assertSame(0, $mkCode, 'failed to build test shapefile: ' . implode("\n", $mk));

        exec('bash ' . escapeshellarg($this->script()) . ' ' . escapeshellarg($shp) . ' ' . escapeshellarg($out) . ' 12 2>&1', $runOut, $runCode);
        $this->assertSame(0, $runCode, 'converter failed: ' . implode("\n", $runOut));

        $raw = (string) file_get_contents($out);
        $lines = array_values(array_filter(explode("\n", $raw), static fn (string $l): bool => $l !== ''));

        // Only the Florida feature survives.
        $this->assertCount(1, $lines);
        $this->assertStringNotContainsString('06037', $raw, 'non-FL feature must be filtered out');

        // GEOID and STATEFP stay JSON strings (quoted) — leading-zero safety.
        $this->assertStringContainsString('"geoid":"12086"', $raw);
        $this->assertStringContainsString('"statefp":"12"', $raw);

        $obj = json_decode($lines[0], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(['geoid', 'name', 'namelsad', 'statefp', 'geometry'], array_keys($obj), 'exactly the adapter key set');
        $this->assertIsString($obj['geoid']);
        $this->assertIsString($obj['statefp']);
        $this->assertContains($obj['geometry']['type'], ['Polygon', 'MultiPolygon']);
        // EPSG:4326 outcome: coordinates preserved in WGS84 lon/lat range.
        $lon = $obj['geometry']['coordinates'][0][0][0];
        $this->assertGreaterThanOrEqual(-180.0, $lon);
        $this->assertLessThanOrEqual(180.0, $lon);

        // Deterministic: a second run yields byte-identical output.
        $out2 = $dir . '/out2.ndjson';
        exec('bash ' . escapeshellarg($this->script()) . ' ' . escapeshellarg($shp) . ' ' . escapeshellarg($out2) . ' 12 2>&1', $r2, $c2);
        $this->assertSame(0, $c2);
        $this->assertSame($raw, (string) file_get_contents($out2), 'converter output must be deterministic');

        // cleanup
        array_map('unlink', glob($dir . '/*') ?: []);
        @rmdir($dir);
    }

    private function hasBinary(string $bin): bool
    {
        exec('command -v ' . escapeshellarg($bin) . ' 2>/dev/null', $o, $code);
        return $code === 0;
    }
}
