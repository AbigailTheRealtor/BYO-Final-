<?php

namespace Tests\Feature\LocationDna;

use App\Models\BuyerCriteriaAuction;
use App\Models\TenantCriteriaAuction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Buyer and Tenant Criteria listings: the same Location DNA search concepts as every other
 * property-search surface, and an Edit page that actually edits.
 *
 * Important Places — miles only, through the shared widget, the same ImportantPlacesService and
 * the same `important_places_json` meta — on Add, Edit and View.
 *
 * And the Edit fix those depended on: both Edit forms posted to the ADD route with no id, so every
 * save created a new listing; and the update methods they should have reached checked no owner —
 * Buyer loaded the record by a posted id, Tenant reassigned `user_id` to whoever posted. Both are
 * owner-only now, and the forms post to them.
 */
class CriteriaLocationDnaTest extends TestCase
{
    use DatabaseTransactions;

    private const WORK = [
        'type' => 'Work', 'type_other' => '', 'address' => '116 8th St E, Tierra Verde, FL 33715',
        'lat' => 27.6917, 'lng' => -82.7215, 'distance_pref' => 'miles', 'distance_value' => 5, 'travel_mode' => 'driving',
    ];

    private const HISTORICAL_MINUTES = [
        'type' => 'School', 'type_other' => '', 'address' => '1 School Rd, Tampa, FL 33602',
        'lat' => 27.95, 'lng' => -82.46, 'distance_pref' => 'minutes', 'distance_value' => 25, 'travel_mode' => 'transit',
    ];

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['buyer_criteria_auctions', 'buyer_criteria_auction_metas', 'tenant_criteria_auctions', 'tenant_criteria_auction_metas'] as $table) {
            if (!Schema::hasTable($table)) {
                $this->markTestSkipped("Table {$table} does not exist in this environment.");
            }
        }

        $this->owner = User::factory()->create(['user_type' => 'agent']);

        config([
            'spatial_basemap.maplibre_renderer_enabled'  => true,
            'spatial_basemap.maplibre_renderer_surfaces' => ['buyer_criteria', 'tenant_criteria', 'display'],
            'spatial_basemap.pmtiles_url'                => 'https://example.invalid/basemap.pmtiles',
        ]);
    }

    private function buyerCriteria(array $meta = [], ?User $owner = null): BuyerCriteriaAuction
    {
        $ownerId = ($owner ?? $this->owner)->id;

        $id = DB::table('buyer_criteria_auctions')->insertGetId([
            'user_id'     => $ownerId,
            'buyer_id'    => $ownerId,
            'max_price'   => 500000,
            'title'       => 'Buyer criteria fixture',
            'is_approved' => true,
            'is_sold'     => false,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $record = BuyerCriteriaAuction::findOrFail($id);
        foreach ($meta as $key => $value) {
            $record->saveMeta($key, is_array($value) ? json_encode($value) : $value);
        }

        return $record->fresh();
    }

    private function tenantCriteria(array $meta = [], ?User $owner = null): TenantCriteriaAuction
    {
        $id = DB::table('tenant_criteria_auctions')->insertGetId([
            'user_id'     => ($owner ?? $this->owner)->id,
            'is_approved' => true,
            'is_sold'     => false,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $record = TenantCriteriaAuction::findOrFail($id);
        // What every real tenant criteria listing carries: the Add form requires a city, a county
        // and a state, and the Edit page reads the first of each unguarded — and hands these list
        // metas straight to in_array()/foreach, so they must at least be lists.
        $meta = array_merge([
            'cities' => ['Tampa'], 'counties' => ['Hillsborough'], 'state' => ['FL'],
            'water_view' => [], 'waterFrontage' => [], 'water_extras' => [], 'water_access' => [],
            'viewReference' => [], 'negotiable' => [], 'property_items' => [], 'prop_condition' => [],
            'leaseLength' => [], 'Furnishings' => [], 'dock' => [],
        ], $meta);
        foreach ($meta as $key => $value) {
            $record->saveMeta($key, is_array($value) ? json_encode($value) : $value);
        }

        return $record->fresh();
    }

    private function assertMilesOnlyImportantPlaces(string $html, string $where): void
    {
        $this->assertStringContainsString('id="ldna-ip-section"', $html, "{$where} must offer Important Places");
        $this->assertStringContainsString('Within (miles)', $html);
        $this->assertStringContainsString('function ldnaLookupAddress(address)', $html, "{$where} must use the server-side lookup");
        $this->assertStringContainsString('name="important_places"', $html, "{$where}: the rows must post with the form");
        foreach (['Within minutes', 'Travel Mode', 'ldna-ip-distpref', 'ldna-ip-mode', 'Commute Preferences'] as $gone) {
            $this->assertStringNotContainsString($gone, $html, "{$where} must not offer [{$gone}]");
        }
    }

    // ── Add / Edit forms ─────────────────────────────────────────────────────

    public function test_both_add_forms_offer_miles_only_important_places(): void
    {
        $this->assertMilesOnlyImportantPlaces(
            $this->actingAs($this->owner)->get(route('buyer_agent.auction.add'))->assertOk()->getContent(),
            'Buyer Criteria Add'
        );
        $this->assertMilesOnlyImportantPlaces(
            $this->actingAs($this->owner)->get(route('agent.tenant.criteria.auction.add'))->assertOk()->getContent(),
            'Tenant Criteria Add'
        );
    }

    public function test_both_edit_forms_post_to_update_and_reload_the_stored_places(): void
    {
        $buyer = $this->buyerCriteria(['important_places_json' => [self::WORK, self::HISTORICAL_MINUTES]]);
        $html  = $this->actingAs($this->owner)->get(route('buyer_agent.auction.edit', $buyer->id))->assertOk()->getContent();

        $this->assertMilesOnlyImportantPlaces($html, 'Buyer Criteria Edit');
        $this->assertStringContainsString('action="' . route('buyer_agent.auction.update') . '"', $html);
        $this->assertStringContainsString('<input type="hidden" name="id" value="' . $buyer->id . '">', $html);
        $this->assertStringContainsString('116 8th St E, Tierra Verde, FL 33715', $html);
        $this->assertStringContainsString('"distance_pref":"minutes"', $html, 'A historical minutes row reloads as minutes');

        $tenant = $this->tenantCriteria(['important_places_json' => [self::WORK]]);
        $html   = $this->actingAs($this->owner)->get(route('agent.tenant.criteria.auction.edit', $tenant->id))->assertOk()->getContent();

        $this->assertMilesOnlyImportantPlaces($html, 'Tenant Criteria Edit');
        $this->assertStringContainsString('action="' . route('agent.tenant.criteria.auction.edit', $tenant->id) . '"', $html);
        $this->assertStringNotContainsString('action="' . route('agent.tenant.criteria.auction.add') . '"', $html);
    }

    // ── Ownership ─────────────────────────────────────────────────────────────

    public function test_another_agent_can_neither_open_nor_save_someone_elses_criteria(): void
    {
        $intruder = User::factory()->create(['user_type' => 'agent']);
        $buyer    = $this->buyerCriteria(['important_places_json' => [self::WORK]]);
        $tenant   = $this->tenantCriteria(['important_places_json' => [self::WORK]]);

        $this->actingAs($intruder)->get(route('buyer_agent.auction.edit', $buyer->id))->assertForbidden();
        $this->actingAs($intruder)->get(route('agent.tenant.criteria.auction.edit', $tenant->id))->assertForbidden();

        $this->actingAs($intruder)->post(route('buyer_agent.auction.update'), [
            'id' => $buyer->id, 'auction_length' => '30 days', 'important_places' => '[]',
        ])->assertForbidden();
        $this->actingAs($intruder)->post(route('agent.tenant.criteria.auction.edit', $tenant->id), [
            'auction_length' => '30 days', 'important_places' => '[]',
        ])->assertForbidden();

        $this->assertSame($this->owner->id, (int) $tenant->fresh()->user_id, 'Posting to the edit URL must never take the listing over');
        $this->assertCount(1, json_decode($buyer->fresh()->info('important_places_json'), true));
        $this->assertCount(1, json_decode($tenant->fresh()->info('important_places_json'), true));
    }

    // ── Save / reload ─────────────────────────────────────────────────────────

    public function test_saving_an_edit_updates_the_listing_and_creates_no_new_one(): void
    {
        $buyer  = $this->buyerCriteria();
        $before = BuyerCriteriaAuction::count();

        $this->actingAs($this->owner)->post(route('buyer_agent.auction.update'), [
            'id'                       => $buyer->id,
            'auction_length'           => '30 days',
            'location_dna_preferences' => json_encode(['zip_codes' => ['33602'], 'radius_searches' => [], 'polygons' => []]),
            'important_places'         => json_encode([self::WORK, self::HISTORICAL_MINUTES]),
        ]);

        $this->assertSame($before, BuyerCriteriaAuction::count(), 'An edit must not create a listing');
        $stored = json_decode($buyer->fresh()->info('important_places_json'), true);
        $this->assertSame('116 8th St E, Tierra Verde, FL 33715', $stored[0]['address']);
        $this->assertSame(27.6917, $stored[0]['lat']);
        $this->assertSame('miles', $stored[0]['distance_pref']);
        $this->assertSame('minutes', $stored[1]['distance_pref'], 'Minutes are preserved, never converted');
        $this->assertEquals(25, $stored[1]['distance_value'], 'The minutes figure itself is kept');

        $tenant  = $this->tenantCriteria();
        $before  = TenantCriteriaAuction::count();

        $this->actingAs($this->owner)->post(route('agent.tenant.criteria.auction.edit', $tenant->id), [
            'auction_length'           => '30 days',
            'location_dna_preferences' => json_encode(['zip_codes' => ['33602'], 'radius_searches' => [], 'polygons' => []]),
            'important_places'         => json_encode([self::WORK]),
        ]);

        $this->assertSame($before, TenantCriteriaAuction::count());
        $this->assertSame([self::WORK], json_decode($tenant->fresh()->info('important_places_json'), true));
        $this->assertSame(['33602'], json_decode($tenant->fresh()->info('location_dna_preferences'), true)['zip_codes']);
    }

    public function test_removing_every_place_sticks(): void
    {
        $tenant = $this->tenantCriteria(['important_places_json' => [self::WORK]]);

        $this->actingAs($this->owner)->post(route('agent.tenant.criteria.auction.edit', $tenant->id), [
            'auction_length' => '30 days', 'important_places' => '',
        ]);

        $this->assertSame([], json_decode($tenant->fresh()->info('important_places_json'), true));
    }

    // ── View ──────────────────────────────────────────────────────────────────

    public function test_both_view_pages_name_the_important_place_they_pin(): void
    {
        $meta = [
            'location_dna_preferences' => ['radius_searches' => [
                ['address' => '315 E Madison St, Tampa, FL 33602', 'lat' => 27.9506, 'lng' => -82.4572, 'radius_miles' => 5],
            ]],
            'important_places_json' => [self::WORK],
        ];

        foreach ([
            'buyer'  => fn () => route('buyer.criteria.view', $this->buyerCriteria($meta)->id),
            'tenant' => fn () => route('tenant.criteria.auction.view', $this->tenantCriteria($meta)->id),
        ] as $role => $url) {
            $html  = $this->actingAs($this->owner)->get($url())->assertOk()->getContent();
            $start = strpos($html, 'data-ldna-criteria-summary');
            $this->assertNotFalse($start, "{$role} criteria view must render the written summary");
            $summary = substr($html, $start, strpos($html, '</section>', $start) - $start);

            $this->assertStringContainsString('315 E Madison St, Tampa, FL 33602', $summary);
            $this->assertStringContainsString('116 8th St E, Tierra Verde, FL 33715', $summary);
            $this->assertStringContainsString('Work', $summary);
            $this->assertSame(2, substr_count($summary, 'Within 5 miles'));
            $this->assertStringNotContainsString('27.6917', $summary);
        }
    }
}
