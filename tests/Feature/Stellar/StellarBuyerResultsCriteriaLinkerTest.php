<?php

namespace Tests\Feature\Stellar;

use App\Models\BuyerCriteriaAuction;
use App\Models\TenantCriteriaAuction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Feature tests for the Criteria Linker (Task: Link Buyer/Tenant Criteria to MLS Matches).
 *
 *  TC-CL01  Agent with zero accessible criteria → no_criteria_listings empty state.
 *  TC-CL02  Agent with exactly one BuyerCriteriaAuction → auto-loads, no selector shown.
 *  TC-CL03  Agent with exactly one TenantCriteriaAuction → auto-loads, runs through pipeline.
 *  TC-CL04  Agent with multiple criteria (mixed) → select_criteria state with full list.
 *  TC-CL05  Non-agent buyer with one active BuyerCriteriaAuction → existing auto-load regression.
 *  TC-CL06  Request with criteria_id owned by another user → safe empty state, no data leak.
 *  TC-CL07  Buyer with multiple criteria profiles → switcher strip present with both IDs.
 *  TC-CL08  Agent sees client's buyer criteria via user_agents relationship.
 *  TC-CL09  Tenant Criteria → BuyerCriteriaPayload → BuyerMatchService → results pipeline.
 *           Proves the full path (DTO mapping + matching engine) runs without error.
 *
 * Uses DatabaseTransactions per project convention (sqlite-memory-test-pattern.md).
 */
