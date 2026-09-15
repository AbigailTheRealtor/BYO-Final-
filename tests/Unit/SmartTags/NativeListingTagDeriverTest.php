<?php

namespace Tests\Unit\SmartTags;

use App\Services\SmartTags\Derivation\NativeListingTagDeriver;
use App\Support\SmartTags\SmartTagContext;
use App\Support\SmartTags\SmartTagListingType;
use App\Support\SmartTags\SmartTagSource;
use App\Support\SmartTags\SmartTagTaxonomy;
use PHPUnit\Framework\TestCase;
use Tests\Unit\SmartTags\Concerns\BuildsSmartTagRecords;

/**
 * Native Seller/Landlord structured form answers → canonical tags.
 */
class NativeListingTagDeriverTest extends TestCase
{
    use BuildsSmartTagRecords;

    private NativeListingTagDeriver $deriver;

    protected function setUp(): void
    {
        parent::setUp();
        SmartTagTaxonomy::flush();
        $this->deriver = new NativeListingTagDeriver();
    }

    /** @test */
    public function seller_residential_form_answers_derive_canonical_tags(): void
    {
        $meta = $this->nativeMeta([
            'property_type'            => 'Residential',
            'waterfront'               => 'Yes',
            'pool_needed'              => 'Yes',
            'pool_type'                => ['private' => true, 'community' => false],
            'garage_needed'            => 'No',
            'interior_features'        => ['Quartz Counters', 'Vaulted Ceiling(s)', 'Open Floorplan', 'Other'],
            'appliances'               => ['Range Gas', 'Dishwasher'],
            'non_negotiable_amenities' => ['Updated Kitchen', 'Updated Bathroom', 'Specific School District', '55 and Over Community', 'Pool'],
            'condition_prop'           => 'No updates needed: Completely updated',
            'water_access'             => ['Gulf/Ocean', 'Canal - Saltwater'],
        ]);

        $context = $this->deriver->contextFor(SmartTagListingType::SellerAgent, $meta);
        $this->assertSame(SmartTagContext::ResidentialSale, $context);

        $evidence = $this->deriver->derive(SmartTagListingType::SellerAgent, $meta, $context);
        $states = $this->states($evidence);

        $this->assertSame('present', $states['waterfront']);
        $this->assertSame('present', $states['private_pool']);
        $this->assertSame('absent', $states['garage']);
        $this->assertSame('present', $states['quartz_countertops']);
        $this->assertSame('present', $states['vaulted_ceilings']);
        $this->assertSame('present', $states['open_floor_plan']);
        $this->assertSame('present', $states['gas_range']);
        $this->assertSame('present', $states['updated_kitchen']);
        $this->assertSame('present', $states['updated_bathrooms']);
        $this->assertSame('present', $states['fully_updated']);
        $this->assertSame('present', $states['water_access']);
        $this->assertSame('present', $states['gulf_or_ocean_access']);
        $this->assertSame('present', $states['canal_frontage']);

        // "Pool" amenity alone is ambiguous; 55+ and school district map to nothing.
        $this->assertArrayNotHasKey('community_pool', $states);

        foreach ($evidence as $item) {
            $this->assertSame(SmartTagSource::StructuredNativeListing, $item->source);
        }
    }

    /** @test */
    public function pool_yes_without_a_pool_type_is_unknown_but_pool_no_is_a_confirmed_absence(): void
    {
        $yes = $this->states($this->deriver->derive(SmartTagListingType::SellerAgent,
            $this->nativeMeta(['property_type' => 'Residential', 'pool_needed' => 'Yes']), SmartTagContext::ResidentialSale));
        $this->assertArrayNotHasKey('private_pool', $yes, 'Pool = Yes may be a community pool');

        $no = $this->states($this->deriver->derive(SmartTagListingType::SellerAgent,
            $this->nativeMeta(['property_type' => 'Residential', 'pool_needed' => 'No']), SmartTagContext::ResidentialSale));
        $this->assertSame('absent', $no['private_pool']);

        $community = $this->states($this->deriver->derive(SmartTagListingType::SellerAgent,
            $this->nativeMeta(['property_type' => 'Residential', 'pool_needed' => 'Yes', 'pool_type' => ['private' => false, 'community' => true]]),
            SmartTagContext::ResidentialSale));
        $this->assertSame('present', $community['community_pool']);
        $this->assertArrayNotHasKey('private_pool', $community);
    }

    /** @test */
    public function landlord_furnishings_map_turnkey_to_furnished_and_empty_array_to_unknown(): void
    {
        $turnkey = $this->states($this->deriver->derive(SmartTagListingType::LandlordAgent,
            $this->nativeMeta(['property_type' => 'Residential Property', 'tenant_require' => '"Turnkey"']), SmartTagContext::ResidentialLease));
        $this->assertSame('present', $turnkey['furnished']);
        $this->assertArrayNotHasKey('turnkey_home', $turnkey, 'Landlord "Turnkey" furnishings are not a home-condition tag');

        $createDefect = $this->states($this->deriver->derive(SmartTagListingType::LandlordAgent,
            $this->nativeMeta(['property_type' => 'Residential Property', 'tenant_require' => '[]']), SmartTagContext::ResidentialLease));
        $this->assertArrayNotHasKey('furnished', $createDefect);
        $this->assertArrayNotHasKey('unfurnished', $createDefect, '"[]" must never read as unfurnished');
    }

