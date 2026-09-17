<?php

namespace Tests\Feature\LocationDna;

use App\Contracts\NearbyPoiFetcherInterface;
use App\Models\PropertyLocationDna;
use App\Models\PropertyLocationPoi;
use App\Services\LocationDna\LocationDnaPoiDistanceService;
use App\Services\LocationDna\LocationDnaSummaryService;
use App\Services\LocationDna\Providers\CanonicalField;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * The corpus round trip, end to end, in CI: provider selection → fetch → persistence →
 * summary → the two Blade surfaces that publish a place name.
 *
 * WHY THE FETCHER IS A FIXTURE HERE AND NOT THE REAL CORPUS
 * --------------------------------------------------------
 * `tests/bootstrap.php` blanks every SPATIAL_* variable on purpose, so the PostGIS cluster
 * is unreachable from the suite by design and `OvertureCorpusPoiAdapter`'s KNN — which is
 * PostGIS SQL (`ST_Distance`, `<->`) and cannot run on the SQLite test database — has no
 * home in CI. Punching a hole in that guard to reach a live cluster from the suite would
 * trade a real safety property for a test.
 *
 * So this file fixtures the ONE seam the cluster sits behind, `NearbyPoiFetcherInterface`,
 * and emits rows in the exact shape `OvertureCorpusPoiAdapter::fetchNearby()` returns —
 * including `_corpus_confidence`, which is the corpus's own stated existence confidence and
 * the reason a corpus row must not be scored as "unrated". Everything downstream of that
 * seam is the real production code. The real-cluster half of the proof is a separate,
 * deliberately non-CI validation run against `overture-2026-06-17.0-fl`.
 *
 * @see \Tests\Unit\Services\LocationDna\OvertureCorpusPoiAdapterTest the SQL-shaping half
 * @see \Tests\Feature\LocationDna\PoiRunProviderGuardTest              the refusal postures
 */
class CorpusPoiEndToEndRenderTest extends TestCase
{
    use RefreshDatabase;

    private const LISTING_TYPE = 'seller_agent';
    private const LISTING_ID   = 90210;

    /** 6817 Stones Throw Circle N, St. Petersburg FL 33710 — a real Pinellas coordinate. */
    private const LAT = 27.788945;
    private const LNG = -82.735144;

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

