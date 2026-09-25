<?php

namespace Tests\Unit\SmartTags;

use App\Services\SmartTags\Seeker\SeekerSmartTagMatcher;
use App\Support\SmartTags\SmartTagComplianceGuard;
use App\Support\SmartTags\SmartTagTaxonomy;
use PHPUnit\Framework\TestCase;

/**
 * The pure half of seeker Smart Tag matching: which picks may be scored at all,
 * and how one listing compares. No container, no database — like the other
 * pure Smart Tag classes.
 */
class SeekerSmartTagMatcherTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        SmartTagTaxonomy::flush();
    }

    /** @test */
    public function natural_light_may_be_scored_although_it_is_not_derivable(): void
    {
        $this->assertSame(['natural_light'], SeekerSmartTagMatcher::governedKeys(['natural_light']));
    }

    /** @test */
    public function accessibility_and_playground_are_never_scored(): void
    {
        $this->assertSame([], SeekerSmartTagMatcher::governedKeys(['accessible_features', 'playground']));
    }

    /** @test */
    public function pending_review_tags_are_never_scored(): void
    {
        $pending = array_keys(array_filter(SmartTagTaxonomy::all(), static fn ($d) => $d->isPendingReview()));
        $this->assertNotEmpty($pending, 'the taxonomy should carry pending-review tags for this guard to mean anything');

        $this->assertSame([], SeekerSmartTagMatcher::governedKeys($pending));
    }

    /** @test */
    public function unknown_malformed_duplicate_and_prohibited_values_are_dropped(): void
    {
        $this->assertSame(
            ['private_pool', 'updated_kitchen'],
            SeekerSmartTagMatcher::governedKeys([
                'private_pool', 'private_pool', 'Private Pool', 42, null, ['x'],
                'not_a_real_tag', 'family_friendly_neighborhood', 'good_schools', 'safe_area',
                'updated_kitchen',
            ]),
        );
    }

    /** @test */
    public function every_scorable_key_and_label_passes_the_fair_housing_guard(): void
    {
        $scorable = SeekerSmartTagMatcher::governedKeys(array_keys(SmartTagTaxonomy::all()));
        $this->assertNotEmpty($scorable);

        foreach ($scorable as $key) {
            $this->assertTrue(SmartTagComplianceGuard::keyIsClean($key), $key);
            $this->assertTrue(SmartTagComplianceGuard::isClean(SmartTagTaxonomy::get($key)->label), $key);
        }

        $this->assertNotContains('accessible_features', $scorable);
        $this->assertNotContains('playground', $scorable);
    }

    /** @test */
    public function no_selection_evaluates_to_nothing(): void
    {
        $this->assertNull(SeekerSmartTagMatcher::evaluate([], ['private_pool']));
        $this->assertNull(SeekerSmartTagMatcher::evaluate([], null));
    }

    /** @test */
    public function matched_share_and_labels_are_over_checkable_picks_only(): void
    {
        $match = SeekerSmartTagMatcher::evaluate(
            ['private_pool', 'natural_light', 'updated_kitchen', 'quartz_countertops'],
            ['updated_kitchen', 'private_pool', 'gas_range'],
            ['quartz_countertops'],
        );

        $this->assertSame(['private_pool', 'updated_kitchen'], $match->matchedKeys);
        $this->assertSame(['quartz_countertops'], $match->knownAbsentKeys);
        $this->assertSame(['natural_light'], $match->unknownKeys());
        $this->assertSame(3, $match->checkableCount());
        $this->assertEqualsWithDelta(2 / 3, $match->share(), 1e-9, 'natural_light is unknown: in neither numerator nor denominator');
        $this->assertSame(['Private Pool', SmartTagTaxonomy::get('updated_kitchen')->label], $match->matchedLabels());
        $this->assertSame(['Quartz Countertops'], $match->knownAbsentLabels());
        $this->assertSame([SmartTagTaxonomy::get('natural_light')->label], $match->unknownLabels());
    }

    /** @test */
    public function without_listing_facts_every_pick_is_unknown(): void
    {
        $match = SeekerSmartTagMatcher::evaluate(['private_pool'], null, ['private_pool']);

        $this->assertSame(0, $match->matchedCount());
        $this->assertSame([], $match->knownAbsentKeys, 'no facts cannot assert absence');
        $this->assertFalse($match->hasCheckablePicks());
        $this->assertSame(0.0, $match->share());
    }

    /** @test */
    public function a_pick_the_listing_has_no_answer_for_is_unknown_never_a_miss(): void
    {
        $match = SeekerSmartTagMatcher::evaluate(['private_pool'], []);

        $this->assertFalse($match->hasCheckablePicks());
        $this->assertSame([], $match->knownAbsentLabels());
        $this->assertSame(['private_pool'], $match->unknownKeys());
    }

    /** @test */
    public function a_known_absent_pick_is_a_checkable_miss(): void
    {
        $match = SeekerSmartTagMatcher::evaluate(['private_pool'], [], ['private_pool']);

        $this->assertTrue($match->hasCheckablePicks());
        $this->assertSame(0.0, $match->share());
        $this->assertSame(['Private Pool'], $match->knownAbsentLabels());
    }
}
