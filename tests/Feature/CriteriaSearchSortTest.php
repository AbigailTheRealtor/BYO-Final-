<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Regression tests for PostgreSQL-compatible sort on Buyer and Tenant Criteria search pages.
 *
 * Covers:
 *  - Default (no sort param) uses inRandomOrder() — no crash on PostgreSQL
 *  - Buyer sort=1 (title DESC), sort=2 (title ASC), sort=3 (created_at DESC), sort=4 (created_at ASC)
 *  - Tenant sort=1 (property_type DESC subquery), sort=2 (ASC), sort=3 (created_at DESC),
 *    sort=4 (created_at ASC), sort=5 (max_price DESC subquery), sort=6 (ASC)
 *  - Unknown/out-of-range sort falls through to inRandomOrder() without crash
 *
 * Tests that depend on tables currently absent from this environment (buyer_criteria_auction_metas,
 * tenant_criteria_auctions, tenant_criteria_auction_metas) are skipped automatically.
 * Those tables will be created by the schema-remediation task; once present all tests run fully.
 */
class CriteriaSearchSortTest extends TestCase
{
    use DatabaseTransactions;

    // ─────────────────────────────────────────────────────────────────────────
    // Skip guards
    // ─────────────────────────────────────────────────────────────────────────

    private function requireBuyerMetaTable(): void
    {
        if (! Schema::hasTable('buyer_criteria_auction_metas')) {
            $this->markTestSkipped('buyer_criteria_auction_metas table absent — will run after schema-remediation task.');
        }
    }

