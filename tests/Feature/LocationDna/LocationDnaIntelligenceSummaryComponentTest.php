<?php

namespace Tests\Feature\LocationDna;

use App\Models\User;
use App\Services\LocationDna\LocationIntelligenceComposer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Tests for the location-dna-intelligence-summary Blade component and its
 * integration into the Buyer Criteria and Tenant Criteria public view pages.
 *
 * Component rendering (a–c) uses view()->render() directly.
 * Page load tests (d–e) use $this->get() with minimal DB fixtures.
 * Composer integration tests (f–i) mock the composer binding.
 */
class LocationDnaIntelligenceSummaryComponentTest extends TestCase
{
    use DatabaseTransactions;

    private function renderComponent(array $summaryLines): string
    {
        return view('components.location-dna-intelligence-summary', [
            'summaryLines' => $summaryLines,
        ])->render();
    }

    /**
     * (a) Empty $summaryLines renders no card markup at all.
     */
    public function test_empty_summary_lines_renders_nothing(): void
    {
        $html = $this->renderComponent([]);

        $this->assertStringNotContainsString('Location Intelligence', $html);
        $this->assertStringNotContainsString('<div class="card', $html);
        $this->assertStringNotContainsString('<ul', $html);
    }

    /**
     * (b) A non-empty array renders the card header and each line as a list item.
     */
    public function test_non_empty_summary_lines_renders_card_with_items(): void
    {
        $lines = [
            'Close to downtown schools',
            'Low flood risk area',
            'Near public transit hubs',
        ];

        $html = $this->renderComponent($lines);

        $this->assertStringContainsString('Location Intelligence', $html);
        $this->assertStringContainsString('<ul', $html);
        foreach ($lines as $line) {
            $this->assertStringContainsString($line, $html);
        }
    }

