<?php

namespace Tests\Feature\FairHousing;

use App\Services\SmartTags\Derivation\ListingDescriptionTagParser;
use App\Services\SmartTags\Derivation\NativeListingTagDeriver;
use App\Services\SmartTags\Derivation\SmartTagSourceRules;
use App\Support\SmartTags\NativeMetaValueReader;
use App\Support\SmartTags\SmartTagComplianceGuard;
use App\Support\SmartTags\SmartTagConfig;
use App\Support\SmartTags\SmartTagContext;
use App\Support\SmartTags\SmartTagListingType;
use App\Support\SmartTags\SmartTagSelectionPolicy;
use App\Support\SmartTags\SmartTagSource;
use App\Support\SmartTags\SmartTagTaxonomy;
use PHPUnit\Framework\TestCase;

/**
 * Smart Tags describe the PROPERTY — never people, protected classes,
 * demographics, neighbourhood quality or "type of people" nearby.
 *
 * The taxonomy is closed and fail-closed: prohibited concepts cannot be declared,
 * cannot be selected by an owner or seeker, cannot be emitted by a parser, and
 * 55+/62+ remains a compliance gate rather than a tag.
 */
class SmartTagTaxonomyComplianceTest extends TestCase
{
    /** Concepts the product brief names as never-tags. */
    private const PROHIBITED_SEEDS = [
        'Family-Friendly', 'family friendly', 'Kid Friendly', 'Great for Families',
        'Safe Neighborhood', 'Good Neighborhood', 'Bad Neighborhood', 'quiet neighborhood', 'desirable area', 'up and coming area',
        'Retirees', 'Young Professionals', 'Empty Nesters', 'Seniors', 'students',
        'type of people', 'demographics', 'low crime', 'crime',
        '55+', '55 plus', 'over 55', 'age restricted', 'active adult', 'adults only',
        'near church', 'synagogue', 'mosque', 'religious',
        'ethnic', 'national origin', 'race',
        'handicap', 'wheelchair users', 'disabled',
        'section 8', 'vouchers', 'source of income',
        'good schools', 'school district', 'top rated schools',
        'married', 'familial',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        SmartTagTaxonomy::flush();
    }

    /** @test */
    public function the_guard_catches_every_prohibited_seed(): void
    {
        foreach (self::PROHIBITED_SEEDS as $seed) {
            $this->assertNotSame([], SmartTagComplianceGuard::violations($seed), "Guard missed: {$seed}");
        }
    }

    /** @test */
    public function the_guard_does_not_flag_legitimate_property_characteristics(): void
    {
        foreach (['Terrace', 'Accessibility Features', 'White Shaker Cabinets', 'Community Pool', 'Gated Community',
                  'Primary Bedroom on Main Floor', 'Fenced Yard', 'Private Offices', 'Playground', 'Guest Suite / Separate Living Quarters',
                  'Pets Allowed', 'Single-family residence', 'Golf Course Community', 'Zero-step entry'] as $legitimate) {
            $this->assertSame([], SmartTagComplianceGuard::violations($legitimate), $legitimate);
        }
    }

    /** @test */
    public function no_prohibited_concept_is_declared_in_the_taxonomy_or_its_phrases(): void
    {
        $this->assertSame([], SmartTagComplianceGuard::taxonomyViolations(SmartTagConfig::taxonomy()));
        $this->assertSame([], SmartTagComplianceGuard::phraseViolations(SmartTagConfig::sources()));
    }

    /** @test */
    public function prohibited_keys_cannot_be_selected_on_any_surface(): void
    {
        $keys = ['family_friendly', 'safe_neighborhood', 'good_neighborhood', 'bad_neighborhood', 'retirees',
                 'young_professionals', 'great_schools', 'fifty_five_plus', 'active_adult_community', 'near_church'];

        foreach ([SmartTagTaxonomy::SURFACE_OWNER, SmartTagTaxonomy::SURFACE_SEEKER] as $surface) {
            foreach (SmartTagContext::cases() as $context) {
                $this->assertSame([], SmartTagSelectionPolicy::project($keys, $context, $surface)->accepted, "{$surface}/{$context->value}");
            }
        }
    }

    /** @test */
    public function steering_and_demographic_prose_produces_no_tags(): void
    {
        $parser = new ListingDescriptionTagParser();
        $text = 'Perfect for young professionals and retirees! Family-friendly, safe neighborhood with great schools, '
              . 'walking distance to churches and the synagogue. Quiet, desirable area. Adults only 55+ community.';

        foreach (SmartTagContext::cases() as $context) {
            foreach ([SmartTagSource::NativeListingDescription, SmartTagSource::MlsRemarks] as $source) {
                $this->assertSame([], array_keys($parser->parse($text, $context, $source)), $context->value);
            }
        }
    }

    /** @test */
    public function fifty_five_plus_is_a_gate_never_a_tag_from_any_source(): void
    {
        $deriver = new NativeListingTagDeriver();
        $meta = new NativeMetaValueReader([
            'property_type'            => 'Residential',
            'leasing_55_plus'          => '55+ Community',
            'non_negotiable_amenities' => json_encode(['55 and Over Community']),
        ]);

        $this->assertSame([], $deriver->derive(SmartTagListingType::SellerAgent, $meta, SmartTagContext::ResidentialSale));
        $this->assertTrue(SmartTagSourceRules::isForbiddenKey('leasing_55_plus'));

        foreach (SmartTagSourceRules::bridgeRules() as $rule) {
            $this->assertNotSame('senior_community_yn', $rule['column'] ?? null);
            $this->assertNotSame('SeniorCommunityYN', $rule['field'] ?? null);
        }
    }

    /** @test */
    public function accessibility_describes_the_property_but_is_never_a_seeker_preference(): void
    {
        $this->assertSame(['accessible_features'],
            SmartTagSelectionPolicy::project(['accessible_features'], SmartTagContext::ResidentialLease, SmartTagTaxonomy::SURFACE_OWNER)->accepted);
        $this->assertSame([],
            SmartTagSelectionPolicy::project(['accessible_features'], SmartTagContext::ResidentialLease, SmartTagTaxonomy::SURFACE_SEEKER)->accepted);

        $this->assertTrue(SmartTagSourceRules::isForbiddenKey('accessibility_requirements'),
            'A tenant disclosure must never become a tag source');
    }

    /** @test */
    public function screening_provider_and_retired_fields_can_never_feed_smart_tags(): void
    {
        foreach (['criminal_background_requirement', 'eviction_history_requirement', 'income_verification_requirement',
                  'landlord_approval_conditions', 'pet_restrictions', 'breed_restrictions',
                  'employment_requirement', 'occupant_types', 'service_animal', 'support_animal',
                  'neighboring_tenants', 'intended_business_use', 'compatibility_preferences', 'rental_purpose'] as $key) {
            $this->assertTrue(SmartTagSourceRules::isForbiddenKey($key), $key);
        }
    }
}
