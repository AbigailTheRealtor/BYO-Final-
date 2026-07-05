<?php

namespace Tests\Unit\Property;

use App\Services\Property\PropertyCandidate;
use Tests\TestCase;

class PropertyCandidateTest extends TestCase
{
    private function make(array $overrides = []): PropertyCandidate
    {
        $defaults = [
            'source' => 'bridge', 'sourceRecordId' => '42',
            'mlsNumber' => 'MLS-1', 'listingKey' => 'K-1',
            'standardStatus' => 'Active', 'mlsStatus' => 'Active',
            'propertyType' => 'Residential', 'propertySubType' => 'Single Family Residence',
            'listPrice' => 350000.0,
            'unparsedAddress' => '123 Main St', 'city' => 'Tampa',
            'stateOrProvince' => 'FL', 'postalCode' => '33601', 'countyOrParish' => 'Hillsborough',
            'bedrooms' => 3, 'bathrooms' => 2, 'livingAreaSqft' => 1800,
            'lotSizeSqft' => 6000, 'yearBuilt' => 1998,
            'latitude' => 27.95, 'longitude' => -82.45,
            'associationFee' => 120.0, 'taxAnnualAmount' => 4200.0,
            'petsAllowed' => 'Yes', 'pool' => true, 'garage' => true,
            'waterfront' => false, 'view' => null, 'waterView' => null,
            'seniorCommunity' => false, 'association' => true,
            'newConstruction' => false, 'cdd' => null,
            'modificationTimestamp' => '2026-01-15 12:00:00',
            'raw' => ['PublicRemarks' => 'Lovely home', 'ListAgentFullName' => 'Jane'],
        ];

        return new PropertyCandidate(...array_merge($defaults, $overrides));
    }

    public function test_exposes_normalized_readonly_fields(): void
    {
        $c = $this->make();

        $this->assertSame('bridge', $c->source);
        $this->assertSame('MLS-1', $c->mlsNumber);
        $this->assertSame('K-1', $c->listingKey);
        $this->assertSame(350000.0, $c->listPrice);
        $this->assertSame(3, $c->bedrooms);
        $this->assertTrue($c->pool);
        $this->assertFalse($c->waterfront);
        $this->assertNull($c->view);
    }

    public function test_to_array_excludes_raw_by_default(): void
    {
        $data = $this->make()->toArray();

        $this->assertArrayNotHasKey('raw', $data);
        $this->assertSame('MLS-1', $data['mls_number']);
        $this->assertSame('K-1', $data['listing_key']);
        $this->assertSame('Tampa', $data['city']);
        $this->assertSame(1800, $data['living_area_sqft']);
        $this->assertTrue($data['pool']);
    }

    public function test_to_array_includes_raw_when_requested(): void
    {
        $data = $this->make()->toArray(includeRaw: true);

        $this->assertArrayHasKey('raw', $data);
        $this->assertSame('Lovely home', $data['raw']['PublicRemarks']);
    }
}
