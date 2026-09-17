<?php

namespace Tests\Feature\LocationDna;

use App\Contracts\NearbyPoiFetcherInterface;
use App\Contracts\ProviderCategorySupport;
use App\Models\PropertyLocationDna;
use App\Models\PropertyLocationPoi;
use App\Services\LocationDna\LocationDnaPoiDistanceService;
use App\Services\LocationDna\LocationDnaSummaryService;
use App\Services\LocationDna\OvertureCorpusPoiAdapter;
use App\Services\LocationDna\Providers\CorpusPoiCategoryMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * `not_found` means ONE thing, and this file is what keeps it meaning that.
 *
 *   not_found   a provider that COVERS this category was asked, and there is no
 *               qualifying place near this property. A fact about the neighbourhood.
 *
 *   no row      the selected provider does not carry this category at all. A fact about
 *               our data coverage, and never a claim about this property.
 *
 * The two were indistinguishable: `fetchNearby()` returns a list, an empty list meant
 * "nothing nearby", and the Overture corpus — which holds seven of the nineteen
 * categories the pipeline asks for — returned an empty list for the other twelve. Every
 * Florida listing therefore carried rows reading "overture_corpus returned zero results
 * for this category" for beach, park, school, hospital, transit and marina. Nothing
 * rendered them, so no customer saw a false statement; but the rows were in the database
 * saying there is no park near this home, when the truth is that we hold no park data.
 *
 * @see \App\Contracts\ProviderCategorySupport
 */
class UnsupportedCategorySemanticsTest extends TestCase
{
    use RefreshDatabase;

    private const LISTING_TYPE = 'seller_agent';
    private const LISTING_ID   = 880001;

    /** 6817 Stones Throw Circle N, St. Petersburg FL 33710. */
    private const LAT = 27.788945;
    private const LNG = -82.735144;

    /** The twelve the corpus does not carry, plus the rating-derived one. */
    private const UNSUPPORTED = [
        'beach', 'beach_access', 'boat_ramp', 'dog_park', 'golf_course', 'hospital',
        'marina', 'park', 'school', 'transit_station', 'waterfront_park',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        PropertyLocationDna::create([
            'listing_type'   => self::LISTING_TYPE,
            'listing_id'     => self::LISTING_ID,
            'source_address' => '6817 STONES THROW CIRCLE N',
            'source_city'    => 'ST PETERSBURG',
            'source_state'   => 'FL',
            'source_zip'     => '33710',
            'geocoded_lat'   => self::LAT,
            'geocoded_lng'   => self::LNG,
            'geocode_status' => 'geocoded',
            'geocode_source' => 'saved_meta',
            'geocoded_at'    => now(),
        ]);

        // Corpus selected; Google switched off AND credential-less, so a fallback would
        // have nothing to send even if one existed.
        config([
            'location_providers.providers.overture_corpus.enabled' => true,
            'google_places.enabled'                                => false,
            'services.google.places_key'                           => null,
            'location_dna.poi.tile_precision'                      => null,
        ]);
    }

    // ── 1. unsupported categories write nothing ─────────────────────────────

    /** @test */
    public function unsupported_categories_do_not_create_not_found_rows(): void
    {
        $this->runPipeline();

        foreach (self::UNSUPPORTED as $category) {
            $this->assertSame(
                0,
                PropertyLocationPoi::where('listing_type', self::LISTING_TYPE)
                    ->where('poi_category', $category)->count(),
                "'{$category}' is not carried by this provider and must persist NO row — "
                . 'a not_found row there reads as a fact about the neighbourhood.'
            );
        }
    }

    /** The rating-derived category goes the same way: the corpus has no reviews at all. */
    public function test_top_rated_dining_writes_no_row_when_the_provider_has_no_ratings(): void
    {
        $this->runPipeline();

        $this->assertSame(
            0,
            PropertyLocationPoi::where('poi_category', 'top_rated_dining')->count(),
            'A provider with no rating signal cannot produce top-rated dining at all.'
        );
    }

