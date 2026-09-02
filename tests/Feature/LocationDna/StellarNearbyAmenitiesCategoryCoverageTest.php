<?php

namespace Tests\Feature\LocationDna;

use App\Models\PropertyLocationDna;
use App\Models\PropertyLocationPoi;
use App\Services\LocationDna\LocationDnaPoiDistanceService;
use App\Services\LocationDna\LocationDnaSummaryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * StellarNearbyAmenitiesCategoryCoverageTest
 *
 * The shared Stellar property-detail page (Buyer, Tenant, Agent, Landlord preview)
 * renders <x-stellar.matchmaker-nearby>, which reads ONLY the thematic blocks that
 * LocationDnaSummaryService writes into summary_json.
 *
 * WHY THIS EXISTS. Five categories — school, hospital, shopping_center, gym and
 * fitness_center — were fetched by the pipeline, stored in property_location_pois and
 * present in summary.nearest_by_category, yet never reached this panel: they belonged
 * to no THEMATIC_BLOCKS entry, so the component (whose section list is explicit) had
 * nothing to iterate. Seller/Landlord listing pages showed them the whole time, which
 * is what made the gap easy to miss — the data was demonstrably there.
 *
 * The tests below run the REAL summary service against real POI rows and render the
 * REAL Blade component, so they fail if either half of that chain regresses. No HTTP,
 * no Google, no provider: the summary service is a pure read-from-DB aggregation.
 *
 * Fixture names are deliberately recognisable ("Test Elementary School") so a failure
 * message names the category that fell out.
 */
class StellarNearbyAmenitiesCategoryCoverageTest extends TestCase
{
    use DatabaseTransactions;

    private const LISTING_TYPE = 'bridge';
    private const LISTING_ID   = 987654;
    private const SOURCE_LAT   = 26.1224;
    private const SOURCE_LNG   = -80.1373;

    /**
     * The five categories this fix restores, with the fixture name each one is
     * seeded with. Keyed by poi_category.
     */
    private const RESTORED = [
        'school'          => 'Test Elementary School',
        'hospital'        => 'Test Hospital',
        'shopping_center' => 'Test Shopping Center',
        'gym'             => 'Test Gym',
        'fitness_center'  => 'Test Fitness Center',
    ];

    /** Categories that already reached the panel and must keep doing so. */
    private const PRE_EXISTING = [
        'grocery_store'   => 'Test Grocery Market',
        'restaurant'      => 'Test Restaurant',
        'park'            => 'Test Park',
        'beach'           => 'Test Beach',
        'transit_station' => 'Test Transit Stop',
        'pharmacy'        => 'Test Pharmacy',
    ];

    // ═════════════════════════════════════════════════════════════════════
    // Fixtures
    // ═════════════════════════════════════════════════════════════════════

    private function seedListing(): void
    {
        $this->seedListingFor(self::LISTING_TYPE, self::LISTING_ID);
    }

    private function seedListingFor(string $listingType, int $listingId): void
    {
        PropertyLocationDna::create([
            'listing_type'   => $listingType,
            'listing_id'     => $listingId,
            'source_address' => '123 Audit Way',
            'source_city'    => 'Fort Lauderdale',
            'source_state'   => 'FL',
            'geocoded_lat'   => self::SOURCE_LAT,
            'geocoded_lng'   => self::SOURCE_LNG,
            'geocode_source' => 'google',
            'geocode_status' => 'geocoded',
            'geocoded_at'    => now(),
        ]);

        $distance = 0.4;
        foreach (array_merge(self::PRE_EXISTING, self::RESTORED) as $category => $name) {
            PropertyLocationPoi::create([
                'listing_type'   => $listingType,
                'listing_id'     => $listingId,
                'poi_category'   => $category,
                'rank'           => 1,
                'poi_subtype'    => ucwords(str_replace('_', ' ', $category)),
                'poi_name'       => $name,
                'source_lat'     => self::SOURCE_LAT,
                'source_lng'     => self::SOURCE_LNG,
                'distance_miles' => $distance,
                'data_source'    => 'google_places',
                'status'         => 'found',
                'calculated_at'  => now(),
            ]);
            $distance += 0.3;
        }
    }

