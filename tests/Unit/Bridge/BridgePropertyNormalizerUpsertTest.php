<?php

namespace Tests\Unit\Bridge;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use App\Jobs\ComputeLocationDna;
use App\Models\BridgeProperty;
use App\Services\Bridge\BridgePropertyNormalizer;
use App\Services\Bridge\CriteriaHashService;
use App\Services\Bridge\LazyBridgeImportService;
use App\Services\Bridge\OData\CriteriaODataFilterBuilderInterface;
use App\Services\Bridge\UpsertResult;
use App\Services\Stellar\Matching\DTO\BuyerCriteriaPayload;

/**
 * Unit tests for BridgePropertyNormalizer::upsert().
 *
 * Covers the four cases defined in the task spec:
 *  (a) New record        → isNew=true,  addressChanged=false → shouldDispatchDna=true
 *  (b) Existing, address unchanged → isNew=false, addressChanged=false → shouldDispatchDna=false
 *  (c) Existing, unparsed_address changed → isNew=false, addressChanged=true → shouldDispatchDna=true
 *  (d) Existing, postal_code changed only → isNew=false, addressChanged=true → shouldDispatchDna=true
 *
 * Additionally verifies that ComputeLocationDna is dispatched exactly when
 * shouldDispatchDna() returns true and not dispatched otherwise.
 */
class BridgePropertyNormalizerUpsertTest extends TestCase
{
    use DatabaseTransactions;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function normalizer(): BridgePropertyNormalizer
    {
        return new BridgePropertyNormalizer();
    }

    private function apiRecord(string $listingKey = 'TEST-001', array $overrides = []): array
    {
        return array_merge([
            'ListingKey'            => $listingKey,
            'ListingId'             => $listingKey . '-id',
            'StandardStatus'        => 'Active',
            'PropertyType'          => 'Residential',
            'ListPrice'             => 300000,
            'UnparsedAddress'       => '100 Oak Ave',
            'City'                  => 'Tampa',
            'StateOrProvince'       => 'FL',
            'PostalCode'            => '33601',
            'BedroomsTotal'         => 3,
            'BathroomsTotalInteger' => 2,
            'LivingArea'            => 1500,
            'ModificationTimestamp' => '2026-01-01T00:00:00Z',
        ], $overrides);
    }

    // -------------------------------------------------------------------------
    // (a) New record
    // -------------------------------------------------------------------------

    public function test_new_record_returns_is_new_true(): void
    {
        $result = $this->normalizer()->upsert($this->apiRecord('NEW-001'));

        $this->assertInstanceOf(UpsertResult::class, $result);
        $this->assertTrue($result->isNew);
        $this->assertFalse($result->addressChanged);
        $this->assertTrue($result->shouldDispatchDna());
    }

    public function test_new_record_persists_to_database(): void
    {
        $this->normalizer()->upsert($this->apiRecord('NEW-002'));

        $this->assertDatabaseHas('bridge_properties', [
            'listing_key' => 'NEW-002',
            'city'        => 'Tampa',
        ]);
    }

    public function test_new_record_returns_model_instance(): void
    {
        $result = $this->normalizer()->upsert($this->apiRecord('NEW-003'));

        $this->assertInstanceOf(BridgeProperty::class, $result->model);
        $this->assertSame('NEW-003', $result->model->listing_key);
    }

    // -------------------------------------------------------------------------
    // (b) Existing record — address unchanged
    // -------------------------------------------------------------------------

    public function test_existing_address_unchanged_returns_address_changed_false(): void
    {
        // First import — creates the row
        $this->normalizer()->upsert($this->apiRecord('EXIST-001'));

        // Second import — same address
        $result = $this->normalizer()->upsert($this->apiRecord('EXIST-001'));

        $this->assertFalse($result->isNew);
        $this->assertFalse($result->addressChanged);
        $this->assertFalse($result->shouldDispatchDna());
    }

    public function test_existing_address_unchanged_does_not_duplicate_row(): void
    {
        $this->normalizer()->upsert($this->apiRecord('EXIST-002'));
        $this->normalizer()->upsert($this->apiRecord('EXIST-002'));

        $this->assertSame(1, BridgeProperty::where('listing_key', 'EXIST-002')->count());
    }

    // -------------------------------------------------------------------------
    // (c) Existing record — unparsed_address changed
    // -------------------------------------------------------------------------

    public function test_address_string_change_returns_address_changed_true(): void
    {
        $this->normalizer()->upsert($this->apiRecord('ADDR-001', ['UnparsedAddress' => '100 Oak Ave']));

        $result = $this->normalizer()->upsert($this->apiRecord('ADDR-001', ['UnparsedAddress' => '200 Elm St']));

        $this->assertFalse($result->isNew);
        $this->assertTrue($result->addressChanged);
        $this->assertTrue($result->shouldDispatchDna());
    }