    /**
     * A CATEGORY_GROUPS SECONDARY is derived from its primary's candidates and must keep
     * working, even though the provider has no category of its own by that name.
     *
     * `fitness_center` is exactly that case and it is easy to get wrong: the corpus
     * crosswalk folds Overture's `fitness_center` token INTO canonical `gym`, so
     * `supportsCategory()` correctly answers false for the fitness_center descriptor —
     * there is no such corpus category. But the rows are derived from the ten gym
     * candidates already fetched, not from a second query, so asking the coverage question
     * there deleted ten legitimate rows. The first cut of this change did precisely that.
     */
    public function test_a_grouped_secondary_still_persists_rows_from_its_primary(): void
    {
        $this->runPipeline($this->groupedFetcher());

        $this->assertGreaterThan(
            0,
            PropertyLocationPoi::where('poi_category', 'gym')->where('status', 'found')->count(),
            'fixture sanity: the primary category must have candidates'
        );
        $this->assertGreaterThan(
            0,
            PropertyLocationPoi::where('poi_category', 'fitness_center')->where('status', 'found')->count(),
            'A grouped secondary derives from its primary and must not be treated as unsupported.'
        );
    }

    // ── 2. a SUPPORTED category with genuinely nothing nearby IS not_found ──

    /**
     * The other half, and the one that proves this change narrowed the meaning of
     * `not_found` rather than abolishing it. The provider covers grocery_store; it simply
     * returns nothing for this coordinate. That IS a fact about the neighbourhood and must
     * still be recorded.
     */
    public function test_a_supported_category_returning_zero_results_still_records_not_found(): void
    {
        $this->runPipeline($this->fetcherReturningNothing());

        $row = PropertyLocationPoi::where('listing_type', self::LISTING_TYPE)
            ->where('poi_category', 'grocery_store')
            ->first();

        $this->assertNotNull($row, 'A supported category that found nothing must still persist a row.');
        $this->assertSame('not_found', $row->status);
        $this->assertStringContainsString('overture_corpus', (string) $row->error);
    }

    // ── 3. supported categories with results still persist found ────────────

    /** @test */
    public function supported_categories_with_results_still_persist_found_rows(): void
    {
        $this->runPipeline();

        $found = PropertyLocationPoi::where('listing_type', self::LISTING_TYPE)
            ->where('poi_category', 'grocery_store')->where('status', 'found')->get();

        $this->assertNotEmpty($found);
        $this->assertSame('Publix', $found->sortBy('distance_miles')->first()->poi_name);
    }

    // ── 4/5. the UI ─────────────────────────────────────────────────────────

    /** @test */
    public function unsupported_categories_do_not_render_and_empty_sections_show_no_heading(): void
    {
        $this->runPipeline();

        $summary = app(LocationDnaSummaryService::class)
            ->summarizeForListing(self::LISTING_TYPE, self::LISTING_ID);
        $pois = $this->pois();

        $panel = Blade::render("@include('partials.location-dna-agent-panel')", [
            'locationDna'            => PropertyLocationDna::firstOrFail(),
            'locationPois'           => $pois,
            'canGenerateLocationDna' => false,
            'listingType'            => self::LISTING_TYPE,
            'listingId'              => self::LISTING_ID,
        ]);
        $stellar = Blade::render(
            '<x-stellar.matchmaker-nearby :location-summary="$s" :location-pois="$p" />',
            ['s' => $summary, 'p' => $pois]
        );

        // Supported results are still published.
        $this->assertStringContainsString('Publix', $panel);
        $this->assertStringContainsString('Publix', $stellar);

        // Unsupported categories appear nowhere, under any spelling.
        foreach (['Beach', 'Marina', 'Boat Ramp', 'Dog Park', 'Golf Course', 'Top Dining'] as $label) {
            $this->assertStringNotContainsString($label, $stellar, "'{$label}' must not render.");
        }

        // And no thematic heading is drawn over an empty section.
        foreach (['Coastal', 'Parks &amp; Recreation', 'Education'] as $heading) {
            $this->assertStringNotContainsString($heading, $stellar, "Empty section '{$heading}' must draw no heading.");
        }
    }

