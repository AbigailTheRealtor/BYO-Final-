<?php

namespace Tests\Unit\ListingPreferences\Taste;

use App\Support\ListingPreferences\SeekerRole;
use App\Support\ListingPreferences\Taste\TasteChoiceTimeline;
use App\Support\ListingPreferences\Taste\TasteConfidence;
use App\Support\ListingPreferences\Taste\TasteDimension;
use App\Support\ListingPreferences\Taste\TasteDirection;
use App\Support\ListingPreferences\Taste\TasteDnaDeriver;
use App\Support\ListingPreferences\Taste\TasteEventRecord;
use App\Support\ListingPreferences\Taste\TasteListingFacts;
use App\Support\ListingPreferences\Taste\TasteProfile;
use App\Support\ListingPreferences\Taste\TasteSignal;
use App\Support\ListingPreferences\Taste\TasteSource;
use App\Support\SmartTags\SmartTagTaxonomy;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * The learner's rules, proven without a database or a booted application.
 *
 * Extends PHPUnit's TestCase on purpose: the deriver, the timeline, the reason
 * catalog and the taxonomy must all answer container-free, and a hidden
 * `config()` would fail every test here rather than surfacing as an empty
 * profile several frames away.
 */
class TasteDnaDeriverTest extends TestCase
{
    private int $seq = 0;

    // ------------------------------------------------------------ direction

    /** @test */
    public function save_is_positive_evidence(): void
    {
        $profile = $this->derive($this->homes(3, 'save', ['natural_light']));
        $signal  = $profile->signal(TasteDimension::SmartTag, 'natural_light');

        $this->assertSame(TasteDirection::Positive, $signal->direction);
        $this->assertSame(TasteConfidence::Established, $signal->confidence);
        $this->assertSame(3, $signal->saveCount);
        $this->assertSame(3, $signal->supportCount);
        $this->assertSame([TasteSource::StatedReason], $signal->sources);
    }

    /** @test */
    public function pass_is_negative_evidence(): void
    {
        $profile = $this->derive($this->homes(3, 'pass', ['needs_complete_update']));
        $signal  = $profile->signal(TasteDimension::SmartTag, 'needs_complete_update');

        $this->assertSame(TasteDirection::Negative, $signal->direction);
        $this->assertSame(TasteConfidence::Established, $signal->confidence);
        $this->assertSame(3, $signal->passCount);
    }

    /** @test */
    public function maybe_is_uncertain_and_never_reads_as_a_save(): void
    {
        $signal = $this->derive($this->homes(5, 'maybe', ['natural_light']))
            ->signal(TasteDimension::SmartTag, 'natural_light');

        $this->assertSame(TasteDirection::Uncertain, $signal->direction);
        $this->assertSame(0.0, $signal->positiveWeight, 'a Maybe is never half a Save');
        $this->assertSame(TasteConfidence::Emerging, $signal->confidence, 'Maybe may be noticed, never called "often"');
    }

    /** @test */
    public function maybe_is_weaker_than_save_and_dilutes_a_positive_pattern(): void
    {
        $saves = $this->derive($this->homes(3, 'save', ['natural_light']))
            ->signal(TasteDimension::SmartTag, 'natural_light');

        $diluted = $this->derive(array_merge(
            $this->homes(3, 'save', ['natural_light']),
            $this->homes(3, 'maybe', ['natural_light'], 'maybe-home'),
        ))->signal(TasteDimension::SmartTag, 'natural_light');

        $this->assertSame(TasteConfidence::Established, $saves->confidence);
        $this->assertSame(TasteDirection::Positive, $diluted->direction);
        $this->assertSame(TasteConfidence::Emerging, $diluted->confidence);
        $this->assertLessThan($saves->agreement, $diluted->agreement);
    }

    // -------------------------------------------------------------- reasons

