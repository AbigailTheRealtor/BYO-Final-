<?php

namespace Tests\Unit\Bridge;

use App\Models\BridgeProperty;
use App\Services\Bridge\BridgePropertyCandidateAdapter;
use Tests\TestCase;

class BridgePropertyCandidateAdapterTest extends TestCase
{
    private function makeModel(array $attributes = []): BridgeProperty
    {
        // make() applies model casts without hitting the database.
        return BridgeProperty::make(array_merge([
            'listing_key'             => 'K-1',
            'listing_id'              => 'MLS-1',
            'standard_status'         => 'Active',
            'mls_status'              => 'Active',
            'property_type'           => 'Residential',
            'property_sub_type'       => 'Single Family Residence',
            'list_price'              => 350000,
            'unparsed_address'        => '123 Main St',
            'city'                    => 'Tampa',
            'state_or_province'       => 'FL',
            'postal_code'             => '33601',
            'county_or_parish'        => 'Hillsborough',
            'bedrooms_total'          => 3,
            'bathrooms_total_integer' => 2,
            'living_area'             => 1800,
            'lot_size_sqft'           => 6000,
            'year_built'              => 1998,
            'latitude'                => 27.9506000,
            'longitude'               => -82.4572000,
            'association_fee'         => 120,
            'tax_annual_amount'       => 4200,
            'pets_allowed'            => 'Yes',
            'pool_private_yn'         => true,
            'garage_yn'               => true,
            'waterfront_yn'           => false,
            'senior_community_yn'     => false,
            'association_yn'          => true,
            'raw_json'                => json_encode(['PublicRemarks' => 'Lovely home', 'DaysOnMarket' => 12]),
        ], $attributes));
    }

    public function test_maps_native_columns_to_candidate(): void
    {
        $candidate = (new BridgePropertyCandidateAdapter())->fromModel($this->makeModel());

        $this->assertSame('bridge', $candidate->source);
        $this->assertSame('MLS-1', $candidate->mlsNumber);
        $this->assertSame('K-1', $candidate->listingKey);
        $this->assertSame('Residential', $candidate->propertyType);
        $this->assertSame(350000.0, $candidate->listPrice);
        $this->assertSame('Tampa', $candidate->city);
        $this->assertSame(3, $candidate->bedrooms);
        $this->assertSame(2, $candidate->bathrooms);
        $this->assertSame(1800, $candidate->livingAreaSqft);
        $this->assertSame(6000, $candidate->lotSizeSqft);
        $this->assertSame(1998, $candidate->yearBuilt);
        $this->assertSame(120.0, $candidate->associationFee);
        $this->assertSame(4200.0, $candidate->taxAnnualAmount);
    }

    public function test_casts_types_correctly(): void
    {
        $candidate = (new BridgePropertyCandidateAdapter())->fromModel($this->makeModel());

        $this->assertIsFloat($candidate->latitude);
        $this->assertIsFloat($candidate->longitude);
        $this->assertEqualsWithDelta(27.9506, $candidate->latitude, 0.0001);
        $this->assertTrue($candidate->pool);
        $this->assertTrue($candidate->garage);
        $this->assertFalse($candidate->waterfront);
        $this->assertTrue($candidate->association);
    }

    public function test_decodes_raw_json_into_raw(): void
    {
        $candidate = (new BridgePropertyCandidateAdapter())->fromModel($this->makeModel());

        $this->assertSame('Lovely home', $candidate->raw['PublicRemarks']);
        $this->assertSame(12, $candidate->raw['DaysOnMarket']);
    }

    public function test_null_columns_map_to_null_not_zero(): void
    {
        $model = $this->makeModel([
            'list_price'     => null,
            'living_area'    => null,
            'year_built'     => null,
            'latitude'       => null,
            'pool_private_yn' => null,
            'raw_json'       => null,
        ]);

        $candidate = (new BridgePropertyCandidateAdapter())->fromModel($model);

        $this->assertNull($candidate->listPrice);
        $this->assertNull($candidate->livingAreaSqft);
        $this->assertNull($candidate->yearBuilt);
        $this->assertNull($candidate->latitude);
        $this->assertNull($candidate->pool);
        $this->assertSame([], $candidate->raw);
    }
}
