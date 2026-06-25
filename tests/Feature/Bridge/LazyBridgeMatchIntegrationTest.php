<?php

namespace Tests\Feature\Bridge;

use App\Models\BridgeCriteriaFetchCache;
use App\Models\BridgeProperty;
use App\Services\Bridge\CriteriaHashService;
use App\Services\Bridge\LazyBridgeImportService;
use App\Services\Bridge\LazyImportResult;
use App\Services\Bridge\OData\CriteriaODataFilterBuilderInterface;
use App\Services\Stellar\BuyerOfferListingCriteriaLoader;
use App\Services\Stellar\Matching\BuyerMatchQueryBuilder;
use App\Services\Stellar\Matching\BuyerMatchResultBuilder;
use App\Services\Stellar\Matching\BuyerMatchScorer;
use App\Services\Stellar\Matching\BuyerMatchService;
use App\Services\Stellar\Matching\DTO\BuyerCriteriaPayload;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * LazyBridgeMatchIntegrationTest
 *
 * End-to-end integration test verifying the lazy-import loop:
 *   BuyerCriteriaPayload → LazyBridgeImportService (mock API, real DB)
 *   → BuyerMatchService::match() → Collection contains freshly upserted property.
 *
 * IT-LB-01  importForCriteria is called once per match() invocation.
 * IT-LB-02  Match result contains a property upserted by the (simulated) import.
 * IT-LB-03  Failed import logs a warning and match() still returns results.
 * IT-LB-04  getLastImportResult() reflects the import status after match().
 * IT-LB-05  Role 'tenant' is forwarded to importForCriteria correctly.
 * IT-LB-06  Cache hit: importForCriteria returns cached result; match() still works.
 *
 * Uses DatabaseTransactions per project convention.
 * LazyBridgeImportService is mocked at the API layer — real DB upserts are
 * performed by inserting bridge_properties rows directly to simulate what the
 * importer would do, keeping the test fast and self-contained.
 */
class LazyBridgeMatchIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    // =========================================================================
    // Helpers
    // =========================================================================

    private function skipIfTableMissing(): void
    {
        if (!Schema::hasTable('bridge_properties')) {
            $this->markTestSkipped('bridge_properties table does not exist in this environment.');
        }
    }

    private function makeCriteria(array $overrides = []): BuyerCriteriaPayload
    {
        return new BuyerCriteriaPayload(array_merge([
            'property_types'      => ['Residential'],
            'is_55_plus_eligible' => false,
            'preferred_cities'    => ['LazyTestCity'],
        ], $overrides));
    }

    /**
     * Insert a bridge_properties row simulating what the lazy importer would upsert.
     */
    private function insertBridgeProperty(array $overrides = []): string
    {
        $key = 'LAZY-IT-' . uniqid();
        DB::table('bridge_properties')->insertOrIgnore(array_merge([
            'listing_key'             => $key,
            'listing_id'              => $key . '-id',
            'standard_status'         => 'Active',
            'property_type'           => 'Residential',
            'list_price'              => 350000,
            'city'                    => 'LazyTestCity',
            'state_or_province'       => 'FL',
            'postal_code'             => '33601',
            'bedrooms_total'          => 3,
            'bathrooms_total_integer' => 2,
            'living_area'             => 1800,
            'senior_community_yn'     => false,
            'raw_json'                => json_encode(['IDXParticipationYN' => true]),
            'created_at'              => now(),
            'updated_at'              => now(),
        ], $overrides));

        return $key;
    }

    /**
     * Build a BuyerMatchService with a preconfigured LazyBridgeImportService mock.
     *
     * @param  LazyImportResult  $returnResult  What importForCriteria() returns.
     * @param  string|null       $expectedRole  If set, assert the mock is called with this role.
     * @return array{BuyerMatchService, \PHPUnit\Framework\MockObject\MockObject}
     */
    private function makeServiceWithMock(
        LazyImportResult $returnResult,
        ?string $expectedRole = null,
        int $expectedCallCount = 1,
    ): array {
        $mock = $this->createMock(LazyBridgeImportService::class);

        $invocation = $mock->expects($this->exactly($expectedCallCount))
            ->method('importForCriteria')
            ->willReturn($returnResult);

        if ($expectedRole !== null) {
            $invocation->with($this->isInstanceOf(BuyerCriteriaPayload::class), $expectedRole);
        }

        $service = new BuyerMatchService(
            new BuyerMatchQueryBuilder(),
            new BuyerMatchScorer(),
            new BuyerMatchResultBuilder(),
            $mock,
        );

        return [$service, $mock];
    }

    // =========================================================================
    // IT-LB-01: importForCriteria is called exactly once per match() invocation
    // =========================================================================

    public function test_it_lb01_import_called_once_per_match_invocation(): void
    {
        $this->skipIfTableMissing();

        [$service] = $this->makeServiceWithMock(
            LazyImportResult::cached(5),
            expectedCallCount: 1,
        );

        $service->match($this->makeCriteria());

        // PHPUnit mock expectation (exactly(1)) is verified automatically on teardown.
        $this->assertTrue(true);
    }

    // =========================================================================
    // IT-LB-02: Match result contains property upserted by the (simulated) import
    // =========================================================================

    public function test_it_lb02_match_returns_freshly_imported_property(): void
    {
        $this->skipIfTableMissing();

        // Simulate the import by pre-inserting the bridge_properties row.
        // In production, LazyBridgeImportService::importForCriteria() would do
        // the upsert; here we insert directly and return a 'fetched' result.
        $listingKey = $this->insertBridgeProperty();

        [$service] = $this->makeServiceWithMock(LazyImportResult::fetched(1));

        $results = $service->match($this->makeCriteria());

        $foundKeys = $results->pluck('listingKey')->all();
        $this->assertContains(
            $listingKey,
            $foundKeys,
            'Property seeded to simulate a lazy import must appear in match results.',
        );

        // No duplicate rows — each listing_key is unique.
        $distinctKeys = array_unique($foundKeys);
        $this->assertSame(
            count($distinctKeys),
            count($foundKeys),
            'match() must not return duplicate result rows.',
        );
    }

    // =========================================================================
    // IT-LB-03: Failed import logs warning; match() still returns local results
    // =========================================================================

    public function test_it_lb03_failed_import_logs_warning_and_match_proceeds(): void
    {
        $this->skipIfTableMissing();

        $listingKey = $this->insertBridgeProperty();

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $msg) {
                return str_contains($msg, 'LazyBridgeImport failed');
            });

        [$service] = $this->makeServiceWithMock(LazyImportResult::failed());

        $results = $service->match($this->makeCriteria());

        // Match should still return the locally-stored property despite import failure.
        $foundKeys = $results->pluck('listingKey')->all();
        $this->assertContains(
            $listingKey,
            $foundKeys,
            'Local results must still be returned even when the import fails.',
        );
    }

    // =========================================================================
    // IT-LB-04: getLastImportResult() reflects the import status after match()
    // =========================================================================

    public function test_it_lb04_get_last_import_result_reflects_status(): void
    {
        $this->skipIfTableMissing();

        [$service] = $this->makeServiceWithMock(LazyImportResult::fetched(3));

        $this->assertNull($service->getLastImportResult(), 'Should be null before first match()');

        $service->match($this->makeCriteria());

        $result = $service->getLastImportResult();
        $this->assertNotNull($result);
        $this->assertTrue($result->isFetched(), 'Last import result should reflect fetched status');
        $this->assertSame(3, $result->recordCount);
    }

    // =========================================================================
    // IT-LB-05: Role 'tenant' forwarded to importForCriteria correctly
    // =========================================================================

    public function test_it_lb05_tenant_role_forwarded_to_importer(): void
    {
        $this->skipIfTableMissing();

        [$service] = $this->makeServiceWithMock(
            LazyImportResult::cached(0),
            expectedRole: 'tenant',
            expectedCallCount: 1,
        );

        $criteria = $this->makeCriteria(['property_types' => ['Residential']]);
        $service->match($criteria, 200, 'tenant');

        // PHPUnit expectation with ->with(..., 'tenant') is verified automatically.
        $this->assertTrue(true);
    }

    // =========================================================================
    // IT-LB-06: Cache hit — importForCriteria returns cached; match still works
    // =========================================================================

    public function test_it_lb06_cache_hit_match_returns_results(): void
    {
        $this->skipIfTableMissing();

        $listingKey = $this->insertBridgeProperty();

        [$service] = $this->makeServiceWithMock(LazyImportResult::cached(42));

        $results = $service->match($this->makeCriteria());

        $result = $service->getLastImportResult();
        $this->assertNotNull($result);
        $this->assertTrue($result->isCached(), 'Import result should be cached');
        $this->assertSame(42, $result->recordCount);

        $foundKeys = $results->pluck('listingKey')->all();
        $this->assertContains(
            $listingKey,
            $foundKeys,
            'Local properties must still be returned on cache hit.',
        );
    }

    // =========================================================================
    // IT-LB-07: Cache reuse — second match() call gets a cached result
    // =========================================================================

    public function test_it_lb07_second_match_call_gets_cached_result(): void
    {
        $this->skipIfTableMissing();

        $listingKey = $this->insertBridgeProperty();

        // Mock returns fetched on the first call, cached on the second.
        $mock = $this->createMock(LazyBridgeImportService::class);
        $mock->expects($this->exactly(2))
            ->method('importForCriteria')
            ->willReturnOnConsecutiveCalls(
                LazyImportResult::fetched(1),
                LazyImportResult::cached(1),
            );

        $service = new BuyerMatchService(
            new BuyerMatchQueryBuilder(),
            new BuyerMatchScorer(),
            new BuyerMatchResultBuilder(),
            $mock,
        );

        $criteria = $this->makeCriteria();

        // First call — fresh import.
        $firstResults = $service->match($criteria);
        $firstImport  = $service->getLastImportResult();
        $this->assertNotNull($firstImport);
        $this->assertTrue($firstImport->isFetched(), 'First call must be a fresh fetch');

        // Second call with identical criteria — importer returns cached (TTL still valid).
        $secondResults = $service->match($criteria);
        $secondImport  = $service->getLastImportResult();
        $this->assertNotNull($secondImport);
        $this->assertTrue($secondImport->isCached(), 'Second call must come from cache');

        // Both calls must return the same property set (no phantom rows on second call).
        $firstKeys  = $firstResults->pluck('listingKey')->sort()->values()->all();
        $secondKeys = $secondResults->pluck('listingKey')->sort()->values()->all();
        $this->assertSame(
            $firstKeys,
            $secondKeys,
            'Results must be identical on cache-hit vs fresh-fetch for the same criteria.',
        );

        $this->assertContains($listingKey, $firstKeys, 'Seeded property must appear in results.');
    }
}