    /** Run the real summary service, then render the real Stellar component from it. */
    private function renderPanel(): string
    {
        $result = (new LocationDnaSummaryService())
            ->summarizeForListing(self::LISTING_TYPE, self::LISTING_ID);

        $this->assertTrue(
            $result['success'] ?? false,
            'Summary service did not complete; the render assertions below would be vacuous.',
        );

        return Blade::render(
            '<x-stellar.matchmaker-nearby :location-summary="$ls" />',
            ['ls' => $result],
        );
    }

    // ═════════════════════════════════════════════════════════════════════
    // The five restored categories
    // ═════════════════════════════════════════════════════════════════════

    /** @test */
    public function the_five_previously_dropped_categories_reach_the_stellar_panel(): void
    {
        $this->seedListing();
        $html = $this->renderPanel();

        foreach (self::RESTORED as $category => $name) {
            $this->assertStringContainsString(
                $name,
                $html,
                "Category '{$category}' is stored and present in nearest_by_category but did not "
                ."reach the Stellar Nearby Amenities panel. Check THEMATIC_BLOCKS in "
                ."LocationDnaSummaryService and the section list in matchmaker-nearby.blade.php.",
            );
        }
    }

    /** @test */
    public function each_restored_category_is_reachable_through_a_thematic_block(): void
    {
        $this->seedListing();

        $summary = (new LocationDnaSummaryService())
            ->summarizeForListing(self::LISTING_TYPE, self::LISTING_ID)['summary'];

        // A category is only renderable if some thematic block carries a non-null
        // distance for it — nearest_by_category alone is not enough, which is exactly
        // the bug this test guards.
        $blocks = array_diff_key(
            $summary,
            array_flip(['geocode', 'nearest_by_category', 'category_counts', 'missing_categories', 'error_categories']),
        );

        foreach (self::RESTORED as $category => $name) {
            $found = false;
            foreach ($blocks as $blockKey => $block) {
                foreach ($block as $outputKey => $miles) {
                    if ($miles !== null && str_contains($outputKey, $this->distanceKeyFragment($category))) {
                        $found = true;
                        break 2;
                    }
                }
            }

            $this->assertTrue(
                $found,
                "Category '{$category}' has no non-null entry in any thematic block, so the "
                .'Stellar panel cannot render it.',
            );
        }
    }

    private function distanceKeyFragment(string $category): string
    {
        return $category;
    }

    // ═════════════════════════════════════════════════════════════════════
    // Regression: categories that already worked
    // ═════════════════════════════════════════════════════════════════════

    /** @test */
    public function the_previously_working_categories_still_render(): void
    {
        $this->seedListing();
        $html = $this->renderPanel();

        foreach (self::PRE_EXISTING as $category => $name) {
            $this->assertStringContainsString(
                $name,
                $html,
                "Category '{$category}' rendered before this change and must continue to.",
            );
        }
    }

    /** @test */
    public function the_four_original_thematic_blocks_are_unchanged(): void
    {
        $this->seedListing();

        $summary = (new LocationDnaSummaryService())
            ->summarizeForListing(self::LISTING_TYPE, self::LISTING_ID)['summary'];

        // The lifestyle scorer reads named keys out of these four blocks. Their shape
        // is therefore load-bearing beyond presentation.
        foreach (['coastal', 'daily_convenience', 'outdoor_recreation', 'transportation'] as $block) {
            $this->assertArrayHasKey($block, $summary, "Original thematic block '{$block}' disappeared.");
        }

        $this->assertArrayHasKey('nearest_beach_miles', $summary['coastal']);
        $this->assertArrayHasKey('nearest_grocery_miles', $summary['daily_convenience']);
        $this->assertArrayHasKey('nearest_park_miles', $summary['outdoor_recreation']);
        $this->assertArrayHasKey('nearest_transit_miles', $summary['transportation']);
    }