    // ── 6. provenance is still Overture for the real rows ───────────────────

    /** @test */
    public function real_overture_rows_keep_overture_provenance_and_attribution(): void
    {
        $this->runPipeline();

        $rows = PropertyLocationPoi::all();
        $this->assertNotEmpty($rows);

        foreach ($rows as $row) {
            $this->assertSame('overture_corpus', $row->data_source);
            $this->assertSame('overture_corpus', $row->provenance_json['provider'] ?? null);
            $this->assertSame('corpus', $row->provenance_json['method'] ?? null);
            $this->assertSame(
                'cdla-permissive-2.0+apache-2.0+cc0-1.0',
                $row->provenance_json['license'] ?? null
            );
        }

        $attribution = Blade::render(
            "@include('partials.location-dna._data-attribution', ['pois' => \$p])",
            ['p' => $this->pois()]
        );
        $this->assertStringContainsString('Overture', $attribution);
        $this->assertStringNotContainsStringIgnoringCase('google', $attribution);
    }

    // ── 7/8. Google is neither called nor substituted ───────────────────────

    /**
     * The failure mode this whole design exists to prevent: an unsupported category
     * quietly falling through to the billable provider. Google is switched off and has no
     * credential, and TestCase binds a Guzzle client that throws on any outbound request —
     * so a fallback would be a hard error, not a silent cost.
     */
    public function test_no_google_request_is_made_and_google_is_not_a_fallback(): void
    {
        $this->runPipeline();

        $this->assertSame(
            0,
            PropertyLocationPoi::where('data_source', 'google_places')->count(),
            'No row may be attributed to Google on a corpus run.'
        );
        $this->assertSame(
            0,
            PropertyLocationPoi::whereIn('poi_category', self::UNSUPPORTED)->count(),
            'An unsupported category must resolve to nothing, never to a second provider.'
        );
    }

    // ── 9. run status and run metadata ──────────────────────────────────────

    /**
     * A run whose provider covers seven of nineteen categories has done its whole job.
     * Skipping the other twelve is intentional, not a partial failure.
     */
    public function test_the_run_succeeds_and_names_the_skipped_categories_in_run_metadata(): void
    {
        $service = new LocationDnaPoiDistanceService(nearbyFetcher: $this->corpusShapedFetcher());
        $result  = $service->calculateForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertTrue($result['success']);
        $this->assertSame('completed', $result['status']);
        $this->assertNull($result['error']);

        // The distinction survives the run without a new status value and without a
        // migration: the rows are absent, and the run says which categories and why.
        $skipped = $service->getLastRunStats()['categories_unsupported_by_provider'] ?? null;
        $this->assertIsArray($skipped);
        foreach (self::UNSUPPORTED as $category) {
            $this->assertContains($category, $skipped, "run metadata must name '{$category}' as unsupported");
        }
        $this->assertNotContains('grocery_store', $skipped, 'a supported category is never reported as unsupported');
    }

    /** A re-run heals rows an older build wrote, without a data-fix command. */
    public function test_a_stale_not_found_row_from_an_earlier_build_is_removed_on_the_next_run(): void
    {
        PropertyLocationPoi::create([
            'listing_type' => self::LISTING_TYPE,
            'listing_id'   => self::LISTING_ID,
            'poi_category' => 'beach',
            'rank'         => 1,
            'status'       => 'not_found',
            'error'        => 'overture_corpus returned zero results for this category',
            'data_source'  => 'overture_corpus',
            'calculated_at'=> now(),
        ]);

        $this->runPipeline();

        $this->assertSame(
            0,
            PropertyLocationPoi::where('poi_category', 'beach')->count(),
            'The stale row must be cleared by the run that now knows better.'
        );
    }

    // ── the real adapter answers the question correctly ─────────────────────

    /** Structural: the shipped adapter opts in, and its answer is the corpus taxonomy. */
    public function test_the_corpus_adapter_reports_its_real_coverage(): void
    {
        $adapter = new OvertureCorpusPoiAdapter();
        $this->assertInstanceOf(ProviderCategorySupport::class, $adapter);

        foreach (LocationDnaPoiDistanceService::CATEGORIES as $key => $meta) {
            $this->assertSame(
                CorpusPoiCategoryMap::corpusCategoryForDescriptor($meta) !== null,
                $adapter->supportsCategory($meta),
                "support for '{$key}' must be the corpus taxonomy's answer, not a second list"
            );
        }
    }

