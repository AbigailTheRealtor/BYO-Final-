<?php

namespace Tests\Unit\Spatial;

use App\Services\Spatial\NormalizedExtractIo;
use App\Services\Spatial\NormalizedPlaceRecord;
use App\Services\Spatial\OvertureCategoryMap;
use App\Services\Spatial\OverturePlaceNormalizer;
use PHPUnit\Framework\TestCase;

/**
 * Batch 2D (Part A) — Overture schema v1.17.0 category-field compatibility.
 *
 * Proves the primary-token precedence categories.primary → taxonomy.primary →
 * basic_category, that each fallback fires ONLY on the ABSENCE of the higher-
 * precedence field, that unknown new-vocabulary values stay unmapped (no
 * invented crosswalk), and that the pinned-release (Batch 2A/2C) behavior is
 * byte-for-byte unchanged.
 *
 * The v1.17.0 fixture is deliberately adversarial: rows A and E carry
 * taxonomy.primary / basic_category values that WOULD map to a different
 * canonical key, so a wrong precedence (or any inference from the ignored
 * fields) changes the asserted outcome.
 */
class OverturePlaceNormalizerV117Test extends TestCase
{
    private array $raw;
    private OverturePlaceNormalizer $normalizer;

    /** Pre-change baseline of the existing 2C fixture output (captured before Part A). */
    private const LEGACY_FIXTURE_SHA256 =
        '1099cb67ae83af648a3cc053c59927c3c56749ba54cf57af172c87b89be45b6b';

    protected function setUp(): void
    {
        parent::setUp();
        $path = dirname(__DIR__, 2) . '/fixtures/spatial/overture/pinellas_raw_places_v117.ndjson';
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

    /** @test Requirement 1 — categories.primary remains authoritative. */
    public function categories_primary_is_authoritative_when_present(): void
    {
        $byRef = $this->indexByRef($this->normalizer->normalize($this->raw)->records);

        // Row A: categories.primary "grocery_store" wins over taxonomy.primary
        // "restaurant" and basic_category "coffee_shop".
        $this->assertArrayHasKey('gers-v117-A', $byRef);
        $this->assertSame('grocery_store', $byRef['gers-v117-A']->category_key);
    }

    /** @test Requirement 1 — a present-but-UNMAPPED categories.primary is still authoritative. */
    public function unmapped_categories_primary_is_not_overridden_by_fallbacks(): void
    {
        $r = $this->normalizer->normalize($this->raw);
        $byRef = $this->indexByRef($r->records);

        // Row E: categories.primary "hotel" is unmapped. taxonomy.primary
        // "restaurant" WOULD map, but the fallback must NOT fire — the row is
        // tallied as "hotel", never kept as restaurant.
        $this->assertArrayNotHasKey('gers-v117-E', $byRef);
        $this->assertArrayHasKey('hotel', $r->unmappedTally);
        foreach ($r->records as $rec) {
            $this->assertNotSame('gers-v117-E', $rec->source_ref,
                'row E was resolved via a lower-precedence field — precedence violated');
        }
    }

    /** @test Requirement 2 — taxonomy.primary activates only when categories.primary is absent. */
    public function taxonomy_primary_activates_only_when_categories_primary_absent(): void
    {
        $byRef = $this->indexByRef($this->normalizer->normalize($this->raw)->records);

        // Row B: no `categories` key → taxonomy.primary "restaurant" resolves.
        // basic_category "coffee_shop" is present but must NOT be used.
        $this->assertArrayHasKey('gers-v117-B', $byRef);
        $this->assertSame('restaurant', $byRef['gers-v117-B']->category_key);
    }

    /** @test Requirement 3 — basic_category activates only when primary AND taxonomy.primary absent. */
    public function basic_category_activates_only_when_primary_and_taxonomy_absent(): void
    {
        $byRef = $this->indexByRef($this->normalizer->normalize($this->raw)->records);

        // Row C: no `categories`, no `taxonomy` → basic_category "pharmacy" resolves.
        $this->assertArrayHasKey('gers-v117-C', $byRef);
        $this->assertSame('pharmacy', $byRef['gers-v117-C']->category_key);
    }

    /** @test Requirement 4 — unknown taxonomy/basic_category values remain unmapped. */
    public function unknown_new_vocabulary_values_remain_unmapped_and_tallied(): void
    {
        $r = $this->normalizer->normalize($this->raw);
        $byRef = $this->indexByRef($r->records);

        // Row D: taxonomy.primary "greasy_diner" (new specific vocab, not in the
        // map) is resolved but unmapped; basic_category "casual_eatery" (new basic
        // vocab) is never reached. The row is tallied, not kept, not inferred.
        $this->assertArrayNotHasKey('gers-v117-D', $byRef);
        $this->assertArrayHasKey('greasy_diner', $r->unmappedTally);
        $this->assertArrayNotHasKey('casual_eatery', $r->unmappedTally);
    }

    /** @test Requirement 5 — no category is ever inferred (full, exact accounting). */
    public function no_category_is_ever_inferred(): void
    {
        $r = $this->normalizer->normalize($this->raw);

        $this->assertSame(5, $r->totalInput);
        $this->assertSame(3, $r->keptCount());
        $this->assertSame(2, $r->rejectedUnmapped);
        $this->assertSame(0, $r->rejectedLowConfidence);
        $this->assertSame(0, $r->rejectedInvalid);
        $this->assertTrue($r->isFullyAccounted());

        // Exactly the three legitimately-resolved keys — nothing conjured from
        // alternate / hierarchy / basic_category vocabularies.
        $keys = array_map(fn (NormalizedPlaceRecord $rec) => $rec->category_key, $r->records);
        sort($keys);
        $this->assertSame(['grocery_store', 'pharmacy', 'restaurant'], $keys);

        // The unmapped tally is exactly the two unknown/authoritative-unmapped tokens.
        $this->assertSame(['greasy_diner' => 1, 'hotel' => 1], $r->unmappedTally);
        $this->assertSame($r->rejectedUnmapped, array_sum($r->unmappedTally));
    }

    /** @test Requirement 4/5 — taxonomy.alternates and taxonomy.hierarchy never affect resolution. */
    public function taxonomy_alternates_and_hierarchy_are_ignored(): void
    {
        $byRef = $this->indexByRef($this->normalizer->normalize($this->raw)->records);

        // Row A hierarchy contains "supermarket" and alternates "convenience_store";
        // row E alternates contain "coffee_shop". None leak into a category_key.
        $this->assertSame('grocery_store', $byRef['gers-v117-A']->category_key);
        foreach ($byRef as $rec) {
            $this->assertContains($rec->category_key,
                ['grocery_store', 'restaurant', 'pharmacy'],
                'a category_key leaked from an ignored taxonomy field');
        }
    }

    /** @test Requirement 6 — the existing Batch 2C fixture output is byte-for-byte unchanged. */
    public function legacy_batch_2c_fixture_output_is_byte_identical(): void
    {
        $legacyPath = dirname(__DIR__, 2) . '/fixtures/spatial/overture/pinellas_raw_places.ndjson';
        $rows = $this->readFixture($legacyPath);

        $result = $this->normalizer->normalize($rows);
        $ndjson = (new NormalizedExtractIo())->toNdjson($result->records);

        $this->assertSame(9, $result->keptCount(), 'Batch 2C kept-count drifted');
        $this->assertSame(
            self::LEGACY_FIXTURE_SHA256,
            hash('sha256', $ndjson),
            'Batch 2C normalized output changed — Part A must be behavior-preserving for categories.primary'
        );
    }
}
