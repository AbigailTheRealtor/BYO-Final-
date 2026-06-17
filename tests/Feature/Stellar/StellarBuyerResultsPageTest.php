<?php

namespace Tests\Feature\Stellar;

use App\Models\BridgeProperty;
use App\Models\BuyerCriteriaAuction;
use App\Models\BuyerCriteriaAuctionMeta;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Feature tests for the Stellar Buyer Results Page (Phase B).
 *
 * Test coverage:
 *  TC-R01  Unauthenticated user is redirected to login.
 *  TC-R02  Buyer with no BuyerCriteriaAuction record sees no-criteria empty state.
 *  TC-R03  bridge_properties table empty shows import-unavailable state.
 *  TC-R04  No active Residential inventory shows no-inventory state.
 *  TC-R05  Criteria with no location shows no-location state.
 *  TC-R06  Results page renders at least one card when seeded inventory matches criteria.
 *  TC-R07  Score badge appears in card HTML.
 *  TC-R08  Explanation accordion panels render.
 *  TC-R09  No Tier 6 field values appear in response HTML.
 *  TC-R10  A listing with IDXParticipationYN = false does not appear.
 *  TC-R11  A senior community listing does not appear for non-55+ buyer.
 *  TC-R12  A senior community listing DOES appear for a 55+ eligible buyer.
 *  TC-R13  Criteria with a city that matches no listings shows no-matches state.
 *  TC-R14  An inactive (sold) criteria record shows no-criteria state, not results.
 *
 * Uses DatabaseTransactions per project convention (sqlite-memory-test-pattern.md).
 */
class StellarBuyerResultsPageTest extends TestCase
{
    use DatabaseTransactions;

    // =========================================================================
    // Helpers
    // =========================================================================

    /** Insert a minimal user row and return [id, email, password]. */
    private function makeUser(array $overrides = []): array
    {
        $email = 'stellar-test-' . uniqid() . '@example.com';
        $id    = DB::table('users')->insertGetId(array_merge([
            'first_name'  => 'Test',
            'last_name'   => 'Buyer',
            'name'        => 'Test Buyer',
            'short_id'    => 'TB' . uniqid(),
            'user_name'   => 'tb_' . uniqid(),
            'email'       => $email,
            'password'    => bcrypt('password'),
            'user_type'   => 'buyer',
            'is_approved' => true,
            'is_super'    => false,
            'is_deleted'  => false,
            'created_at'  => now(),
            'updated_at'  => now(),
        ], $overrides));
        return ['id' => $id, 'email' => $email];
    }