    public function test_address_null_to_non_null_is_treated_as_changed(): void
    {
        // Insert row with no address
        BridgeProperty::create([
            'listing_key'     => 'ADDR-002',
            'standard_status' => 'Active',
            'imported_at'     => now(),
        ]);

        $result = $this->normalizer()->upsert($this->apiRecord('ADDR-002', ['UnparsedAddress' => '999 Pine Rd']));

        $this->assertFalse($result->isNew);
        $this->assertTrue($result->addressChanged);
        $this->assertTrue($result->shouldDispatchDna());
    }

    // -------------------------------------------------------------------------
    // (d) Existing record — postal_code changed only
    // -------------------------------------------------------------------------

    public function test_postal_code_change_only_returns_address_changed_true(): void
    {
        $this->normalizer()->upsert($this->apiRecord('ZIP-001', ['PostalCode' => '33601']));

        $result = $this->normalizer()->upsert($this->apiRecord('ZIP-001', ['PostalCode' => '33602']));

        $this->assertFalse($result->isNew);
        $this->assertTrue($result->addressChanged);
        $this->assertTrue($result->shouldDispatchDna());
    }

    // -------------------------------------------------------------------------
    // (e) Existing record — coordinates changed (address text unchanged)
    // -------------------------------------------------------------------------

    public function test_latitude_change_triggers_address_changed(): void
    {
        // Insert row with original coordinates
        BridgeProperty::create([
            'listing_key'      => 'COORD-001',
            'standard_status'  => 'Active',
            'unparsed_address' => '100 Oak Ave',
            'postal_code'      => '33601',
            'latitude'         => 27.9506,
            'longitude'        => -82.4572,
            'imported_at'      => now(),
        ]);

        // MLS corrects latitude only — address text stays the same
        $result = $this->normalizer()->upsert($this->apiRecord('COORD-001', [
            'UnparsedAddress' => '100 Oak Ave',
            'PostalCode'      => '33601',
            'Latitude'        => 27.9600,
            'Longitude'       => -82.4572,
        ]));

        $this->assertFalse($result->isNew);
        $this->assertTrue($result->addressChanged, 'Latitude change should mark addressChanged=true');
        $this->assertTrue($result->shouldDispatchDna());
    }

    public function test_longitude_change_triggers_address_changed(): void
    {
        BridgeProperty::create([
            'listing_key'      => 'COORD-002',
            'standard_status'  => 'Active',
            'unparsed_address' => '200 Elm St',
            'postal_code'      => '33602',
            'latitude'         => 27.9506,
            'longitude'        => -82.4572,
            'imported_at'      => now(),
        ]);

        // Only longitude differs
        $result = $this->normalizer()->upsert($this->apiRecord('COORD-002', [
            'UnparsedAddress' => '200 Elm St',
            'PostalCode'      => '33602',
            'Latitude'        => 27.9506,
            'Longitude'       => -82.5000,
        ]));

        $this->assertFalse($result->isNew);
        $this->assertTrue($result->addressChanged, 'Longitude change should mark addressChanged=true');
        $this->assertTrue($result->shouldDispatchDna());
    }

    public function test_coordinates_unchanged_does_not_trigger_address_changed(): void
    {
        $this->normalizer()->upsert($this->apiRecord('COORD-003', [
            'Latitude'  => 27.9506,
            'Longitude' => -82.4572,
        ]));

        $result = $this->normalizer()->upsert($this->apiRecord('COORD-003', [
            'Latitude'  => 27.9506,
            'Longitude' => -82.4572,
        ]));

        $this->assertFalse($result->isNew);
        $this->assertFalse($result->addressChanged);
        $this->assertFalse($result->shouldDispatchDna());
    }

    // -------------------------------------------------------------------------
    // Missing ListingKey — null passthrough
    // -------------------------------------------------------------------------

    public function test_missing_listing_key_returns_null(): void
    {
        $result = $this->normalizer()->upsert([]);

        $this->assertNull($result);
    }

    // -------------------------------------------------------------------------
    // is_permanent enforcement
    // -------------------------------------------------------------------------

    public function test_deleting_non_permanent_record_succeeds(): void
    {
        $property = BridgeProperty::create([
            'listing_key'     => 'PERM-001',
            'standard_status' => 'Active',
            'is_permanent'    => false,
            'imported_at'     => now(),
        ]);

        $property->delete();

        $this->assertDatabaseMissing('bridge_properties', ['listing_key' => 'PERM-001']);
    }

