<?php

namespace Tests\Unit\SmartTags;

use App\Services\SmartTags\Derivation\BridgeStructuredTagDeriver;
use App\Support\SmartTags\SmartTagContext;
use App\Support\SmartTags\SmartTagSource;
use App\Support\SmartTags\SmartTagTaxonomy;
use PHPUnit\Framework\TestCase;
use Tests\Unit\SmartTags\Concerns\BuildsSmartTagRecords;

/**
 * Bridge structured MLS fields → canonical tags, against the real per-type fixtures.
 */
class BridgeStructuredTagDeriverTest extends TestCase
{
    use BuildsSmartTagRecords;

    private BridgeStructuredTagDeriver $deriver;

    protected function setUp(): void
    {
        parent::setUp();
        SmartTagTaxonomy::flush();
        $this->deriver = new BridgeStructuredTagDeriver();
    }

    /** @test */
    public function residential_lease_structured_values_derive_canonical_tags(): void
    {
        $record = $this->bridgeFixture('residential_lease');
        $context = $this->deriver->contextFor($record);
        $this->assertSame(SmartTagContext::ResidentialLease, $context);

        $states = $this->states($this->deriver->derive($record, $context));

        // Authoritative booleans.
        $this->assertSame('present', $states['private_pool']);
        $this->assertSame('present', $states['waterfront']);
        $this->assertSame('present', $states['garage']);
        $this->assertSame('present', $states['spa']);
        $this->assertSame('absent', $states['fireplace'], 'FireplaceYN = false is an authoritative structured No');
        $this->assertSame('absent', $states['carport']);

        // Multi-select values through the shared dictionaries.
        $this->assertSame('present', $states['open_floor_plan']);
        $this->assertSame('present', $states['high_ceilings']);
        $this->assertSame('present', $states['solid_surface_countertops']);
        $this->assertSame('present', $states['tile_flooring']);
        $this->assertSame('present', $states['furnished']);
        $this->assertSame('present', $states['cable_included']);
        $this->assertSame('present', $states['lawn_care_included']);
        $this->assertSame('present', $states['pest_control_included']);

        // PetsAllowed = ["No"] is an authoritative pet policy.
        $this->assertSame('absent', $states['pets_allowed']);
    }

    /** @test */
    public function every_evidence_item_is_structured_mls_in_the_listing_context(): void
    {
        $record = $this->bridgeFixture('residential_lease');
        foreach ($this->deriver->derive($record, SmartTagContext::ResidentialLease) as $evidence) {
            $this->assertSame(SmartTagSource::StructuredMls, $evidence->source);
            $this->assertSame(SmartTagContext::ResidentialLease, $evidence->context);
            $this->assertTrue(SmartTagTaxonomy::get($evidence->tagKey)->appliesTo(SmartTagContext::ResidentialLease));
            $this->assertNotNull($evidence->ruleId);
        }
    }

    /** @test */
    public function a_missing_checklist_value_is_unknown_not_absent(): void
    {
        $record = $this->bridgeFixture('residential');
        $states = $this->states($this->deriver->derive($record, SmartTagContext::ResidentialSale));

        // InteriorFeatures has no "Vaulted Ceiling(s)" — that says nothing about ceilings.
        $this->assertArrayNotHasKey('vaulted_ceilings', $states);
        $this->assertArrayNotHasKey('quartz_countertops', $states);
        $this->assertSame('present', $states['walk_in_closet']);
        $this->assertSame('present', $states['split_floor_plan']);
        $this->assertSame('present', $states['vacant']);
    }

    /** @test */
    public function residential_sale_does_not_emit_rental_tags_even_when_the_fields_exist(): void
    {
        $record = $this->bridgeFixture('residential', ['Furnished' => 'Furnished', 'OwnerPays' => ['Water']]);
        $states = $this->states($this->deriver->derive($record, SmartTagContext::ResidentialSale));

        $this->assertArrayNotHasKey('pets_allowed', $states, 'PetsAllowed exists on the record, but pets_allowed is a lease tag');
        $this->assertArrayNotHasKey('furnished', $states);
        $this->assertArrayNotHasKey('water_included', $states);
    }

