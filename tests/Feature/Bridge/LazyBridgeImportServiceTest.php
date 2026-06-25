<?php

namespace Tests\Feature\Bridge;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\Bridge\BridgeApiService;
use App\Services\Bridge\BridgePropertyNormalizer;
use App\Services\Bridge\CriteriaHashService;
use App\Services\Bridge\LazyBridgeImportService;
use App\Services\Bridge\LazyImportResult;
use App\Services\Bridge\OData\CriteriaODataFilterBuilderInterface;
use App\Services\Stellar\Matching\DTO\BuyerCriteriaPayload;
use App\Models\BridgeCriteriaFetchCache;
use App\Models\BridgeProperty;

class LazyBridgeImportServiceTest extends TestCase
{
    use DatabaseTransactions;

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makePayload(array $overrides = []): BuyerCriteriaPayload
    {
        return new BuyerCriteriaPayload(array_merge([
            'property_types'      => ['Residential'],
            'is_55_plus_eligible' => false,
        ], $overrides));
    }

    private function makeApiRecord(string $listingKey = 'K-001'): array
    {
        return [
            'ListingKey'             => $listingKey,
            'ListingId'              => $listingKey . '-id',
            'StandardStatus'         => 'Active',
            'PropertyType'           => 'Residential',
            'ListPrice'              => 350000,
            'UnparsedAddress'        => '123 Main St',
            'City'                   => 'Tampa',
            'StateOrProvince'        => 'FL',
            'PostalCode'             => '33601',
            'BedroomsTotal'          => 3,
            'BathroomsTotalInteger'  => 2,
            'LivingArea'             => 1800,
            'ModificationTimestamp'  => '2026-01-15T12:00:00Z',
        ];
    }

    /**
     * Build a service with a mocked API and a stub filter builder injected for both roles.
     * The stub builder always returns a fixed OData filter string so tests don't depend
     * on buyer/tenant builder internals.
     */
    private function makeService(BridgeApiService $api): LazyBridgeImportService
    {
        $stubBuilder = $this->createMock(CriteriaODataFilterBuilderInterface::class);
        $stubBuilder->method('build')->willReturn("StandardStatus eq 'Active'");

        return new LazyBridgeImportService(
            new CriteriaHashService(),
            $api,
            new BridgePropertyNormalizer(),
            builders: ['buyer' => $stubBuilder, 'tenant' => $stubBuilder],
        );
    }

    // =========================================================================
    // Cache-miss path
    // =========================================================================

    public function test_cache_miss_calls_api_and_upserts_records(): void
    {
        $payload = $this->makePayload(['max_price' => 400000]);
        $api     = $this->createMock(BridgeApiService::class);

        $records = [$this->makeApiRecord('K-100'), $this->makeApiRecord('K-101')];

        $api->expects($this->once())
            ->method('fetchPropertiesPaginated')
            ->willReturn($records);

        $service = $this->makeService($api);
        $result  = $service->importForCriteria($payload, 'buyer');

        $this->assertTrue($result->isFetched());
        $this->assertFalse($result->fromCache);
        $this->assertSame(2, $result->recordCount);

        $this->assertDatabaseHas('bridge_properties', ['listing_key' => 'K-100']);
        $this->assertDatabaseHas('bridge_properties', ['listing_key' => 'K-101']);
    }

    public function test_cache_miss_writes_cache_row(): void
    {
        $payload = $this->makePayload(['max_price' => 200000]);
        $api     = $this->createMock(BridgeApiService::class);
        $api->method('fetchPropertiesPaginated')->willReturn([$this->makeApiRecord('K-200')]);

        $service = $this->makeService($api);
        $service->importForCriteria($payload, 'buyer');

        $hash = (new CriteriaHashService())->hash($payload, 'buyer');

        $row = BridgeCriteriaFetchCache::where('criteria_hash', $hash)->first();
        $this->assertNotNull($row);
        $this->assertSame('buyer', $row->role);
        $this->assertSame(1, $row->record_count);
        $this->assertNotNull($row->last_fetched_at);
        $this->assertTrue($row->expires_at->isFuture());
    }

