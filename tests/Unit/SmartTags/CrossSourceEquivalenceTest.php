<?php

namespace Tests\Unit\SmartTags;

use App\Services\SmartTags\Derivation\BridgeStructuredTagDeriver;
use App\Services\SmartTags\Derivation\ListingDescriptionTagParser;
use App\Services\SmartTags\Derivation\NativeListingTagDeriver;
use App\Services\SmartTags\SmartTagResolver;
use App\Support\SmartTags\SmartTagContext;
use App\Support\SmartTags\SmartTagListingType;
use App\Support\SmartTags\SmartTagSelectionPolicy;
use App\Support\SmartTags\SmartTagSource;
use App\Support\SmartTags\SmartTagTaxonomy;
use PHPUnit\Framework\TestCase;
use Tests\Unit\SmartTags\Concerns\BuildsSmartTagRecords;

/**
 * THE core product requirement: a Bridge property and a BidYourOffer-native
 * property describing the same feature resolve to the same canonical key.
 * The source differs; the canonical meaning does not.
 */
class CrossSourceEquivalenceTest extends TestCase
{
    use BuildsSmartTagRecords;

    protected function setUp(): void
    {
        parent::setUp();
        SmartTagTaxonomy::flush();
    }

    /** @test */
    public function equivalent_bridge_and_native_seller_facts_resolve_to_equivalent_tag_sets(): void
    {
        $bridge = $this->bridgeRecord([
            'PropertyType'      => 'Residential',
            'PoolPrivateYN'     => true,
            'WaterfrontYN'      => true,
            'GarageYN'          => false,
            'InteriorFeatures'  => ['Quartz Counters', 'Vaulted Ceiling(s)', 'Open Floorplan', 'Walk-In Closet(s)'],
            'Appliances'        => ['Range Gas'],
            'STELLAR_WaterAccess' => ['Gulf/Ocean'],
            'STELLAR_WaterAccessYN' => true,
        ]);

        $native = $this->nativeMeta([
            'property_type'     => 'Residential',
            'pool_type'         => ['private' => true, 'community' => false],
            'waterfront'        => 'Yes',
            'garage_needed'     => 'No',
            'interior_features' => ['Quartz Counters', 'Vaulted Ceiling(s)', 'Open Floorplan', 'Walk-In Closet(s)'],
            'appliances'        => ['Range Gas'],
            'water_access'      => ['Gulf/Ocean'],
        ]);

        $bridgeDeriver = new BridgeStructuredTagDeriver();
        $nativeDeriver = new NativeListingTagDeriver();

        $bridgeContext = $bridgeDeriver->contextFor($bridge);
        $nativeContext = $nativeDeriver->contextFor(SmartTagListingType::SellerAgent, $native);
        $this->assertSame($bridgeContext, $nativeContext);

        $fromBridge = SmartTagResolver::resolve(array_values($bridgeDeriver->derive($bridge, $bridgeContext)), $bridgeContext);
        $fromNative = SmartTagResolver::resolve(array_values($nativeDeriver->derive(SmartTagListingType::SellerAgent, $native, $nativeContext)), $nativeContext);

        $project = static function ($resolution): array {
            $out = [];
            foreach ($resolution->assignments as $key => $row) {
                $out[$key] = [$row->state->value, $row->context->value];
            }
            ksort($out);
            return $out;
        };

        $this->assertSame($project($fromBridge), $project($fromNative));
        $this->assertSame(['present', 'residential.sale'], $project($fromNative)['quartz_countertops']);
        $this->assertSame(['absent', 'residential.sale'], $project($fromNative)['garage']);
    }

    /** @test */
    public function quartz_means_one_key_across_every_source_and_the_seeker_surface(): void
    {
        $context = SmartTagContext::ResidentialSale;

        $mls = (new BridgeStructuredTagDeriver())->derive($this->bridgeRecord(['PropertyType' => 'Residential', 'InteriorFeatures' => ['Quartz Counters']]), $context);
        $native = (new NativeListingTagDeriver())->derive(SmartTagListingType::SellerAgent, $this->nativeMeta(['property_type' => 'Residential', 'interior_features' => ['Quartz Counters']]), $context);
        $description = (new ListingDescriptionTagParser())->parse('Quartz countertops.', $context, SmartTagSource::NativeListingDescription);
        $remarks = (new ListingDescriptionTagParser())->parse('Quartz countertops.', $context, SmartTagSource::MlsRemarks);
        $manual = SmartTagSelectionPolicy::project(['quartz_countertops'], $context, SmartTagTaxonomy::SURFACE_OWNER);
        $seeker = SmartTagSelectionPolicy::project(['quartz_countertops'], $context, SmartTagTaxonomy::SURFACE_SEEKER);

        $this->assertArrayHasKey('quartz_countertops', $mls);
        $this->assertArrayHasKey('quartz_countertops', $native);
        $this->assertArrayHasKey('quartz_countertops', $description);
        $this->assertArrayHasKey('quartz_countertops', $remarks);
        $this->assertSame(['quartz_countertops'], $manual->accepted);
        $this->assertSame(['quartz_countertops'], $seeker->accepted);
    }

    /** @test */
    public function landlord_and_bridge_residential_lease_share_furnishing_and_pet_semantics(): void
    {
        $context = SmartTagContext::ResidentialLease;

        $bridge = (new BridgeStructuredTagDeriver())->derive($this->bridgeRecord([
            'PropertyType' => 'Residential Lease', 'Furnished' => 'Furnished', 'PetsAllowed' => ['No'], 'OwnerPays' => ['Water', 'Trash Collection'],
        ]), $context);

        $native = (new NativeListingTagDeriver())->derive(SmartTagListingType::LandlordAgent, $this->nativeMeta([
            'property_type' => 'Residential Property', 'tenant_require' => 'Furnished', 'pets' => 'No', 'rent_includes' => ['Water', 'Trash Collection'],
        ]), $context);

        $this->assertSame($this->states($bridge), $this->states($native));
    }
}
