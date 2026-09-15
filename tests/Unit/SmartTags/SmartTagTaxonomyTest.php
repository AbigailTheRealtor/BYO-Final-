<?php

namespace Tests\Unit\SmartTags;

use App\Support\SmartTags\SmartTagContext;
use App\Support\SmartTags\SmartTagDefinition;
use App\Support\SmartTags\SmartTagTaxonomy;
use PHPUnit\Framework\TestCase;

/**
 * The canonical taxonomy: valid, closed, and context-aware.
 *
 * Pure — runs without a booted application, the same way the selection policy
 * and derivers must.
 */
class SmartTagTaxonomyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        SmartTagTaxonomy::flush();
    }

    /** @test */
    public function the_taxonomy_is_structurally_valid(): void
    {
        $this->assertSame([], SmartTagTaxonomy::validationErrors());
        $this->assertNotSame('', SmartTagTaxonomy::version());
        $this->assertGreaterThan(100, count(SmartTagTaxonomy::all()));
    }

    /** @test */
    public function every_seven_contexts_has_applicable_tags_and_only_seven_exist(): void
    {
        $this->assertCount(7, SmartTagContext::cases());

        foreach (SmartTagContext::cases() as $context) {
            $this->assertNotEmpty(SmartTagTaxonomy::forContext($context), "No tags apply to {$context->value}");
        }
    }

    /** @test */
    public function residential_interior_tags_do_not_leak_into_vacant_land(): void
    {
        $land = array_keys(SmartTagTaxonomy::forContext(SmartTagContext::LandSale));

        foreach (['updated_kitchen', 'quartz_countertops', 'vaulted_ceilings', 'walk_in_closet', 'private_pool',
                  'fenced_yard', 'garage', 'screened_lanai_porch', 'move_in_ready', 'turnkey_home'] as $residential) {
            $this->assertNotContains($residential, $land, "{$residential} must not be offered for Vacant Land");
        }

        foreach (['cleared_land', 'pasture', 'fenced_lot', 'well_water', 'waterfront'] as $landTag) {
            $this->assertContains($landTag, $land);
        }
    }

    /** @test */
    public function commercial_tags_do_not_leak_into_residential(): void
    {
        foreach ([SmartTagContext::ResidentialSale, SmartTagContext::ResidentialLease] as $context) {
            $keys = array_keys(SmartTagTaxonomy::forContext($context));
            foreach (['loading_dock', 'overhead_doors', 'truck_well', 'reception_area', 'vanilla_shell',
                      'three_phase_power', 'liquor_license_included', 'turnkey_business', 'fenced_lot'] as $commercial) {
                $this->assertNotContains($commercial, $keys, "{$commercial} must not be offered in {$context->value}");
            }
        }
    }

    /** @test */
    public function land_and_business_tags_do_not_clutter_residential_lease_or_commercial_lease(): void
    {
        $residentialLease = array_keys(SmartTagTaxonomy::forContext(SmartTagContext::ResidentialLease));
        $commercialLease = array_keys(SmartTagTaxonomy::forContext(SmartTagContext::CommercialLease));

        foreach (['cleared_land', 'pasture', 'wooded_land', 'buildable_lot', 'teardown'] as $land) {
            $this->assertNotContains($land, $residentialLease);
        }

        foreach (['sold_with_real_estate', 'liquor_license_included', 'inventory_included', 'goodwill_included', 'turnkey_business'] as $business) {
            $this->assertNotContains($business, $commercialLease, "{$business} is Business Opportunity only");
        }
    }

    /** @test */
    public function one_key_one_meaning_splits_context_dependent_phrases(): void
    {
        $this->assertSame(['residential.sale'], SmartTagTaxonomy::get('turnkey_home')->contextValues());
        $this->assertSame(['business.sale'], SmartTagTaxonomy::get('turnkey_business')->contextValues());

        $yard = SmartTagTaxonomy::get('fenced_yard')->contextValues();
        $lot = SmartTagTaxonomy::get('fenced_lot')->contextValues();
        $this->assertSame([], array_intersect($yard, $lot), 'fenced_yard and fenced_lot must never share a context');

        $this->assertNull(SmartTagTaxonomy::get('turnkey'), 'A context-ambiguous "turnkey" key must not exist');
    }

    /** @test */
    public function conflicts_are_symmetric(): void
    {
        foreach (SmartTagTaxonomy::all() as $key => $definition) {
            foreach ($definition->conflictsWith as $other) {
                $this->assertTrue(SmartTagTaxonomy::get($other)->conflictsWith($key), "{$key} ↔ {$other} is not symmetric");
            }
        }

        $this->assertTrue(SmartTagTaxonomy::get('move_in_ready')->conflictsWith('fixer_upper'));
        $this->assertTrue(SmartTagTaxonomy::get('furnished')->conflictsWith('unfurnished'));
    }

    /** @test */
    public function seeker_restricted_tags_are_owner_describable_but_not_seeker_selectable(): void
    {
        foreach (['accessible_features', 'playground'] as $key) {
            $definition = SmartTagTaxonomy::get($key);
            $this->assertTrue($definition->isOwnerSelectable(), "{$key} should be owner-describable");
            $this->assertFalse($definition->isSeekerSelectable(), "{$key} must not be a Buyer/Tenant preference in V1");
            $this->assertSame(SmartTagDefinition::COMPLIANCE_RESTRICTED, $definition->complianceStatus);
        }
    }

    /** @test */
    public function pets_allowed_carries_the_assistance_animal_notice(): void
    {
        $pets = SmartTagTaxonomy::get('pets_allowed');

        $this->assertNotNull($pets->complianceNotice);
        $this->assertStringContainsStringIgnoringCase('assistance animals', $pets->complianceNotice);
        $this->assertStringContainsStringIgnoringCase('not pets', $pets->complianceNotice);
    }

    /** @test */
    public function pending_review_tags_are_neither_selectable_nor_publicly_displayed(): void
    {
        $pending = array_filter(SmartTagTaxonomy::all(), static fn (SmartTagDefinition $d) => $d->isPendingReview());
        $this->assertNotEmpty($pending);

        foreach ($pending as $key => $definition) {
            $this->assertFalse($definition->isOwnerSelectable(), $key);
            $this->assertFalse($definition->isSeekerSelectable(), $key);
            $this->assertFalse($definition->isPubliclyDisplayable(), $key);
        }
    }

    /** @test */
    public function surface_filtering_returns_only_selectable_tags_in_display_order(): void
    {
        $owner = SmartTagTaxonomy::forContext(SmartTagContext::ResidentialSale, SmartTagTaxonomy::SURFACE_OWNER);
        $seeker = SmartTagTaxonomy::forContext(SmartTagContext::ResidentialSale, SmartTagTaxonomy::SURFACE_SEEKER);

        $this->assertArrayHasKey('playground', $owner);
        $this->assertArrayNotHasKey('playground', $seeker);
        $this->assertArrayNotHasKey('gated_community', $owner);

        $orders = array_map(static fn (SmartTagDefinition $d) => $d->displayOrder, array_values($owner));
        $sorted = $orders;
        sort($sorted);
        $this->assertSame($sorted, $orders);
    }

    /** @test */
    public function fifty_five_plus_is_never_a_tag(): void
    {
        foreach (array_keys(SmartTagTaxonomy::all()) as $key) {
            $this->assertDoesNotMatchRegularExpression('/55|62|senior|age_restrict|active_adult/i', $key);
        }
    }
}