    public function test_cache_miss_second_empty_page_stops_pagination(): void
    {
        config(['bridge.lazy_page_size' => 1]);

        $payload = $this->makePayload();
        $api     = $this->createMock(BridgeApiService::class);

        $api->expects($this->exactly(2))
            ->method('fetchPropertiesPaginated')
            ->willReturnOnConsecutiveCalls(
                [$this->makeApiRecord('K-300')],
                [],
            );

        $service = $this->makeService($api);
        $result  = $service->importForCriteria($payload, 'buyer');

        $this->assertTrue($result->isFetched());
        $this->assertSame(1, $result->recordCount);
    }

    // =========================================================================
    // Cache-hit path
    // =========================================================================

    public function test_cache_hit_skips_api_call(): void
    {
        $payload = $this->makePayload(['max_price' => 300000]);
        $hash    = (new CriteriaHashService())->hash($payload, 'buyer');

        BridgeCriteriaFetchCache::create([
            'criteria_hash'   => $hash,
            'role'            => 'buyer',
            'last_fetched_at' => now()->subMinutes(10),
            'record_count'    => 5,
            'expires_at'      => now()->addMinutes(50),
        ]);

        $api = $this->createMock(BridgeApiService::class);
        $api->expects($this->never())->method('fetchPropertiesPaginated');

        $service = $this->makeService($api);
        $result  = $service->importForCriteria($payload, 'buyer');

        $this->assertTrue($result->isCached());
        $this->assertTrue($result->fromCache);
        $this->assertSame(5, $result->recordCount);
        $this->assertFalse($result->isPartial());
    }

    public function test_expired_cache_row_triggers_fresh_api_call(): void
    {
        $payload = $this->makePayload(['max_price' => 300000]);
        $hash    = (new CriteriaHashService())->hash($payload, 'buyer');

        BridgeCriteriaFetchCache::create([
            'criteria_hash'   => $hash,
            'role'            => 'buyer',
            'last_fetched_at' => now()->subHours(3),
            'record_count'    => 5,
            'expires_at'      => now()->subHours(2),
        ]);

        $api = $this->createMock(BridgeApiService::class);
        $api->expects($this->once())
            ->method('fetchPropertiesPaginated')
            ->willReturn([$this->makeApiRecord('K-400')]);

        $service = $this->makeService($api);
        $result  = $service->importForCriteria($payload, 'buyer');

        $this->assertTrue($result->isFetched());
        $this->assertSame(1, $result->recordCount);

        $row = BridgeCriteriaFetchCache::where('criteria_hash', $hash)->first();
        $this->assertSame(1, $row->record_count);
        $this->assertTrue($row->expires_at->isFuture());
    }

    // =========================================================================
    // API-failure path
    // =========================================================================

    public function test_api_exception_returns_failed_result(): void
    {
        Log::shouldReceive('warning')->atLeast()->once();
        Log::shouldReceive('info')->zeroOrMoreTimes();

        $payload = $this->makePayload(['max_price' => 500000]);
        $api     = $this->createMock(BridgeApiService::class);
        $api->method('fetchPropertiesPaginated')
            ->willThrowException(new \RuntimeException('Connection refused'));

        $service = $this->makeService($api);
        $result  = $service->importForCriteria($payload, 'buyer');

        $this->assertTrue($result->isFailed());
        $this->assertSame(0, $result->recordCount);
        $this->assertFalse($result->fromCache);
    }

    public function test_api_failure_does_not_write_cache_row(): void
    {
        Log::shouldReceive('warning')->atLeast()->once();
        Log::shouldReceive('info')->zeroOrMoreTimes();

        $payload = $this->makePayload(['max_price' => 500000]);
        $hash    = (new CriteriaHashService())->hash($payload, 'buyer');

        $api = $this->createMock(BridgeApiService::class);
        $api->method('fetchPropertiesPaginated')
            ->willThrowException(new \RuntimeException('Timeout'));

        $service = $this->makeService($api);
        $service->importForCriteria($payload, 'buyer');

        $this->assertDatabaseMissing('bridge_criteria_fetch_cache', ['criteria_hash' => $hash]);
    }