class StellarBuyerResultsCriteriaLinkerTest extends TestCase
{
    use DatabaseTransactions;

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeUser(array $overrides = []): array
    {
        $email = 'cl-test-' . uniqid() . '@example.com';
        $id    = DB::table('users')->insertGetId(array_merge([
            'first_name'  => 'CL',
            'last_name'   => 'Test',
            'name'        => 'CL Test',
            'short_id'    => 'CLT' . uniqid(),
            'user_name'   => 'clt_' . uniqid(),
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

    private function makeBuyerCriteria(int $userId, array $metaOverrides = []): int
    {
        $id = DB::table('buyer_criteria_auctions')->insertGetId([
            'user_id'     => $userId,
            'buyer_id'    => $userId,
            'title'       => 'Test Buyer Criteria',
            'max_price'   => 500000,
            'bedrooms'    => 2,
            'bathrooms'   => 1,
            'is_approved' => true,
            'is_sold'     => false,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $meta = array_merge([
            'property_types'   => json_encode(['Residential']),
            'preferred_cities' => json_encode(['Orlando']),
        ], $metaOverrides);

        foreach ($meta as $key => $value) {
            DB::table('buyer_criteria_auction_metas')->insert([
                'buyer_criteria_auction_id' => $id,
                'meta_key'                  => $key,
                'meta_value'                => $value,
            ]);
        }

        return $id;
    }

    private function makeTenantCriteria(int $userId, array $metaOverrides = []): int
    {
        if (!Schema::hasTable('tenant_criteria_auctions')) {
            return -1;
        }

        $id = DB::table('tenant_criteria_auctions')->insertGetId([
            'user_id'     => $userId,
            'is_approved' => true,
            'is_sold'     => false,
            'is_draft'    => false,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $meta = array_merge([
            'property_type' => 'Residential Property',
            'cities'        => json_encode(['Orlando']),
            'monthly_price' => '2000',
        ], $metaOverrides);

        foreach ($meta as $key => $value) {
            DB::table('tenant_criteria_auction_metas')->insert([
                'tenant_criteria_auction_id' => $id,
                'meta_key'                   => $key,
                'meta_value'                 => $value,
            ]);
        }

        return $id;
    }

    private function insertListing(array $overrides = []): void
    {
        DB::table('bridge_properties')->insert(array_merge([
            'listing_key'             => 'CLTEST-' . uniqid(),
            'listing_id'              => 'CLLID-' . uniqid(),
            'standard_status'         => 'Active',
            'property_type'           => 'Residential',
            'list_price'              => 350000,
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
        ], $overrides));
    }

    private function actingAsDbUser(int $userId): void
    {
        $user = \App\Models\User::find($userId);
        if (!$user) {
            $this->markTestSkipped("User {$userId} not found.");
        }
        $this->actingAs($user);
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
    // TC-CL01: Agent with zero accessible criteria → no_criteria_listings state
    // =========================================================================

    /** @test */
    public function agent_with_no_criteria_sees_no_criteria_listings_state(): void
    {
        $this->skipIfTablesMissing();

        $this->insertListing();

        $agent = $this->makeUser(['user_type' => 'agent']);
        $this->actingAsDbUser($agent['id']);

        $response = $this->get(route('stellar.buyer.results'));

        $response->assertStatus(200);
        $response->assertSee('No Buyer or Tenant Criteria listings found', false);
    }

    // =========================================================================
    // TC-CL02: Agent with one BuyerCriteriaAuction → auto-loads, no selector shown
    // =========================================================================

    /** @test */
    public function agent_with_one_buyer_criteria_auto_loads_without_selector(): void
    {
        $this->skipIfTablesMissing();

        $this->insertListing(['city' => 'Orlando', 'list_price' => 350000]);

        $agent = $this->makeUser(['user_type' => 'agent']);
        $this->makeBuyerCriteria($agent['id']);
        $this->actingAsDbUser($agent['id']);

        $response = $this->get(route('stellar.buyer.results'));

        $response->assertStatus(200);
        // Should NOT show the criteria-choice selector
        $response->assertDontSee('Choose a Criteria Profile', false);
        // Should NOT show the no_criteria_listings state
        $response->assertDontSee('No Buyer or Tenant Criteria listings found', false);
        // Should be on a matching pipeline path (results, no_matches, or no_location)
        $response->assertDontSee('select_criteria', false);
    }

    // =========================================================================
    // TC-CL03: Agent with one TenantCriteriaAuction → auto-loads through pipeline
    // =========================================================================

    /** @test */
    public function agent_with_one_tenant_criteria_auto_loads_through_pipeline(): void
    {
        $this->skipIfTablesMissing();

        if (!Schema::hasTable('tenant_criteria_auctions') || !Schema::hasTable('tenant_criteria_auction_metas')) {
            $this->markTestSkipped('tenant_criteria_auctions table does not exist in this environment.');
        }

        $this->insertListing(['city' => 'Orlando', 'list_price' => 1500]);

        $agent = $this->makeUser(['user_type' => 'agent']);
        $this->makeTenantCriteria($agent['id']);
        $this->actingAsDbUser($agent['id']);

        $response = $this->get(route('stellar.buyer.results'));

        $response->assertStatus(200);
        // Must not show the criteria selector (one auto-selected)
        $response->assertDontSee('Choose a Criteria Profile', false);
        // Must not show no_criteria_listings (we have a tenant criteria)
        $response->assertDontSee('No Buyer or Tenant Criteria listings found', false);
    }

    // =========================================================================
    // TC-CL04: Agent with multiple criteria (mixed) → select_criteria state
    // =========================================================================

    /** @test */
    public function agent_with_multiple_criteria_sees_select_criteria_state(): void
    {
        $this->skipIfTablesMissing();

        if (!Schema::hasTable('tenant_criteria_auctions') || !Schema::hasTable('tenant_criteria_auction_metas')) {
            $this->markTestSkipped('tenant_criteria_auctions table does not exist in this environment.');
        }

        $this->insertListing();

        $agent = $this->makeUser(['user_type' => 'agent']);
        $this->makeBuyerCriteria($agent['id']);
        $this->makeTenantCriteria($agent['id']);
        $this->actingAsDbUser($agent['id']);

        $response = $this->get(route('stellar.buyer.results'));

        $response->assertStatus(200);
        $response->assertSee('Choose a Criteria Profile', false);
        // Both profiles should appear in the list
        $response->assertSee('Buyer Criteria', false);
        $response->assertSee('Tenant Criteria', false);
    }

    // =========================================================================
    // TC-CL05: Non-agent buyer with one BuyerCriteriaAuction → regression test
    // =========================================================================

    /** @test */
    public function non_agent_buyer_with_active_criteria_auto_loads_results(): void
    {
        $this->skipIfTablesMissing();

        $this->insertListing(['city' => 'Orlando', 'list_price' => 350000]);

        $buyer = $this->makeUser(['user_type' => 'buyer']);
        $this->makeBuyerCriteria($buyer['id']);
        $this->actingAsDbUser($buyer['id']);

        $response = $this->get(route('stellar.buyer.results'));

        $response->assertStatus(200);
        // Should not fall into the select_criteria state
        $response->assertDontSee('Choose a Criteria Profile', false);
        // Should not be the no_criteria_listings state
        $response->assertDontSee('No Buyer or Tenant Criteria listings found', false);
    }

    // =========================================================================
    // TC-CL07: Explicit criteria_id selection with multiple profiles → switcher visible
    // =========================================================================

    /** @test */
    public function explicit_criteria_selection_shows_switcher_when_multiple_profiles_exist(): void
    {
        $this->skipIfTablesMissing();

        $this->insertListing(['city' => 'Orlando', 'list_price' => 350000]);

        $buyer = $this->makeUser(['user_type' => 'buyer']);
        $criteriaId1 = $this->makeBuyerCriteria($buyer['id']);
        $criteriaId2 = $this->makeBuyerCriteria($buyer['id'], [
            'property_types'   => json_encode(['Residential']),
            'preferred_cities' => json_encode(['Orlando']),
        ]);
        $this->actingAsDbUser($buyer['id']);

        $response = $this->get(route('stellar.buyer.results', [
            'criteria_type' => 'buyer',
            'criteria_id'   => $criteriaId1,
        ]));

        $response->assertStatus(200);
        // The switcher strip must render because the user has more than one profile
        $response->assertSee('Switch profile:', false);
        // Both criteria should appear in the switcher dropdown
        $response->assertSee('criteria_id=' . $criteriaId2, false);
    }

    // =========================================================================
    // TC-CL08: Agent with a hired buyer client sees client's criteria in list
    // =========================================================================

    /** @test */
    public function agent_sees_client_buyer_criteria_via_user_agents_relationship(): void
    {
        $this->skipIfTablesMissing();

        if (!Schema::hasTable('user_agents')) {
            $this->markTestSkipped('user_agents table does not exist in this environment.');
        }

        $this->insertListing(['city' => 'Orlando', 'list_price' => 350000]);

        // Client owns the criteria, NOT the agent
        $client = $this->makeUser(['user_type' => 'buyer']);
        $criteriaId = $this->makeBuyerCriteria($client['id']);

        // Agent is hired by the client (user_agents relationship)
        $agent = $this->makeUser(['user_type' => 'agent']);
        DB::table('user_agents')->insert([
            'user_id'    => $client['id'],
            'agent_id'   => $agent['id'],
            'type'       => 'buyer',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAsDbUser($agent['id']);

        // Agent requests with no explicit criteria_id — should auto-resolve client's criteria
        $response = $this->get(route('stellar.buyer.results'));

        $response->assertStatus(200);
        // Agent should NOT see the no_criteria_listings state — they can access client criteria
        $response->assertDontSee('No Buyer or Tenant Criteria listings found', false);
    }

    // =========================================================================
    // TC-CL06: Request with criteria_id owned by another user → safe empty state
    // =========================================================================

    /** @test */
    public function request_with_foreign_criteria_id_returns_safe_empty_state(): void
    {
        $this->skipIfTablesMissing();

        $this->insertListing();

        // User A owns the criteria
        $ownerUser = $this->makeUser(['user_type' => 'buyer']);
        $criteriaId = $this->makeBuyerCriteria($ownerUser['id']);

        // User B tries to access User A's criteria
        $attackerUser = $this->makeUser(['user_type' => 'buyer']);
        $this->actingAsDbUser($attackerUser['id']);

        $response = $this->get(route('stellar.buyer.results', [
            'criteria_type' => 'buyer',
            'criteria_id'   => $criteriaId,
        ]));

        $response->assertStatus(200);
        // Must show safe empty state — no data from the other user's criteria
        $response->assertSee('No Buyer or Tenant Criteria listings found', false);
        // Must not show result cards (no data leakage — score badges only appear in result cards)
        $response->assertDontSee('data-testid="score-badge"', false);
    }

    // =========================================================================
    // TC-CL09: Tenant Criteria → BuyerCriteriaPayload → BuyerMatchService pipeline
    //
    // Proves the end-to-end path:
    //   TenantCriteriaAuction EAV → TenantCriteriaLoader → BuyerCriteriaPayload
    //   → BuyerMatchService::match() → controller renders results or no_matches
    //
    // If the DTO mapping fails, BuyerCriteriaPayload throws InvalidArgumentException
    // and the controller returns no_criteria state. This test asserts neither
    // no_criteria nor an error state is produced, confirming the pipeline ran cleanly.
    // =========================================================================

    /** @test */
    public function tenant_criteria_produces_valid_payload_and_runs_through_match_pipeline(): void
    {
        $this->skipIfTablesMissing();

        if (!Schema::hasTable('tenant_criteria_auctions') || !Schema::hasTable('tenant_criteria_auction_metas')) {
            $this->markTestSkipped('tenant_criteria_auctions table does not exist in this environment.');
        }

        // Seed a matching Active Residential listing in Orlando
        $this->insertListing([
            'city'                    => 'Tampa',
            'list_price'              => 1800,
            'standard_status'         => 'Active',
            'property_type'           => 'Residential',
            'bedrooms_total'          => 2,
            'bathrooms_total_integer' => 1,
        ]);

        $agent = $this->makeUser(['user_type' => 'agent']);

        // Create a TenantCriteriaAuction with city + price that aligns with the listing
        $criteriaId = $this->makeTenantCriteria($agent['id'], [
            'property_type' => 'Residential Property',
            'cities'        => json_encode(['Tampa']),
            'monthly_price' => '2000',
            'bedrooms'      => '2',
            'bathrooms'     => '1',
        ]);

        $this->actingAsDbUser($agent['id']);

        $response = $this->get(route('stellar.buyer.results', [
            'criteria_type' => 'tenant',
            'criteria_id'   => $criteriaId,
        ]));

        $response->assertStatus(200);

        // Must NOT show states that indicate the DTO failed or criteria were not found
        $response->assertDontSee('Your buyer profile isn&#039;t complete yet', false);
        $response->assertDontSee('No Buyer or Tenant Criteria listings found', false);
        $response->assertDontSee('The selected criteria profile isn', false);

        // Must show one of the valid pipeline outcome states:
        //  - result cards (matched), OR
        //  - no_matches (ran the engine, city matched but no score threshold), OR
        //  - no_location (should NOT happen — we seeded cities), OR
        //  - Listing data is being set up (should NOT happen — we seeded a listing)
        //
        // The presence of "Your Matched Listings" confirms the page rendered successfully.
        $response->assertSee('Your Matched Listings', false);
    }
}