    /** @test */
    public function structured_reasons_contribute_independently_of_listing_facts(): void
    {
        $profile = $this->derive(array_merge(
            $this->homes(3, 'save', ['natural_light']),
            $this->homes(2, 'pass', ['too_expensive'], 'pricey'),
        ));

        // No facts were supplied at all: both signals come from what was SAID.
        $this->assertTrue($profile->signal(TasteDimension::SmartTag, 'natural_light')->hasSource(TasteSource::StatedReason));

        $price = $profile->signal(TasteDimension::Reason, 'too_expensive');
        $this->assertSame(TasteDirection::Negative, $price->direction);
        $this->assertSame(TasteConfidence::Emerging, $price->confidence);
        $this->assertSame('Too expensive', $price->label);
    }

    /** @test */
    public function a_reason_and_the_same_listed_feature_on_one_choice_count_once(): void
    {
        $events = $this->homes(3, 'save', ['private_pool']);
        $facts  = $this->factsFor($events, new TasteListingFacts(tagKeys: ['private_pool']));

        $signal = $this->derive($events, $facts)->signal(TasteDimension::SmartTag, 'private_pool');

        $this->assertSame(3.0, $signal->positiveWeight, 'stated 1.0 per home, never stated + observed');
        $this->assertSame(
            [TasteSource::ListingCharacteristic, TasteSource::StatedReason],
            $signal->sources
        );
    }

    /** @test */
    public function a_listed_feature_alone_is_weaker_than_a_stated_reason(): void
    {
        $events = $this->homes(3, 'save', []);
        $facts  = $this->factsFor($events, new TasteListingFacts(tagKeys: ['garage']));

        $signal = $this->derive($events, $facts)->signal(TasteDimension::SmartTag, 'garage');

        $this->assertSame(1.5, $signal->strength);
        $this->assertSame(TasteConfidence::Emerging, $signal->confidence, 'three observed homes are a hint, not "often"');
    }

    /** @test */
    public function other_and_unspecified_reasons_are_never_learned(): void
    {
        $profile = $this->derive($this->homes(4, 'save', ['other', 'layout', 'kitchen', 'condition']));

        $this->assertSame([], $profile->signals);
    }

    /** @test */
    public function location_reasons_are_not_learned_in_phase_4(): void
    {
        $profile = $this->derive($this->homes(4, 'pass', ['location_proximity', 'commute', 'busy_road']));

        $this->assertSame([], $profile->signals, 'no structural link to the customer\'s own places exists yet');
    }

    /** @test */
    public function free_text_in_a_snapshot_is_ignored_not_parsed(): void
    {
        $profile = $this->derive($this->homes(4, 'save', [
            'Great neighbourhood, lovely families nearby',
            'natural light!!',
            'NATURAL_LIGHT',
            ['nested' => 'natural_light'],
        ]));

        $this->assertSame([], $profile->signals);
    }

    // ---------------------------------------------------- repetition & time

    /** @test */
    public function repeated_independent_choices_strengthen_evidence(): void
    {
        $tiers = [];

        foreach ([1, 2, 3] as $n) {
            $tiers[$n] = $this->derive($this->homes($n, 'save', ['natural_light']))
                ->signal(TasteDimension::SmartTag, 'natural_light')->confidence;
        }

        $this->assertSame(TasteConfidence::Insufficient, $tiers[1]);
        $this->assertSame(TasteConfidence::Emerging, $tiers[2]);
        $this->assertSame(TasteConfidence::Established, $tiers[3]);
    }

    /** @test */
    public function a_single_event_never_becomes_a_displayable_pattern(): void
    {
        $profile = $this->derive($this->homes(1, 'save', ['natural_light', 'garage', 'private_pool', 'price']));

        $this->assertNotEmpty($profile->signals, 'it is recorded');
        $this->assertSame([], $profile->displayable(), 'but one choice is never a taste');
    }