    public function test_http_503_from_bridge_api_returns_failed_and_does_not_write_cache(): void
    {
        config([
            'bridge.dataset' => 'stellar',
            'bridge.token'   => 'test-token',
        ]);

        Http::fake([
            '*' => Http::response(['error' => 'Service Unavailable'], 503),
        ]);

        $payload = $this->makePayload(['max_price' => 250000]);
        $hash    = (new CriteriaHashService())->hash($payload, 'buyer');

        $realApi = new BridgeApiService();
        $service = new LazyBridgeImportService(
            new CriteriaHashService(),
            $realApi,
            new BridgePropertyNormalizer(),
        );

        $result = $service->importForCriteria($payload, 'buyer');

        $this->assertTrue($result->isFailed(), 'Expected failed result from HTTP 503');
        $this->assertSame(0, $result->recordCount);
        $this->assertFalse($result->fromCache);
        $this->assertDatabaseMissing('bridge_criteria_fetch_cache', ['criteria_hash' => $hash]);
    }

    // =========================================================================
    // Role-specific builder resolution
    // =========================================================================

    public function test_buyer_role_uses_buyer_builder(): void
    {
        $buyerBuilder  = $this->createMock(CriteriaODataFilterBuilderInterface::class);
        $tenantBuilder = $this->createMock(CriteriaODataFilterBuilderInterface::class);

        $buyerBuilder->expects($this->once())
            ->method('build')
            ->willReturn("StandardStatus eq 'Active' and PropertyType eq 'Residential'");
        $tenantBuilder->expects($this->never())->method('build');

        $api = $this->createMock(BridgeApiService::class);
        $api->method('fetchPropertiesPaginated')->willReturn([]);

        $service = new LazyBridgeImportService(
            new CriteriaHashService(),
            $api,
            new BridgePropertyNormalizer(),
            builders: ['buyer' => $buyerBuilder, 'tenant' => $tenantBuilder],
        );

        $service->importForCriteria($this->makePayload(), 'buyer');
    }

    public function test_tenant_role_uses_tenant_builder(): void
    {
        $buyerBuilder  = $this->createMock(CriteriaODataFilterBuilderInterface::class);
        $tenantBuilder = $this->createMock(CriteriaODataFilterBuilderInterface::class);

        $tenantBuilder->expects($this->once())
            ->method('build')
            ->willReturn("StandardStatus eq 'Active' and PropertyType eq 'Residential'");
        $buyerBuilder->expects($this->never())->method('build');

        $api = $this->createMock(BridgeApiService::class);
        $api->method('fetchPropertiesPaginated')->willReturn([]);

        $service = new LazyBridgeImportService(
            new CriteriaHashService(),
            $api,
            new BridgePropertyNormalizer(),
            builders: ['buyer' => $buyerBuilder, 'tenant' => $tenantBuilder],
        );

        $service->importForCriteria($this->makePayload(), 'tenant');
    }

    public function test_unsupported_role_throws_invalid_argument_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/unsupported role/i');

        $api = $this->createMock(BridgeApiService::class);
        $api->expects($this->never())->method('fetchPropertiesPaginated');