    /** A fetcher that does not opt in is treated as supporting everything — unchanged. */
    public function test_a_fetcher_without_the_interface_is_unaffected(): void
    {
        $this->runPipeline($this->fetcherReturningNothing(withInterface: false));

        $this->assertGreaterThan(
            0,
            PropertyLocationPoi::where('poi_category', 'beach')->count(),
            'A provider that never opted in must behave exactly as before: not_found as usual.'
        );
    }

    // ── fixtures ────────────────────────────────────────────────────────────

    private function pois()
    {
        return PropertyLocationPoi::where('listing_type', self::LISTING_TYPE)
            ->where('listing_id', self::LISTING_ID)
            ->orderBy('poi_category')->orderBy('rank')->get();
    }

    private function runPipeline(?NearbyPoiFetcherInterface $fetcher = null): void
    {
        (new LocationDnaPoiDistanceService(nearbyFetcher: $fetcher ?? $this->corpusShapedFetcher()))
            ->calculateForListing(self::LISTING_TYPE, self::LISTING_ID);
    }

    /**
     * Emits what `OvertureCorpusPoiAdapter` emits — including its real coverage answer,
     * so the seven supported categories behave and the rest are declined before any fetch.
     */
    private function corpusShapedFetcher(): NearbyPoiFetcherInterface
    {
        return new class implements NearbyPoiFetcherInterface, ProviderCategorySupport {
            public function supportsCategory(array $meta): bool
            {
                return CorpusPoiCategoryMap::corpusCategoryForDescriptor($meta) !== null;
            }

            public function fetchNearby(float $lat, float $lng, array $meta): array
            {
                if (($meta['google_type'] ?? null) !== 'grocery_or_supermarket') {
                    return [];
                }

                return [[
                    'name'     => 'Publix',
                    'geometry' => ['location' => ['lat' => 27.7930, 'lng' => -82.7401]],
                    'types'    => ['grocery_or_supermarket'],
                    'vicinity' => 'Publix',
                    'place_id' => 'overture:gers:publix-1',
                    '_corpus_confidence' => 0.99,
                ]];
            }
        };
    }

    /**
     * Mirrors the real corpus for the grouped pair: `gym` is supported and returns
     * candidates; `fitness_center` is not a corpus category of its own.
     */
    private function groupedFetcher(): NearbyPoiFetcherInterface
    {
        return new class implements NearbyPoiFetcherInterface, ProviderCategorySupport {
            public function supportsCategory(array $meta): bool
            {
                return CorpusPoiCategoryMap::corpusCategoryForDescriptor($meta) !== null;
            }

            public function fetchNearby(float $lat, float $lng, array $meta): array
            {
                if (($meta['google_type'] ?? null) !== 'gym' || ($meta['keyword'] ?? null) !== null) {
                    return [];
                }

                return [[
                    'name'     => 'LA Fitness',
                    'geometry' => ['location' => ['lat' => 27.7905, 'lng' => -82.7360]],
                    'types'    => ['gym'],
                    'vicinity' => 'LA Fitness',
                    'place_id' => 'overture:gers:gym-1',
                    '_corpus_confidence' => 0.94,
                ]];
            }
        };
    }

    /** Covers every category but finds nothing — the genuine not_found case. */
    private function fetcherReturningNothing(bool $withInterface = true): NearbyPoiFetcherInterface
    {
        return $withInterface
            ? new class implements NearbyPoiFetcherInterface, ProviderCategorySupport {
                public function supportsCategory(array $meta): bool { return true; }
                public function fetchNearby(float $lat, float $lng, array $meta): array { return []; }
            }
            : new class implements NearbyPoiFetcherInterface {
                public function fetchNearby(float $lat, float $lng, array $meta): array { return []; }
            };
    }
}