    // ═════════════════════════════════════════════════════════════════════
    // Completeness — no stored category may be silently unreachable
    // ═════════════════════════════════════════════════════════════════════

    /** @test */
    public function every_fetched_category_is_reachable_from_the_stellar_panel(): void
    {
        $reachable = [];
        foreach ($this->thematicBlocks() as $categoryMap) {
            foreach (array_keys($categoryMap) as $category) {
                $reachable[$category] = true;
            }
        }

        $fetched = array_keys(LocationDnaPoiDistanceService::CATEGORIES);
        $missing = array_values(array_diff($fetched, array_keys($reachable)));

        $this->assertSame(
            [],
            $missing,
            'These categories are fetched and stored but reach no thematic block, so the Stellar '
            .'panel can never display them: '.implode(', ', $missing).'. Add them to '
            .'LocationDnaSummaryService::THEMATIC_BLOCKS (and the component section list), or '
            .'document why they are deliberately hidden.',
        );
    }

    /** @test */
    public function gym_and_fitness_center_do_not_render_twice_for_one_place(): void
    {
        // gym and fitness_center are a CATEGORY_GROUPS pair sharing one raw fetch, so
        // they can legitimately resolve to the same physical business. Showing it twice
        // reads as a data error to a consumer.
        PropertyLocationDna::create([
            'listing_type'   => self::LISTING_TYPE,
            'listing_id'     => self::LISTING_ID,
            'source_address' => '123 Audit Way',
            'source_city'    => 'Fort Lauderdale',
            'source_state'   => 'FL',
            'geocoded_lat'   => self::SOURCE_LAT,
            'geocoded_lng'   => self::SOURCE_LNG,
            'geocode_source' => 'google',
            'geocode_status' => 'geocoded',
            'geocoded_at'    => now(),
        ]);

        foreach (['gym', 'fitness_center'] as $category) {
            PropertyLocationPoi::create([
                'listing_type'   => self::LISTING_TYPE,
                'listing_id'     => self::LISTING_ID,
                'poi_category'   => $category,
                'rank'           => 1,
                'poi_subtype'    => ucwords(str_replace('_', ' ', $category)),
                'poi_name'       => 'Test Shared Fitness Place',
                'source_lat'     => self::SOURCE_LAT,
                'source_lng'     => self::SOURCE_LNG,
                'distance_miles' => 0.9,
                'data_source'    => 'google_places',
                'status'         => 'found',
                'calculated_at'  => now(),
            ]);
        }

        $html = $this->renderPanel();

        $this->assertSame(
            1,
            substr_count($html, 'Test Shared Fitness Place'),
            'gym and fitness_center resolved to the same place and were rendered twice.',
        );
    }

    // ═════════════════════════════════════════════════════════════════════
    // Control: the Seller/Landlord panel is a different renderer and must not move
    // ═════════════════════════════════════════════════════════════════════

    /**
     * Seller/Landlord listing pages render partials.location-dna-agent-panel, which
     * reads property_location_pois DIRECTLY and never consults thematic blocks. It
     * therefore showed all nineteen categories before this change and must still show
     * them after. This is the control that proves the fix is confined to the Stellar
     * summary path.
     *
     * @test
     * @dataProvider sellerLandlordListingTypes
     */
    public function the_seller_landlord_panel_still_renders_every_fetched_category(string $listingType): void
    {
        $listingId = 4242;
        $categories = array_keys(LocationDnaPoiDistanceService::CATEGORIES);

        $pois = collect();
        foreach ($categories as $i => $category) {
            $poi = new PropertyLocationPoi();
            $poi->listing_type   = $listingType;
            $poi->listing_id     = $listingId;
            $poi->poi_category   = $category;
            $poi->rank           = 1;
            $poi->poi_name       = 'Control '.ucwords(str_replace('_', ' ', $category));
            $poi->distance_miles = 0.5 + ($i * 0.1);
            $poi->status         = 'found';
            $pois->push($poi);
        }

        $dna = new PropertyLocationDna();
        $dna->listing_type   = $listingType;
        $dna->listing_id     = $listingId;
        $dna->geocode_status = 'geocoded';
        $dna->generated_at   = now();

        $html = view('partials.location-dna-agent-panel', [
            'listingType'            => $listingType,
            'listingId'              => $listingId,
            'locationDna'            => $dna,
            'locationPois'           => $pois,
            'canGenerateLocationDna' => false,
        ])->render();

        $this->assertStringContainsString('Nearby Points of Interest', $html);

        foreach ($categories as $category) {
            $this->assertStringContainsString(
                'Control '.ucwords(str_replace('_', ' ', $category)),
                $html,
                "The {$listingType} panel stopped rendering category '{$category}'.",
            );
        }
    }

