<?php

namespace Tests\Feature\Stellar;

use App\Models\BridgeProperty;
use App\Models\TenantAgentAuction;
use App\Services\Bridge\LazyBridgeImportService;
use App\Services\Bridge\LazyImportResult;
use App\Services\Stellar\Matching\BuyerMatchQueryBuilder;
use App\Services\Stellar\Matching\BuyerMatchResultBuilder;
use App\Services\Stellar\Matching\BuyerMatchScorer;
use App\Services\Stellar\Matching\BuyerMatchService;
use App\Services\Stellar\Matching\DTO\BuyerCriteriaPayload;
use App\Services\Stellar\TenantOfferListingCriteriaLoader;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Tenant Commercial Lease MLS Matching — regression tests.
 *
 * TC-TCLM-01  CriteriaLoader maps any 'commercial*' EAV property_type to 'Commercial Lease'.
 * TC-TCLM-02  CriteriaLoader maps a non-commercial EAV property_type to 'Residential'.
 * TC-TCLM-03  End-to-end: seeded Commercial Lease bridge record + matching offer listing → ≥1 match with score > 0.
 * TC-TCLM-04  Price filter: null list_price commercial lease record is NOT excluded when max_price is set.
 * TC-TCLM-05  Price filter: commercial lease record with list_price ≤ max_price is included.
 * TC-TCLM-06  Scorer: matching lease term awards 2 lifestyle pts.
 * TC-TCLM-07  Scorer: non-matching lease term awards 0 pts.
 * TC-TCLM-08  Scorer: no preference (empty preferredLeaseTerms) awards 0 pts (dimension inactive).
 * TC-TCLM-09  Scorer: preference set but Bridge LeaseTerm absent → neutral 2 pts (don't penalise missing data).
 *
 * Uses DatabaseTransactions per project convention (sqlite-memory-test-pattern.md).
 * TenantAgentAuction is fully guarded — created via DB::table()->insertGetId() + findOrFail().
 * 'Commercial Lease' confirmed as the exact Bridge OData PropertyType string (live import 2026-06-23).
 */
class TenantCommercialLeaseMatchingTest extends TestCase
{
    use DatabaseTransactions;

    // =========================================================================
    // Helpers
    // =========================================================================

    private function skipIfTableMissing(string ...$tables): void
    {
        $needed = empty($tables)
            ? ['bridge_properties', 'tenant_agent_auctions', 'tenant_agent_auction_metas']
            : $tables;

        foreach ($needed as $table) {
            if (!Schema::hasTable($table)) {
                $this->markTestSkipped("Table {$table} does not exist in this environment.");
            }
        }
    }

    private function makeService(?LazyBridgeImportService $lazyImport = null): BuyerMatchService
    {
        if ($lazyImport === null) {
            $lazyImport = $this->createMock(LazyBridgeImportService::class);
            $lazyImport->method('importForCriteria')->willReturn(LazyImportResult::cached(0));
        }

        return new BuyerMatchService(
            new BuyerMatchQueryBuilder(),
            new BuyerMatchScorer(),
            new BuyerMatchResultBuilder(),
            $lazyImport,
        );
    }

    private function makeUser(): int
    {
        return DB::table('users')->insertGetId([
            'first_name'  => 'TCLM',
            'last_name'   => 'Test',
            'name'        => 'TCLM Test',
            'short_id'    => 'TCLM' . uniqid(),
            'user_name'   => 'tclm_' . uniqid(),
            'email'       => 'tclm-' . uniqid() . '@example.com',
            'password'    => bcrypt('password'),
            'user_type'   => 'tenant',
            'is_approved' => true,
            'is_super'    => false,
            'is_deleted'  => false,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    /**
     * Create a TenantAgentAuction offer listing record with the given EAV meta.
     * Returns the loaded Eloquent model.
     */
    private function makeTenantOfferListing(int $userId, array $meta = []): TenantAgentAuction
    {
        $id = DB::table('tenant_agent_auctions')->insertGetId([
            'user_id'         => $userId,
            'is_approved'     => true,
            'is_draft'        => false,
            'is_sold'         => false,
            'auction_ended'   => false,
            'referral_locked' => false,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $auction = TenantAgentAuction::findOrFail($id);

        $auction->saveMeta('workflow_type', 'offer_listing');

        foreach ($meta as $key => $value) {
            $auction->saveMeta($key, $value);
        }

        return $auction->fresh();
    }

    private function insertBridgeListing(array $overrides = []): string
    {
        $key = 'TCLM-' . uniqid();
        DB::table('bridge_properties')->insert(array_merge([
            'listing_key'             => $key,
            'listing_id'              => 'TCLM-LID-' . uniqid(),
            'standard_status'         => 'Active',
            'property_type'           => 'Commercial Lease',
            'list_price'              => 3500.00,
            'city'                    => 'Tampa',
            'state_or_province'       => 'FL',
            'postal_code'             => '33614',
            'senior_community_yn'     => null,
            'raw_json'                => json_encode(['IDXParticipationYN' => true]),
            'created_at'              => now(),
            'updated_at'              => now(),
        ], $overrides));
        return $key;
    }

    /**
     * Build a BridgeProperty Eloquent model from the database for scorer tests.
     */
    private function insertAndLoadBridgeProperty(array $overrides = []): BridgeProperty
    {
        $key = $this->insertBridgeListing($overrides);
        return BridgeProperty::where('listing_key', $key)->firstOrFail();
    }

    private function makeCriteria(array $overrides = []): BuyerCriteriaPayload
    {
        return new BuyerCriteriaPayload(array_merge([
            'property_types'      => ['Commercial Lease'],
            'is_55_plus_eligible' => false,
        ], $overrides));
    }

    // =========================================================================
    // TC-TCLM-01: CriteriaLoader maps 'commercial*' EAV property_type to 'Commercial Lease'
    // =========================================================================

    public function test_tc_tclm_01_commercial_property_type_maps_to_bridge_commercial_lease(): void
    {
        $this->skipIfTableMissing();

        $userId  = $this->makeUser();
        $auction = $this->makeTenantOfferListing($userId, [
            'property_type' => 'Commercial Lease Tenant',
        ]);

        $loader = new TenantOfferListingCriteriaLoader();
        $result = $loader->loadById($auction->id, [$userId]);

        $this->assertNotNull($result, 'loadById must return a non-null array for an approved offer listing');
        $this->assertArrayHasKey('property_types', $result);
        $this->assertContains(
            'Commercial Lease',
            $result['property_types'],
            'EAV property_type containing "commercial" must map to the Bridge OData string "Commercial Lease"'
        );
    }

    // Additional commercial type variant: bare 'commercial' (case-insensitive)
    public function test_tc_tclm_01b_lowercase_commercial_maps_to_commercial_lease(): void
    {
        $this->skipIfTableMissing();

        $userId  = $this->makeUser();
        $auction = $this->makeTenantOfferListing($userId, [
            'property_type' => 'commercial',
        ]);

        $loader = new TenantOfferListingCriteriaLoader();
        $result = $loader->loadById($auction->id, [$userId]);

        $this->assertNotNull($result);
        $this->assertContains('Commercial Lease', $result['property_types']);
    }

    // =========================================================================
    // TC-TCLM-02: Non-commercial EAV property_type maps to 'Residential'
    // =========================================================================

    public function test_tc_tclm_02_non_commercial_property_type_maps_to_residential(): void
    {
        $this->skipIfTableMissing();

        $userId  = $this->makeUser();
        $auction = $this->makeTenantOfferListing($userId, [
            'property_type' => 'Residential Property',
        ]);

        $loader = new TenantOfferListingCriteriaLoader();
        $result = $loader->loadById($auction->id, [$userId]);

        $this->assertNotNull($result);
        $this->assertContains(
            'Residential',
            $result['property_types'],
            'Non-commercial EAV property_type must map to "Residential"'
        );
        $this->assertNotContains('Commercial Lease', $result['property_types']);
    }

    // =========================================================================
    // TC-TCLM-03: End-to-end — Commercial Lease bridge record + offer listing → ≥1 match
    // =========================================================================

    public function test_tc_tclm_03_e2e_commercial_lease_listing_matches_offer_listing(): void
    {
        $this->skipIfTableMissing();

        $bridgeKey = $this->insertBridgeListing([
            'city'        => 'Tampa',
            'postal_code' => '33614',
            'list_price'  => 3500.00,
        ]);

        $userId  = $this->makeUser();
        $auction = $this->makeTenantOfferListing($userId, [
            'property_type' => 'Commercial Lease Tenant',
            'zipCodes'      => json_encode(['33614']),
        ]);

        $loader   = new TenantOfferListingCriteriaLoader();
        $criteria = $loader->loadById($auction->id, [$userId]);

        $this->assertNotNull($criteria, 'Criteria loader must return a non-null payload');

        $payload = new BuyerCriteriaPayload($criteria);
        $results = $this->makeService()->match($payload);

        $this->assertGreaterThanOrEqual(
            1,
            $results->count(),
            'At least one Commercial Lease result must be returned for a matching city/ZIP'
        );

        $matchedKeys = $results->pluck('listingKey')->all();
        $this->assertContains(
            $bridgeKey,
            $matchedKeys,
            'The seeded Commercial Lease bridge record must appear in match results'
        );

        $matchedResult = $results->first(fn($r) => $r->listingKey === $bridgeKey);
        $this->assertNotNull($matchedResult);
        $this->assertGreaterThan(0, $matchedResult->totalScore, 'Matched result must have a score > 0');
    }

    // =========================================================================
    // TC-TCLM-04: Price filter — null list_price is NOT excluded when max_price is set
    // =========================================================================

    public function test_tc_tclm_04_null_list_price_passes_through_price_ceiling(): void
    {
        $this->skipIfTableMissing('bridge_properties');

        $nullPriceKey = $this->insertBridgeListing([
            'list_price' => null,
            'city'       => 'Tampa',
        ]);

        $criteria = $this->makeCriteria([
            'max_price'        => 5000,
            'preferred_cities' => ['Tampa'],
        ]);

        $results = $this->makeService()->match($criteria);

        $keys = $results->pluck('listingKey')->all();

        $this->assertContains(
            $nullPriceKey,
            $keys,
            'A Commercial Lease listing with null list_price must NOT be excluded when max_price is set (orWhereNull passthrough)'
        );
    }

    // =========================================================================
    // TC-TCLM-05: Price filter — list_price ≤ max_price is included
    // =========================================================================

    public function test_tc_tclm_05_list_price_within_ceiling_is_included(): void
    {
        $this->skipIfTableMissing('bridge_properties');

        $withinKey = $this->insertBridgeListing([
            'list_price' => 3500.00,
            'city'       => 'Tampa',
        ]);
        $overKey = $this->insertBridgeListing([
            'listing_key' => 'TCLM-OVER-' . uniqid(),
            'listing_id'  => 'TCLM-OVER-LID-' . uniqid(),
            'list_price'  => 6000.00,
            'city'        => 'Tampa',
        ]);

        $criteria = $this->makeCriteria([
            'max_price'        => 5000,
            'preferred_cities' => ['Tampa'],
        ]);

        $results = $this->makeService()->match($criteria);
        $keys    = $results->pluck('listingKey')->all();

        $this->assertContains($withinKey, $keys, 'list_price ≤ max_price must be included');
        $this->assertNotContains($overKey, $keys, 'list_price > max_price must be excluded');
    }

    // =========================================================================
    // TC-TCLM-06: Scorer — matching lease term awards 2 lifestyle pts
    // =========================================================================

    public function test_tc_tclm_06_matching_lease_term_awards_two_lifestyle_points(): void
    {
        $this->skipIfTableMissing('bridge_properties');

        $listing = $this->insertAndLoadBridgeProperty([
            'raw_json' => json_encode([
                'IDXParticipationYN' => true,
                'LeaseTerm'          => '24 Months',
            ]),
        ]);

        $criteria = $this->makeCriteria([
            'preferred_lease_terms' => ['2 Years'],
        ]);

        $scorer = new BuyerMatchScorer();
        $result = $scorer->score($listing, $criteria);

        $this->assertSame(
            2,
            $result->categoryScores['lifestyle'],
            'Matching lease term ("2 Years" vs "24 Months") must award 2 lifestyle pts'
        );
    }

    // =========================================================================
    // TC-TCLM-07: Scorer — non-matching lease term awards 0 pts
    // =========================================================================

    public function test_tc_tclm_07_non_matching_lease_term_awards_zero_points(): void
    {
        $this->skipIfTableMissing('bridge_properties');

        $listing = $this->insertAndLoadBridgeProperty([
            'raw_json' => json_encode([
                'IDXParticipationYN' => true,
                'LeaseTerm'          => '12 Months',
            ]),
        ]);

        $criteria = $this->makeCriteria([
            'preferred_lease_terms' => ['2 Years'],
        ]);

        $scorer = new BuyerMatchScorer();
        $result = $scorer->score($listing, $criteria);

        $this->assertSame(
            0,
            $result->categoryScores['lifestyle'],
            'Non-matching lease term ("2 Years" preference vs "12 Months" listing) must award 0 lifestyle pts'
        );
    }

    // =========================================================================
    // TC-TCLM-08: Scorer — no lease-term preference → dimension inactive (0 pts)
    //             Preserves pre-existing buyer/residential scoring unchanged.
    // =========================================================================

    public function test_tc_tclm_08_no_lease_term_preference_dimension_is_inactive(): void
    {
        $this->skipIfTableMissing('bridge_properties');

        $listing = $this->insertAndLoadBridgeProperty([
            'raw_json' => json_encode([
                'IDXParticipationYN' => true,
                'LeaseTerm'          => '24 Months',
            ]),
        ]);

        $criteria = $this->makeCriteria([
            'preferred_lease_terms' => [],
        ]);

        $scorer = new BuyerMatchScorer();
        $result = $scorer->score($listing, $criteria);

        $this->assertSame(
            0,
            $result->categoryScores['lifestyle'],
            'When preferredLeaseTerms is empty, the lease-term dimension must be inactive (0 pts) — preserving pre-existing buyer/residential scoring'
        );
    }

    // =========================================================================
    // TC-TCLM-09: Scorer — preference set but Bridge LeaseTerm absent → neutral 2 pts
    // =========================================================================

    public function test_tc_tclm_09_missing_bridge_lease_term_awards_neutral_two_points(): void
    {
        $this->skipIfTableMissing('bridge_properties');

        $listing = $this->insertAndLoadBridgeProperty([
            'raw_json' => json_encode([
                'IDXParticipationYN' => true,
            ]),
        ]);

        $criteria = $this->makeCriteria([
            'preferred_lease_terms' => ['1 Year'],
        ]);

        $scorer = new BuyerMatchScorer();
        $result = $scorer->score($listing, $criteria);

        $this->assertSame(
            2,
            $result->categoryScores['lifestyle'],
            'When preference is set but Bridge listing has no LeaseTerm, neutral 2 pts must be awarded (do not penalise missing data)'
        );
    }

    // =========================================================================
    // TC-TCLM-09b: Scorer — month-to-month preference matches Bridge "Month-to-Month"
    // =========================================================================

    public function test_tc_tclm_09b_month_to_month_lease_term_matches(): void
    {
        $this->skipIfTableMissing('bridge_properties');

        $listing = $this->insertAndLoadBridgeProperty([
            'raw_json' => json_encode([
                'IDXParticipationYN' => true,
                'LeaseTerm'          => 'Month-to-Month',
            ]),
        ]);

        $criteria = $this->makeCriteria([
            'preferred_lease_terms' => ['Month-to-Month'],
        ]);

        $scorer = new BuyerMatchScorer();
        $result = $scorer->score($listing, $criteria);

        $this->assertSame(
            2,
            $result->categoryScores['lifestyle'],
            '"Month-to-Month" preference must match Bridge "Month-to-Month" LeaseTerm for 2 pts'
        );
    }
}
