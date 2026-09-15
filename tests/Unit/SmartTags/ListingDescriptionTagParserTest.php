<?php

namespace Tests\Unit\SmartTags;

use App\Services\SmartTags\Derivation\ListingDescriptionTagParser;
use App\Services\SmartTags\Derivation\SmartTagSourceRules;
use App\Support\SmartTags\SmartTagContext;
use App\Support\SmartTags\SmartTagSource;
use App\Support\SmartTags\SmartTagState;
use App\Support\SmartTags\SmartTagTaxonomy;
use PHPUnit\Framework\TestCase;
use Tests\Unit\SmartTags\Concerns\BuildsSmartTagRecords;

/**
 * Deterministic description parsing. Every remark here is synthetic.
 */
class ListingDescriptionTagParserTest extends TestCase
{
    use BuildsSmartTagRecords;

    private ListingDescriptionTagParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        SmartTagTaxonomy::flush();
        $this->parser = new ListingDescriptionTagParser();
    }

    /** @return string[] */
    private function tags(string $text, SmartTagContext $context = SmartTagContext::ResidentialSale, SmartTagSource $source = SmartTagSource::NativeListingDescription): array
    {
        return $this->presentKeys($this->parser->parse($text, $context, $source));
    }

    /** @test */
    public function the_product_example_produces_the_expected_canonical_tags(): void
    {
        $tags = $this->tags('Beautifully renovated kitchen with quartz countertops, white shaker cabinets and stainless steel appliances.');

        foreach (['updated_kitchen', 'quartz_countertops', 'white_cabinets', 'shaker_cabinets', 'stainless_appliances'] as $expected) {
            $this->assertContains($expected, $tags);
        }
    }

    /** @test */
    public function synonyms_normalise_to_one_canonical_key(): void
    {
        foreach (['Quartz counters throughout.', 'New QUARTZ COUNTERTOPS.', 'Quartz surfaces in the kitchen.', "Quartz\u{00A0}countertops."] as $text) {
            $this->assertContains('quartz_countertops', $this->tags($text), $text);
        }

        foreach (['Remodeled kitchen.', 'Kitchen was fully updated in 2023.', 'Upgraded kitchen.'] as $text) {
            $this->assertContains('updated_kitchen', $this->tags($text), $text);
        }
    }

    /** @test */
    public function negation_prevents_a_tag_and_never_creates_an_absence(): void
    {
        foreach (['No pool.', 'There is no fireplace.', 'Home does not have a garage, but has a carport.', 'Sold without appliances.'] as $text) {
            foreach ($this->parser->parse($text, SmartTagContext::ResidentialSale, SmartTagSource::NativeListingDescription) as $evidence) {
                $this->assertSame(SmartTagState::Present, $evidence->state, 'Descriptions never emit absent');
            }
        }

        $this->assertNotContains('fireplace', $this->tags('There is no fireplace.'));
        $this->assertNotContains('private_pool', $this->tags('Not a pool home.'));
        $this->assertNotContains('pets_allowed', $this->tags('No pets allowed.', SmartTagContext::ResidentialLease));
        $this->assertNotContains('as_is', $this->tags('The property is not being sold as-is.'));
        $this->assertNotContains('waterfront', $this->tags('A non-waterfront lot.'));
        $this->assertContains('carport', $this->tags('No garage, but a covered carport.'));
    }

    /** @test */
    public function absence_of_language_means_unknown(): void
    {
        $this->assertSame([], $this->parser->parse('', SmartTagContext::ResidentialSale, SmartTagSource::NativeListingDescription));
        $this->assertSame([], $this->parser->parse(null, SmartTagContext::ResidentialSale, SmartTagSource::NativeListingDescription));
        $this->assertNotContains('quartz_countertops', $this->tags('Lovely three bedroom home near the park.'));
    }

    /** @test */
    public function vague_marketing_language_creates_no_condition_tags(): void
    {
        $conditionKeys = array_keys(array_filter(SmartTagTaxonomy::all(), static fn ($d) => $d->category === 'condition'));

        foreach ((array) SmartTagSourceRules::descriptionSetting('vague_marketing_phrases') as $phrase) {
            $tags = $this->tags("This home is {$phrase}.");
            $this->assertSame([], array_values(array_intersect($tags, $conditionKeys)), "\"{$phrase}\" produced a condition tag");
        }

        $tags = $this->tags('Make it your own and bring your vision to this blank canvas with endless possibilities!');
        $this->assertSame([], array_values(array_intersect($tags, $conditionKeys)));
    }

    /** @test */
    public function condition_tags_are_parsed_conservatively(): void
    {
        $this->assertContains('fixer_upper', $this->tags('A true fixer upper on a great lot.'));
        $this->assertContains('handyman_special', $this->tags('Handyman special — bring your tools.'));
        $this->assertContains('needs_tlc', $this->tags('Needs some TLC.'));
        $this->assertContains('move_in_ready', $this->tags('Move-in ready!'));
        $this->assertContains('turnkey_home', $this->tags('Turnkey home.'));
        $this->assertContains('investor_special', $this->tags('Investor special.'));
        $this->assertContains('renovation_opportunity', $this->tags('Bring your contractor.'));

        // A need is not a completed renovation.
        $this->assertNotContains('fully_updated', $this->tags('Needs to be fully renovated.'));
        $this->assertContains('needs_complete_update', $this->tags('Needs a complete renovation.'));

        // "Original owner" is ownership, not condition; "investor opportunity" is ordinary marketing.
        $this->assertNotContains('original_condition', $this->tags('Original owner, lovingly kept.'));
        $this->assertNotContains('investor_special', $this->tags('Great investor opportunity.'));
    }

    /** @test */
    public function turnkey_meanings_are_context_specific(): void
    {
        $residential = $this->tags('Turnkey and ready.', SmartTagContext::ResidentialSale);
        $this->assertContains('turnkey_home', $residential);
        $this->assertNotContains('turnkey_business', $residential);

        $business = $this->tags('Turnkey business with loyal regulars.', SmartTagContext::BusinessSale);
        $this->assertContains('turnkey_business', $business);
        $this->assertNotContains('turnkey_home', $business);

        $lease = $this->tags('Turnkey furnished rental.', SmartTagContext::ResidentialLease);
        $this->assertNotContains('turnkey_home', $lease);
        $this->assertNotContains('turnkey_business', $lease);

        $this->assertNotContains('turnkey_home', $this->tags('Turnkey vacation rental opportunity.', SmartTagContext::ResidentialSale));
    }

    /** @test */
    public function lookalikes_are_suppressed(): void
    {
        $this->assertNotContains('private_pool', $this->tags('Heated community pool and clubhouse.'));
        $this->assertContains('community_pool', $this->tags('Heated community pool and clubhouse.'));
        $this->assertNotContains('private_pool', $this->tags('Plenty of room for a pool.'));
        $this->assertNotContains('spa', $this->tags('Spa-like primary bath.'));
        $this->assertNotContains('dock', $this->tags('Two loading docks and dock-high doors.', SmartTagContext::CommercialSale));
        $this->assertContains('loading_dock', $this->tags('Two loading docks and dock-high doors.', SmartTagContext::CommercialSale));
        $this->assertContains('dock', $this->tags('Private dock with boat lift.'));
        $this->assertNotContains('hardwood_flooring', $this->tags('Wood-look tile floors.'));
        $this->assertNotContains('in_unit_laundry', $this->tags('Community laundry room on each floor.', SmartTagContext::ResidentialLease));
    }

    /** @test */
    public function proximity_language_is_location_dna_not_a_property_tag(): void
    {
        $this->assertNotContains('boat_slip_marina', $this->tags('Minutes to boat slips and dining.'));
        $this->assertNotContains('golf_course_community', $this->tags('Close to the golf course community clubhouse.'));
        $this->assertNotContains('waterfront', $this->tags('Walk to waterfront restaurants.'));
        $this->assertNotContains('tennis_court', $this->tags('Near tennis courts and schools.'));
    }

    /** @test */
    public function residential_description_does_not_leak_into_land_or_commercial(): void
    {
        $text = 'Updated kitchen with quartz countertops, private pool and fenced yard. Loading dock. Liquor license.';

        $land = $this->tags($text, SmartTagContext::LandSale);
        foreach (['updated_kitchen', 'quartz_countertops', 'private_pool', 'fenced_yard', 'loading_dock', 'liquor_license_included'] as $leak) {
            $this->assertNotContains($leak, $land);
        }

        $residential = $this->tags($text, SmartTagContext::ResidentialSale);
        $this->assertNotContains('loading_dock', $residential);
        $this->assertNotContains('liquor_license_included', $residential);
    }

    /** @test */
    public function only_approved_canonical_keys_are_emitted_for_both_description_sources(): void
    {
        $text = 'Stunning family-friendly home in a safe neighborhood near great schools and churches. '
              . 'Quartz countertops, gourmet chef kitchen, smart thermostat, private pool.';

        foreach ([SmartTagSource::NativeListingDescription, SmartTagSource::MlsRemarks] as $source) {
            foreach ($this->parser->parse($text, SmartTagContext::ResidentialSale, $source) as $evidence) {
                $this->assertTrue(SmartTagTaxonomy::has($evidence->tagKey), $evidence->tagKey);
                $this->assertSame($source, $evidence->source);
                $this->assertStringStartsWith('description.', (string) $evidence->ruleId);
                $this->assertStringNotContainsString('quartz countertops', strtolower((string) $evidence->ruleId), 'Evidence must not carry listing text');
            }
        }
    }

    /** @test */
    public function a_structured_source_cannot_be_passed_to_the_parser(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->parser->parse('private pool', SmartTagContext::ResidentialSale, SmartTagSource::StructuredMls);
    }

    /** @test */
    public function whitespace_and_typography_do_not_change_what_is_parsed(): void
    {
        $a = $this->tags("Move\u{2011}in ready.  Quartz\ncountertops.");
        $b = $this->tags('move-in ready. quartz countertops.');

        $this->assertContains('move_in_ready', $a);
        $this->assertSame($b, array_values(array_intersect($a, $b)));
    }
}
