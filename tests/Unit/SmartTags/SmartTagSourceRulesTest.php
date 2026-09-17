<?php

namespace Tests\Unit\SmartTags;

use App\Services\SmartTags\Derivation\SmartTagSourceRules;
use App\Support\SmartTags\SmartTagConfig;
use App\Support\SmartTags\SmartTagListingType;
use App\Support\SmartTags\SmartTagTaxonomy;
use PHPUnit\Framework\TestCase;

/**
 * Every source rule stays inside the canonical taxonomy, and the taxonomy's
 * derivability flags tell the truth about the rules.
 */
class SmartTagSourceRulesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        SmartTagTaxonomy::flush();
        SmartTagSourceRules::flush();
    }

    /** @test */
    public function the_source_rules_are_structurally_valid(): void
    {
        $this->assertSame([], SmartTagSourceRules::validationErrors());
    }

    /** @test */
    public function derivability_flags_match_the_rules_that_exist(): void
    {
        $mls = [];
        $native = [];

        foreach (SmartTagSourceRules::bridgeRules() as $rule) {
            foreach (SmartTagSourceRules::tagsEmittableBy($rule) as $tag) {
                $mls[$tag] = true;
            }
        }
        foreach ([SmartTagListingType::SellerAgent, SmartTagListingType::LandlordAgent] as $type) {
            foreach (SmartTagSourceRules::nativeRules($type) as $rule) {
                foreach (SmartTagSourceRules::tagsEmittableBy($rule) as $tag) {
                    if (SmartTagSourceRules::ruleContexts($rule, $tag, $type) !== []) {
                        $native[$tag] = true;
                    }
                }
            }
        }
        foreach (array_keys(SmartTagSourceRules::descriptionRules()) as $tag) {
            $mls[$tag] = true;
            $native[$tag] = true;
        }

        foreach (SmartTagTaxonomy::all() as $key => $definition) {
            $this->assertSame(isset($mls[$key]), $definition->mlsDerivable, "{$key}: mls_derivable does not match the rules");
            $this->assertSame(isset($native[$key]), $definition->nativeDerivable, "{$key}: native_derivable does not match the rules");
        }
    }

    /** @test */
    public function no_structured_rule_reads_a_forbidden_or_free_text_field(): void
    {
        foreach ([SmartTagListingType::SellerAgent, SmartTagListingType::LandlordAgent] as $type) {
            foreach (SmartTagSourceRules::nativeRules($type) as $rule) {
                $this->assertFalse(SmartTagSourceRules::isForbiddenKey((string) $rule['field']), "{$rule['id']} reads {$rule['field']}");
            }
        }

        foreach (['breed_restrictions', 'pet_restrictions', 'landlord_approval_conditions', 'neighboring_tenants',
                  'intended_business_use', 'compatibility_preferences', 'buyer_deal_breakers', 'criminal_background_requirement',
                  'other_non_negotiable_amenities', 'custom_income_requirement', 'leasing_55_plus'] as $forbidden) {
            $this->assertTrue(SmartTagSourceRules::isForbiddenKey($forbidden), "{$forbidden} must be forbidden");
        }
    }

    /** @test */
    public function bridge_structured_rules_never_read_remarks_or_listing_terms(): void
    {
        foreach (SmartTagSourceRules::bridgeRules() as $rule) {
            $field = (string) ($rule['field'] ?? $rule['column'] ?? '');
            $this->assertStringNotContainsStringIgnoringCase('Remarks', $field, $rule['id']);
            $this->assertNotSame('ListingTerms', $field, "{$rule['id']}: ListingTerms display classification is unresolved");
        }
    }

    /** @test */
    public function the_native_description_field_is_the_public_description_and_nothing_else(): void
    {
        $this->assertSame('additional_details', SmartTagSourceRules::descriptionField(SmartTagListingType::SellerAgent)['meta_key']);
        $this->assertNull(SmartTagSourceRules::descriptionField(SmartTagListingType::SellerAgent)['gate']);

        $landlord = SmartTagSourceRules::descriptionField(SmartTagListingType::LandlordAgent);
        $this->assertSame('additional_details', $landlord['meta_key']);
        $this->assertSame('landlord_provider_text', $landlord['gate'], 'Landlord prose must be read through the provider-text policy');
    }

    /** @test */
    public function excluded_amenity_options_map_to_no_tag(): void
    {
        $amenities = (array) SmartTagConfig::sources()['vocabularies']['amenities'];
        $exclusions = (array) SmartTagConfig::sources()['amenity_exclusions'];

        $this->assertArrayHasKey('55 and Over Community', $exclusions);
        $this->assertArrayHasKey('Specific School District', $exclusions);

        foreach (array_keys($exclusions) as $option) {
            $this->assertArrayNotHasKey($option, $amenities, "{$option} must not map to a Smart Tag");
        }
    }

    /** @test */
    public function non_negotiable_amenities_and_structured_quartz_resolve_to_the_same_canonical_key_as_mls(): void
    {
        $this->assertSame('updated_kitchen', SmartTagSourceRules::vocabulary('amenities')['updated kitchen']);
        $this->assertSame('quartz_countertops', SmartTagSourceRules::vocabulary('interior_features')['quartz counters']);
        $this->assertSame('vaulted_ceilings', SmartTagSourceRules::vocabulary('interior_features')['vaulted ceiling(s)']);
    }
}
