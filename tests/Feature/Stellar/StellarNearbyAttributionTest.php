<?php

namespace Tests\Feature\Stellar;

use App\Models\BridgeProperty;
use App\Models\PropertyLocationDna;
use App\Models\PropertyLocationPoi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Attribution on the Stellar nearby-places surface.
 *
 * THE GAP THIS CLOSES. `/stellar/property/{listingKey}` is the only page that
 * displays a Bridge listing's points of interest, and it renders them through
 * `<x-stellar.matchmaker-nearby>` — which had no attribution. The Location DNA
 * attribution partial was wired only into the seller/landlord panel. So enabling
 * the Overture corpus would have published Overture/Foursquare place names here
 * with no credit, breaching the two licences (CDLA-Permissive-2.0 and Apache-2.0)
 * that require attribution wherever the data appears.
 *
 * ATTRIBUTION FOLLOWS THE PERSISTED ROWS, NOT CONFIGURATION. Every test below
 * leaves the corpus provider DISABLED and varies only what is stored in
 * `property_location_pois.provenance_json`. That is the contract from the
 * prerequisites work, and it is the reason a row written under one provider still
 * carries its own obligation after the switch that fetched it is flipped.
 *
 * @see \App\Support\LocationDna\LocationDataAttribution
 * @see \Tests\Feature\LocationDna\LocationDnaAttributionSurfaceTest
 */
class StellarNearbyAttributionTest extends TestCase
{
    use RefreshDatabase;

    private const KEY       = 'ATTR-KEY-1';
    private const MLS       = 'ATTR-MLS-1';
    private const OTHER_KEY = 'ATTR-KEY-2';
    private const OTHER_MLS = 'ATTR-MLS-2';

    protected function setUp(): void
    {
        parent::setUp();

        // The provider stays OFF for every test in this file. Attribution must be
        // driven by stored provenance alone; if a test only passed with the corpus
        // enabled, the contract would be config-driven and wrong.
        config([
            'overture_corpus_poi.enabled'                          => false,
            'overture_corpus_poi.corpus_version'                   => null,
            'location_providers.providers.overture_corpus.enabled'  => false,
        ]);
    }

    // ── Fixtures ────────────────────────────────────────────────────────────

    private function seedListing(string $key = self::KEY, string $mls = self::MLS): BridgeProperty
    {
        return BridgeProperty::create([
            'listing_key'       => $key,
            'listing_id'        => $mls,
            'standard_status'   => 'Active',
            'property_type'     => 'Residential Lease',
            'unparsed_address'  => '4200 1ST STREET UNIT 2',
            'city'              => 'ST PETERSBURG',
            'state_or_province' => 'FL',
            'postal_code'       => '33703',
            'list_price'        => 2400,
            'raw_json'          => json_encode([
                'ListingKey'                     => $key,
                'ListingId'                      => $mls,
                'StandardStatus'                 => 'Active',
                'PropertyType'                   => 'Residential Lease',
                'UnparsedAddress'                => '4200 1ST STREET UNIT 2',
                'City'                           => 'ST PETERSBURG',
                'StateOrProvince'                => 'FL',
                'PostalCode'                     => '33703',
                'IDXParticipationYN'             => true,
                'InternetEntireListingDisplayYN' => true,
                'InternetAddressDisplayYN'       => true,
            ]),
            'imported_at' => now(),
        ]);
    }

    private function seedDna(int $listingId): PropertyLocationDna
    {
        return PropertyLocationDna::create([
            'listing_type'   => 'bridge',
            'listing_id'     => $listingId,
            'source_address' => '4200 1ST STREET UNIT 2',
            'source_city'    => 'ST PETERSBURG',
            'source_state'   => 'FL',
            'geocoded_lat'   => 27.8106070,
            'geocoded_lng'   => -82.6347650,
            'geocode_source' => 'saved_meta',
            'geocode_status' => 'geocoded',
            'geocoded_at'    => now(),
        ]);
    }