    /** @test */
    public function commercial_sale_building_features_derive_commercial_tags(): void
    {
        $record = $this->bridgeFixture('commercial_sale');
        $context = $this->deriver->contextFor($record);
        $this->assertSame(SmartTagContext::CommercialSale, $context);

        $states = $this->states($this->deriver->derive($record, $context));

        $this->assertSame('present', $states['fenced_lot']);
        $this->assertSame('present', $states['outside_storage']);
        $this->assertSame('present', $states['reception_area']);
        $this->assertSame('present', $states['tenant_occupied']);
        $this->assertArrayNotHasKey('fenced_yard', $states, 'Residential fencing key must not appear on a commercial listing');
        $this->assertArrayNotHasKey('loading_dock', $states, 'Zero dock-high bays is not a loading dock');
    }

    /** @test */
    public function business_opportunity_does_not_inherit_land_or_residential_lot_tags(): void
    {
        $record = $this->bridgeFixture('business_opportunity');
        $context = $this->deriver->contextFor($record);
        $this->assertSame(SmartTagContext::BusinessSale, $context);

        $states = $this->states($this->deriver->derive($record, $context));

        $this->assertSame('present', $states['sold_with_real_estate']);
        $this->assertSame('present', $states['fenced_lot']);
        $this->assertSame('present', $states['waterfront']);

        // LotFeatures on this record include Cleared, Pasture, Oversized Lot, Zoned for Horses.
        foreach (['cleared_land', 'pasture', 'oversized_lot', 'zoned_for_horses'] as $leak) {
            $this->assertArrayNotHasKey($leak, $states, "{$leak} does not apply to Business Opportunity");
        }
    }

    /** @test */
    public function commercial_build_out_tags_do_not_leak_into_vacant_land(): void
    {
        $record = $this->bridgeFixture('vacant_land');
        $context = $this->deriver->contextFor($record);
        $this->assertSame(SmartTagContext::LandSale, $context);

        $states = $this->states($this->deriver->derive($record, $context));

        $this->assertArrayNotHasKey('reception_area', $states);
        $this->assertArrayNotHasKey('outside_storage', $states);
        $this->assertArrayNotHasKey('tenant_occupied', $states);
        $this->assertSame('present', $states['fenced_lot']);
    }

    /** @test */
    public function an_unsupported_property_type_has_no_context(): void
    {
        $this->assertNull($this->deriver->contextFor($this->bridgeRecord(['PropertyType' => 'Farm'])));
        $this->assertNull($this->deriver->contextFor($this->bridgeRecord([])));
    }

    /** @test */
    public function the_deriver_ignores_public_remarks_entirely(): void
    {
        $record = $this->bridgeFixture('residential', [
            'PublicRemarks' => 'Quartz countertops, private pool, vaulted ceilings, updated kitchen, move-in ready.',
        ]);

        $keys = $this->presentKeys($this->deriver->derive($record, SmartTagContext::ResidentialSale));

        foreach (['quartz_countertops', 'private_pool', 'vaulted_ceilings', 'updated_kitchen', 'move_in_ready'] as $remarksOnly) {
            $this->assertNotContains($remarksOnly, $keys);
        }

        $this->assertArrayNotHasKey('field:PublicRemarks', $this->deriver->structuredInputs($record),
            'Remarks must not even feed the structured change-detection hash');
    }

    /** @test */
    public function structured_inputs_change_only_when_a_rule_field_changes(): void
    {
        $a = $this->deriver->structuredInputs($this->bridgeFixture('residential'));
        $b = $this->deriver->structuredInputs($this->bridgeFixture('residential', ['ListPrice' => 999999, 'PublicRemarks' => 'changed']));
        $c = $this->deriver->structuredInputs($this->bridgeFixture('residential', ['InteriorFeatures' => ['Vaulted Ceiling(s)']]));

        $this->assertSame($a, $b);
        $this->assertNotSame($a, $c);
    }
}
