<?php

namespace Tests\Feature\LocationDna;

use App\Contracts\NearbyPoiFetcherInterface;
use App\Models\PropertyLocationDna;
use App\Models\PropertyLocationPoi;
use App\Services\LocationDna\LocationDnaPoiDistanceService;
use App\Services\LocationDna\LocationDnaSummaryService;
use App\Services\LocationDna\OvertureCorpusPoiAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * End-to-end downstream compatibility, with the corpus adapter as the source.
 *
 * THE QUESTION THIS ANSWERS
 * -------------------------
 * The adapter's own tests prove it returns the right SHAPE. That is not the same as
 * proving the existing pipeline can consume it. The rows it emits have to survive
 * exclusion filtering, ranking, persistence into `property_location_pois`, and
 * summarisation into `property_location_dna.summary_json` — four stages written against
 * Google's payload — and come out the other side as the same summary keys the listing UI
 * and the matching consumers already read.
 *
 * So this test drives the REAL `LocationDnaPoiDistanceService` and the REAL
 * `LocationDnaSummaryService` with a REAL `OvertureCorpusPoiAdapter`, whose only
 * substitution is the single method that talks to a database. Nothing about the output
 * schema is asserted from the adapter's side; every assertion reads what the pipeline
 * actually wrote.
 *
 * The named keys below are exactly the ones `x-stellar.matchmaker-nearby` renders and
 * `LocationDnaLifestyleScoreService` reads. If corpus-backed candidates could not produce
 * them, the feature would be shipping a data source the product cannot display.
 *
 * NOTHING IS WRITTEN TO PRODUCTION: `RefreshDatabase` on the suite's in-memory SQLite,
 * and the spatial cluster is never contacted.
 */
class CorpusPoiDownstreamCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    private const LISTING_TYPE = 'seller_agent';
    private const LISTING_ID   = 9001;

    /** 6817 Stones Throw Circle N, St. Petersburg FL 33710 — Pinellas County. */
    private const LAT = 27.788945;
    private const LNG = -82.735144;

    /**
     * One plausible nearest place per corpus-supported category, at the distances the
     * live corpus actually returns for this address.
     *
     * These are FIXTURE VALUES, not production logic: the adapter has no knowledge of any
     * business name, and nothing in `app/` contains them. They are here so the assertions
     * below are legible.
     */
    private const NEAREST = [
        'grocery_store'   => ['Publix',                      27.79300, -82.74010,  627.9],
        'pharmacy'        => ['Walgreens Pharmacy',          27.80200, -82.72100, 1979.5],
        'restaurant'      => ["Chili's Grill & Bar",         27.79400, -82.73000,  659.7],
        'coffee_shop'     => ['Starbucks',                   27.79150, -82.73800,  515.0],
        'gym'             => ['LA Fitness',                  27.79020, -82.73400,  257.5],
        'gas_station'     => ['Speedway',                    27.79600, -82.74200, 1367.9],
        'shopping_center' => ['Marketplace Shopping Center', 27.79000, -82.73700,  241.4],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'overture_corpus_poi.enabled'              => true,
            'overture_corpus_poi.corpus_version'       => 'overture-2026-06-17.0-fl',
            'overture_corpus_poi.regions'              => ['US-FL'],
            'overture_corpus_poi.region_bounds'        => [
                'US-FL' => ['west' => -87.63, 'south' => 24.40, 'east' => -79.97, 'north' => 31.00],
            ],
            'overture_corpus_poi.max_results'          => 20,
            'overture_corpus_poi.default_radius_miles' => 25,
            'overture_corpus_poi.max_radius_miles'     => 25,
            'overture_corpus_poi.overfetch_floor'      => 20,
            'overture_corpus_poi.overfetch_factor'     => 2,

            // The corpus is the provider of record for this run, so the Google whole-run
            // guards do not apply and provenance is stamped from the registry.
            'location_providers.providers.overture_corpus.enabled' => true,
            'google_places.enabled'                                => false,
            'services.google.places_key'                           => null,
        ]);

        PropertyLocationDna::create([
            'listing_type'   => self::LISTING_TYPE,
            'listing_id'     => self::LISTING_ID,
            'source_address' => '6817 STONES THROW CIRCLE N UNIT 17208',
            'source_city'    => 'ST PETERSBURG',
            'source_county'  => 'Pinellas',
            'source_state'   => 'FL',
            'source_zip'     => '33710',
            'geocoded_lat'   => self::LAT,
            'geocoded_lng'   => self::LNG,
            'geocode_status' => 'geocoded',
            'geocode_source' => 'saved_meta',
            'geocoded_at'    => now(),
        ]);
    }

    // ── persistence ─────────────────────────────────────────────────────────

    public function test_corpus_candidates_persist_as_property_location_poi_rows(): void
    {
        $this->runPipeline();

        foreach (array_keys(self::NEAREST) as $category) {
            $row = PropertyLocationPoi::where('listing_type', self::LISTING_TYPE)
                ->where('listing_id', self::LISTING_ID)
                ->where('poi_category', $category)
                ->where('rank', 1)
                ->first();

            $this->assertNotNull($row, "no rank-1 row persisted for '{$category}'");
            $this->assertSame('found', $row->status, "'{$category}' should be found");
            $this->assertSame(self::NEAREST[$category][0], $row->poi_name);
            $this->assertNotNull($row->distance_miles, "'{$category}' must carry a distance");
        }
    }

    /** Provenance names the corpus and its licence — never Google. */
    public function test_persisted_rows_carry_corpus_provenance(): void
    {
        $this->runPipeline();

        $row = PropertyLocationPoi::where('poi_category', 'grocery_store')->where('rank', 1)->firstOrFail();

        $this->assertSame('overture_corpus', $row->provenance_json['provider'] ?? null);
        $this->assertSame(
            'cdla-permissive-2.0+apache-2.0+cc0-1.0',
            $row->provenance_json['license'] ?? null,
            'Overture Places is NOT ODbL — see CorpusPoiLicenseProvenanceTest.'
        );
        $this->assertSame(
            'overture:gers:grocery_store',
            $row->provenance_json['raw_ref'] ?? null,
            'The corpus source_ref should ride through as the opaque raw_ref, as a place_id would.'
        );
    }

    /**
     * The uningested categories persist `not_found`, not a substitute and not an error.
     * This is the claim the whole feature rests on being honest about.
     */
    public function test_uningested_categories_persist_not_found_rather_than_a_substitute(): void
    {
        $this->runPipeline();

        foreach (['beach', 'school', 'park', 'hospital', 'transit_station'] as $category) {
            $row = PropertyLocationPoi::where('poi_category', $category)->where('rank', 1)->first();

            $this->assertNotNull($row, "'{$category}' should still record an outcome");
            $this->assertSame(
                'not_found',
                $row->status,
                "'{$category}' has no corpus rows; it must record not_found, never a substitute or an error."
            );
            $this->assertNull($row->poi_name);
        }
    }

    // ── the summary contract (§17) ──────────────────────────────────────────

    /**
     * The named keys the UI reads. Each must be a real number produced from a
     * corpus-backed candidate — the whole point of the phase.
     */
    public function test_the_summary_keys_the_ui_reads_are_produced_from_corpus_candidates(): void
    {
        $this->runPipeline();

        $summary = $this->summarise();

        $expected = [
            'daily_convenience.nearest_grocery_miles',
            'daily_convenience.nearest_pharmacy_miles',
            'daily_convenience.nearest_coffee_miles',
            'daily_convenience.nearest_restaurant_miles',
            'health_and_fitness.nearest_gym_miles',
            'transportation.nearest_gas_station_miles',
            'shopping.nearest_shopping_center_miles',
        ];

        foreach ($expected as $path) {
            [$block, $key] = explode('.', $path);

            $this->assertArrayHasKey($block, $summary, "summary is missing the '{$block}' block");
            $this->assertArrayHasKey($key, $summary[$block], "'{$block}' is missing '{$key}'");
            $this->assertIsFloat($summary[$block][$key], "{$path} must be a distance, not null");
            $this->assertGreaterThan(0.0, $summary[$block][$key], "{$path} must be a positive distance");
        }
    }

    /** `nearest_by_category` carries the names the cards render. */
    public function test_nearest_by_category_names_the_corpus_places(): void
    {
        $this->runPipeline();

        $byCategory = $this->summarise()['nearest_by_category'];

        $this->assertSame('Publix', $byCategory['grocery_store']['name']);
        $this->assertSame('LA Fitness', $byCategory['gym']['name']);
        $this->assertSame('Marketplace Shopping Center', $byCategory['shopping_center']['name']);
    }

    /**
     * The output SCHEMA is unchanged. Not "contains what we expected" — the block set and
     * the top-level key set are exactly what `LocationDnaSummaryService` has always
     * emitted, so no consumer sees a new or missing key because the provider changed.
     */
    public function test_the_summary_schema_is_unchanged_by_the_provider_swap(): void
    {
        $this->runPipeline();

        $this->assertSame([
            'geocode',
            'nearest_by_category',
            'category_counts',
            'coastal',
            'daily_convenience',
            'outdoor_recreation',
            'transportation',
            'education',
            'health_and_fitness',
            'shopping',
            'missing_categories',
            'error_categories',
        ], array_keys($this->summarise()));
    }

    /**
     * The uningested categories appear as nulls inside their blocks and are named in
     * `missing_categories` — present and empty, which is what lets the UI hide a section
     * rather than render a blank one.
     */
    public function test_uningested_categories_are_null_in_their_blocks_and_reported_missing(): void
    {
        $this->runPipeline();

        $summary = $this->summarise();

        $this->assertNull($summary['coastal']['nearest_beach_miles']);
        $this->assertNull($summary['education']['nearest_school_miles']);
        $this->assertNull($summary['outdoor_recreation']['nearest_park_miles']);
        $this->assertNull($summary['health_and_fitness']['nearest_hospital_miles']);
        $this->assertNull($summary['transportation']['nearest_transit_miles']);

        foreach (['beach', 'school', 'park', 'hospital', 'transit_station'] as $category) {
            $this->assertContains($category, $summary['missing_categories']);
        }

        $this->assertSame(
            [],
            $summary['error_categories'],
            'An uningested category is missing, never an error — the corpus answered correctly.'
        );
    }

    /** The summary is persisted and stamped, exactly as it is for any provider. */
    public function test_the_summary_is_persisted_onto_the_location_dna_record(): void
    {
        $this->runPipeline();
        $this->summarise();

        $record = PropertyLocationDna::where('listing_type', self::LISTING_TYPE)
            ->where('listing_id', self::LISTING_ID)
            ->firstOrFail();

        $this->assertNotNull($record->summary_json);
        $this->assertNotNull($record->generated_at);
        $this->assertSame('Publix', $record->summary_json['nearest_by_category']['grocery_store']['name']);
    }

    // ── driving the real pipeline ───────────────────────────────────────────

    private function runPipeline(): array
    {
        return (new LocationDnaPoiDistanceService(nearbyFetcher: $this->corpusFetcher()))
            ->calculateForListing(self::LISTING_TYPE, self::LISTING_ID);
    }

    private function summarise(): array
    {
        $result = (new LocationDnaSummaryService())->summarizeForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertSame('completed', $result['status'], 'summary did not complete: ' . json_encode($result));

        return $result['summary'];
    }

    /**
     * A real `OvertureCorpusPoiAdapter` with only its database read replaced.
     *
     * Everything the pipeline exercises — descriptor recovery, the supported-category
     * gate, the region gate, row mapping, the type token, the exception contract — is the
     * production implementation. Only the rows come from a fixture.
     */
    private function corpusFetcher(): NearbyPoiFetcherInterface
    {
        return new class extends OvertureCorpusPoiAdapter {
            public function isAvailable(): bool
            {
                return true;
            }

            protected function selectRows(string $sql, array $bindings): array
            {
                // Binding index 2 is the corpus category_key — see buildKnnQuery().
                $category = $bindings[2] ?? null;
                $fixture  = CorpusPoiDownstreamCompatibilityTest::fixtureFor($category);

                if ($fixture === null) {
                    return [];
                }

                [$name, $lat, $lng, $meters] = $fixture;

                return [(object) [
                    'name'       => $name,
                    'brand'      => $name,
                    'confidence' => 0.98,
                    'source_ref' => 'overture:gers:' . $category,
                    'last_seen'  => '2026-06-17 00:00:00',
                    'attrs'      => null,
                    'poi_lat'    => $lat,
                    'poi_lng'    => $lng,
                    'meters'     => $meters,
                ]];
            }
        };
    }

    /** @return array{0:string,1:float,2:float,3:float}|null */
    public static function fixtureFor(?string $category): ?array
    {
        return self::NEAREST[$category] ?? null;
    }
}