    /** @test */
    public function both_landlord_condition_vocabularies_are_understood(): void
    {
        foreach (['Updated / Renovated', 'No updates needed: Completely updated'] as $value) {
            $states = $this->states($this->deriver->derive(SmartTagListingType::LandlordAgent,
                $this->nativeMeta(['property_type' => 'Residential Property', 'condition_prop' => $value]), SmartTagContext::ResidentialLease));
            $this->assertSame('present', $states['fully_updated'], $value);
        }

        $older = $this->states($this->deriver->derive(SmartTagListingType::LandlordAgent,
            $this->nativeMeta(['property_type' => 'Residential Property', 'condition_prop' => 'Older but Well Maintained']), SmartTagContext::ResidentialLease));
        $this->assertSame('present', $older['older_well_maintained']);
    }

    /** @test */
    public function landlord_commercial_building_features_and_pets(): void
    {
        $meta = $this->nativeMeta([
            'property_type'     => 'Commercial Property',
            'building_features' => ['Loading Dock', 'Overhead Doors', "Elevator \u{2013} None", 'Furnished'],
            'number_of_offices' => '4',
            'pets'              => 'Yes',
        ]);

        $states = $this->states($this->deriver->derive(SmartTagListingType::LandlordAgent, $meta, SmartTagContext::CommercialLease));

        $this->assertSame('present', $states['loading_dock']);
        $this->assertSame('present', $states['overhead_doors']);
        $this->assertSame('absent', $states['elevator']);
        $this->assertSame('present', $states['furnished']);
        $this->assertSame('present', $states['private_offices']);
        $this->assertArrayNotHasKey('pets_allowed', $states, 'pets_allowed applies to residential leases only');
    }

    /** @test */
    public function residential_form_fields_never_emit_commercial_tags_and_vice_versa(): void
    {
        $residential = $this->states($this->deriver->derive(SmartTagListingType::SellerAgent,
            $this->nativeMeta(['property_type' => 'Residential', 'building_features' => ['Loading Dock', 'Drive-Through'], 'non_negotiable_amenities' => ['Loading Dock']]),
            SmartTagContext::ResidentialSale));
        $this->assertArrayNotHasKey('loading_dock', $residential);
        $this->assertArrayNotHasKey('drive_through', $residential);

        $land = $this->states($this->deriver->derive(SmartTagListingType::SellerAgent,
            $this->nativeMeta(['property_type' => 'Vacant Land', 'interior_features' => ['Quartz Counters'], 'non_negotiable_amenities' => ['Updated Kitchen'], 'vegetation' => ['Cleared'], 'fences' => ['Split Rail']]),
            SmartTagContext::LandSale));
        $this->assertArrayNotHasKey('quartz_countertops', $land);
        $this->assertArrayNotHasKey('updated_kitchen', $land);
        $this->assertSame('present', $land['cleared_land']);
        $this->assertSame('present', $land['fenced_lot']);
    }

    /** @test */
    public function business_sale_structured_fields_derive_business_tags(): void
    {
        $states = $this->states($this->deriver->derive(SmartTagListingType::SellerAgent,
            $this->nativeMeta([
                'property_type'        => 'Business',
                'real_estate_purchase' => 'Business Only',
                'licenses'             => ['Liquor'],
                'sale_includes'        => ['Inventory', 'Goodwill', 'Equipment/Fixtures'],
            ]), SmartTagContext::BusinessSale));

        $this->assertSame('absent', $states['sold_with_real_estate']);
        $this->assertSame('present', $states['liquor_license_included']);
        $this->assertSame('present', $states['inventory_included']);
        $this->assertSame('present', $states['goodwill_included']);
        $this->assertSame('present', $states['equipment_fixtures_included']);
    }

    /** @test */
    public function forbidden_free_text_fields_are_never_read(): void
    {
        $states = $this->states($this->deriver->derive(SmartTagListingType::LandlordAgent,
            $this->nativeMeta([
                'property_type'                => 'Residential Property',
                'breed_restrictions'           => 'private pool, fireplace, updated kitchen',
                'pet_restrictions'             => 'waterfront with dock',
                'landlord_approval_conditions' => 'garage and quartz countertops',
                'other_non_negotiable_amenities' => 'Updated Kitchen',
                'leasing_55_plus'              => 'Yes',
                'criminal_background_requirement' => 'Pool',
            ]), SmartTagContext::ResidentialLease));

        $this->assertSame([], $states);
    }

    /** @test */
    public function answered_keys_are_only_authoritative_yes_no_style_fields(): void
    {
        $meta = $this->nativeMeta([
            'property_type'            => 'Residential',
            'waterfront'               => 'No',
            'garage_needed'            => 'Yes',
            'non_negotiable_amenities' => ['Updated Kitchen', 'Fireplace'],
        ]);

        $answered = $this->deriver->answeredKeys(SmartTagListingType::SellerAgent, $meta, SmartTagContext::ResidentialSale);
        sort($answered);

        $this->assertSame(['garage', 'waterfront'], $answered, 'Checklist presence is never authoritative');
    }

    /** @test */
    public function the_deriver_refuses_bridge_rows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->deriver->derive(SmartTagListingType::Bridge, $this->nativeMeta([]), SmartTagContext::ResidentialSale);
    }
}
