<?php

namespace Tests\Unit\ListingPreferences\Taste;

use App\Support\ListingPreferences\SeekerRole;
use App\Support\ListingPreferences\Taste\TasteChoiceTimeline;
use App\Support\ListingPreferences\Taste\TasteDnaDeriver;
use App\Support\ListingPreferences\Taste\TasteEventRecord;
use App\Support\ListingPreferences\Taste\TasteListingFacts;
use App\Support\ListingPreferences\Taste\TasteObservation;
use App\Support\ListingPreferences\Taste\TasteObservationPresenter;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * The words a customer reads — and everything they must never read.
 */
class TasteObservationPresenterTest extends TestCase
{
    private int $seq = 0;

    /** @test */
    public function an_established_pattern_says_often_and_an_emerging_one_says_tend_to(): void
    {
        $often = $this->one($this->homes(3, 'save', ['natural_light']));
        $tend  = $this->one($this->homes(2, 'save', ['natural_light']));

        $this->assertSame('Natural Light', $often->headline);
        $this->assertSame('You often Save homes with this.', $often->summary);
        $this->assertSame('You tend to Save homes with this.', $tend->summary);
        $this->assertSame(TasteObservation::GROUP_SAVE, $often->group);
    }

    /** @test */
    public function a_negative_pattern_is_worded_as_passing(): void
    {
        $observation = $this->one($this->homes(3, 'pass', ['needs_complete_update']));

        $this->assertSame('Needs Complete Update', $observation->headline);
        $this->assertSame('You often Pass on homes with this.', $observation->summary);
        $this->assertSame(TasteObservation::GROUP_PASS, $observation->group);
    }

    /** @test */
    public function conflicting_evidence_is_described_as_mixed(): void
    {
        $observation = $this->one(array_merge(
            $this->homes(3, 'save', ['garage']),
            $this->homes(3, 'pass', ['garage'], 'p'),
        ));

        $this->assertSame(TasteObservation::GROUP_MIXED, $observation->group);
        $this->assertStringContainsString('mixed', $observation->summary);
    }

    /** @test */
    public function every_observation_explains_its_evidence_and_its_source(): void
    {
        $observation = $this->one(array_merge(
            $this->homes(3, 'save', ['natural_light']),
            $this->homes(1, 'maybe', ['natural_light'], 'm'),
        ));

        $this->assertSame('From 4 homes you chose: 3 Saved, 1 Maybe.', $observation->evidence);
        $this->assertSame('Based on reasons you picked.', $observation->source);
    }

    /** @test */
    public function a_criteria_reason_is_worded_as_something_the_customer_said(): void
    {
        $observation = $this->one($this->homes(3, 'pass', ['too_expensive']));

        $this->assertSame('Too expensive', $observation->headline);
        $this->assertSame('You often mention this when you Pass on a home.', $observation->summary);
    }

    /** @test */
    public function numeric_ranges_read_as_plain_facts(): void
    {
        $events = array_merge($this->homes(3, 'save', []), $this->homes(3, 'pass', [], 'p'));
        $facts  = [];
        foreach ($events as $i => $event) {
            $facts[$event->refKey()] = new TasteListingFacts(bedrooms: $i < 3 ? 3 + ($i % 2) : 1);
        }

        $observations = TasteObservationPresenter::present(
            TasteDnaDeriver::derive(1, SeekerRole::Buyer, TasteChoiceTimeline::build($events), $facts)
        );

        $beds = array_values(array_filter($observations, static fn ($o) => $o->headline === 'Bedrooms'))[0];

        $this->assertSame(
            'Homes you Save usually have 3–4 bedrooms. The ones you Pass on usually have 1 bedroom.',
            $beds->summary
        );
        $this->assertSame('Seen across homes you Saved, from details those homes currently list — not a reason you picked.', $beds->source);
    }