    private function requireTenantTables(): void
    {
        if (! Schema::hasTable('tenant_criteria_auctions') || ! Schema::hasTable('tenant_criteria_auction_metas')) {
            $this->markTestSkipped('tenant_criteria_auctions / tenant_criteria_auction_metas absent — will run after schema-remediation task.');
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function makeBuyerListing(array $override = []): int
    {
        $user = User::factory()->create(['user_type' => 'buyer']);

        return DB::table('buyer_criteria_auctions')->insertGetId(array_merge([
            'user_id'     => $user->id,
            'buyer_id'    => $user->id,
            'max_price'   => 350000,
            'title'       => 'Criteria Sort Test Listing',
            'is_approved' => true,
            'is_sold'     => false,
            'created_at'  => now(),
            'updated_at'  => now(),
        ], $override));
    }

    private function makeTenantListing(array $metaRows = []): int
    {
        $user = User::factory()->create(['user_type' => 'tenant']);

        $id = DB::table('tenant_criteria_auctions')->insertGetId([
            'user_id'     => $user->id,
            'is_approved' => true,
            'is_sold'     => false,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        foreach ($metaRows as $key => $value) {
            DB::table('tenant_criteria_auction_metas')->insert([
                'tenant_criteria_auction_id' => $id,
                'meta_key'   => $key,
                'meta_value' => $value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $id;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Buyer Criteria — /search/buyer-criteria-auctions
    // ─────────────────────────────────────────────────────────────────────────

    public function test_buyer_criteria_search_default_no_sort_returns_200(): void
    {
        $this->requireBuyerMetaTable();
        $this->makeBuyerListing();

        $response = $this->get(route('buyer.criteria.searchListing'));

        $response->assertStatus(200);
        $response->assertViewIs('buyer_criteria.search');
    }

    public function test_buyer_criteria_search_sort1_title_desc_returns_200_and_uses_title_column(): void
    {
        $this->requireBuyerMetaTable();
        $this->makeBuyerListing(['title' => 'Alpha Listing']);
        $this->makeBuyerListing(['title' => 'Zeta Listing']);

        DB::enableQueryLog();
        $response = $this->get(route('buyer.criteria.searchListing', ['sort' => 1]));
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $response->assertStatus(200);
        $response->assertViewIs('buyer_criteria.search');

        $orderSql = collect($queries)->first(fn($q) => str_contains(strtolower($q['query']), 'order by'));
        $this->assertNotNull($orderSql, 'Expected an ORDER BY query');
        $this->assertStringContainsString('"title"', strtolower($orderSql['query']));
        $this->assertStringContainsString('desc', strtolower($orderSql['query']));
        $this->assertStringNotContainsString('"address"', strtolower($orderSql['query']));
    }

    public function test_buyer_criteria_search_sort2_title_asc_returns_200_and_uses_title_column(): void
    {
        $this->requireBuyerMetaTable();
        $this->makeBuyerListing(['title' => 'Alpha Listing']);
        $this->makeBuyerListing(['title' => 'Zeta Listing']);

        DB::enableQueryLog();
        $response = $this->get(route('buyer.criteria.searchListing', ['sort' => 2]));
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $response->assertStatus(200);
        $this->assertStringContainsString('"title"', strtolower(
            collect($queries)->first(fn($q) => str_contains(strtolower($q['query']), 'order by'))['query'] ?? ''
        ));
        $this->assertStringNotContainsString('"address"', strtolower(
            collect($queries)->first(fn($q) => str_contains(strtolower($q['query']), 'order by'))['query'] ?? ''
        ));
    }

    public function test_buyer_criteria_search_sort3_created_at_desc_returns_200(): void
    {
        $this->requireBuyerMetaTable();
        $this->makeBuyerListing();

        $response = $this->get(route('buyer.criteria.searchListing', ['sort' => 3]));

        $response->assertStatus(200);
        $response->assertViewIs('buyer_criteria.search');
    }

    public function test_buyer_criteria_search_sort4_created_at_asc_returns_200(): void
    {
        $this->requireBuyerMetaTable();
        $this->makeBuyerListing();

        $response = $this->get(route('buyer.criteria.searchListing', ['sort' => 4]));

        $response->assertStatus(200);
        $response->assertViewIs('buyer_criteria.search');
    }

    public function test_buyer_criteria_search_unknown_sort_falls_through_to_random_order(): void
    {
        $this->requireBuyerMetaTable();
        $this->makeBuyerListing();

        DB::enableQueryLog();
        $response = $this->get(route('buyer.criteria.searchListing', ['sort' => 99]));
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $response->assertStatus(200);

        $orderSql = collect($queries)->first(fn($q) => str_contains(strtolower($q['query']), 'order by'));
        $this->assertNotNull($orderSql, 'Expected an ORDER BY (RANDOM()) query for unknown sort');
        $this->assertStringContainsString('random()', strtolower($orderSql['query']));
    }

    public function test_buyer_criteria_search_returns_count_in_view(): void
    {
        $this->requireBuyerMetaTable();
        $this->makeBuyerListing();
        $this->makeBuyerListing();

        $response = $this->get(route('buyer.criteria.searchListing'));

        $response->assertStatus(200);
        $response->assertViewHas('count');
        $this->assertGreaterThanOrEqual(2, $response->viewData('count'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Tenant Criteria — /tenant/criteria/auctions/search
    // ─────────────────────────────────────────────────────────────────────────

    public function test_tenant_criteria_search_default_no_sort_returns_200(): void
    {
        $this->requireTenantTables();
        $this->makeTenantListing(['property_type' => 'Single Family', 'max_price' => '2500']);

        $response = $this->get(route('tenant.criteria.auctions.search'));

        $response->assertStatus(200);
        $response->assertViewIs('tenant_criteria.search');
    }

    public function test_tenant_criteria_search_sort1_uses_property_type_subquery_desc(): void
    {
        $this->requireTenantTables();
        $this->makeTenantListing(['property_type' => 'Condo']);
        $this->makeTenantListing(['property_type' => 'Single Family']);

        DB::enableQueryLog();
        $response = $this->get(route('tenant.criteria.auctions.search', ['sort' => 1]));
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $response->assertStatus(200);
        $response->assertViewIs('tenant_criteria.search');

        $orderSql = collect($queries)->first(fn($q) => str_contains(strtolower($q['query']), 'order by'));
        $this->assertNotNull($orderSql, 'Expected an ORDER BY query');
        $this->assertStringContainsString("meta_key = 'property_type'", $orderSql['query']);
        $this->assertStringContainsString('desc', strtolower($orderSql['query']));
        $this->assertStringNotContainsString('"address"', strtolower($orderSql['query']));
    }

    public function test_tenant_criteria_search_sort2_uses_property_type_subquery_asc(): void
    {
        $this->requireTenantTables();
        $this->makeTenantListing(['property_type' => 'Condo']);
        $this->makeTenantListing(['property_type' => 'Single Family']);

        DB::enableQueryLog();
        $response = $this->get(route('tenant.criteria.auctions.search', ['sort' => 2]));
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $response->assertStatus(200);
        $this->assertStringContainsString("meta_key = 'property_type'",
            collect($queries)->first(fn($q) => str_contains(strtolower($q['query']), 'order by'))['query'] ?? ''
        );
        $this->assertStringNotContainsString('"address"', strtolower(
            collect($queries)->first(fn($q) => str_contains(strtolower($q['query']), 'order by'))['query'] ?? ''
        ));
    }

    public function test_tenant_criteria_search_sort3_created_at_desc_returns_200(): void
    {
        $this->requireTenantTables();
        $this->makeTenantListing();

        $response = $this->get(route('tenant.criteria.auctions.search', ['sort' => 3]));

        $response->assertStatus(200);
        $response->assertViewIs('tenant_criteria.search');
    }

    public function test_tenant_criteria_search_sort4_created_at_asc_returns_200(): void
    {
        $this->requireTenantTables();
        $this->makeTenantListing();

        $response = $this->get(route('tenant.criteria.auctions.search', ['sort' => 4]));

        $response->assertStatus(200);
        $response->assertViewIs('tenant_criteria.search');
    }

    public function test_tenant_criteria_search_sort5_uses_max_price_subquery_desc(): void
    {
        $this->requireTenantTables();
        $this->makeTenantListing(['max_price' => '1500']);
        $this->makeTenantListing(['max_price' => '3000']);

        DB::enableQueryLog();
        $response = $this->get(route('tenant.criteria.auctions.search', ['sort' => 5]));
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $response->assertStatus(200);
        $response->assertViewIs('tenant_criteria.search');

        $orderSql = collect($queries)->first(fn($q) => str_contains(strtolower($q['query']), 'order by'));
        $this->assertNotNull($orderSql, 'Expected an ORDER BY query');
        $this->assertStringContainsString("meta_key = 'max_price'", $orderSql['query']);
        $this->assertStringContainsString('desc', strtolower($orderSql['query']));
        $this->assertStringNotContainsString('"price"', strtolower($orderSql['query']));
    }

    public function test_tenant_criteria_search_sort6_uses_max_price_subquery_asc(): void
    {
        $this->requireTenantTables();
        $this->makeTenantListing(['max_price' => '1500']);
        $this->makeTenantListing(['max_price' => '3000']);

        DB::enableQueryLog();
        $response = $this->get(route('tenant.criteria.auctions.search', ['sort' => 6]));
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $response->assertStatus(200);
        $this->assertStringContainsString("meta_key = 'max_price'",
            collect($queries)->first(fn($q) => str_contains(strtolower($q['query']), 'order by'))['query'] ?? ''
        );
        $this->assertStringNotContainsString('"price"', strtolower(
            collect($queries)->first(fn($q) => str_contains(strtolower($q['query']), 'order by'))['query'] ?? ''
        ));
    }

    public function test_tenant_criteria_search_unknown_sort_falls_through_to_random_order(): void
    {
        $this->requireTenantTables();
        $this->makeTenantListing();

        DB::enableQueryLog();
        $response = $this->get(route('tenant.criteria.auctions.search', ['sort' => 99]));
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $response->assertStatus(200);

        $orderSql = collect($queries)->first(fn($q) => str_contains(strtolower($q['query']), 'order by'));
        $this->assertNotNull($orderSql, 'Expected an ORDER BY (RANDOM()) query for unknown sort');
        $this->assertStringContainsString('random()', strtolower($orderSql['query']));
    }

    public function test_tenant_criteria_search_returns_count_in_view(): void
    {
        $this->requireTenantTables();
        $this->makeTenantListing();
        $this->makeTenantListing();

        $response = $this->get(route('tenant.criteria.auctions.search'));

        $response->assertStatus(200);
        $response->assertViewHas('count');
        $this->assertGreaterThanOrEqual(2, $response->viewData('count'));
    }
}