    /**
     * (c) A line containing a <script> tag is HTML-escaped in the output.
     */
    public function test_script_tag_in_line_is_html_escaped(): void
    {
        $html = $this->renderComponent(['<script>alert(1)</script>']);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    /**
     * (d) The Buyer Criteria public view loads successfully and the composer
     *     mock's summary is available; existing map/flood/school vars still present.
     */
    public function test_buyer_criteria_view_loads_without_location_intelligence_summary(): void
    {
        if (!Schema::hasTable('buyer_criteria_auctions') || !Schema::hasTable('buyer_criteria_auction_metas')) {
            $this->markTestSkipped('buyer_criteria_auctions or buyer_criteria_auction_metas table does not exist in this environment.');
        }

        $this->mock(LocationIntelligenceComposer::class, function ($mock) {
            $mock->shouldReceive('compose')
                 ->andReturn(['summary' => ['summary_lines' => []]]);
        });

        $user = User::factory()->create();

        $auctionId = DB::table('buyer_criteria_auctions')->insertGetId([
            'user_id'     => $user->id,
            'buyer_id'    => $user->id,
            'max_price'   => 0,
            'title'       => 'Test Buyer Listing',
            'is_approved' => true,
            'is_sold'     => false,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $this->get(route('buyer.criteria.view', $auctionId))
             ->assertStatus(200);
    }

    /**
     * (e) The Tenant Criteria public view loads successfully even when no
     *     $locationIntelligenceSummary variable is passed from the controller.
     *
     * Skipped when tenant_criteria_auctions table is absent in this environment.
     */
    public function test_tenant_criteria_view_loads_without_location_intelligence_summary(): void
    {
        if (!Schema::hasTable('tenant_criteria_auctions')) {
            $this->markTestSkipped('tenant_criteria_auctions table does not exist in this environment.');
        }

        $this->mock(LocationIntelligenceComposer::class, function ($mock) {
            $mock->shouldReceive('compose')
                 ->andReturn(['summary' => ['summary_lines' => []]]);
        });

        $user = User::factory()->create();

        $auctionId = DB::table('tenant_criteria_auctions')->insertGetId([
            'user_id'     => $user->id,
            'is_approved' => true,
            'is_sold'     => false,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $this->get(route('tenant.criteria.auction.view', $auctionId))
             ->assertStatus(200);
    }

    /**
     * (f) Buyer view: composer returns real summary lines; locationIntelligenceSummary
     *     is passed to the view with those lines.
     */
    public function test_buyer_view_passes_location_intelligence_summary_from_composer(): void
    {
        if (!Schema::hasTable('buyer_criteria_auctions') || !Schema::hasTable('buyer_criteria_auction_metas')) {
            $this->markTestSkipped('buyer_criteria_auctions or buyer_criteria_auction_metas table does not exist in this environment.');
        }

        $summaryLines = ['Low flood risk', 'Top-rated schools nearby'];

        $this->mock(LocationIntelligenceComposer::class, function ($mock) use ($summaryLines) {
            $mock->shouldReceive('compose')
                 ->once()
                 ->andReturn(['summary' => ['summary_lines' => $summaryLines]]);
        });

        $user = User::factory()->create();

        $auctionId = DB::table('buyer_criteria_auctions')->insertGetId([
            'user_id'     => $user->id,
            'buyer_id'    => $user->id,
            'max_price'   => 0,
            'title'       => 'Test Buyer Listing',
            'is_approved' => true,
            'is_sold'     => false,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $this->get(route('buyer.criteria.view', $auctionId))
             ->assertStatus(200)
             ->assertViewHas('locationIntelligenceSummary', ['summary_lines' => $summaryLines])
             ->assertViewHas('boundaryData')
             ->assertViewHas('floodZoneData')
             ->assertViewHas('schoolDistrictData');
    }

    /**
     * (g) Tenant view: composer returns real summary lines; locationIntelligenceSummary
     *     is passed to the view with those lines.
     */
    public function test_tenant_view_passes_location_intelligence_summary_from_composer(): void
    {
        if (!Schema::hasTable('tenant_criteria_auctions')) {
            $this->markTestSkipped('tenant_criteria_auctions table does not exist in this environment.');
        }

        $summaryLines = ['Close to transit', 'Low crime area'];

        $this->mock(LocationIntelligenceComposer::class, function ($mock) use ($summaryLines) {
            $mock->shouldReceive('compose')
                 ->once()
                 ->andReturn(['summary' => ['summary_lines' => $summaryLines]]);
        });

        $user = User::factory()->create();

        $auctionId = DB::table('tenant_criteria_auctions')->insertGetId([
            'user_id'     => $user->id,
            'is_approved' => true,
            'is_sold'     => false,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $this->get(route('tenant.criteria.auction.view', $auctionId))
             ->assertStatus(200)
             ->assertViewHas('locationIntelligenceSummary', ['summary_lines' => $summaryLines])
             ->assertViewHas('boundaryData')
             ->assertViewHas('floodZoneData')
             ->assertViewHas('schoolDistrictData');
    }

    /**
     * (h) Buyer view: composer throws; page still returns 200 and
     *     locationIntelligenceSummary falls back to empty summary_lines.
     */
    public function test_buyer_view_returns_200_when_composer_throws(): void
    {
        if (!Schema::hasTable('buyer_criteria_auctions') || !Schema::hasTable('buyer_criteria_auction_metas')) {
            $this->markTestSkipped('buyer_criteria_auctions or buyer_criteria_auction_metas table does not exist in this environment.');
        }

        $this->mock(LocationIntelligenceComposer::class, function ($mock) {
            $mock->shouldReceive('compose')
                 ->once()
                 ->andThrow(new \RuntimeException('Composer exploded'));
        });

        $user = User::factory()->create();

        $auctionId = DB::table('buyer_criteria_auctions')->insertGetId([
            'user_id'     => $user->id,
            'buyer_id'    => $user->id,
            'max_price'   => 0,
            'title'       => 'Test Buyer Listing',
            'is_approved' => true,
            'is_sold'     => false,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $this->get(route('buyer.criteria.view', $auctionId))
             ->assertStatus(200)
             ->assertViewHas('locationIntelligenceSummary', ['summary_lines' => []]);
    }

    /**
     * (i) Tenant view: composer throws; page still returns 200 and
     *     locationIntelligenceSummary falls back to empty summary_lines.
     */
    public function test_tenant_view_returns_200_when_composer_throws(): void
    {
        if (!Schema::hasTable('tenant_criteria_auctions')) {
            $this->markTestSkipped('tenant_criteria_auctions table does not exist in this environment.');
        }

        $this->mock(LocationIntelligenceComposer::class, function ($mock) {
            $mock->shouldReceive('compose')
                 ->once()
                 ->andThrow(new \RuntimeException('Composer exploded'));
        });

        $user = User::factory()->create();

        $auctionId = DB::table('tenant_criteria_auctions')->insertGetId([
            'user_id'     => $user->id,
            'is_approved' => true,
            'is_sold'     => false,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $this->get(route('tenant.criteria.auction.view', $auctionId))
             ->assertStatus(200)
             ->assertViewHas('locationIntelligenceSummary', ['summary_lines' => []]);
    }

    // =========================================================================
    // HTML-level card visibility tests (Phase 4F)
    // Verifies the Blade component actually renders or hides the card in the
    // full HTTP response body — not just the view variable.
    // =========================================================================

    /**
     * (j) Buyer view: response HTML contains the "Location Intelligence" card
     *     heading when the composer returns non-empty summary_lines.
     */
    public function test_buyer_view_html_shows_intelligence_card_when_summary_lines_present(): void
    {
        if (!Schema::hasTable('buyer_criteria_auctions') || !Schema::hasTable('buyer_criteria_auction_metas')) {
            $this->markTestSkipped('buyer_criteria_auctions or buyer_criteria_auction_metas table does not exist in this environment.');
        }

        $summaryLines = ['Low flood risk area', 'Top-rated schools nearby'];

        $this->mock(LocationIntelligenceComposer::class, function ($mock) use ($summaryLines) {
            $mock->shouldReceive('compose')
                 ->once()
                 ->andReturn(['summary' => ['summary_lines' => $summaryLines]]);
        });

        $user = User::factory()->create();

        $auctionId = DB::table('buyer_criteria_auctions')->insertGetId([
            'user_id'     => $user->id,
            'buyer_id'    => $user->id,
            'max_price'   => 0,
            'title'       => 'Test Buyer – Card Visible',
            'is_approved' => true,
            'is_sold'     => false,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $this->get(route('buyer.criteria.view', $auctionId))
             ->assertStatus(200)
             ->assertSee('Location Intelligence')
             ->assertSee('Low flood risk area')
             ->assertSee('Top-rated schools nearby');
    }

    /**
     * (k) Buyer view: response HTML does NOT contain "Location Intelligence"
     *     card heading when the composer returns empty summary_lines.
     */
    public function test_buyer_view_html_hides_intelligence_card_when_summary_lines_empty(): void
    {
        if (!Schema::hasTable('buyer_criteria_auctions') || !Schema::hasTable('buyer_criteria_auction_metas')) {
            $this->markTestSkipped('buyer_criteria_auctions or buyer_criteria_auction_metas table does not exist in this environment.');
        }

        $this->mock(LocationIntelligenceComposer::class, function ($mock) {
            $mock->shouldReceive('compose')
                 ->once()
                 ->andReturn(['summary' => ['summary_lines' => []]]);
        });

        $user = User::factory()->create();

        $auctionId = DB::table('buyer_criteria_auctions')->insertGetId([
            'user_id'     => $user->id,
            'buyer_id'    => $user->id,
            'max_price'   => 0,
            'title'       => 'Test Buyer – Card Hidden',
            'is_approved' => true,
            'is_sold'     => false,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $this->get(route('buyer.criteria.view', $auctionId))
             ->assertStatus(200)
             ->assertDontSee('Location Intelligence');
    }

    /**
     * (l) Tenant view: response HTML contains the "Location Intelligence" card
     *     heading when the composer returns non-empty summary_lines.
     */
    public function test_tenant_view_html_shows_intelligence_card_when_summary_lines_present(): void
    {
        if (!Schema::hasTable('tenant_criteria_auctions')) {
            $this->markTestSkipped('tenant_criteria_auctions table does not exist in this environment.');
        }

        $summaryLines = ['Close to transit hubs', 'Low crime area'];

        $this->mock(LocationIntelligenceComposer::class, function ($mock) use ($summaryLines) {
            $mock->shouldReceive('compose')
                 ->once()
                 ->andReturn(['summary' => ['summary_lines' => $summaryLines]]);
        });

        $user = User::factory()->create();

        $auctionId = DB::table('tenant_criteria_auctions')->insertGetId([
            'user_id'     => $user->id,
            'is_approved' => true,
            'is_sold'     => false,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $this->get(route('tenant.criteria.auction.view', $auctionId))
             ->assertStatus(200)
             ->assertSee('Location Intelligence')
             ->assertSee('Close to transit hubs')
             ->assertSee('Low crime area');
    }

    /**
     * (m) Tenant view: response HTML does NOT contain "Location Intelligence"
     *     card heading when the composer returns empty summary_lines.
     */
    public function test_tenant_view_html_hides_intelligence_card_when_summary_lines_empty(): void
    {
        if (!Schema::hasTable('tenant_criteria_auctions')) {
            $this->markTestSkipped('tenant_criteria_auctions table does not exist in this environment.');
        }

        $this->mock(LocationIntelligenceComposer::class, function ($mock) {
            $mock->shouldReceive('compose')
                 ->once()
                 ->andReturn(['summary' => ['summary_lines' => []]]);
        });

        $user = User::factory()->create();

        $auctionId = DB::table('tenant_criteria_auctions')->insertGetId([
            'user_id'     => $user->id,
            'is_approved' => true,
            'is_sold'     => false,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $this->get(route('tenant.criteria.auction.view', $auctionId))
             ->assertStatus(200)
             ->assertDontSee('Location Intelligence');
    }

    /**
     * (n) Buyer view: when the composer throws, the response HTML does NOT
     *     contain the "Location Intelligence" card — the empty-state guard
     *     fires correctly from the exception fallback payload.
     */
    public function test_buyer_view_html_has_no_intelligence_card_when_composer_throws(): void
    {
        if (!Schema::hasTable('buyer_criteria_auctions') || !Schema::hasTable('buyer_criteria_auction_metas')) {
            $this->markTestSkipped('buyer_criteria_auctions or buyer_criteria_auction_metas table does not exist in this environment.');
        }

        $this->mock(LocationIntelligenceComposer::class, function ($mock) {
            $mock->shouldReceive('compose')
                 ->once()
                 ->andThrow(new \RuntimeException('Simulated composer failure'));
        });

        $user = User::factory()->create();

        $auctionId = DB::table('buyer_criteria_auctions')->insertGetId([
            'user_id'     => $user->id,
            'buyer_id'    => $user->id,
            'max_price'   => 0,
            'title'       => 'Test Buyer – Composer Exception',
            'is_approved' => true,
            'is_sold'     => false,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $this->get(route('buyer.criteria.view', $auctionId))
             ->assertStatus(200)
             ->assertDontSee('Location Intelligence');
    }

    // =========================================================================
    // Map component regression tests (Phase 4F)
    // Verifies that the location-dna-map Blade component renders its expected
    // structure and that the intelligence summary and map variables are both
    // passed to the view by the controller (no regressions from Phase 4E wiring).
    // =========================================================================

    /**
     * (o) Buyer view: the controller passes all Location DNA variables to the
     *     view — locationDnaPreferences, legacyLocation, boundaryData,
     *     floodZoneData, schoolDistrictData, and locationIntelligenceSummary.
     *     This is a regression guard for Phase 4E controller wiring.
     */
    public function test_buyer_view_passes_all_location_dna_view_vars(): void
    {
        if (!Schema::hasTable('buyer_criteria_auctions') || !Schema::hasTable('buyer_criteria_auction_metas')) {
            $this->markTestSkipped('buyer_criteria_auctions or buyer_criteria_auction_metas table does not exist in this environment.');
        }

        $this->mock(LocationIntelligenceComposer::class, function ($mock) {
            $mock->shouldReceive('compose')
                 ->andReturn(['summary' => ['summary_lines' => []]]);
        });

        $user = User::factory()->create();

        $auctionId = DB::table('buyer_criteria_auctions')->insertGetId([
            'user_id'     => $user->id,
            'buyer_id'    => $user->id,
            'max_price'   => 0,
            'title'       => 'Test Buyer – DNA Vars Regression',
            'is_approved' => true,
            'is_sold'     => false,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $response = $this->get(route('buyer.criteria.view', $auctionId))
             ->assertStatus(200);

        $response->assertViewHas('locationDnaPreferences');
        $response->assertViewHas('legacyLocation');
        $response->assertViewHas('boundaryData');
        $response->assertViewHas('floodZoneData');
        $response->assertViewHas('schoolDistrictData');
        $response->assertViewHas('locationIntelligenceSummary');
    }

    /**
     * (p) Tenant view: the controller passes all Location DNA variables to the
     *     view — regression guard for Phase 4E controller wiring.
     */
    public function test_tenant_view_passes_all_location_dna_view_vars(): void
    {
        if (!Schema::hasTable('tenant_criteria_auctions')) {
            $this->markTestSkipped('tenant_criteria_auctions table does not exist in this environment.');
        }

        $this->mock(LocationIntelligenceComposer::class, function ($mock) {
            $mock->shouldReceive('compose')
                 ->andReturn(['summary' => ['summary_lines' => []]]);
        });

        $user = User::factory()->create();

        $auctionId = DB::table('tenant_criteria_auctions')->insertGetId([
            'user_id'     => $user->id,
            'is_approved' => true,
            'is_sold'     => false,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $response = $this->get(route('tenant.criteria.auction.view', $auctionId))
             ->assertStatus(200);

        $response->assertViewHas('locationDnaPreferences');
        $response->assertViewHas('legacyLocation');
        $response->assertViewHas('boundaryData');
        $response->assertViewHas('floodZoneData');
        $response->assertViewHas('schoolDistrictData');
        $response->assertViewHas('locationIntelligenceSummary');
    }

    /**
     * (q) Map component: renders the chip fallback block (not a blank map div)
     *     when the preference tier is "cities" and no GeoJSON boundary data
     *     is available — verifies the $hasMapData / fallback path.
     */
    public function test_map_component_renders_chip_block_for_cities_without_boundary_data(): void
    {
        $html = view('components.location-dna-map', [
            'preferences'        => ['cities' => ['Orlando', 'Tampa'], 'zip_codes' => [], 'neighborhoods' => [], 'polygons' => [], 'radius_searches' => [], 'flexible_location' => false, 'location_notes' => ''],
            'legacyLocation'     => ['cities' => [], 'counties' => [], 'states' => [], 'zip_codes' => []],
            'boundaryData'       => ['geojson_polygons' => [], 'fallback' => true],
            'floodZoneData'      => ['flood_zones' => [], 'available' => false],
            'schoolDistrictData' => ['school_districts' => [], 'available' => false],
        ])->render();

        $this->assertStringContainsString('Orlando', $html);
        $this->assertStringContainsString('Tampa', $html);
        $this->assertStringNotContainsString('<div id="ldna-display-', $html);
    }

    /**
     * (r) Map component: renders the "No location preferences" fallback when
     *     both preferences and legacyLocation are empty — verifies Tier 6.
     */
    public function test_map_component_renders_no_location_fallback_when_all_data_empty(): void
    {
        $html = view('components.location-dna-map', [
            'preferences'        => null,
            'legacyLocation'     => ['cities' => [], 'counties' => [], 'states' => [], 'zip_codes' => []],
            'boundaryData'       => null,
            'floodZoneData'      => null,
            'schoolDistrictData' => null,
        ])->render();

        $this->assertStringContainsString('No location preferences have been specified', $html);
        $this->assertStringNotContainsString('<div id="ldna-display-', $html);
    }
}