    /** @test */
    public function an_observed_characteristic_never_reads_as_a_reason_the_customer_picked(): void
    {
        // Saved with NO reason: the pool is only something the homes list.
        $saved = $this->homes(3, 'save', []);
        $observed = $this->oneWith($saved, $this->facts($saved, new TasteListingFacts(tagKeys: ['private_pool'])));

        $this->assertSame('Private Pool', $observed->headline);
        $this->assertStringStartsWith('Seen across homes you Saved', $observed->source);
        $this->assertStringContainsString('currently list', $observed->source, 'a correlation against today\'s facts, and said to be');
        $this->assertStringContainsString('not a reason you picked', $observed->source);
        $this->assertStringNotContainsString('Based on reasons you picked', $observed->source);
        $this->assertStringNotContainsString('mention', $observed->summary, 'only a stated reason is "mentioned"');

        $passed = $this->homes(3, 'pass', [], 'p');
        $this->assertStringStartsWith(
            'Seen across homes you Passed on',
            $this->oneWith($passed, $this->facts($passed, new TasteListingFacts(tagKeys: ['private_pool'])))->source,
        );
    }

    /** @test */
    public function a_reason_the_customer_picked_says_so_even_when_the_homes_also_list_it(): void
    {
        $events = $this->homes(3, 'save', ['private_pool']);
        $both   = $this->oneWith($events, $this->facts($events, new TasteListingFacts(tagKeys: ['private_pool'])));

        $this->assertSame('Based on reasons you picked, and also seen in details those homes currently list.', $both->source);
    }

    /** @test */
    public function insufficient_evidence_is_never_presented(): void
    {
        $this->assertSame([], TasteObservationPresenter::present($this->profile($this->homes(1, 'save', ['natural_light', 'garage']))));
    }

    /** @test */
    public function nothing_internal_reaches_the_words(): void
    {
        $events = array_merge(
            $this->homes(3, 'save', ['natural_light', 'price']),
            $this->homes(3, 'pass', ['needs_complete_update', 'too_expensive'], 'p'),
            $this->homes(2, 'maybe', ['private_pool'], 'm'),
        );

        $text = json_encode(array_map(
            static fn (TasteObservation $o) => $o->toArray(),
            TasteObservationPresenter::present($this->profile($events))
        ));

        foreach (['natural_light', 'needs_complete_update', 'too_expensive', 'smart_tag', 'property_subtype', 'byo:', 'mls:',
                  'seller_agent', 'strength', 'agreement', 'weight', 'established', 'emerging', '%', '0.', 'user'] as $leak) {
            $this->assertStringNotContainsStringIgnoringCase($leak, (string) $text, "customer text must not contain {$leak}");
        }
    }

    /** @test */
    public function groups_come_back_in_a_fixed_order_with_empty_groups_dropped(): void
    {
        $groups = TasteObservationPresenter::grouped(TasteObservationPresenter::present($this->profile(array_merge(
            $this->homes(3, 'pass', ['needs_complete_update']),
            $this->homes(3, 'save', ['natural_light'], 's'),
        ))));

        $this->assertSame([TasteObservation::GROUP_SAVE, TasteObservation::GROUP_PASS], array_keys($groups));
    }

    // ---------------------------------------------------------------- helpers

    private function one(array $events): TasteObservation
    {
        $observations = TasteObservationPresenter::present($this->profile($events));
        $this->assertCount(1, $observations);

        return $observations[0];
    }

    private function oneWith(array $events, array $facts): TasteObservation
    {
        $observations = TasteObservationPresenter::present(
            TasteDnaDeriver::derive(1, SeekerRole::Buyer, TasteChoiceTimeline::build($events), $facts)
        );
        $this->assertCount(1, $observations);

        return $observations[0];
    }

    /** The same facts on every home in the list. */
    private function facts(array $events, TasteListingFacts $facts): array
    {
        $out = [];
        foreach ($events as $event) {
            $out[$event->refKey()] = $facts;
        }

        return $out;
    }

    private function profile(array $events)
    {
        return TasteDnaDeriver::derive(1, SeekerRole::Buyer, TasteChoiceTimeline::build($events), []);
    }

    private function homes(int $n, string $state, array $reasons, string $prefix = 'h'): array
    {
        $out = [];

        for ($i = 0; $i < $n; $i++) {
            $this->seq++;
            $out[] = new TasteEventRecord(
                $this->seq,
                "byo:seller_agent:{$prefix}{$i}",
                'seller_agent',
                $this->seq,
                $state,
                $reasons,
                new DateTimeImmutable('2026-09-01T00:00:00+00:00 +' . $this->seq . ' seconds'),
            );
        }

        return $out;
    }
}