    /**
     * One found POI row with an explicit provider recorded in provenance.
     *
     * `data_source` is deliberately set to the MISLEADING legacy literal in the
     * Overture cases, so a regression that resolved attribution from that column
     * instead of from provenance fails here rather than in production.
     */
    private function seedPoi(int $listingId, string $provider, string $category, string $name, float $miles): PropertyLocationPoi
    {
        return PropertyLocationPoi::create([
            'listing_type'    => 'bridge',
            'listing_id'      => $listingId,
            'poi_category'    => $category,
            'rank'            => 1,
            'poi_subtype'     => $category,
            'poi_name'        => $name,
            'poi_lat'         => 27.81,
            'poi_lng'         => -82.63,
            'source_lat'      => 27.8106070,
            'source_lng'      => -82.6347650,
            'distance_miles'  => $miles,
            'data_source'     => $provider === 'overture_corpus' ? 'google_places' : $provider,
            'provenance_json' => ['provider' => $provider, 'license' => 'x', 'contributors' => [$provider]],
            'status'          => 'found',
            'calculated_at'   => now(),
        ]);
    }

    private function viewPage(string $key = self::KEY)
    {
        return $this->actingAs(User::factory()->create())
            ->get(route('stellar.property.show', ['listingKey' => $key]));
    }

    // ── A. Overture-backed rows ─────────────────────────────────────────────

    /** @test */
    public function overture_backed_pois_render_the_attribution(): void
    {
        $listing = $this->seedListing();
        $this->seedDna($listing->id);
        $this->seedPoi($listing->id, 'overture_corpus', 'grocery_store', 'Whole Foods Market', 0.154);

        $response = $this->viewPage();

        $response->assertOk();
        $response->assertSee('Overture Maps Foundation', false);
        $response->assertSee('Foursquare', false);
    }

    /** @test */
    public function the_attribution_renders_exactly_once_however_many_categories_there_are(): void
    {
        // The obligation attaches to the surface, not to each row. Seven categories
        // must not produce seven credits.
        $listing = $this->seedListing();
        $this->seedDna($listing->id);

        foreach ([
            ['grocery_store', 'Whole Foods Market', 0.154],
            ['pharmacy', 'Walgreens Pharmacy', 0.279],
            ['coffee_shop', 'Starbucks Coffee', 0.260],
            ['restaurant', 'Root + Clay', 0.274],
            ['gym', 'Suncoast Fitness', 0.165],
            ['gas_station', 'Mobil', 0.133],
            ['shopping_center', 'Northeast Park Shopping Center', 0.351],
        ] as [$cat, $name, $mi]) {
            $this->seedPoi($listing->id, 'overture_corpus', $cat, $name, $mi);
        }

        $body = $this->viewPage()->getContent();

        $this->assertSame(
            1,
            substr_count($body, 'location-dna-attribution'),
            'The Location DNA attribution block rendered more than once on the page.'
        );
        $this->assertSame(1, substr_count($body, 'Overture Maps Foundation'));
    }

    /** @test */
    public function the_attribution_links_to_the_data_sources_surface(): void
    {
        $listing = $this->seedListing();
        $this->seedDna($listing->id);
        $this->seedPoi($listing->id, 'overture_corpus', 'grocery_store', 'Whole Foods Market', 0.154);

        $this->viewPage()->assertSee(route('data-sources'), false);
    }

    /** @test */
    public function places_is_never_presented_as_odbl(): void
    {
        // Four of Overture's six themes ARE ODbL; Places is not one of them, and
        // claiming otherwise asserts a share-alike obligation the licence does not create.
        $listing = $this->seedListing();
        $this->seedDna($listing->id);
        $this->seedPoi($listing->id, 'overture_corpus', 'grocery_store', 'Whole Foods Market', 0.154);

        $this->viewPage()->assertDontSee('ODbL', false);
    }

    // ── B. No rows ──────────────────────────────────────────────────────────

    /** @test */
    public function a_listing_with_no_poi_rows_renders_no_attribution(): void
    {
        $listing = $this->seedListing();
        $this->seedDna($listing->id);

        $response = $this->viewPage();

        $response->assertOk();
        $response->assertDontSee('Overture Maps Foundation', false);
        $this->assertStringNotContainsString('location-dna-attribution', $response->getContent());
    }