    /** @test */
    public function toggling_one_home_repeatedly_counts_as_one_home(): void
    {
        $events = [];
        foreach (['save', 'pass', 'save', 'pass', 'save', 'save', 'save'] as $i => $state) {
            $events[] = $this->event('byo:seller_agent:1', $state, ['natural_light'], $i);
        }

        $signal = $this->derive($events)->signal(TasteDimension::SmartTag, 'natural_light');

        $this->assertSame(1, $signal->supportCount);
        $this->assertFalse($signal->isDisplayable());
    }

    /** @test */
    public function a_reason_revision_replaces_the_earlier_answer(): void
    {
        $events = [];
        foreach ([1, 2, 3] as $h) {
            $events[] = $this->event("byo:seller_agent:{$h}", 'save', ['natural_light'], $h * 10);
            $events[] = $this->event("byo:seller_agent:{$h}", 'save', ['garage'], $h * 10 + 1);
        }

        $profile = $this->derive($events);

        $this->assertNull($profile->signal(TasteDimension::SmartTag, 'natural_light'), 'the corrected answer replaced it');
        $this->assertSame(TasteConfidence::Established, $profile->signal(TasteDimension::SmartTag, 'garage')->confidence);
    }

    /** @test */
    public function conflicting_evidence_reduces_confidence_and_then_reads_as_mixed(): void
    {
        $agreeing = $this->homes(3, 'save', ['natural_light']);

        $oneContrary = $this->derive(array_merge($agreeing, $this->homes(1, 'pass', ['natural_light'], 'contrary')))
            ->signal(TasteDimension::SmartTag, 'natural_light');

        $this->assertSame(TasteDirection::Positive, $oneContrary->direction);
        $this->assertSame(TasteConfidence::Emerging, $oneContrary->confidence, 'established → emerging');

        $balanced = $this->derive(array_merge($agreeing, $this->homes(3, 'pass', ['natural_light'], 'contrary')))
            ->signal(TasteDimension::SmartTag, 'natural_light');

        $this->assertSame(TasteDirection::Mixed, $balanced->direction);
        $this->assertTrue($balanced->isDisplayable(), 'stated conflict is told to the customer as mixed');
    }

    /** @test */
    public function a_listed_feature_on_both_sides_is_not_announced_as_mixed(): void
    {
        $events = array_merge($this->homes(3, 'save', []), $this->homes(3, 'pass', [], 'passed'));
        $facts  = $this->factsFor($events, new TasteListingFacts(tagKeys: ['central_air']));

        $signal = $this->derive($events, $facts)->signal(TasteDimension::SmartTag, 'central_air');

        $this->assertSame(TasteDirection::Mixed, $signal->direction);
        $this->assertFalse($signal->isDisplayable(), 'a feature every home has explains nothing');
    }

    /** @test */
    public function later_contrary_feedback_outweighs_older_evidence_without_deleting_it(): void
    {
        $events = [];
        foreach ([1, 2, 3, 4] as $h) {
            $events[] = $this->event("byo:seller_agent:{$h}", 'save', ['natural_light'], $h);
            $events[] = $this->event("byo:seller_agent:{$h}", 'pass', ['natural_light'], 100 + $h);
        }

        $signal = $this->derive($events)->signal(TasteDimension::SmartTag, 'natural_light');

        $this->assertSame(TasteDirection::Negative, $signal->direction, 'what they do now wins');
        $this->assertSame(4, $signal->saveCount, 'the earlier Saves are still in the evidence');
        $this->assertSame(1.0, $signal->positiveWeight, 'kept at the superseded weight');
        $this->assertSame(4.0, $signal->negativeWeight);
    }