        $service = $this->makeService($api);
        $service->importForCriteria($this->makePayload(), 'landlord');
    }

    // =========================================================================
    // Pagination caps
    // =========================================================================

    public function test_max_records_cap_stops_pagination_early_and_marks_partial(): void
    {
        config(['bridge.lazy_max_records' => 2, 'bridge.lazy_max_pages' => 20]);

        $payload = $this->makePayload();
        $api     = $this->createMock(BridgeApiService::class);

        $api->method('fetchPropertiesPaginated')
            ->willReturn([$this->makeApiRecord('K-501'), $this->makeApiRecord('K-502'), $this->makeApiRecord('K-503')]);

        $service = $this->makeService($api);
        $result  = $service->importForCriteria($payload, 'buyer');

        $this->assertTrue($result->isFetched());
        $this->assertSame(2, $result->recordCount);
        $this->assertTrue($result->isPartial(), 'Expected wasPartial=true when max-records cap is hit');
    }

    public function test_max_pages_cap_stops_pagination_early_and_marks_partial(): void
    {
        config(['bridge.lazy_max_pages' => 2, 'bridge.lazy_max_records' => 1000, 'bridge.lazy_page_size' => 1]);

        $payload = $this->makePayload();
        $api     = $this->createMock(BridgeApiService::class);

        $callCount = 0;
        $api->method('fetchPropertiesPaginated')
            ->willReturnCallback(function () use (&$callCount) {
                $callCount++;
                return [$this->makeApiRecord("K-60{$callCount}")];
            });

        $service = $this->makeService($api);
        $result  = $service->importForCriteria($payload, 'buyer');

        $this->assertTrue($result->isFetched());
        $this->assertSame(2, $result->recordCount);
        $this->assertSame(2, $callCount);
        $this->assertTrue($result->isPartial(), 'Expected wasPartial=true when max-pages cap is hit');
    }

    public function test_full_import_is_not_marked_partial(): void
    {
        config(['bridge.lazy_page_size' => 1]);

        $payload = $this->makePayload();
        $api     = $this->createMock(BridgeApiService::class);
        $api->method('fetchPropertiesPaginated')
            ->willReturnOnConsecutiveCalls(
                [$this->makeApiRecord('K-800')],
                [],
            );

        $service = $this->makeService($api);
        $result  = $service->importForCriteria($payload, 'buyer');

        $this->assertTrue($result->isFetched());
        $this->assertFalse($result->isPartial(), 'Expected wasPartial=false for a complete feed consumption');
    }

    public function test_partial_import_uses_shorter_ttl(): void
    {
        config([
            'bridge.lazy_max_records'          => 1,
            'bridge.lazy_max_pages'            => 20,
            'bridge.lazy_ttl_minutes'          => 60,
            'bridge.lazy_partial_ttl_minutes'  => 5,
        ]);

        $payload = $this->makePayload(['max_price' => 111000]);
        $hash    = (new CriteriaHashService())->hash($payload, 'buyer');

        $api = $this->createMock(BridgeApiService::class);
        $api->method('fetchPropertiesPaginated')
            ->willReturn([$this->makeApiRecord('K-900'), $this->makeApiRecord('K-901')]);

        $service = $this->makeService($api);
        $result  = $service->importForCriteria($payload, 'buyer');

        $this->assertTrue($result->isPartial());

        $row = BridgeCriteriaFetchCache::where('criteria_hash', $hash)->first();
        $this->assertNotNull($row);
        $this->assertTrue(
            $row->expires_at->lte(now()->addMinutes(6)),
            'Partial cache TTL should be ≤ 5 min (with 1 min buffer for test runtime)'
        );
        $this->assertTrue(
            $row->expires_at->gt(now()),
            'Partial cache expires_at should still be in the future'
        );
    }

    public function test_partial_with_zero_partial_ttl_does_not_write_cache(): void
    {
        config([
            'bridge.lazy_max_records'         => 1,
            'bridge.lazy_max_pages'           => 20,
            'bridge.lazy_partial_ttl_minutes' => 0,
        ]);

        $payload = $this->makePayload(['max_price' => 222000]);
        $hash    = (new CriteriaHashService())->hash($payload, 'buyer');

        $api = $this->createMock(BridgeApiService::class);
        $api->method('fetchPropertiesPaginated')
            ->willReturn([$this->makeApiRecord('K-950'), $this->makeApiRecord('K-951')]);

        $service = $this->makeService($api);
        $result  = $service->importForCriteria($payload, 'buyer');

        $this->assertTrue($result->isPartial());
        $this->assertDatabaseMissing('bridge_criteria_fetch_cache', ['criteria_hash' => $hash]);
    }

    // =========================================================================
    // Upsert behaviour
    // =========================================================================

    public function test_existing_bridge_property_is_updated_not_duplicated(): void
    {
        BridgeProperty::create([
            'listing_key'     => 'K-700',
            'standard_status' => 'Active',
            'city'            => 'OldCity',
            'property_type'   => 'Residential',
            'imported_at'     => now(),
        ]);

        $payload = $this->makePayload();
        $api     = $this->createMock(BridgeApiService::class);
        $updated = $this->makeApiRecord('K-700');
        $updated['City'] = 'NewCity';
        $api->method('fetchPropertiesPaginated')->willReturn([$updated]);

        $service = $this->makeService($api);
        $service->importForCriteria($payload, 'buyer');

        $this->assertSame(1, BridgeProperty::where('listing_key', 'K-700')->count());
        $this->assertDatabaseHas('bridge_properties', ['listing_key' => 'K-700', 'city' => 'NewCity']);
    }

    // =========================================================================
    // Advisory locking
    // =========================================================================

    /**
     * Compute the same lock key the service uses so we can query pg_advisory_locks.
     * Mirrors LazyBridgeImportService::hashToLockKey() exactly.
     */
    private function hashToLockKey(string $hash): int
    {
        return unpack('J', hex2bin(substr($hash, 0, 16)))[1];
    }

    /**
     * IT-LB-ADV-01
     *
     * Double-checked locking: when a cache row appears while we waited for the
     * advisory lock (simulating another process completing the import first),
     * the Bridge API must NOT be called — the result comes from the cache row.
     */
    public function test_double_check_returns_cached_after_concurrent_import(): void
    {
        $payload = $this->makePayload(['max_price' => 123000]);
        $hash    = (new CriteriaHashService())->hash($payload, 'buyer');

        $api = $this->createMock(BridgeApiService::class);
        $api->expects($this->never())->method('fetchPropertiesPaginated');

        $stubBuilder = $this->createMock(\App\Services\Bridge\OData\CriteriaODataFilterBuilderInterface::class);
        $stubBuilder->method('build')->willReturn("StandardStatus eq 'Active'");

        // Subclass that, on lock acquisition, inserts the cache row the "winner" would
        // have written — simulating concurrent import completing during our lock wait.
        $service = new class(
            new CriteriaHashService(),
            $api,
            new BridgePropertyNormalizer(),
            ['buyer' => $stubBuilder, 'tenant' => $stubBuilder],
        ) extends LazyBridgeImportService {
            protected function acquireAdvisoryLock(int $lockKey, string $hash, string $role): bool
            {
                BridgeCriteriaFetchCache::create([
                    'criteria_hash'   => $hash,
                    'role'            => $role,
                    'last_fetched_at' => now(),
                    'record_count'    => 7,
                    'expires_at'      => now()->addHour(),
                ]);
                return true;
            }

            protected function releaseAdvisoryLock(int $lockKey): void {}
        };

        $result = $service->importForCriteria($payload, 'buyer');

        $this->assertTrue($result->isCached(), 'Expected cached result from double-check');
        $this->assertSame(7, $result->recordCount);
        $this->assertSame($hash, $result->criteriaHash);
    }

    /**
     * IT-LB-ADV-02
     *
     * Advisory lock is released after a successful import so that a subsequent
     * request for the same criteria hash is not blocked indefinitely.
     */
    public function test_advisory_lock_released_after_successful_import(): void
    {
        $payload = $this->makePayload(['max_price' => 250000]);
        $api     = $this->createMock(BridgeApiService::class);
        $api->method('fetchPropertiesPaginated')
            ->willReturn([$this->makeApiRecord('K-ADV-01')]);

        $hash    = (new CriteriaHashService())->hash($payload, 'buyer');
        $lockKey = $this->hashToLockKey($hash);

        $this->makeService($api)->importForCriteria($payload, 'buyer');

        // If the lock was properly released, pg_try_advisory_lock() returns true.
        $rows = DB::select('SELECT pg_try_advisory_lock(?) AS acquired', [$lockKey]);
        $this->assertTrue((bool) $rows[0]->acquired, 'Advisory lock must be released after successful import');

        // Release the lock we just acquired in the assertion above.
        DB::select('SELECT pg_advisory_unlock(?)', [$lockKey]);
    }

    /**
     * IT-LB-ADV-03
     *
     * Advisory lock is released even when the Bridge API call throws, ensuring
     * that a failed import does not permanently block subsequent requests for the
     * same criteria hash.
     */
    public function test_advisory_lock_released_after_api_failure(): void
    {
        $payload = $this->makePayload(['max_price' => 99000]);
        $api     = $this->createMock(BridgeApiService::class);
        $api->method('fetchPropertiesPaginated')
            ->willThrowException(new \RuntimeException('Bridge timeout'));

        $hash    = (new CriteriaHashService())->hash($payload, 'buyer');
        $lockKey = $this->hashToLockKey($hash);

        $result = $this->makeService($api)->importForCriteria($payload, 'buyer');

        $this->assertTrue($result->isFailed());

        // Lock must be released even on failure (finally block guarantee).
        $rows = DB::select('SELECT pg_try_advisory_lock(?) AS acquired', [$lockKey]);
        $this->assertTrue((bool) $rows[0]->acquired, 'Advisory lock must be released after API failure');

        DB::select('SELECT pg_advisory_unlock(?)', [$lockKey]);
    }

    /**
     * IT-LB-ADV-04
     *
     * hashToLockKey() is deterministic: the same hex string always produces the
     * same signed int64, and the result is a PHP int (not a float), confirming
     * it fits within PostgreSQL's bigint range.
     */
    public function test_hash_to_lock_key_is_deterministic_and_integer(): void
    {
        $hash = hash('sha256', 'test-criteria-buyer');

        $key1 = $this->hashToLockKey($hash);
        $key2 = $this->hashToLockKey($hash);

        $this->assertSame($key1, $key2, 'hashToLockKey must be deterministic');
        $this->assertIsInt($key1, 'hashToLockKey must return a PHP int (not float)');

        // Different hashes must (almost certainly) produce different keys.
        $otherKey = $this->hashToLockKey(hash('sha256', 'test-criteria-tenant'));
        $this->assertNotSame($key1, $otherKey);
    }

    // =========================================================================
    // LazyImportResult DTO
    // =========================================================================

    public function test_lazy_import_result_named_constructors(): void
    {
        $cached         = LazyImportResult::cached(17);
        $cachedDefault  = LazyImportResult::cached();
        $fetched        = LazyImportResult::fetched(42);
        $fetchedPartial = LazyImportResult::fetched(10, wasPartial: true);
        $failed         = LazyImportResult::failed();

        $this->assertTrue($cached->isCached());
        $this->assertTrue($cached->fromCache);
        $this->assertSame(17, $cached->recordCount);
        $this->assertFalse($cached->isPartial());
        $this->assertSame(LazyImportResult::STATUS_CACHED, $cached->status);

        $this->assertSame(0, $cachedDefault->recordCount);

        $this->assertTrue($fetched->isFetched());
        $this->assertFalse($fetched->fromCache);
        $this->assertSame(42, $fetched->recordCount);
        $this->assertFalse($fetched->isPartial());
        $this->assertSame(LazyImportResult::STATUS_FETCHED, $fetched->status);

        $this->assertTrue($fetchedPartial->isFetched());
        $this->assertTrue($fetchedPartial->isPartial());
        $this->assertSame(10, $fetchedPartial->recordCount);

        $this->assertTrue($failed->isFailed());
        $this->assertFalse($failed->fromCache);
        $this->assertSame(0, $failed->recordCount);
        $this->assertFalse($failed->isPartial());
        $this->assertSame(LazyImportResult::STATUS_FAILED, $failed->status);
    }
}
