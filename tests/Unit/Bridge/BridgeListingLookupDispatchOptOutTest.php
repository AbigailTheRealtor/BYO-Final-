<?php

namespace Tests\Unit\Bridge;

use App\Jobs\ComputeLocationDna;
use App\Services\Bridge\BridgeApiService;
use App\Services\Bridge\BridgeListingLookupService;
use App\Services\Bridge\BridgePropertyCandidateAdapter;
use App\Services\Bridge\BridgePropertyNormalizer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * Phase 4 · git-C13a — the additive `bool $dispatchDna = true` opt-out on BridgeListingLookupService.
 *
 * The default (true) reproduces the seam's original behavior exactly: a fresh API-fallback cache
 * write dispatches ComputeLocationDna. Passing false suppresses that dispatch (so the Match Check
 * caller can route enrichment through LocationDnaEnrichmentGuard instead) WITHOUT otherwise changing
 * the lookup result. A local cache hit dispatches nothing either way and is not exercised here.
 *
 * Runs against the shared dev DB with DatabaseTransactions rollback, so identifiers/cities are
 * synthetic and collision-proof (mirrors BridgeListingLookupServiceTest).
 */
class BridgeListingLookupDispatchOptOutTest extends TestCase
{
    use DatabaseTransactions;

    private const CITY = 'PhpunitOptOutCity';

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
    }

    private function makeService(BridgeApiService $api): BridgeListingLookupService
    {
        return new BridgeListingLookupService(
            $api,
            new BridgePropertyNormalizer(),
            new BridgePropertyCandidateAdapter(),
        );
    }

    private function apiReturning(string $listingKey, array $overrides = []): BridgeApiService
    {
        $api = $this->createMock(BridgeApiService::class);
        $api->method('fetchProperties')->willReturn([array_merge([
            'ListingKey'            => $listingKey,
            'ListingId'             => $listingKey . '-id',
            'StandardStatus'        => 'Active',
            'PropertyType'          => 'Residential',
            'ListPrice'             => 350000,
            'UnparsedAddress'       => '123 Main St',
            'City'                  => self::CITY,
            'StateOrProvince'       => 'FL',
            'PostalCode'            => '33601',
            'ModificationTimestamp' => '2026-01-15T12:00:00Z',
        ], $overrides)]);

        return $api;
    }

    /** @test */
    public function find_by_mls_number_default_true_dispatches_on_fresh_cache(): void
    {
        $service = $this->makeService($this->apiReturning('PHPUNIT-OPTOUT-DEFAULT'));

        // No explicit arg → default true → parity with today's behavior.
        $service->findByMlsNumber('PHPUNIT-OPTOUT-DEFAULT-id');

        Bus::assertDispatched(ComputeLocationDna::class);
    }

    /** @test */
    public function find_by_mls_number_false_suppresses_dispatch_on_fresh_cache(): void
    {
        $service = $this->makeService($this->apiReturning('PHPUNIT-OPTOUT-OFF'));

        $candidate = $service->findByMlsNumber('PHPUNIT-OPTOUT-OFF-id', dispatchDna: false);

        // The row is still cached and returned — only the DNA dispatch is suppressed.
        $this->assertNotNull($candidate);
        $this->assertSame('PHPUNIT-OPTOUT-OFF', $candidate->listingKey);
        $this->assertDatabaseHas('bridge_properties', ['listing_key' => 'PHPUNIT-OPTOUT-OFF']);
        Bus::assertNotDispatched(ComputeLocationDna::class);
    }

    /** @test */
    public function find_by_listing_key_false_suppresses_dispatch(): void
    {
        $service = $this->makeService($this->apiReturning('PHPUNIT-OPTOUT-KEY'));

        $service->findByListingKey('PHPUNIT-OPTOUT-KEY', dispatchDna: false);

        Bus::assertNotDispatched(ComputeLocationDna::class);
    }

    /** @test */
    public function search_by_address_default_true_dispatches_but_false_suppresses(): void
    {
        // Default true on a fresh (API-fallback) address search → dispatch.
        $this->makeService($this->apiReturning('PHPUNIT-OPTOUT-ADDR-A', ['City' => self::CITY . 'A']))
            ->searchByAddress(['street_number' => '1', 'street_name' => 'Main', 'city' => self::CITY . 'A']);
        Bus::assertDispatched(ComputeLocationDna::class);

        Bus::fake(); // reset the recorder

        // Same fresh path with dispatchDna:false → no dispatch.
        $this->makeService($this->apiReturning('PHPUNIT-OPTOUT-ADDR-B', ['City' => self::CITY . 'B']))
            ->searchByAddress(
                ['street_number' => '1', 'street_name' => 'Main', 'city' => self::CITY . 'B'],
                dispatchDna: false,
            );
        Bus::assertNotDispatched(ComputeLocationDna::class);
    }
}