    /** @test */
    public function clearing_keeps_history_as_superseded_evidence(): void
    {
        $events = [];
        foreach ([1, 2, 3] as $h) {
            $events[] = $this->event("byo:seller_agent:{$h}", 'save', ['natural_light'], $h);
            $events[] = $this->event("byo:seller_agent:{$h}", null, [], 10 + $h);
        }

        $signal = $this->derive($events)->signal(TasteDimension::SmartTag, 'natural_light');

        $this->assertNotNull($signal, 'undo does not erase what was chosen');
        $this->assertSame(3, $signal->saveCount);
        $this->assertSame(0.75, $signal->positiveWeight);
        $this->assertSame(TasteConfidence::Insufficient, $signal->confidence, 'but it no longer stands as a taste');
        $this->assertSame(TasteDirection::Positive, $signal->direction, 'and a clear is never read as a Pass');
    }

    /** @test */
    public function first_and_last_supporting_timestamps_are_recorded(): void
    {
        $signal = $this->derive($this->homes(3, 'save', ['natural_light']))
            ->signal(TasteDimension::SmartTag, 'natural_light');

        $this->assertSame('2026-09-01T00:00:00+00:00', $signal->firstAt->format(DATE_ATOM));
        $this->assertSame('2026-09-01T00:00:02+00:00', $signal->lastAt->format(DATE_ATOM));
    }

    // ---------------------------------------------------------- determinism

    /** @test */
    public function the_same_history_in_any_input_order_derives_the_identical_profile(): void
    {
        $events = array_merge(
            $this->homes(3, 'save', ['natural_light', 'garage']),
            $this->homes(3, 'pass', ['needs_complete_update', 'too_expensive'], 'passed'),
            $this->homes(2, 'maybe', ['private_pool'], 'unsure'),
        );
        $facts = $this->factsFor($events, new TasteListingFacts(tagKeys: ['garage'], bedrooms: 3, bathrooms: 2));

        $expected = $this->derive($events, $facts)->toArray();

        foreach ([1, 2, 3, 4, 5] as $seed) {
            mt_srand($seed);
            $shuffled = $events;
            shuffle($shuffled);
            $this->assertSame($expected, $this->derive($shuffled, $facts)->toArray(), "seed {$seed}");
        }
    }

    // ----------------------------------------------------- governance gates

    /** @test */
    public function natural_light_is_learnable_though_it_is_never_derived(): void
    {
        $tag = SmartTagTaxonomy::get('natural_light');
        $this->assertTrue($tag->isSeekerSelectable());
        $this->assertFalse($tag->mlsDerivable || $tag->nativeDerivable, 'governance: natural_light stays non-derivable');

        $this->assertTrue($this->derive($this->homes(3, 'save', ['natural_light']))
            ->signal(TasteDimension::SmartTag, 'natural_light')->isDisplayable());
    }

    /** @test */
    public function protected_and_non_seeker_selectable_tags_are_never_learned_even_from_listing_facts(): void
    {
        $excluded = ['accessible_features', 'playground'];

        // Every other active tag that is NOT seeker-selectable, plus any retired
        // or pending-review one — asked of the taxonomy, not listed here.
        foreach (SmartTagTaxonomy::all() as $key => $definition) {
            if (! $definition->isSeekerSelectable()) {
                $excluded[] = $key;
            }
        }
        $excluded = array_values(array_unique($excluded));

        foreach (['accessible_features', 'playground'] as $key) {
            $this->assertFalse(SmartTagTaxonomy::get($key)->isSeekerSelectable(), "{$key} must stay non-seeker-selectable");
        }

        $events = $this->homes(6, 'save', array_merge($excluded, ['accessible_features', 'playground']));
        $facts  = $this->factsFor($events, new TasteListingFacts(tagKeys: $excluded));

        $learned = array_map(static fn ($s) => $s->key, $this->derive($events, $facts)->signals);

        foreach ($excluded as $key) {
            $this->assertNotContains($key, $learned, "{$key} must never become a taste signal");
        }
    }

    /** @test */
    public function property_subtypes_are_learned_but_other_and_prohibited_values_are_dropped(): void
    {
        $events = $this->homes(3, 'save', []);
        $facts  = $this->factsFor($events, new TasteListingFacts(subtypes: [
            ' Condominium ', 'Other', 'Other - see remarks', 'Adults Only Community', '55+ Community', str_repeat('x', 80),
        ]));

        $keys = array_map(static fn ($s) => $s->key, $this->derive($events, $facts)->signals);

        $this->assertSame(['condominium'], $keys);
    }

