<?php

namespace Tests\Unit\Services\LocationDna\Providers;

use App\Services\LocationDna\CanonicalCategoryRegistry;
use App\Services\LocationDna\LocationDnaPoiDistanceService;
use App\Services\LocationDna\Providers\CorpusPoiCategoryMap;
use App\Services\Spatial\OvertureCategoryMap;
use Tests\TestCase;

/**
 * The claim that matters most in this feature: WHICH CATEGORIES THE CORPUS CAN ANSWER.
 *
 * A map that over-claims produces a listing page that looks complete and is wrong —
 * the "nearest beach" filled in by whatever row came back. A map that under-claims
 * silently drops a category the corpus does hold. Both fail invisibly in production,
 * so both are pinned here.
 */
class CorpusPoiCategoryMapTest extends TestCase
{
    /** The seven the Overture first slice actually loaded. */
    private const LOADED = [
        'coffee_shop',
        'gas_station',
        'grocery_store',
        'gym',
        'pharmacy',
        'restaurant',
        'shopping_center',
    ];

    /**
     * Categories the pipeline asks for that NO import has been run for. Each is declared
     * in CanonicalCategoryRegistry against an open-data provider marked PROVIDER_PLANNED
     * (NOAA CUSP, NCES, PAD-US, CMS, GTFS/NTD, USGS, OSM, FAA NASR).
     */
    private const NOT_LOADED = [
        'beach', 'beach_access', 'school', 'park', 'hospital', 'transit_station',
        'boat_ramp', 'marina', 'waterfront_park', 'dog_park', 'golf_course',
        'airport', 'urgent_care',
    ];

    public function test_supported_set_is_exactly_the_loaded_seven(): void
    {
        $this->assertSame(self::LOADED, CorpusPoiCategoryMap::supportedCanonicalKeys());
    }

    /**
     * The supported set is DERIVED from the corpus taxonomy, not restated beside it.
     * If a later import adds a category to OvertureCategoryMap, this map must follow
     * without an edit — and if it ever stopped following, this fails.
     */
    public function test_supported_set_tracks_the_corpus_taxonomy(): void
    {
        $taxonomy = (new OvertureCategoryMap())->canonicalKeys();
        sort($taxonomy);

        $this->assertSame($taxonomy, CorpusPoiCategoryMap::supportedCanonicalKeys());
    }

    public function test_every_loaded_category_is_supported_and_maps_to_itself(): void
    {
        foreach (self::LOADED as $key) {
            $this->assertTrue(CorpusPoiCategoryMap::supports($key), "{$key} should be supported");
            $this->assertSame($key, CorpusPoiCategoryMap::corpusCategoryFor($key));
        }
    }

    /** The heart of it: no unimported category may resolve to anything. */
    public function test_no_unimported_category_is_ever_claimed(): void
    {
        foreach (self::NOT_LOADED as $key) {
            $this->assertFalse(
                CorpusPoiCategoryMap::supports($key),
                "{$key} has no corpus rows and must not be claimed as supported"
            );
            $this->assertNull(
                CorpusPoiCategoryMap::corpusCategoryFor($key),
                "{$key} must resolve to null, not to a substitute category"
            );
        }
    }

    /** Every category the map names must be a real canonical key, not a typo. */
    public function test_supported_keys_are_all_real_canonical_categories(): void
    {
        foreach (CorpusPoiCategoryMap::supportedCanonicalKeys() as $key) {
            $this->assertTrue(
                CanonicalCategoryRegistry::has($key),
                "{$key} is claimed as supported but is not a canonical category"
            );
        }
    }

    /** Registry aliases resolve — 'gyms' is the plural alias of 'gym'. */
    public function test_registry_aliases_resolve_to_the_corpus_category(): void
    {
        $this->assertSame('gym', CorpusPoiCategoryMap::corpusCategoryFor('gyms'));
    }

    public function test_an_unknown_category_resolves_to_null_rather_than_throwing(): void
    {
        $this->assertFalse(CorpusPoiCategoryMap::supports('not_a_category'));
        $this->assertNull(CorpusPoiCategoryMap::corpusCategoryFor('not_a_category'));
    }

    // ── buyer / tenant view slugs ───────────────────────────────────────────

    public function test_only_the_two_servable_buyer_tenant_slugs_resolve(): void
    {
        $this->assertSame('gym', CorpusPoiCategoryMap::corpusCategoryForViewSlug('gyms'));
        $this->assertSame('shopping_center', CorpusPoiCategoryMap::corpusCategoryForViewSlug('shopping'));
    }

