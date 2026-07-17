<?php

namespace Tests\Unit\Spatial;

use App\Services\Spatial\NormalizedExtractIo;
use App\Services\Spatial\OvertureCategoryMap;
use App\Services\Spatial\OverturePlaceNormalizer;
use PHPUnit\Framework\TestCase;

/**
 * Batch 2A (B2/B3) — offline normalizer against the committed Pinellas fixture:
 * confidence filtering, primary-category-only behavior, source_count derivation,
 * unmapped-rows-counted-not-lost, full accounting, and determinism.
 */
class OverturePlaceNormalizerTest extends TestCase
{
    private array $raw;
    private OverturePlaceNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $path = dirname(__DIR__, 2) . '/fixtures/spatial/overture/pinellas_raw_places.ndjson';
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

    /** @test */
    public function the_fixture_covers_all_eight_source_categories_plus_filtered_rows(): void
    {
        // 9 keepable + 2 low-confidence + 4 unmapped = 15.
        $this->assertCount(15, $this->raw);
    }

    /** @test */
    public function every_input_row_is_accounted_for_in_exactly_one_bucket(): void
    {
        $r = $this->normalizer->normalize($this->raw);

        $this->assertSame(15, $r->totalInput);
        $this->assertSame(9, $r->keptCount());
        $this->assertSame(4, $r->rejectedUnmapped);
        $this->assertSame(2, $r->rejectedLowConfidence);
        $this->assertSame(0, $r->rejectedInvalid);
        $this->assertTrue($r->isFullyAccounted());
    }

    /** @test */
    public function confidence_below_the_floor_is_filtered(): void
    {
        $r = $this->normalizer->normalize($this->raw);

        // None of the kept rows fall below the 0.90 floor.
        foreach ($r->records as $rec) {
            $this->assertGreaterThanOrEqual(0.90, $rec->confidence);
        }
        // The two sub-floor rows (0.75, 0.89) are the only low-confidence rejects.
        $this->assertSame(2, $r->rejectedLowConfidence);
    }

    /** @test */
    public function unmapped_rows_are_counted_and_tallied_not_silently_lost(): void
    {
        $r = $this->normalizer->normalize($this->raw);

        $this->assertSame(4, $r->rejectedUnmapped);
        $this->assertSame(['bar' => 2, 'school' => 1, 'hotel' => 1], $r->unmappedTally);
        // The tally sums to the unmapped reject count — nothing lost.
        $this->assertSame($r->rejectedUnmapped, array_sum($r->unmappedTally));
    }

    /** @test */
    public function only_the_primary_category_is_considered_alternate_is_ignored(): void
    {
        // gers-pinellas-0204 has primary "hotel" (unmapped) but alternate
        // ["restaurant","coffee_shop"] (both mapped). It MUST be rejected.
        $r = $this->normalizer->normalize($this->raw);

        foreach ($r->records as $rec) {
            $this->assertNotSame('gers-pinellas-0204', $rec->source_ref,
                'a row was kept via its ALTERNATE category — primary-only violated');
        }
        $this->assertArrayHasKey('hotel', $r->unmappedTally);
    }

    /** @test */
    public function source_count_is_derived_from_distinct_datasets(): void
    {
        $byRef = $this->indexByRef($this->normalizer->normalize($this->raw)->records);

        $this->assertSame(3, $byRef['gers-pinellas-0002']->source_count, 'meta+msft+fsq');
        $this->assertSame(2, $byRef['gers-pinellas-0001']->source_count, 'meta+msft');
        $this->assertSame(1, $byRef['gers-pinellas-0003']->source_count, 'meta only');
    }

    /** @test */
    public function fitness_center_and_gym_both_land_in_canonical_gym(): void
    {
        $byRef = $this->indexByRef($this->normalizer->normalize($this->raw)->records);

        $this->assertSame('gym', $byRef['gers-pinellas-0006']->category_key); // primary gym
        $this->assertSame('gym', $byRef['gers-pinellas-0007']->category_key); // primary fitness_center
    }

    /** @test */
    public function a_case_and_whitespace_variant_primary_still_maps(): void
    {
        // gers-pinellas-0009 primary is "  Restaurant " → restaurant, kept.
        $byRef = $this->indexByRef($this->normalizer->normalize($this->raw)->records);
        $this->assertArrayHasKey('gers-pinellas-0009', $byRef);
        $this->assertSame('restaurant', $byRef['gers-pinellas-0009']->category_key);
    }

    /** @test */
    public function structurally_invalid_rows_are_counted_in_the_invalid_bucket(): void
    {
        $rows = [
            ['id' => null, 'categories' => ['primary' => 'restaurant'], 'confidence' => 0.99,
                'geometry' => ['type' => 'Point', 'coordinates' => [-82.6, 27.7]]],
            ['id' => 'gers-nogeom', 'categories' => ['primary' => 'restaurant'], 'confidence' => 0.99],
        ];
        $r = $this->normalizer->normalize($rows);

        $this->assertSame(2, $r->totalInput);
        $this->assertSame(0, $r->keptCount());
        $this->assertSame(2, $r->rejectedInvalid);
        $this->assertTrue($r->isFullyAccounted());
    }

    /** @test */
    public function normalization_is_deterministic_and_idempotent(): void
    {
        $io = new NormalizedExtractIo();
        $a = $io->toNdjson($this->normalizer->normalize($this->raw)->records);
        $b = $io->toNdjson($this->normalizer->normalize($this->raw)->records);

        $this->assertSame($a, $b);
        // Re-parsing and re-serializing is a fixed point.
        $this->assertSame($a, $io->toNdjson($io->fromNdjson($a)));
    }

    /** @return array<string,\App\Services\Spatial\NormalizedPlaceRecord> */
    private function indexByRef(array $records): array
    {
        $out = [];
        foreach ($records as $rec) {
            $out[$rec->source_ref] = $rec;
        }

        return $out;
    }
}