    /** @test */
    public function sub_type_learning_cannot_bypass_the_compliance_guard_or_learn_placeholders(): void
    {
        $events = $this->homes(3, 'save', []);
        $facts  = $this->factsFor($events, new TasteListingFacts(subtypes: [
            'Townhouse',
            // Placeholders say nothing about the home.
            'Non-Applicable', 'N/A', 'None', 'Unknown',
            // Every one of these is a SmartTagComplianceGuard category.
            'Active Adult Community', 'Senior Living', 'Wheelchair Home', 'Section 8 Housing',
            'Family-Friendly Home', 'Church Property', 'Safe Neighborhood Home', 'Top Rated Schools Home',
        ]));

        $keys = array_map(static fn ($s) => $s->key, $this->derive($events, $facts)->signals);

        $this->assertSame(['townhouse'], $keys);
    }

    /** @test */
    public function the_dimension_list_has_no_location_or_people_dimension(): void
    {
        $this->assertSame(
            ['smart_tag', 'reason', 'bedrooms', 'bathrooms', 'living_area', 'lot_size', 'property_subtype'],
            TasteDimension::values(),
            'adding a dimension is a governance change (LISTING_PREFERENCE_GOVERNANCE.md §13)'
        );
    }

    // ------------------------------------------- per-home transitions

    /** @test */
    public function save_then_pass_is_one_home_whose_pass_carries_full_weight(): void
    {
        $profile = $this->derive($this->sequence(['save', 'pass']));
        $signal  = $profile->signal(TasteDimension::SmartTag, 'natural_light');

        $this->assertOneHome($profile, $signal, choices: 2);
        $this->assertWeights($signal, pos: TasteDnaDeriver::W_SUPERSEDED, neg: TasteDnaDeriver::W_CURRENT, unc: 0.0);
        $this->assertSame([1, 0, 1], [$signal->saveCount, $signal->maybeCount, $signal->passCount]);
        $this->assertSame(TasteDirection::Negative, $signal->direction);
    }

    /** @test */
    public function pass_then_save_is_one_home_whose_save_carries_full_weight(): void
    {
        $profile = $this->derive($this->sequence(['pass', 'save']));
        $signal  = $profile->signal(TasteDimension::SmartTag, 'natural_light');

        $this->assertOneHome($profile, $signal, choices: 2);
        $this->assertWeights($signal, pos: TasteDnaDeriver::W_CURRENT, neg: TasteDnaDeriver::W_SUPERSEDED, unc: 0.0);
        $this->assertSame(TasteDirection::Positive, $signal->direction);
    }

    /** @test */
    public function save_then_maybe_then_pass_keeps_every_step_on_one_home(): void
    {
        $profile = $this->derive($this->sequence(['save', 'maybe', 'pass']));
        $signal  = $profile->signal(TasteDimension::SmartTag, 'natural_light');

        $this->assertOneHome($profile, $signal, choices: 3);
        $this->assertWeights($signal, pos: TasteDnaDeriver::W_SUPERSEDED, neg: TasteDnaDeriver::W_CURRENT, unc: TasteDnaDeriver::W_SUPERSEDED);
        $this->assertSame([1, 1, 1], [$signal->saveCount, $signal->maybeCount, $signal->passCount]);
    }

    /** @test */
    public function save_then_clear_keeps_the_save_at_historical_weight_and_is_never_a_pass(): void
    {
        $profile = $this->derive($this->sequence(['save', null]));
        $signal  = $profile->signal(TasteDimension::SmartTag, 'natural_light');

        $this->assertOneHome($profile, $signal, choices: 1);
        $this->assertWeights($signal, pos: TasteDnaDeriver::W_SUPERSEDED, neg: 0.0, unc: 0.0);
        $this->assertSame(0, $signal->passCount, 'a clear is never read as a Pass');
        $this->assertFalse(TasteChoiceTimeline::build($this->sequence(['save', null]))[0]->latest()->current);
    }