    public function test_deleting_permanent_record_throws_runtime_exception(): void
    {
        $property = BridgeProperty::create([
            'listing_key'     => 'PERM-002',
            'standard_status' => 'Active',
            'is_permanent'    => true,
            'imported_at'     => now(),
        ]);

        try {
            $property->delete();
            $this->fail('Expected RuntimeException was not thrown');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('permanently retained', $e->getMessage());
            $this->assertStringContainsString('PERM-002', $e->getMessage());
        }

        // Record must still exist after the failed delete
        $this->assertDatabaseHas('bridge_properties', ['listing_key' => 'PERM-002']);
    }

    public function test_non_permanent_scope_excludes_permanent_records(): void
    {
        BridgeProperty::create([
            'listing_key'     => 'SCOPE-001',
            'standard_status' => 'Active',
            'is_permanent'    => true,
            'imported_at'     => now(),
        ]);
        BridgeProperty::create([
            'listing_key'     => 'SCOPE-002',
            'standard_status' => 'Active',
            'is_permanent'    => false,
            'imported_at'     => now(),
        ]);

        $keys = BridgeProperty::nonPermanent()
            ->whereIn('listing_key', ['SCOPE-001', 'SCOPE-002'])
            ->pluck('listing_key')
            ->toArray();

        $this->assertContains('SCOPE-002', $keys);
        $this->assertNotContains('SCOPE-001', $keys);
    }

    // -------------------------------------------------------------------------
    // DNA dispatch integration — tested via LazyBridgeImportService
    //
    // The normalizer returns UpsertResult; the callers (service / command) own
    // the dispatch decision. We exercise dispatch through LazyBridgeImportService
    // because it is the easiest caller to instantiate without I/O.
    // -------------------------------------------------------------------------

    private function makePayload(): BuyerCriteriaPayload
    {
        return new BuyerCriteriaPayload([
            'property_types'      => ['Residential'],
            'is_55_plus_eligible' => false,
        ]);
    }

    private function makeServiceWithRecords(array $records): LazyBridgeImportService
    {
        $stubBuilder = $this->createMock(CriteriaODataFilterBuilderInterface::class);
        $stubBuilder->method('build')->willReturn("StandardStatus eq 'Active'");

        $api = $this->createMock(\App\Services\Bridge\BridgeApiService::class);
        $api->method('fetchPropertiesPaginated')
            ->willReturnOnConsecutiveCalls($records, []);

        return new LazyBridgeImportService(
            new CriteriaHashService(),
            $api,
            $this->normalizer(),
            builders: ['buyer' => $stubBuilder, 'tenant' => $stubBuilder],
        );
    }

    public function test_new_record_dispatches_compute_location_dna(): void
    {
        Queue::fake();

        $service = $this->makeServiceWithRecords([$this->apiRecord('DNA-001')]);
        $service->importForCriteria($this->makePayload(), 'buyer');

        Queue::assertPushed(ComputeLocationDna::class, function ($job) {
            return $job->listingType === 'bridge' && $job->listingId > 0;
        });
    }

    public function test_address_unchanged_does_not_dispatch_compute_location_dna(): void
    {
        // Pre-create the row with the same address as the incoming record.
        BridgeProperty::create([
            'listing_key'      => 'DNA-002',
            'standard_status'  => 'Active',
            'unparsed_address' => '100 Oak Ave',
            'postal_code'      => '33601',
            'imported_at'      => now(),
        ]);

        Queue::fake();

        $service = $this->makeServiceWithRecords([
            $this->apiRecord('DNA-002', [
                'UnparsedAddress' => '100 Oak Ave',
                'PostalCode'      => '33601',
            ]),
        ]);
        $service->importForCriteria($this->makePayload(), 'buyer');

        Queue::assertNotPushed(ComputeLocationDna::class);
    }

    public function test_address_changed_dispatches_compute_location_dna(): void
    {
        BridgeProperty::create([
            'listing_key'      => 'DNA-003',
            'standard_status'  => 'Active',
            'unparsed_address' => '100 Oak Ave',
            'postal_code'      => '33601',
            'imported_at'      => now(),
        ]);

        Queue::fake();

        $service = $this->makeServiceWithRecords([
            $this->apiRecord('DNA-003', [
                'UnparsedAddress' => '200 Elm St',
                'PostalCode'      => '33601',
            ]),
        ]);
        $service->importForCriteria($this->makePayload(), 'buyer');

        Queue::assertPushed(ComputeLocationDna::class);
    }
}
