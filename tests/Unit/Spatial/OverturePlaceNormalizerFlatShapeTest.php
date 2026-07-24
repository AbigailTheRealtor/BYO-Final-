<?php

namespace Tests\Unit\Spatial;

use App\Services\Spatial\NormalizedPlaceRecord;
use App\Services\Spatial\OvertureCategoryMap;
use App\Services\Spatial\OverturePlaceNormalizer;
use PHPUnit\Framework\TestCase;

/**
 * Batch 2A (seam fix) — the committed DuckDB extract
 * (spikes/phase-2-batch-2a-overture-first-slice/sql/extract_places.sql) emits a
 * FLAT, renamed row shape (source_ref / gers_id / primary_category / name /
 * brand / confidence / source_count / lon / lat). This test proves that raw
 * flat-extractor output normalizes DIRECTLY through the committed normalizer —
 * no intermediate adapter — via the additive id (`gers_id`/`source_ref`) and
 * category (`primary_category`) fallbacks. It complements the nested-shape
 * coverage in OverturePlaceNormalizerTest / OverturePlaceNormalizerV117Test.
 */
class OverturePlaceNormalizerFlatShapeTest extends TestCase
{
    private array $raw;
    private OverturePlaceNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $path = dirname(__DIR__, 2) . '/fixtures/spatial/overture/pinellas_raw_flat_places.ndjson';
        $this->raw = $this->readFixture($path);
        $this->normalizer = new OverturePlaceNormalizer(new OvertureCategoryMap(), 0.90);
    }

    private function readFixture(string $path): array
    {
        $this->assertFileExists($path);
        $rows = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $rows[] = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
        }

        return $rows;
    }

    /** @return array<string,NormalizedPlaceRecord> */
    private function indexByRef(array $records): array
    {
        $out = [];
        foreach ($records as $rec) {
            $out[$rec->source_ref] = $rec;
        }

        return $out;
    }

    /** @test The fixture uses ONLY the committed flat DuckDB aliases (no nested Overture keys). */
    public function the_fixture_is_purely_flat_shaped(): void
    {
        $flatKeys = ['source_ref', 'primary_category', 'name', 'brand', 'confidence', 'source_count', 'lon', 'lat'];
        $nestedKeys = ['id', 'categories', 'taxonomy', 'basic_category', 'geometry', 'names', 'sources'];

        foreach ($this->raw as $row) {
            foreach ($nestedKeys as $k) {
                $this->assertArrayNotHasKey($k, $row, "flat fixture must not carry the nested key [{$k}]");
            }
            foreach ($flatKeys as $k) {
                $this->assertArrayHasKey($k, $row, "flat fixture row must carry the flat alias [{$k}]");
            }
        }
    }

    /** @test Flat rows normalize directly: no invalid, no unmapped, full accounting. */
    public function flat_extractor_rows_normalize_with_no_adapter(): void
    {
        $r = $this->normalizer->normalize($this->raw);

        // 10 total = 9 keepable + 1 sub-floor. Every mapped, valid row survives —
        // proving the id + category flat fallbacks close the seam (pre-fix this
        // was 10 rejectedInvalid, 0 kept).
        $this->assertSame(10, $r->totalInput);
        $this->assertSame(9, $r->keptCount());
        $this->assertSame(0, $r->rejectedInvalid, 'flat rows must resolve an id — none structurally invalid');
        $this->assertSame(0, $r->rejectedUnmapped, 'every flat primary_category is a mapped source token');
        $this->assertSame(1, $r->rejectedLowConfidence, 'exactly the one sub-0.90 row is filtered');
        $this->assertTrue($r->isFullyAccounted());
    }

    /** @test The one sub-0.90 flat row is the only low-confidence reject. */
    public function the_sub_floor_flat_row_is_rejected_on_confidence(): void
    {
        $byRef = $this->indexByRef($this->normalizer->normalize($this->raw)->records);

        // flat-pinellas-0009 has confidence 0.80 → dropped, not kept.
        $this->assertArrayNotHasKey('flat-pinellas-0009', $byRef);
    }

    /** @test All seven canonical category keys appear; fitness_center collapses to gym. */
    public function all_seven_canonical_categories_are_represented(): void
    {
        $records = $this->normalizer->normalize($this->raw)->records;

        $byCategory = [];
        foreach ($records as $rec) {
            $byCategory[$rec->category_key] = ($byCategory[$rec->category_key] ?? 0) + 1;
        }

        $this->assertEqualsCanonicalizing(
            ['coffee_shop', 'gas_station', 'grocery_store', 'gym', 'pharmacy', 'restaurant', 'shopping_center'],
            array_keys($byCategory),
            'exactly the 7 canonical keys must be represented'
        );

        // gym (flat-0004) + fitness_center (flat-0005) both land in canonical gym.
        $this->assertSame(2, $byCategory['gym'], 'gym + fitness_center collapse to gym');

        $byRef = $this->indexByRef($records);
        $this->assertSame('gym', $byRef['flat-pinellas-0004']->category_key, 'flat gym → gym');
        $this->assertSame('gym', $byRef['flat-pinellas-0005']->category_key, 'flat fitness_center → gym');
    }

    /** @test Every kept flat row is at or above the 0.90 floor. */
    public function kept_confidence_stays_at_or_above_floor(): void
    {
        foreach ($this->normalizer->normalize($this->raw)->records as $rec) {
            $this->assertGreaterThanOrEqual(0.90, $rec->confidence);
        }
    }

    /** @test External refs (source_ref) are unique across the kept records. */
    public function external_refs_are_unique(): void
    {
        $records = $this->normalizer->normalize($this->raw)->records;

        $refs = array_map(fn (NormalizedPlaceRecord $rec) => $rec->source_ref, $records);
        $this->assertSame(count($refs), count(array_unique($refs)), 'source_ref must be unique');
    }

    /** @test Identity resolves from the flat `gers_id` alias when nested `id` is absent. */
    public function id_resolves_from_gers_id(): void
    {
        $byRef = $this->indexByRef($this->normalizer->normalize($this->raw)->records);

        // flat-pinellas-0001 carries gers_id (no nested id) → both source_ref and
        // gers_id on the normalized record resolve to that value.
        $this->assertArrayHasKey('flat-pinellas-0001', $byRef);
        $this->assertSame('flat-pinellas-0001', $byRef['flat-pinellas-0001']->gers_id);
        $this->assertSame('flat-pinellas-0001', $byRef['flat-pinellas-0001']->source_ref);
    }

    /** @test Identity falls back to `source_ref` when both nested id AND gers_id are absent. */
    public function id_resolves_from_source_ref_when_gers_id_absent(): void
    {
        // Direct-array assertion to make the precedence explicit: the fixture's
        // flat-pinellas-0010 row has NO gers_id key, only source_ref.
        $this->assertArrayNotHasKey('gers_id', $this->raw[9]);
        $this->assertSame('flat-pinellas-0010', $this->raw[9]['source_ref']);

        $byRef = $this->indexByRef($this->normalizer->normalize($this->raw)->records);
        $this->assertArrayHasKey('flat-pinellas-0010', $byRef, 'row resolved its id from source_ref and was kept');
        $this->assertSame('flat-pinellas-0010', $byRef['flat-pinellas-0010']->gers_id);
    }

    /** @test Coordinates, name, brand (scalar + null) carry through from the flat aliases. */
    public function flat_coordinates_name_and_brand_carry_through(): void
    {
        $byRef = $this->indexByRef($this->normalizer->normalize($this->raw)->records);

        $rec = $byRef['flat-pinellas-0001'];
        $this->assertSame(-82.64, $rec->lon);
        $this->assertSame(27.77, $rec->lat);
        $this->assertSame('Flat Bean Coffee', $rec->name);
        $this->assertSame('Flatbucks', $rec->brand, 'scalar flat brand carries through');
        $this->assertSame('Point', $rec->geometry_type);

        // A null flat brand normalizes to null (not the string "").
        $this->assertNull($byRef['flat-pinellas-0002']->brand, 'null flat brand → null');
    }

    /** @test The flat `source_count` scalar is preserved (real provenance kept). */
    public function flat_source_count_is_preserved(): void
    {
        $byRef = $this->indexByRef($this->normalizer->normalize($this->raw)->records);

        // flat-pinellas-0001 carries source_count 7 → 7 survives.
        $this->assertSame(7, $byRef['flat-pinellas-0001']->source_count);
        // flat-pinellas-0004 carries source_count 1 → stays 1.
        $this->assertSame(1, $byRef['flat-pinellas-0004']->source_count);
    }

    /**
     * @test Missing / null / zero / negative / non-numeric flat source_count all
     * floor safely to 1 — a place always has at least the Overture record itself.
     */
    public function flat_source_count_edge_values_floor_to_one(): void
    {
        $base = [
            'source_ref' => 'flat-edge',
            'primary_category' => 'restaurant',
            'name' => 'Edge Diner',
            'brand' => null,
            'confidence' => 0.95,
            'lon' => -82.60,
            'lat' => 27.70,
        ];

        $cases = [
            'missing' => $base, // no source_count key at all
            'null' => $base + ['source_count' => null],
            'zero' => $base + ['source_count' => 0],
            'negative' => $base + ['source_count' => -3],
            'nonnumeric' => $base + ['source_count' => 'lots'],
        ];

        foreach ($cases as $label => $row) {
            $r = $this->normalizer->normalize([$row]);
            $this->assertSame(1, $r->keptCount(), "[{$label}] row should still be kept");
            $this->assertSame(1, $r->records[0]->source_count, "[{$label}] flat source_count must floor to 1");
        }
    }

    /**
     * @test A present, non-empty nested `sources[]` stays authoritative even when a
     * flat `source_count` scalar is also present — flat never overrides nested.
     */
    public function nested_sources_remain_authoritative_over_flat_source_count(): void
    {
        $row = [
            'source_ref' => 'flat-nested-hybrid',
            'primary_category' => 'restaurant',
            'sources' => [['dataset' => 'meta'], ['dataset' => 'msft'], ['dataset' => 'meta']],
            'source_count' => 99, // must be ignored — nested wins
            'name' => 'Hybrid Grill',
            'brand' => null,
            'confidence' => 0.95,
            'lon' => -82.60,
            'lat' => 27.70,
        ];

        $rec = $this->normalizer->normalize([$row])->records[0];
        $this->assertSame(2, $rec->source_count, 'distinct nested datasets (meta, msft) win over flat 99');
    }
}