    /** @test */
    public function pass_then_clear_keeps_the_pass_at_historical_weight_and_adds_nothing(): void
    {
        $profile = $this->derive($this->sequence(['pass', null]));
        $signal  = $profile->signal(TasteDimension::SmartTag, 'natural_light');

        $this->assertOneHome($profile, $signal, choices: 1);
        $this->assertWeights($signal, pos: 0.0, neg: TasteDnaDeriver::W_SUPERSEDED, unc: 0.0);
        $this->assertSame(1, $signal->passCount, 'the historical Pass is still one Pass, not two');
    }

    /** @test */
    public function repeated_save_pass_toggling_on_one_home_cannot_inflate_support(): void
    {
        $toggles = $this->sequence(['save', 'pass', 'save', 'pass', 'save', 'pass', 'save', 'pass', 'save']);
        $profile = $this->derive($toggles);
        $signal  = $profile->signal(TasteDimension::SmartTag, 'natural_light');

        $this->assertOneHome($profile, $signal, choices: 9);
        $this->assertWeights($signal, pos: TasteDnaDeriver::W_CURRENT, neg: TasteDnaDeriver::W_SUPERSEDED, unc: 0.0);
        $this->assertSame([1, 1], [$signal->saveCount, $signal->passCount], 'nine clicks, one home');
        $this->assertFalse($signal->isDisplayable(), 'one home, however often it is toggled, is not a taste');

        // Toggling one home adds exactly what one Save would to a real pattern.
        $withToggles = $this->derive(array_merge($this->homes(2, 'save', ['natural_light'], 'steady'), $toggles))
            ->signal(TasteDimension::SmartTag, 'natural_light');
        $withOneSave = $this->derive(array_merge($this->homes(2, 'save', ['natural_light'], 'steady'), $this->sequence(['save'])))
            ->signal(TasteDimension::SmartTag, 'natural_light');

        $this->assertSame(3, $withToggles->supportCount);
        $this->assertSame($withOneSave->saveCount, $withToggles->saveCount);
        $this->assertSame($withOneSave->positiveWeight, $withToggles->positiveWeight);
    }

    // --------------------------------------------------------------- numeric

    /** @test */
    public function numeric_facts_describe_the_usual_range_of_saved_homes(): void
    {
        $events = $this->homes(4, 'save', []);
        $facts  = [];
        foreach ([3, 3, 4, 4] as $i => $beds) {
            $facts[$events[$i]->refKey()] = new TasteListingFacts(bedrooms: $beds, livingArea: 1800 + $i * 120);
        }

        $profile = $this->derive($events, $facts);
        $beds    = $profile->signal(TasteDimension::Bedrooms, 'bedrooms');

        $this->assertSame(TasteDirection::Positive, $beds->direction);
        $this->assertSame(TasteConfidence::Emerging, $beds->confidence);
        $this->assertSame([3.0, 4.0], [$beds->saveBand->low, $beds->saveBand->high]);
        $this->assertSame(1800.0, $profile->signal(TasteDimension::LivingArea, 'living_area')->saveBand->low);
    }

    /** @test */
    public function overlapping_saved_and_passed_ranges_explain_nothing_and_are_not_shown(): void
    {
        $events = array_merge($this->homes(3, 'save', []), $this->homes(3, 'pass', [], 'passed'));
        $facts  = $this->factsFor($events, new TasteListingFacts(bedrooms: 3));

        $beds = $this->derive($events, $facts)->signal(TasteDimension::Bedrooms, 'bedrooms');

        $this->assertSame(TasteDirection::Mixed, $beds->direction);
        $this->assertFalse($beds->isDisplayable());
    }

