<?php

namespace Tests\Feature\Bridge;

use App\Jobs\ComputeLocationDna;
use App\Models\BridgeProperty;
use App\Services\Bridge\BridgeApiService;
use App\Services\Bridge\BridgeListingLookupService;
use App\Services\Bridge\BridgePropertyCandidateAdapter;
use App\Services\Bridge\BridgePropertyNormalizer;
use App\Services\Property\PropertyCandidate;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * NOTE on the test database: in this environment feature tests execute against
 * the shared PostgreSQL dev DB (heliumdb) with DatabaseTransactions rollback —
 * NOT an empty SQLite :memory: table. So these tests use synthetic, collision-
 * proof identifiers/cities (PHPUNIT_* / a unique city) rather than assuming an
 * empty table, and neutralise job dispatch with Bus::fake().
 */
class BridgeListingLookupServiceTest extends TestCase
{
    use DatabaseTransactions;

    /** A city string that will not collide with any seeded MLS data. */
    private const CITY = 'PhpunitLookupCity';

    protected function setUp(): void
    {
        parent::setUp();
        // Neutralise ComputeLocationDna (dispatched on upsert / any model observer)
        // so tests are fast and side-effect free; api-fallback tests assert on it.
        Bus::fake();
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function makeService(BridgeApiService $api): BridgeListingLookupService
    {
        return new BridgeListingLookupService(
            $api,
            new BridgePropertyNormalizer(),
            new BridgePropertyCandidateAdapter(),
        );
    }

    private function makeApiRecord(string $listingKey, array $overrides = []): array
    {
        return array_merge([
            'ListingKey'            => $listingKey,
            'ListingId'             => $listingKey . '-id',
            'StandardStatus'        => 'Active',
            'PropertyType'          => 'Residential',
            'ListPrice'             => 350000,
            'UnparsedAddress'       => '123 Main St',
            'City'                  => self::CITY,
            'StateOrProvince'       => 'FL',
            'PostalCode'            => '33601',
            'BedroomsTotal'         => 3,
            'BathroomsTotalInteger' => 2,
            'LivingArea'            => 1800,
            'ModificationTimestamp' => '2026-01-15T12:00:00Z',
        ], $overrides);
    }

    private function seedLocal(array $attributes = []): BridgeProperty
    {
        return BridgeProperty::create(array_merge([
            'listing_key'       => 'PHPUNIT-K-LOCAL',
            'listing_id'        => 'PHPUNIT-MLS-LOCAL',
            'standard_status'   => 'Active',
            'property_type'     => 'Residential',
            'list_price'        => 400000,
            'unparsed_address'  => '999 Bay St',
            'city'              => self::CITY,
            'state_or_province' => 'FL',
            'postal_code'       => '33601',
            'raw_json'          => json_encode(['ListingKey' => 'PHPUNIT-K-LOCAL']),
        ], $attributes));
    }

    // ── findByMlsNumber ──────────────────────────────────────────────────────

    public function test_find_by_mls_number_returns_local_without_api_call(): void
    {
        $this->seedLocal(['listing_id' => 'PHPUNIT-MLS-LOCAL', 'listing_key' => 'PHPUNIT-K-LOCAL']);

        $api = $this->createMock(BridgeApiService::class);
        $api->expects($this->never())->method('fetchProperties');

        $candidate = $this->makeService($api)->findByMlsNumber('PHPUNIT-MLS-LOCAL');

        $this->assertInstanceOf(PropertyCandidate::class, $candidate);
        $this->assertSame('PHPUNIT-K-LOCAL', $candidate->listingKey);
        $this->assertSame('bridge', $candidate->source);
        $this->assertNotNull($candidate->sourceRecordId);
    }

    public function test_find_by_mls_number_falls_back_to_api_and_caches(): void
    {
        $captured = null;
        $api = $this->createMock(BridgeApiService::class);
        $api->method('fetchProperties')->willReturnCallback(
            function (int $limit, ?string $filter) use (&$captured) {
                $captured = $filter;
                return [$this->makeApiRecord('PHPUNIT-K-API', ['ListingId' => 'PHPUNIT-MLS-API'])];
            }
        );

        $candidate = $this->makeService($api)->findByMlsNumber('PHPUNIT-MLS-API');

        $this->assertSame("ListingId eq 'PHPUNIT-MLS-API'", $captured);
        $this->assertNotNull($candidate);
        $this->assertSame('PHPUNIT-K-API', $candidate->listingKey);
        $this->assertDatabaseHas('bridge_properties', [
            'listing_key' => 'PHPUNIT-K-API',
            'listing_id'  => 'PHPUNIT-MLS-API',
        ]);
        Bus::assertDispatched(ComputeLocationDna::class);
    }

    public function test_find_by_mls_number_returns_null_when_api_empty(): void
    {
        $api = $this->createMock(BridgeApiService::class);
        $api->method('fetchProperties')->willReturn([]);

        $this->assertNull($this->makeService($api)->findByMlsNumber('PHPUNIT-MLS-MISSING'));
    }

    public function test_find_by_mls_number_blank_returns_null_without_api(): void
    {
        $api = $this->createMock(BridgeApiService::class);
        $api->expects($this->never())->method('fetchProperties');

        $this->assertNull($this->makeService($api)->findByMlsNumber('   '));
    }

    public function test_mls_number_with_apostrophe_is_escaped_in_filter(): void
    {
        $captured = null;
        $api = $this->createMock(BridgeApiService::class);
        $api->method('fetchProperties')->willReturnCallback(
            function (int $limit, ?string $filter) use (&$captured) {
                $captured = $filter;
                return [];
            }
        );

        $this->makeService($api)->findByMlsNumber("PHPUNIT-O'Brien");

        $this->assertSame("ListingId eq 'PHPUNIT-O''Brien'", $captured);
    }

    // ── findByListingKey ─────────────────────────────────────────────────────

    public function test_find_by_listing_key_returns_local_without_api_call(): void
    {
        $this->seedLocal(['listing_key' => 'PHPUNIT-K-XYZ']);

        $api = $this->createMock(BridgeApiService::class);
        $api->expects($this->never())->method('fetchProperties');

        $candidate = $this->makeService($api)->findByListingKey('PHPUNIT-K-XYZ');

        $this->assertNotNull($candidate);
        $this->assertSame('PHPUNIT-K-XYZ', $candidate->listingKey);
    }

    public function test_find_by_listing_key_falls_back_to_api_with_listingkey_filter(): void
    {
        $captured = null;
        $api = $this->createMock(BridgeApiService::class);
        $api->method('fetchProperties')->willReturnCallback(
            function (int $limit, ?string $filter) use (&$captured) {
                $captured = $filter;
                return [$this->makeApiRecord('PHPUNIT-K-REMOTE')];
            }
        );

        $candidate = $this->makeService($api)->findByListingKey('PHPUNIT-K-REMOTE');

        $this->assertSame("ListingKey eq 'PHPUNIT-K-REMOTE'", $captured);
        $this->assertSame('PHPUNIT-K-REMOTE', $candidate->listingKey);
        $this->assertDatabaseHas('bridge_properties', ['listing_key' => 'PHPUNIT-K-REMOTE']);
    }

    // ── searchByAddress ──────────────────────────────────────────────────────

    public function test_search_by_address_local_hit_returns_collection_without_api(): void
    {
        $this->seedLocal(['listing_key' => 'PHPUNIT-K-A', 'listing_id' => 'PHPUNIT-A']);
        $this->seedLocal(['listing_key' => 'PHPUNIT-K-B', 'listing_id' => 'PHPUNIT-B']);

        $api = $this->createMock(BridgeApiService::class);
        $api->expects($this->never())->method('fetchProperties');

        // Unique city guarantees only the two rows seeded above match locally.
        $results = $this->makeService($api)->searchByAddress(['city' => self::CITY]);

        $this->assertCount(2, $results);
        $this->assertContainsOnlyInstancesOf(PropertyCandidate::class, $results);
        $keys = $results->pluck('listingKey')->all();
        $this->assertContains('PHPUNIT-K-A', $keys);
        $this->assertContains('PHPUNIT-K-B', $keys);
    }

    public function test_search_by_address_api_fallback_returns_multiple_units(): void
    {
        // A city with no local rows forces the API path.
        $nowhere = 'PhpunitNowhereCity';

        $captured = null;
        $api = $this->createMock(BridgeApiService::class);
        $api->method('fetchProperties')->willReturnCallback(
            function (int $limit, ?string $filter) use (&$captured, $nowhere) {
                $captured = $filter;
                return [
                    $this->makeApiRecord('PHPUNIT-K-UNIT-100', ['ListingId' => 'PHPUNIT-100', 'City' => $nowhere]),
                    $this->makeApiRecord('PHPUNIT-K-UNIT-200', ['ListingId' => 'PHPUNIT-200', 'City' => $nowhere]),
                ];
            }
        );

        $results = $this->makeService($api)->searchByAddress([
            'street_number' => '123',
            'street_name'   => 'Main',
            'city'          => $nowhere,
        ]);

        $this->assertSame(
            "StreetNumber eq '123' and contains(StreetName,'Main') and City eq '{$nowhere}'",
            $captured
        );
        $this->assertCount(2, $results);
        $this->assertDatabaseHas('bridge_properties', ['listing_key' => 'PHPUNIT-K-UNIT-100']);
        $this->assertDatabaseHas('bridge_properties', ['listing_key' => 'PHPUNIT-K-UNIT-200']);
    }

    public function test_search_by_address_empty_parts_returns_empty_without_api(): void
    {
        $api = $this->createMock(BridgeApiService::class);
        $api->expects($this->never())->method('fetchProperties');

        $service = $this->makeService($api);

        $this->assertTrue($service->searchByAddress([])->isEmpty());
        $this->assertTrue($service->searchByAddress(['city' => '   '])->isEmpty());
    }
}