    /**
     * A buyer asking for nearby hospitals must get nothing from a corpus with no
     * hospitals in it — not the nearest gym.
     */
    public function test_unservable_buyer_tenant_slugs_resolve_to_null(): void
    {
        foreach (['schools', 'parks', 'hospitals', 'airports', 'downtown'] as $slug) {
            $this->assertNull(
                CorpusPoiCategoryMap::corpusCategoryForViewSlug($slug),
                "buyer/tenant slug '{$slug}' has no corpus rows and must resolve to null"
            );
        }
    }

    // ── descriptor recovery ─────────────────────────────────────────────────

    /**
     * The invariant the whole descriptor recovery rests on. If two categories ever shared
     * a (google_type, keyword) pair, LocationDnaPoiTileCache would already be serving one
     * category's cached candidates for the other — so this guards two things at once.
     */
    public function test_the_descriptor_pair_is_a_unique_identity_across_all_categories(): void
    {
        $this->assertTrue(
            CorpusPoiCategoryMap::descriptorPairsAreUnique(),
            'Two pipeline categories share a (google_type, keyword) pair — descriptor '
            . 'recovery and the tile cache key would both become ambiguous.'
        );
    }

    /** Every pipeline category must be recoverable from its own descriptor. */
    public function test_every_pipeline_descriptor_recovers_its_own_category(): void
    {
        foreach (LocationDnaPoiDistanceService::CATEGORIES as $key => $meta) {
            $this->assertSame(
                $key,
                CorpusPoiCategoryMap::canonicalKeyForDescriptor($meta),
                "descriptor for '{$key}' did not recover its own category key"
            );
        }
    }

    public function test_a_supported_descriptor_resolves_to_its_corpus_category(): void
    {
        $grocery = LocationDnaPoiDistanceService::CATEGORIES['grocery_store'];

        $this->assertSame('grocery_store', CorpusPoiCategoryMap::corpusCategoryForDescriptor($grocery));
    }

    /**
     * `fitness_center` shares google_type 'gym' with `gym` and is told apart only by its
     * keyword. It must recover as itself, not as gym — and since it is not in the corpus
     * taxonomy it must then resolve to null rather than borrowing gym's rows.
     */
    public function test_fitness_center_is_told_apart_from_gym_by_its_keyword(): void
    {
        $gym     = LocationDnaPoiDistanceService::CATEGORIES['gym'];
        $fitness = LocationDnaPoiDistanceService::CATEGORIES['fitness_center'];

        $this->assertSame('gym', CorpusPoiCategoryMap::canonicalKeyForDescriptor($gym));
        $this->assertSame('fitness_center', CorpusPoiCategoryMap::canonicalKeyForDescriptor($fitness));

        $this->assertSame('gym', CorpusPoiCategoryMap::corpusCategoryForDescriptor($gym));
        $this->assertNull(CorpusPoiCategoryMap::corpusCategoryForDescriptor($fitness));
    }

    /** Same shape for park / waterfront_park, which share google_type 'park'. */
    public function test_waterfront_park_is_told_apart_from_park_by_its_keyword(): void
    {
        $park       = LocationDnaPoiDistanceService::CATEGORIES['park'];
        $waterfront = LocationDnaPoiDistanceService::CATEGORIES['waterfront_park'];

        $this->assertSame('park', CorpusPoiCategoryMap::canonicalKeyForDescriptor($park));
        $this->assertSame('waterfront_park', CorpusPoiCategoryMap::canonicalKeyForDescriptor($waterfront));

        // Neither is loaded.
        $this->assertNull(CorpusPoiCategoryMap::corpusCategoryForDescriptor($park));
        $this->assertNull(CorpusPoiCategoryMap::corpusCategoryForDescriptor($waterfront));
    }

    /** An unplaceable descriptor is answered with null, never with a guess. */
    public function test_an_unknown_descriptor_resolves_to_null(): void
    {
        $unknown = ['google_type' => 'submarine_dock', 'keyword' => null, 'label' => 'Nope'];

        $this->assertNull(CorpusPoiCategoryMap::canonicalKeyForDescriptor($unknown));
        $this->assertNull(CorpusPoiCategoryMap::corpusCategoryForDescriptor($unknown));
    }

    /** A null keyword and an empty-string keyword name the same category. */
    public function test_null_and_empty_keyword_are_the_same_descriptor(): void
    {
        $withNull  = ['google_type' => 'pharmacy', 'keyword' => null];
        $withEmpty = ['google_type' => 'pharmacy', 'keyword' => ''];
        $omitted   = ['google_type' => 'pharmacy'];

        $this->assertSame('pharmacy', CorpusPoiCategoryMap::canonicalKeyForDescriptor($withNull));
        $this->assertSame('pharmacy', CorpusPoiCategoryMap::canonicalKeyForDescriptor($withEmpty));
        $this->assertSame('pharmacy', CorpusPoiCategoryMap::canonicalKeyForDescriptor($omitted));
    }
}