    /** @test */
    public function lot_size_is_a_buyer_dimension_only(): void
    {
        $events = $this->homes(3, 'save', []);
        $facts  = $this->factsFor($events, new TasteListingFacts(lotAcres: 0.25));

        $this->assertNotNull($this->derive($events, $facts, SeekerRole::Buyer)->signal(TasteDimension::LotSize, 'lot_size'));
        $this->assertNull($this->derive($events, $facts, SeekerRole::Tenant)->signal(TasteDimension::LotSize, 'lot_size'));
    }

    /** @test */
    public function implausible_numeric_values_are_ignored(): void
    {
        $events = $this->homes(3, 'save', []);
        $facts  = $this->factsFor($events, new TasteListingFacts(bedrooms: 400, bathrooms: -2, livingArea: 0));

        $this->assertNull($this->derive($events, $facts)->signal(TasteDimension::Bedrooms, 'bedrooms'));
        $this->assertNull($this->derive($events, $facts)->signal(TasteDimension::Bathrooms, 'bathrooms'));
    }

    // ---------------------------------------------------------------- helpers

    /**
     * @param  list<TasteEventRecord>          $events
     * @param  array<string, TasteListingFacts> $facts
     */
    private function derive(array $events, array $facts = [], SeekerRole $role = SeekerRole::Buyer): TasteProfile
    {
        return TasteDnaDeriver::derive(7, $role, TasteChoiceTimeline::build($events), $facts);
    }

    /**
     * One event per home, each on its own listing.
     *
     * @return list<TasteEventRecord>
     */
    private function homes(int $n, string $state, array $reasons, string $prefix = 'home'): array
    {
        $out = [];

        for ($i = 0; $i < $n; $i++) {
            $out[] = $this->event("byo:seller_agent:{$prefix}{$i}", $state, $reasons, $i);
        }

        return $out;
    }

    private function event(string $subject, ?string $state, array $reasons, int $second): TasteEventRecord
    {
        $this->seq++;

        return new TasteEventRecord(
            id:          $this->seq,
            subjectKey:  $subject,
            listingType: 'seller_agent',
            listingId:   abs(crc32($subject)) % 1_000_000 + 1,
            toState:     $state,
            reasonKeys:  $reasons,
            at:          new DateTimeImmutable('2026-09-01T00:00:00+00:00 +' . $second . ' seconds'),
        );
    }

    /**
     * One home, one event per step (null = a clear), each with natural_light.
     *
     * @param  list<string|null> $states
     * @return list<TasteEventRecord>
     */
    private function sequence(array $states, string $subject = 'byo:seller_agent:toggled'): array
    {
        $out = [];

        foreach ($states as $i => $state) {
            $out[] = $this->event($subject, $state, $state === null ? [] : ['natural_light'], 100 + $i);
        }

        return $out;
    }

    private function assertOneHome(TasteProfile $profile, ?TasteSignal $signal, int $choices): void
    {
        $this->assertNotNull($signal);
        $this->assertSame(1, $profile->homeCount, 'a state change never makes a second home');
        $this->assertSame($choices, $profile->choiceCount);
        $this->assertSame(1, $signal->supportCount);
    }

    private function assertWeights(TasteSignal $signal, float $pos, float $neg, float $unc): void
    {
        $this->assertSame(
            ['pos' => $pos, 'neg' => $neg, 'unc' => $unc],
            ['pos' => $signal->positiveWeight, 'neg' => $signal->negativeWeight, 'unc' => $signal->uncertainWeight],
        );
    }

    /**
     * The same facts on every home in the list.
     *
     * @param  list<TasteEventRecord> $events
     * @return array<string, TasteListingFacts>
     */
    private function factsFor(array $events, TasteListingFacts $facts): array
    {
        $out = [];

        foreach ($events as $event) {
            $out[$event->refKey()] = $facts;
        }

        return $out;
    }
}