    /** @return array<string, array{string}> */
    public static function sellerLandlordListingTypes(): array
    {
        return [
            'seller'   => ['seller_agent'],
            'landlord' => ['landlord_agent'],
        ];
    }

    // ═════════════════════════════════════════════════════════════════════
    // Buyer / Tenant parity on the shared detail page
    // ═════════════════════════════════════════════════════════════════════

    /**
     * The Stellar property-detail route is shared by Buyer and Tenant, and the only
     * thing that differs between them in the request is criteria_type. Asserting the
     * two controllers are "the same class" would prove nothing, so this drives the
     * real HTTP route once per role and compares what each actually receives.
     *
     * @test
     */
    public function buyer_and_tenant_receive_the_same_nearby_amenities_on_the_shared_detail_page(): void
    {
        $listing = $this->seedBridgeListing();
        $user    = \App\Models\User::factory()->create();

        $rendered = [];

        foreach (['buyer', 'tenant'] as $criteriaType) {
            $response = $this->actingAs($user)->get(
                route('stellar.property.show', ['listingKey' => $listing->listing_key])
                .'?criteria_type='.$criteriaType
            );

            $response->assertOk();
            $html = $response->getContent();

            foreach (array_merge(self::PRE_EXISTING, self::RESTORED) as $category => $name) {
                $this->assertStringContainsString(
                    $name,
                    $html,
                    "Category '{$category}' is missing from the shared Stellar detail page "
                    ."when reached as '{$criteriaType}'.",
                );
            }

            $rendered[$criteriaType] = $this->extractNearbySection($html);
        }

        $this->assertNotSame('', $rendered['buyer'], 'Nearby Amenities section was not found in the page.');
        $this->assertSame(
            $rendered['buyer'],
            $rendered['tenant'],
            'Buyer and Tenant received different Nearby Amenities output from the shared detail page.',
        );
    }

    private function seedBridgeListing(): \App\Models\BridgeProperty
    {
        $listing = \App\Models\BridgeProperty::create([
            'listing_key'            => 'AUDITKEY'.self::LISTING_ID,
            'listing_id'             => 'A'.self::LISTING_ID,
            'standard_status'        => 'Active',
            'property_type'          => 'Residential',
            'list_price'             => 500000,
            'unparsed_address'       => '123 Audit Way',
            'city'                   => 'Fort Lauderdale',
            'state_or_province'      => 'FL',
            'postal_code'            => '33301',
            'latitude'               => self::SOURCE_LAT,
            'longitude'              => self::SOURCE_LNG,
            'raw_json'               => json_encode(['IDXParticipationYN' => true]),
            'modification_timestamp' => now(),
            'imported_at'            => now(),
        ]);

        // The DNA/POI rows must hang off the bridge row's primary key, which is what
        // the controller passes to summarizeForListing('bridge', $listing->id).
        $this->seedListingFor(self::LISTING_TYPE, $listing->id);

        return $listing;
    }

    /** Pull just the Nearby Amenities card out of a full page for comparison. */
    private function extractNearbySection(string $html): string
    {
        $start = strpos($html, 'Nearby Amenities');
        if ($start === false) {
            return '';
        }

        return substr($html, $start, 4000);
    }

    /** @return array<string, array<string, string>> */
    private function thematicBlocks(): array
    {
        $reflection = new \ReflectionClass(LocationDnaSummaryService::class);

        return $reflection->getConstant('THEMATIC_BLOCKS');
    }
}
