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
 * Legacy Tenant Criteria ZIPs reach matching.
 *
 * The tenant criteria form has no standalone ZIP input — its only ZIP entry point is the
 * Location DNA widget, which the controller stores inside `location_dna_preferences` as
 * `zip_codes`. `TenantCriteriaLoader` decoded that blob for radii, polygons and state and
 * then sent `preferred_zip_codes => []`, so every tenant ZIP was stored and silently never
 * matched, while `BuyerCriteriaLoader` read the identical key.
 *
 * Nothing about matching changes: the loader now hands the shared engine the ZIPs it was
 * already storing, read with the Buyer loader's semantics (the key's presence decides).
 */
class TenantCriteriaZipMatchingTest extends TestCase
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

    private function makeUser(): int
    {
        return DB::table('users')->insertGetId([
            'first_name'  => 'ZipFix',
            'last_name'   => 'Test',
            'name'        => 'ZipFix Test',
            'short_id'    => 'ZPFIX' . uniqid(),
            'user_name'   => 'zpfix_' . uniqid(),
            'email'       => 'zpfix-' . uniqid() . '@example.com',
            'password'    => bcrypt('password'),
            'user_type'   => 'tenant',
            'is_approved' => true,
            'is_super'    => false,
            'is_deleted'  => false,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    /** @return array{0:int,1:TenantCriteriaAuction} */
    private function makeTenantCriteria(array $meta): array
    {
        $userId = $this->makeUser();

        $id = DB::table('tenant_criteria_auctions')->insertGetId([
            'user_id'     => $userId,
            'is_approved' => true,
            'is_sold'     => false,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $record = TenantCriteriaAuction::findOrFail($id);

        foreach (array_merge(['property_types' => json_encode(['Residential'])], $meta) as $key => $value) {
            $record->saveMeta($key, $value);
        }

        return [$userId, $record];
    }

    private function load(array $blob, array $meta = []): BuyerCriteriaPayload
    {
        [$userId, $record] = $this->makeTenantCriteria(['location_dna_preferences' => json_encode($blob)] + $meta);

        $loaded = (new TenantCriteriaLoader())->loadById($record->id, [$userId]);
        $this->assertNotNull($loaded, 'The tenant criteria loader must return a payload');

        return new BuyerCriteriaPayload($loaded);
    }

    private function insertListing(array $overrides): string
    {
        $key = 'TZIP-' . uniqid();

        DB::table('bridge_properties')->insert(array_merge([
            'listing_key'             => $key,
            'listing_id'              => 'LID-' . uniqid(),
            'standard_status'         => 'Active',
            'property_type'           => 'Residential',
            'list_price'              => 400000,
            'city'                    => 'Orlando',
            'state_or_province'       => 'FL',
            'postal_code'             => '32801',
            'county_or_parish'        => 'Orange',
            'bedrooms_total'          => 3,
            'bathrooms_total_integer' => 2,
            'living_area'             => 1800,
            'senior_community_yn'     => false,
            'raw_json'                => json_encode(['IDXParticipationYN' => true]),
            'created_at'              => now(),
            'updated_at'              => now(),
        ], $overrides, ['listing_key' => $key]));

        return $key;
    }

    /** @return list<string> */
    private function eligibleKeys(BuyerCriteriaPayload $criteria): array
    {
        return (new BuyerMatchQueryBuilder())->build($criteria)->pluck('listing_key')->all();
    }

    public function test_a_stored_tenant_zip_reaches_the_matching_payload(): void
    {
        $criteria = $this->load(['zip_codes' => ['33602'], 'cities' => [], 'radius_searches' => [], 'polygons' => []]);

        $this->assertSame(['33602'], $criteria->preferredZipCodes);
    }

    public function test_matching_admits_the_in_zip_property_and_not_the_out_of_zip_one(): void
    {
        $inZip  = $this->insertListing(['city' => 'Tampa', 'postal_code' => '33602', 'county_or_parish' => 'Hillsborough']);
        $outZip = $this->insertListing(['city' => 'Orlando', 'postal_code' => '32801', 'county_or_parish' => 'Orange']);

        $keys = $this->eligibleKeys($this->load(['zip_codes' => ['33602']]));

        $this->assertContains($inZip, $keys, 'A listing in the tenant\'s ZIP must match');
        $this->assertNotContains($outZip, $keys, 'A listing outside the tenant\'s only ZIP must not');
    }

    public function test_a_cleared_zip_list_stays_cleared(): void
    {
        $this->assertSame([], $this->load(['zip_codes' => []])->preferredZipCodes);
        $this->assertSame([], $this->load(['radius_searches' => []])->preferredZipCodes, 'No key, no ZIPs');
    }

    public function test_blank_and_duplicate_zips_are_dropped(): void
    {
        $this->assertSame(['33602', '33701'], $this->load(['zip_codes' => ['33602', '', '33602', '  ', '33701']])->preferredZipCodes);
    }

    public function test_a_commercial_tenant_keeps_its_zips_and_its_commercial_type(): void
    {
        $criteria = $this->load(['zip_codes' => ['33602']], ['property_type' => 'Commercial Lease']);

        $this->assertSame(['33602'], $criteria->preferredZipCodes);
        $this->assertSame(['Commercial'], $criteria->propertyTypes, 'The residential/commercial mapping is unchanged');
    }

    public function test_the_loader_writes_nothing(): void
    {
        [$userId, $record] = $this->makeTenantCriteria(['location_dna_preferences' => json_encode(['zip_codes' => ['33602']])]);
        $before = DB::table('tenant_criteria_auction_metas')->where('tenant_criteria_auction_id', $record->id)->orderBy('id')->get()->toArray();

        (new TenantCriteriaLoader())->loadById($record->id, [$userId]);

        $after = DB::table('tenant_criteria_auction_metas')->where('tenant_criteria_auction_id', $record->id)->orderBy('id')->get()->toArray();
        $this->assertEquals($before, $after);
    }
}