        // Open the ROUTING gate only. The adapter's own gate stays shut, which is correct
        // here: a fetcher is injected, so the adapter is never constructed.
        config([
            'location_providers.providers.overture_corpus.enabled' => true,
            'google_places.enabled'                                => false,
            'services.google.places_key'                           => null,
            'location_dna.poi.tile_precision'                      => null,
        ]);
    }

    // ── the round trip ──────────────────────────────────────────────────────

    /** Persisted rows carry the corpus identity, not Google's, and miles as the unit. */
    public function test_corpus_candidates_persist_with_corpus_identity_and_miles(): void
    {
        $this->runPipeline();

        $grocery = PropertyLocationPoi::where('listing_type', self::LISTING_TYPE)
            ->where('listing_id', self::LISTING_ID)
            ->where('poi_category', 'grocery_store')
            ->where('status', 'found')
            ->orderBy('rank')
            ->get();

        $this->assertNotEmpty($grocery, 'The corpus candidates should have persisted.');

        $publix = $grocery->firstWhere('poi_name', 'Publix');
        $this->assertNotNull($publix, 'The nearest corpus grocery store should be persisted by name.');

        // Association
        $this->assertSame(self::LISTING_TYPE, $publix->listing_type);
        $this->assertSame(self::LISTING_ID, (int) $publix->listing_id);

        // Distance, in MILES, measured from the listing's own coordinate.
        $this->assertEqualsWithDelta(self::LAT, (float) $publix->source_lat, 1e-6);
        $this->assertEqualsWithDelta(self::LNG, (float) $publix->source_lng, 1e-6);
        $this->assertGreaterThan(0.0, (float) $publix->distance_miles);
        $this->assertLessThan(1.0, (float) $publix->distance_miles);

        // No travel time is invented anywhere on this path.
        $this->assertNull($publix->travel_time_minutes);

        // Provider + provenance, one identity, no contradiction.
        $this->assertSame('overture_corpus', $publix->data_source);
        $this->assertSame('overture_corpus', $publix->provenance_json['provider']);
        $this->assertSame($publix->data_source, $publix->provenance_json['provider']);
        $this->assertSame('cdla-permissive-2.0+apache-2.0+cc0-1.0', $publix->provenance_json['license']);
        $this->assertSame(['overture_corpus'], $publix->provenance_json['contributors']);

        // A corpus read issued no request; recording it as an API call would be false.
        $this->assertSame(CanonicalField::METHOD_CORPUS, $publix->provenance_json['method']);

        // The opaque upstream reference — the Overture GERS id — and never Place content.
        $this->assertSame('overture:gers:publix-1', $publix->provenance_json['raw_ref']);

        // The corpus states its own existence confidence and it is believed. 0.5 here would
        // mean the rating-derived scorer had overwritten a real measurement with the
        // "unrated, no quality signal" constant.
        $this->assertEqualsWithDelta(0.99, (float) $publix->confidence, 1e-6);

        // Corpus/version stamps.
        $this->assertNotNull($publix->pois_fetch_version);
        $this->assertNotNull($publix->pois_scoring_version);
    }

    /**
     * A category the corpus holds no rows for records `not_found` — the honest per-category
     * answer — and the message names the provider that was actually asked.
     */
    public function test_an_unserved_category_names_the_provider_that_was_asked(): void
    {
        $this->runPipeline();

        $school = PropertyLocationPoi::where('listing_type', self::LISTING_TYPE)
            ->where('poi_category', 'school')
            ->firstOrFail();

        $this->assertSame('not_found', $school->status);
        $this->assertStringContainsString('overture_corpus', (string) $school->error);
        $this->assertStringNotContainsStringIgnoringCase(
            'google',
            (string) $school->error,
            'A run that never constructed a Google adapter must not blame Google for an empty category.'
        );
    }

    // ── the UI ──────────────────────────────────────────────────────────────

    /**
     * The shared agent panel — the seller, landlord and Hire Agent surface — renders the
     * persisted corpus places with miles, and carries the Overture notice.
     */
    public function test_the_agent_panel_renders_corpus_places_with_miles_and_attribution(): void
    {
        $this->runPipeline();

        $html = Blade::render(
            "@include('partials.location-dna-agent-panel')",
            [
                'locationDna'             => PropertyLocationDna::where('listing_id', self::LISTING_ID)->firstOrFail(),
                'locationPois'            => $this->persistedPois(),
                'canGenerateLocationDna'  => false,
                'listingType'             => self::LISTING_TYPE,
                'listingId'               => self::LISTING_ID,
            ]
        );

        $this->assertStringContainsString('Nearby Points of Interest', $html);
        $this->assertStringContainsString('Publix', $html);

        // MILES, to two decimals, computed by the production path from the persisted
        // coordinates. Note this is NOT the adapter's `_corpus_distance_miles`: the
        // persistence layer re-derives the stored distance with its own Haversine so that
        // one column has one definition across every provider. Both are straight-line
        // measurements and they agree to well under a hundredth of a mile.
        $this->assertStringContainsString('0.41 mi', $html);
        $this->assertStringContainsString('1.23 mi', $html);

        // The empty state must be gone — that is the regression this whole phase fixes.
        $this->assertStringNotContainsString('No nearby POIs recorded for this listing', $html);

        // Attribution travels with the place names. CDLA-Permissive-2.0 and Apache-2.0
        // both require it, and it is resolved from each row's own provenance.
        $this->assertStringContainsString('Overture Maps Foundation', $html);
        $this->assertStringContainsString('Data sources', $html);
    }

    /** The Stellar detail surface renders from the summary, and carries the notice too. */
    public function test_the_stellar_nearby_component_renders_corpus_places_and_attribution(): void
    {
        $this->runPipeline();

        $summary = app(LocationDnaSummaryService::class)
            ->summarizeForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertSame('completed', $summary['status'], 'The summary step must complete on corpus rows.');

        $html = Blade::render(
            '<x-stellar.matchmaker-nearby :location-summary="$s" :location-pois="$p" />',
            ['s' => $summary, 'p' => $this->persistedPois()]
        );

        $this->assertStringContainsString('Nearby Amenities', $html);
        $this->assertStringContainsString('Publix', $html);
        $this->assertStringContainsString('Overture Maps Foundation', $html);
        $this->assertStringNotContainsString('Location analysis not yet available', $html);
    }

    /**
     * NO ORPHAN SECTION HEADINGS when a provider answers some categories and not others.
     *
     * The corpus serves seven of the nineteen categories the pipeline asks for, so this is
     * the NORMAL state, not an edge case. The component's section filter counted array
     * KEYS, and a thematic block whose distances are all null still has its keys — so
     * "Coastal", "Parks & Recreation" and "Education" rendered as bare headings with
     * nothing beneath them. Invisible until now only because no listing had POI rows at
     * all and the whole component fell to its "not yet available" branch.
     */
    public function test_a_section_with_no_measured_distance_renders_no_heading(): void
    {
        $this->runPipeline();

        $summary = app(LocationDnaSummaryService::class)
            ->summarizeForListing(self::LISTING_TYPE, self::LISTING_ID);

        $html = Blade::render(
            '<x-stellar.matchmaker-nearby :location-summary="$s" :location-pois="$p" />',
            ['s' => $summary, 'p' => $this->persistedPois()]
        );

        // The fixture serves grocery only, so Daily Convenience is the one section with a
        // measured distance. Every other thematic heading must be absent entirely.
        $this->assertStringContainsString('Daily Convenience', $html);

        foreach (['Coastal', 'Parks &amp; Recreation', 'Education', 'Transportation', 'Shopping'] as $emptySection) {
            $this->assertStringNotContainsString(
                $emptySection,
                $html,
                "'{$emptySection}' has no measured distance and must not render as a bare heading."
            );
        }
    }

    /** Nothing on any of these surfaces credits Google for corpus data. */
    public function test_no_surface_attributes_corpus_places_to_google(): void
    {
        $this->runPipeline();

        $html = Blade::render(
            "@include('partials.location-dna._data-attribution', ['pois' => \$p])",
            ['p' => $this->persistedPois()]
        );

        $this->assertStringContainsString('Overture', $html);
        $this->assertStringNotContainsStringIgnoringCase('google', $html);
    }

    // ── fixtures ────────────────────────────────────────────────────────────

    private function persistedPois()
    {
        return PropertyLocationPoi::where('listing_type', self::LISTING_TYPE)
            ->where('listing_id', self::LISTING_ID)
            ->orderBy('poi_category')
            ->orderBy('rank')
            ->get();
    }

    private function runPipeline(): void
    {
        (new LocationDnaPoiDistanceService(nearbyFetcher: $this->corpusShapedFetcher()))
            ->calculateForListing(self::LISTING_TYPE, self::LISTING_ID);
    }

    /**
     * Emits exactly what `OvertureCorpusPoiAdapter::fetchNearby()` emits, for the one
     * category the fixture serves, and `[]` for every other — which is what the adapter
     * does for a category `CorpusPoiCategoryMap` cannot place.
     *
     * `_corpus_distance_miles` is carried because the adapter carries it, but note that
     * the persistence layer does NOT store it — it re-derives the distance from the
     * coordinates with its own Haversine, so `property_location_pois.distance_miles` has a
     * single definition across every provider. The asserted `0.41 mi` is therefore what the
     * production code computes from the coordinates below, not a number copied from a
     * fixture. Real corpus distances are proved by the live validation run instead.
     */
    private function corpusShapedFetcher(): NearbyPoiFetcherInterface
    {
        return new class implements NearbyPoiFetcherInterface {
            public function fetchNearby(float $lat, float $lng, array $meta): array
            {
                if (($meta['google_type'] ?? null) !== 'grocery_or_supermarket') {
                    return [];
                }

                return [
                    [
                        'name'     => 'Publix',
                        'geometry' => ['location' => ['lat' => 27.7930, 'lng' => -82.7401]],
                        'types'    => ['grocery_or_supermarket'],
                        'vicinity' => 'Publix',
                        'place_id' => 'overture:gers:publix-1',
                        '_corpus_confidence'     => 0.99,
                        '_corpus_distance_miles' => 0.413,
                        '_corpus_last_seen'      => null,
                    ],
                    [
                        'name'     => 'Save A Lot',
                        'geometry' => ['location' => ['lat' => 27.8010, 'lng' => -82.7500]],
                        'types'    => ['grocery_or_supermarket'],
                        'vicinity' => 'Save A Lot',
                        'place_id' => 'overture:gers:savealot-1',
                        '_corpus_confidence'     => 0.92,
                        '_corpus_distance_miles' => 1.234,
                        '_corpus_last_seen'      => null,
                    ],
                ];
            }
        };
    }
}