    /** @test */
    public function rows_without_recorded_provenance_render_no_attribution(): void
    {
        // A guessed credit is a false statement about someone else's data.
        $listing = $this->seedListing();
        $this->seedDna($listing->id);

        PropertyLocationPoi::create([
            'listing_type' => 'bridge', 'listing_id' => $listing->id,
            'poi_category' => 'grocery_store', 'rank' => 1, 'poi_subtype' => 'grocery_store',
            'poi_name' => 'Somewhere', 'distance_miles' => 0.2,
            'provenance_json' => null, 'status' => 'found', 'calculated_at' => now(),
        ]);

        $this->viewPage()->assertDontSee('Overture Maps Foundation', false);
    }

    // ── C. Non-Overture rows ────────────────────────────────────────────────

    /** @test */
    public function google_backed_rows_do_not_produce_a_false_overture_credit(): void
    {
        $listing = $this->seedListing();
        $this->seedDna($listing->id);
        $this->seedPoi($listing->id, 'google_places', 'grocery_store', 'Publix', 0.29);

        $response = $this->viewPage();

        $response->assertOk();
        $response->assertDontSee('Overture Maps Foundation', false);
        $response->assertSee('Some place information provided by Google.', false);
    }

    // ── D. Mixed providers ──────────────────────────────────────────────────

    /** @test */
    public function a_mixed_page_credits_every_provider_actually_persisted(): void
    {
        // The state an activation passes through: corpus rows written today beside
        // Google rows written before the switch.
        $listing = $this->seedListing();
        $this->seedDna($listing->id);
        $this->seedPoi($listing->id, 'overture_corpus', 'grocery_store', 'Whole Foods Market', 0.154);
        $this->seedPoi($listing->id, 'google_places', 'pharmacy', 'CVS Pharmacy', 0.36);

        $response = $this->viewPage();

        $response->assertSee('Overture Maps Foundation', false);
        $response->assertSee('Some place information provided by Google.', false);
        $this->assertSame(1, substr_count($response->getContent(), 'location-dna-attribution'));
    }

    // ── Scoping ─────────────────────────────────────────────────────────────

    /** @test */
    public function another_listings_poi_rows_cannot_reach_this_page(): void
    {
        // Proven through the controller, not by asserting on the query: seed a
        // SECOND listing with Overture rows and none on the first. If the page
        // credited Overture, the scoping is broken.
        $listing = $this->seedListing();
        $this->seedDna($listing->id);

        $other = $this->seedListing(self::OTHER_KEY, self::OTHER_MLS);
        $this->seedDna($other->id);
        $this->seedPoi($other->id, 'overture_corpus', 'grocery_store', 'Other Listing Grocery', 0.1);

        $response = $this->viewPage(self::KEY);

        $response->assertOk();
        $response->assertDontSee('Overture Maps Foundation', false);
        $response->assertDontSee('Other Listing Grocery', false);
    }

    /** @test */
    public function rows_of_another_listing_type_with_the_same_id_are_not_used(): void
    {
        // `listing_id` is unique per listing_type, not globally. A seller_agent row
        // sharing this id must not be attributed to a bridge listing.
        $listing = $this->seedListing();
        $this->seedDna($listing->id);

        PropertyLocationPoi::create([
            'listing_type' => 'seller_agent', 'listing_id' => $listing->id,
            'poi_category' => 'grocery_store', 'rank' => 1, 'poi_subtype' => 'grocery_store',
            'poi_name' => 'Seller Agent Grocery', 'distance_miles' => 0.1,
            'provenance_json' => ['provider' => 'overture_corpus'],
            'status' => 'found', 'calculated_at' => now(),
        ]);

        $response = $this->viewPage();

        $response->assertDontSee('Overture Maps Foundation', false);
        $response->assertDontSee('Seller Agent Grocery', false);
    }

    // ── Existing behaviour preserved ────────────────────────────────────────