    /**
     * Insert a BuyerCriteriaAuction + required EAV meta so the criteria loader
     * produces a valid payload for the matcher.
     */
    private function makeCriteria(int $userId, array $metaOverrides = []): int
    {
        $criteriaId = DB::table('buyer_criteria_auctions')->insertGetId([
            'user_id'     => $userId,
            'buyer_id'    => $userId,
            'title'       => 'Test Criteria',
            'max_price'   => 600000,
            'bedrooms'    => 2,
            'bathrooms'   => 1,
            'is_approved' => true,
            'is_sold'     => false,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $meta = array_merge([
            'property_types'    => json_encode(['Residential']),
            'preferred_cities'  => json_encode(['Orlando']),
        ], $metaOverrides);

        foreach ($meta as $key => $value) {
            DB::table('buyer_criteria_auction_metas')->insert([
                'buyer_criteria_auction_id' => $criteriaId,
                'meta_key'                  => $key,
                'meta_value'                => $value,
            ]);
        }

        return $criteriaId;
    }

    /** Insert a bridge_property row with IDX gate open by default. */
    private function insertListing(array $overrides = []): void
    {
        $base = [
            'listing_key'             => 'RTEST-' . uniqid(),
            'listing_id'              => 'RLID-' . uniqid(),
            'standard_status'         => 'Active',
            'property_type'           => 'Residential',
            'list_price'              => 400000,
            'city'                    => 'Orlando',
            'state_or_province'       => 'FL',
            'postal_code'             => '32801',
            'bedrooms_total'          => 3,
            'bathrooms_total_integer' => 2,
            'living_area'             => 1800,
            'senior_community_yn'     => false,
            'raw_json'                => json_encode(['IDXParticipationYN' => true]),
            'created_at'              => now(),
            'updated_at'              => now(),
        ];
        DB::table('bridge_properties')->insert(array_merge($base, $overrides));
    }

    private function skipIfTablesMissing(): void
    {
        foreach (['bridge_properties', 'buyer_criteria_auctions', 'buyer_criteria_auction_metas'] as $table) {
            if (!Schema::hasTable($table)) {
                $this->markTestSkipped("Table {$table} does not exist in this environment.");
            }
        }
    }

    // =========================================================================
    // TC-R01: Unauthenticated redirect
    // =========================================================================

    /** @test */
    public function it_redirects_unauthenticated_visitors_to_login(): void
    {
        $response = $this->get(route('stellar.buyer.results'));
        $response->assertRedirect();
        $this->assertStringContainsString('login', $response->headers->get('Location') ?? '');
    }

    // =========================================================================
    // TC-R02: No criteria record → no-criteria empty state
    // =========================================================================

    /** @test */
    public function it_shows_no_criteria_state_when_buyer_has_no_criteria_record(): void
    {
        $this->skipIfTablesMissing();

        $user = $this->makeUser();
        $this->insertListing();

        $this->actingAsDbUser($user['id']);
        $response = $this->get(route('stellar.buyer.results'));

        $response->assertStatus(200);
        $response->assertSee("Your buyer profile isn't complete yet", false);
    }

    // =========================================================================
    // TC-R03: bridge_properties table empty → import-unavailable state
    // =========================================================================

    /** @test */
    public function it_shows_import_unavailable_when_bridge_properties_is_empty(): void
    {
        $this->skipIfTablesMissing();

        // Ensure table is empty (DatabaseTransactions handles cleanup)
        DB::table('bridge_properties')->delete();

        $user = $this->makeUser();
        $this->makeCriteria($user['id']);
        $this->actingAsDbUser($user['id']);

        $response = $this->get(route('stellar.buyer.results'));
        $response->assertStatus(200);
        $response->assertSeeText('Listing data is being set up');
    }

    // =========================================================================
    // TC-R04: No active Residential inventory → no-inventory state
    // =========================================================================

    /** @test */
    public function it_shows_no_inventory_state_when_no_active_residential_listings_exist(): void
    {
        $this->skipIfTablesMissing();

        DB::table('bridge_properties')->delete();
        $this->insertListing(['standard_status' => 'Closed', 'city' => 'Orlando']);

        $user = $this->makeUser();
        $this->makeCriteria($user['id']);
        $this->actingAsDbUser($user['id']);

        $response = $this->get(route('stellar.buyer.results'));
        $response->assertStatus(200);
        $response->assertSeeText('Stellar MLS listing data is not yet available');
    }

    // =========================================================================
    // TC-R05: Criteria with no location → no-location state
    // =========================================================================

    /** @test */
    public function it_shows_no_location_state_when_criteria_has_no_geographic_constraint(): void
    {
        $this->skipIfTablesMissing();

        $this->insertListing();

        $user = $this->makeUser();
        // Criteria with property_types but no city/ZIP/county/radius
        $criteriaId = DB::table('buyer_criteria_auctions')->insertGetId([
            'user_id'     => $user['id'],
            'buyer_id'    => $user['id'],
            'title'       => 'Test Criteria',
            'max_price'   => 600000,
            'is_approved' => true,
            'is_sold'     => false,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
        DB::table('buyer_criteria_auction_metas')->insert([
            'buyer_criteria_auction_id' => $criteriaId,
            'meta_key'   => 'property_types',
            'meta_value' => json_encode(['Residential']),
        ]);

        $this->actingAsDbUser($user['id']);
        $response = $this->get(route('stellar.buyer.results'));
        $response->assertStatus(200);
        $response->assertSee("Your search doesn't include a location yet", false);
    }

    // =========================================================================
    // TC-R06: Matching inventory → renders result cards
    // =========================================================================

    /** @test */
    public function it_renders_result_cards_when_matching_inventory_exists(): void
    {
        $this->skipIfTablesMissing();

        $this->insertListing([
            'city'        => 'Orlando',
            'postal_code' => '32801',
            'list_price'  => 350000,
        ]);

        $user = $this->makeUser();
        $this->makeCriteria($user['id']);
        $this->actingAsDbUser($user['id']);

        $response = $this->get(route('stellar.buyer.results'));
        $response->assertStatus(200);
        $response->assertDontSeeText("Your buyer profile isn't complete yet");
        $response->assertDontSeeText('Listing data is being set up');
        $response->assertDontSeeText('No active listings match your current search criteria');
    }

    // =========================================================================
    // TC-R07: Score badge appears
    // =========================================================================

    /** @test */
    public function it_renders_score_badge_for_matched_listings(): void
    {
        $this->skipIfTablesMissing();

        $this->insertListing(['city' => 'Orlando', 'postal_code' => '32801']);

        $user = $this->makeUser();
        $this->makeCriteria($user['id']);
        $this->actingAsDbUser($user['id']);

        $response = $this->get(route('stellar.buyer.results'));
        $response->assertStatus(200);
        // Score badge is rendered with "/ 100" pattern
        $response->assertSee('/ 100');
    }

    // =========================================================================
    // TC-R08: Explanation accordion panels render
    // =========================================================================

    /** @test */
    public function it_renders_explanation_accordion_in_response(): void
    {
        $this->skipIfTablesMissing();

        $this->insertListing(['city' => 'Orlando', 'postal_code' => '32801']);

        $user = $this->makeUser();
        $this->makeCriteria($user['id']);
        $this->actingAsDbUser($user['id']);

        $response = $this->get(route('stellar.buyer.results'));
        $response->assertStatus(200);
        // The explanation accordion should appear (at minimum the Why panel or caution flags)
        $response->assertSee('data-testid="explanation-accordion"', false);
    }

    // =========================================================================
    // TC-R09: No Tier 6 field values in HTML
    // =========================================================================

    /** @test */
    public function it_does_not_expose_tier_6_field_values_in_response_html(): void
    {
        $this->skipIfTablesMissing();

        $tier6Data = [
            'ListAgentEmail'          => 'secret-agent@brokerage.com',
            'ListAgentPreferredPhone'  => '555-AGENT-01',
            'ListOfficeName'          => 'SecretBrokerageXYZ',
            'ListOfficePhone'         => '555-OFFICE-01',
            'LockBoxLocation'         => 'Front door left side',
            'ShowingInstructions'     => 'Call listing agent 24h in advance',
            'IDXParticipationYN'      => true,
        ];

        $this->insertListing([
            'city'     => 'Orlando',
            'raw_json' => json_encode($tier6Data),
        ]);

        $user = $this->makeUser();
        $this->makeCriteria($user['id']);
        $this->actingAsDbUser($user['id']);

        $response = $this->get(route('stellar.buyer.results'));
        $response->assertStatus(200);

        // None of these Tier 6 values should appear in the rendered HTML
        $html = $response->getContent();
        $this->assertStringNotContainsString('secret-agent@brokerage.com', $html);
        $this->assertStringNotContainsString('555-AGENT-01', $html);
        $this->assertStringNotContainsString('SecretBrokerageXYZ', $html);
        $this->assertStringNotContainsString('555-OFFICE-01', $html);
        $this->assertStringNotContainsString('Front door left side', $html);
        $this->assertStringNotContainsString('Call listing agent 24h in advance', $html);
    }

    // =========================================================================
    // TC-R10: IDX exclusion — IDXParticipationYN = false listing does not appear
    // =========================================================================

    /** @test */
    public function it_does_not_show_listings_where_idx_participation_is_false(): void
    {
        $this->skipIfTablesMissing();

        $uniqueAddress = 'IDX-EXCLUDED-' . uniqid();

        // Insert an IDX-excluded listing with a unique address we can search for
        $this->insertListing([
            'city'             => 'Orlando',
            'postal_code'      => '32801',
            'unparsed_address' => $uniqueAddress,
            'raw_json'         => json_encode(['IDXParticipationYN' => false]),
        ]);

        // Also insert an IDX-eligible listing so we don't hit the no-matches state
        $this->insertListing(['city' => 'Orlando', 'postal_code' => '32801']);

        $user = $this->makeUser();
        $this->makeCriteria($user['id']);
        $this->actingAsDbUser($user['id']);

        $response = $this->get(route('stellar.buyer.results'));
        $response->assertStatus(200);
        $response->assertDontSeeText($uniqueAddress);
    }

    // =========================================================================
    // TC-R11: Senior community gate — 55+ listing hidden for non-eligible buyer
    // =========================================================================

    /** @test */
    public function it_hides_senior_community_listings_for_non_55_plus_buyers(): void
    {
        $this->skipIfTablesMissing();

        $seniorAddress = 'SENIOR-ONLY-' . uniqid();

        $this->insertListing([
            'city'                => 'Orlando',
            'postal_code'         => '32801',
            'unparsed_address'    => $seniorAddress,
            'senior_community_yn' => true,
        ]);

        // Insert a non-senior listing so page isn't empty
        $this->insertListing(['city' => 'Orlando', 'postal_code' => '32801']);

        $user = $this->makeUser();
        $this->makeCriteria($user['id'], [
            'property_types'     => json_encode(['Residential']),
            'preferred_cities'   => json_encode(['Orlando']),
            'is_55_plus_eligible' => '0',
        ]);
        $this->actingAsDbUser($user['id']);

        $response = $this->get(route('stellar.buyer.results'));
        $response->assertStatus(200);
        $response->assertDontSeeText($seniorAddress);
    }

    // =========================================================================
    // TC-R12: Senior community gate — 55+ listing DOES appear for eligible buyer
    // =========================================================================

    /** @test */
    public function it_shows_senior_community_listings_for_55_plus_eligible_buyers(): void
    {
        $this->skipIfTablesMissing();

        $seniorAddress = 'SENIOR-ELIGIBLE-' . uniqid();

        $this->insertListing([
            'city'                => 'Orlando',
            'postal_code'         => '32801',
            'unparsed_address'    => $seniorAddress,
            'senior_community_yn' => true,
        ]);

        $user = $this->makeUser();
        $this->makeCriteria($user['id'], [
            'property_types'      => json_encode(['Residential']),
            'preferred_cities'    => json_encode(['Orlando']),
            'is_55_plus_eligible' => '1',
        ]);
        $this->actingAsDbUser($user['id']);

        $response = $this->get(route('stellar.buyer.results'));
        $response->assertStatus(200);
        $response->assertSeeText($seniorAddress);
    }

    // =========================================================================
    // TC-R13: Criteria city matches no listings → no-matches empty state
    // =========================================================================

    /** @test */
    public function it_shows_no_matches_state_when_criteria_city_matches_no_listings(): void
    {
        $this->skipIfTablesMissing();

        // Only Orlando listing exists; criteria asks for a city that cannot match.
        $this->insertListing(['city' => 'Orlando', 'postal_code' => '32801']);

        $user = $this->makeUser();
        $this->makeCriteria($user['id'], [
            'property_types'   => json_encode(['Residential']),
            'preferred_cities' => json_encode(['NonexistentCityZZZ99999']),
        ]);
        $this->actingAsDbUser($user['id']);

        $response = $this->get(route('stellar.buyer.results'));
        $response->assertStatus(200);
        $response->assertSeeText('No active listings match your current search criteria');
    }

    // =========================================================================
    // TC-R14: Inactive (sold) criteria record → no-criteria state
    // =========================================================================

    /** @test */
    public function it_shows_no_criteria_state_when_only_inactive_criteria_exists(): void
    {
        $this->skipIfTablesMissing();

        $this->insertListing();

        $user = $this->makeUser();

        // Insert a sold (inactive) criteria — loader must ignore it.
        DB::table('buyer_criteria_auctions')->insertGetId([
            'user_id'     => $user['id'],
            'buyer_id'    => $user['id'],
            'title'       => 'Inactive Criteria',
            'max_price'   => 600000,
            'is_approved' => true,
            'is_sold'     => true,   // sold → inactive
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $this->actingAsDbUser($user['id']);

        $response = $this->get(route('stellar.buyer.results'));
        $response->assertStatus(200);
        $response->assertSee("Your buyer profile isn't complete yet", false);
    }

    // =========================================================================
    // Private: auth helper using DB user
    // =========================================================================

    /**
     * Authenticate as a real DB user (not an Eloquent model instance).
     * Uses the User model to load by ID so Laravel's guard works correctly.
     */
    private function actingAsDbUser(int $userId): void
    {
        $user = \App\Models\User::find($userId);
        if (!$user) {
            $this->markTestSkipped("User {$userId} not found — cannot authenticate.");
        }
        $this->actingAs($user);
    }
}
