<?php

namespace Tests\Unit\Services\LocationDna\Providers;

use App\Services\LocationDna\LocationDnaPoiDistanceService;
use App\Services\LocationDna\Providers\CorpusPoiCategoryMap;
use App\Services\Spatial\OvertureCategoryMap;
use App\Services\Spatial\OvertureTaxonomyMapV2;
use Tests\TestCase;

/**
 * The compatibility boundary between the corpus and Location DNA.
 *
 * Corpus v2 holds sixteen categories; Location DNA answers from exactly seven. The seven
 * are an explicit list, so widening the corpus cannot widen Location DNA. If an eighth
 * category is ever wanted, it is added to CorpusPoiCategoryMap::LOCATION_DNA_KEYS and to
 * this test, deliberately — never as a side effect of an import.
 */
class LocationDnaCorpusCategoryBoundaryTest extends TestCase
{
    private const LOCATION_DNA_SEVEN = [
        'coffee_shop',
        'gas_station',
        'grocery_store',
        'gym',
        'pharmacy',
        'restaurant',
        'shopping_center',
    ];

    /** Corpus v2 / brand-search only — must never reach Location DNA automatically. */
    private const V2_ONLY_NINE = [
        'convenience_store',
        'fast_food_restaurant',
        'cafe',
        'burger_restaurant',
        'department_store',
        'chicken_restaurant',
        'drugstore',
        'taco_restaurant',
        'superstore',
    ];

    public function test_location_dna_supports_exactly_the_seven(): void
    {
        $supported = CorpusPoiCategoryMap::supportedCanonicalKeys();

        $this->assertSame(self::LOCATION_DNA_SEVEN, $supported);
        $this->assertCount(7, $supported);
        $this->assertSame([], array_values(array_diff($supported, self::LOCATION_DNA_SEVEN)), 'an eighth category leaked in');
        $this->assertSame([], array_values(array_diff(self::LOCATION_DNA_SEVEN, $supported)), 'a category went missing');
        $this->assertSame(self::LOCATION_DNA_SEVEN, CorpusPoiCategoryMap::LOCATION_DNA_KEYS);
    }

    public function test_no_v2_only_category_reaches_location_dna(): void
    {
        foreach (self::V2_ONLY_NINE as $key) {
            $this->assertNotContains($key, CorpusPoiCategoryMap::supportedCanonicalKeys(), $key);
            $this->assertFalse(CorpusPoiCategoryMap::supports($key), $key);
            $this->assertNull(CorpusPoiCategoryMap::corpusCategoryFor($key), $key);
        }
    }

    public function test_the_v2_corpus_is_wider_than_location_dna_by_exactly_the_nine(): void
    {
        $v2 = (new OvertureTaxonomyMapV2())->canonicalKeys();

        $this->assertCount(16, $v2);
        $this->assertEqualsCanonicalizing(
            self::V2_ONLY_NINE,
            array_values(array_diff($v2, CorpusPoiCategoryMap::supportedCanonicalKeys())),
        );
    }

    public function test_every_location_dna_category_is_still_produced_by_both_corpus_crosswalks(): void
    {
        // Explicit is not the same as unanchored: each of the seven must remain a key the
        // v1 (active) and v2 (future) corpora can actually carry, or Location DNA would claim
        // a category no corpus row can hold.
        $v1 = (new OvertureCategoryMap())->canonicalKeys();
        $v2 = (new OvertureTaxonomyMapV2())->canonicalKeys();

        foreach (self::LOCATION_DNA_SEVEN as $key) {
            $this->assertContains($key, $v1, "v1 cannot produce {$key}");
            $this->assertContains($key, $v2, "v2 cannot produce {$key}");
        }
    }

    public function test_the_pipeline_asks_for_none_of_the_v2_only_categories(): void
    {
        foreach (self::V2_ONLY_NINE as $key) {
            $this->assertArrayNotHasKey($key, LocationDnaPoiDistanceService::CATEGORIES, $key);
        }
    }

    public function test_no_pipeline_descriptor_resolves_outside_the_seven(): void
    {
        // The adapter's own question (supportsCategory → corpusCategoryForDescriptor) is the
        // path a fetch actually takes; prove it directly rather than only through supports().
        foreach (LocationDnaPoiDistanceService::CATEGORIES as $key => $descriptor) {
            $resolved = CorpusPoiCategoryMap::corpusCategoryForDescriptor($descriptor);

            if ($resolved !== null) {
                $this->assertContains($resolved, self::LOCATION_DNA_SEVEN, $key);
            }
            $this->assertNotContains($resolved, self::V2_ONLY_NINE, $key);
        }
    }

    public function test_the_supported_set_is_not_derived_from_any_corpus_crosswalk(): void
    {
        // Strip comments and docblocks, then prove the executable code references neither
        // crosswalk. A derived set is what would let corpus breadth leak into Location DNA.
        $source = (string) file_get_contents(
            (new \ReflectionClass(CorpusPoiCategoryMap::class))->getFileName()
        );

        $code = '';
        foreach (token_get_all($source) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $code .= is_array($token) ? $token[1] : $token;
        }

        $this->assertStringNotContainsString('OvertureCategoryMap', $code);
        $this->assertStringNotContainsString('OvertureTaxonomyMapV2', $code);
        $this->assertStringNotContainsString('place_categories', $code);
    }
}