    /** @test */
    public function the_nearby_list_itself_still_renders_names_and_distances(): void
    {
        // The nearby list is driven by $locationSummary and must be untouched by
        // this change; a fix that delivered attribution and broke the results would
        // pass every assertion above.
        $listing = $this->seedListing();
        $this->seedDna($listing->id);
        $this->seedPoi($listing->id, 'overture_corpus', 'grocery_store', 'Whole Foods Market', 0.154);

        $response = $this->viewPage();

        $response->assertSee('Nearby Amenities', false);
        $response->assertSee('Whole Foods Market', false);
        $response->assertSee('Grocery Store', false);
    }

    /** @test */
    public function the_stellar_mls_attribution_remains_separate_and_intact(): void
    {
        // Two obligations on one page: where the LISTING came from (Bridge/Stellar
        // IDX terms) and where the PLACES came from (open-data licences). Collapsing
        // them would let a reader take one as covering the other.
        $listing = $this->seedListing();
        $this->seedDna($listing->id);
        $this->seedPoi($listing->id, 'overture_corpus', 'grocery_store', 'Whole Foods Market', 0.154);

        // Asserted on RENDERED output, not on the files: the component's own comment
        // legitimately names the MLS block in order to explain why the two are kept
        // apart, and a Blade comment reaches no reader.
        $rendered = $this->renderComponentWith($this->overturePoiCollection());

        $this->assertStringContainsString('Overture Maps Foundation', $rendered);

        // The place attribution must make no MLS provenance claim.
        $this->assertStringNotContainsString('Stellar MLS', $rendered);
        $this->assertStringNotContainsString('deemed reliable', $rendered);
        $this->assertStringNotContainsString('Bridge Data Output', $rendered);

        // And the shared MLS block is untouched, still saying what it always said.
        $mls = (string) file_get_contents(base_path('resources/views/offer-listing/partials/_mls_attribution.blade.php'));
        $this->assertStringContainsString('Stellar MLS', $mls);
        $this->assertStringNotContainsString('Overture', $mls);
    }

    /** @test */
    public function the_component_fetches_no_data_of_its_own(): void
    {
        // Proven behaviourally rather than by grepping the file for query syntax.
        //
        // Rows exist in the database for this listing, but the component is rendered
        // WITHOUT the prop. A component that fetched its own data would find them and
        // attribute them; one that only reads what it is handed renders nothing.
        $listing = $this->seedListing();
        $this->seedDna($listing->id);
        $this->seedPoi($listing->id, 'overture_corpus', 'grocery_store', 'Whole Foods Market', 0.154);

        $this->assertSame(
            1,
            PropertyLocationPoi::where('listing_type', 'bridge')->where('listing_id', $listing->id)->count(),
            'Fixture did not persist a row, so the assertions below would be vacuous.'
        );

        $withoutProp = \Illuminate\Support\Facades\Blade::render(
            '<x-stellar.matchmaker-nearby :location-summary="$s" />',
            ['s' => $this->summaryFixture()]
        );

        $this->assertStringContainsString('Whole Foods Market', $withoutProp, 'The nearby list must still render from the summary.');
        $this->assertStringNotContainsString('location-dna-attribution', $withoutProp);
        $this->assertStringNotContainsString('Overture Maps Foundation', $withoutProp);
    }

    // -- Helpers ------------------------------------------------------------

    /** A summary payload shaped like LocationDnaSummaryService's completed response. */
    private function summaryFixture(): array
    {
        return [
            'status'  => 'completed',
            'summary' => [
                'nearest_by_category' => [
                    'grocery_store' => ['name' => 'Whole Foods Market', 'status' => 'found'],
                ],
                'daily_convenience' => ['nearest_grocery_miles' => 0.154],
            ],
        ];
    }

    /** An unsaved POI row carrying Overture provenance. */
    private function overturePoiCollection()
    {
        $poi = new PropertyLocationPoi();
        $poi->provenance_json = ['provider' => 'overture_corpus'];

        return collect([$poi]);
    }

    /** Render the component in isolation with a given POI collection. */
    private function renderComponentWith($pois): string
    {
        return \Illuminate\Support\Facades\Blade::render(
            '<x-stellar.matchmaker-nearby :location-summary="$s" :location-pois="$p" />',
            ['s' => $this->summaryFixture(), 'p' => $pois]
        );
    }
}
