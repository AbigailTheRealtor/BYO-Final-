<?php

namespace Tests\Feature\Stellar;

use App\Models\TenantCriteriaAuction;
use App\Services\Stellar\Matching\BuyerMatchQueryBuilder;
use App\Services\Stellar\Matching\DTO\BuyerCriteriaPayload;
use App\Services\Stellar\TenantCriteriaLoader;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Tenant Criteria cities and counties: the explicit form field first, the Location DNA widget's
 * list as a fallback.
 *
 * The tenant criteria form carries both — its own "cities" / "counties" fields and the Location
 * DNA widget, which stores its lists inside `location_dna_preferences`. The loader used to read
 * only the explicit fields, so places named solely on the map never reached matching. The rule
 * now is a FALLBACK: never a union (two representations must not widen the search) and never an
 * override (a map value must not replace what was typed into the explicit field).
 *
 * Both sides are normalised with BuyerCriteriaLoader's rules, because the matcher compares
 * exactly against Bridge's spelling.
 */
class TenantCriteriaLocationPrecedenceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['users', 'bridge_properties', 'tenant_criteria_auctions', 'tenant_criteria_auction_metas'] as $table) {
            if (!Schema::hasTable($table)) {
                $this->markTestSkipped("Table {$table} does not exist in this environment.");
            }
        }
    }

    private function load(array $blob, array $meta = []): BuyerCriteriaPayload
    {
        $userId = DB::table('users')->insertGetId([
            'first_name' => 'LocPrec', 'last_name' => 'Test', 'name' => 'LocPrec Test',
            'short_id' => 'LOCP' . uniqid(), 'user_name' => 'locp_' . uniqid(),
            'email' => 'locp-' . uniqid() . '@example.com', 'password' => bcrypt('password'),
            'user_type' => 'tenant', 'is_approved' => true, 'is_super' => false, 'is_deleted' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $id = DB::table('tenant_criteria_auctions')->insertGetId([
            'user_id' => $userId, 'is_approved' => true, 'is_sold' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $record = TenantCriteriaAuction::findOrFail($id);
        foreach (['location_dna_preferences' => $blob] + $meta as $key => $value) {
            $record->saveMeta($key, is_array($value) ? json_encode($value) : $value);
        }

        $loaded = (new TenantCriteriaLoader())->loadById($record->id, [$userId]);
        $this->assertNotNull($loaded, 'The tenant criteria loader must return a payload');

        return new BuyerCriteriaPayload($loaded);
    }

    private function insertListing(array $overrides): string
    {
        $key = 'TLOC-' . uniqid();

        DB::table('bridge_properties')->insert(array_merge([
            'listing_key' => $key, 'listing_id' => 'LID-' . uniqid(), 'standard_status' => 'Active',
            'property_type' => 'Residential', 'list_price' => 400000,
            'city' => 'Orlando', 'state_or_province' => 'FL', 'postal_code' => '32801', 'county_or_parish' => 'Orange',
            'latitude' => 28.5383, 'longitude' => -81.3792,
            'bedrooms_total' => 3, 'bathrooms_total_integer' => 2, 'living_area' => 1800,
            'senior_community_yn' => false, 'raw_json' => json_encode(['IDXParticipationYN' => true]),
            'created_at' => now(), 'updated_at' => now(),
        ], $overrides, ['listing_key' => $key]));

        return $key;
    }

    /** @return list<string> */
    private function eligibleKeys(BuyerCriteriaPayload $criteria): array
    {
        return (new BuyerMatchQueryBuilder())->build($criteria)->pluck('listing_key')->all();
    }

    // ── CASE 1 & 2 — cities ─────────────────────────────────────────────────

    public function test_case_1_an_explicit_city_wins_over_a_different_map_city(): void
    {
        $tampa   = $this->insertListing(['city' => 'Tampa', 'postal_code' => '33602', 'county_or_parish' => 'Hillsborough']);
        $orlando = $this->insertListing(['city' => 'Orlando']);

        $criteria = $this->load(['cities' => ['Orlando, FL']], ['cities' => ['Tampa']]);

        $this->assertSame(['Tampa'], $criteria->preferredCities, 'Explicit field wins; no union with the map');
        $keys = $this->eligibleKeys($criteria);
        $this->assertContains($tampa, $keys);
        $this->assertNotContains($orlando, $keys, 'The map city must not widen the search');
    }

    public function test_case_2_an_empty_explicit_city_falls_back_to_the_map_city(): void
    {
        $stPete  = $this->insertListing(['city' => 'Saint Petersburg', 'postal_code' => '33701', 'county_or_parish' => 'Pinellas']);
        $orlando = $this->insertListing(['city' => 'Orlando']);

        $criteria = $this->load(['cities' => ['St. Petersburg, FL']], ['cities' => []]);

        $this->assertSame(['Saint Petersburg'], $criteria->preferredCities, 'Normalised to Bridge spelling');
        $keys = $this->eligibleKeys($criteria);
        $this->assertContains($stPete, $keys);
        $this->assertNotContains($orlando, $keys);
    }

    public function test_a_missing_explicit_city_field_also_falls_back(): void
    {
        $this->assertSame(['Tampa'], $this->load(['cities' => ['Tampa, FL']])->preferredCities);
    }

    // ── CASE 3 & 4 — counties ───────────────────────────────────────────────

    public function test_case_3_an_explicit_county_wins_over_a_different_map_county(): void
    {
        $hillsborough = $this->insertListing(['city' => 'Brandon', 'county_or_parish' => 'Hillsborough', 'postal_code' => '33510']);
        $orange       = $this->insertListing(['county_or_parish' => 'Orange']);

        $criteria = $this->load(['counties' => ['Orange County, FL']], ['counties' => ['Hillsborough County']]);

        $this->assertSame(['Hillsborough'], $criteria->preferredCounties);
        $keys = $this->eligibleKeys($criteria);
        $this->assertContains($hillsborough, $keys);
        $this->assertNotContains($orange, $keys);
    }

    public function test_case_4_an_empty_explicit_county_falls_back_to_the_map_county(): void
    {
        $pinellas = $this->insertListing(['city' => 'Clearwater', 'county_or_parish' => 'Pinellas', 'postal_code' => '33755']);
        $orange   = $this->insertListing(['county_or_parish' => 'Orange']);

        $criteria = $this->load(['counties' => ['Pinellas County, FL']], ['counties' => []]);

        $this->assertSame(['Pinellas'], $criteria->preferredCounties);
        $keys = $this->eligibleKeys($criteria);
        $this->assertContains($pinellas, $keys);
        $this->assertNotContains($orange, $keys);
    }

    // ── CASE 5 — nothing on either side ─────────────────────────────────────

    public function test_case_5_both_sides_empty_stays_empty(): void
    {
        $criteria = $this->load(['cities' => [], 'counties' => []], ['cities' => [], 'counties' => []]);

        $this->assertSame([], $criteria->preferredCities);
        $this->assertSame([], $criteria->preferredCounties);

        $noBlob = $this->load([]);
        $this->assertSame([], $noBlob->preferredCities);
        $this->assertSame([], $noBlob->preferredCounties);
    }

    // ── CASE 6 & 7 — ZIP, radius and polygon are untouched ──────────────────

    public function test_case_6_zip_matching_still_works_beside_explicit_cities(): void
    {
        $criteria = $this->load(['zip_codes' => ['33602'], 'cities' => ['Orlando, FL']], ['cities' => ['Tampa']]);

        $this->assertSame(['33602'], $criteria->preferredZipCodes);
        $this->assertSame(['Tampa'], $criteria->preferredCities);
    }

    public function test_case_7_radius_and_polygon_matching_still_work(): void
    {
        $inside  = $this->insertListing(['city' => 'Tampa', 'postal_code' => '33602', 'latitude' => 27.9506, 'longitude' => -82.4572]);
        $outside = $this->insertListing([]);   // Orlando, ~80 miles away

        $radius = $this->load(['radius_searches' => [['address' => '315 E Madison St, Tampa, FL 33602', 'lat' => 27.9506, 'lng' => -82.4572, 'radius_miles' => 5]]]);
        $this->assertCount(1, $radius->radiusSearches);
        $keys = $this->eligibleKeys($radius);
        $this->assertContains($inside, $keys);
        $this->assertNotContains($outside, $keys);

        $polygon = $this->load(['polygons' => [['label' => 'Downtown', 'path' => [
            ['lat' => 27.93, 'lng' => -82.48], ['lat' => 27.93, 'lng' => -82.44],
            ['lat' => 27.97, 'lng' => -82.44], ['lat' => 27.97, 'lng' => -82.48],
        ]]]]);
        $this->assertCount(1, $polygon->polygons);
        $keys = $this->eligibleKeys($polygon);
        $this->assertContains($inside, $keys);
        $this->assertNotContains($outside, $keys);
    }

    // ── CASE 8 — commercial ─────────────────────────────────────────────────

    public function test_case_8_commercial_tenant_criteria_follow_the_same_contract(): void
    {
        $fallback = $this->load(['cities' => ['Tampa, FL'], 'counties' => ['Hillsborough County, FL']], ['property_type' => 'Commercial Lease']);
        $this->assertSame(['Commercial'], $fallback->propertyTypes);
        $this->assertSame(['Tampa'], $fallback->preferredCities);
        $this->assertSame(['Hillsborough'], $fallback->preferredCounties);

        $explicit = $this->load(['cities' => ['Orlando, FL']], ['property_type' => 'Commercial Lease', 'cities' => ['Tampa']]);
        $this->assertSame(['Commercial'], $explicit->propertyTypes);
        $this->assertSame(['Tampa'], $explicit->preferredCities);
    }
}
