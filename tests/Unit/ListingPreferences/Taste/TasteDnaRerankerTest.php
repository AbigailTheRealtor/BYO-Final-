<?php

namespace Tests\Unit\ListingPreferences\Taste;

use App\Support\ListingPreferences\SeekerRole;
use App\Support\ListingPreferences\Taste\TasteConfidence;
use App\Support\ListingPreferences\Taste\TasteDimension;
use App\Support\ListingPreferences\Taste\TasteDirection;
use App\Support\ListingPreferences\Taste\TasteDnaDeriver;
use App\Support\ListingPreferences\Taste\TasteDnaReranker;
use App\Support\ListingPreferences\Taste\TasteListingFacts;
use App\Support\ListingPreferences\Taste\TasteNumericBand;
use App\Support\ListingPreferences\Taste\TasteProfile;
use App\Support\ListingPreferences\Taste\TasteRerankCandidate;
use App\Support\ListingPreferences\Taste\TasteRerankExplanation;
use App\Support\ListingPreferences\Taste\TasteSignal;
use App\Support\ListingPreferences\Taste\TasteSource;
use App\Support\SmartTags\SmartTagTaxonomy;
use LogicException;
use PHPUnit\Framework\TestCase;

/**
 * Phase 5's reranking rules, proven without a database or a booted application.
 *
 * Extends PHPUnit's TestCase on purpose, like TasteDnaDeriverTest: the reranker
 * must answer container-free, so a hidden `config()` fails here loudly.
 */
class TasteDnaRerankerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        SmartTagTaxonomy::flush();
    }

    // ---------------------------------------------------------- no evidence

    /** @test */
    public function no_profile_leaves_the_existing_order_exactly(): void
    {
        $candidates = $this->candidates([['a', 90, ['natural_light']], ['b', 90, []], ['c', 88, ['natural_light']]]);

        $result = TasteDnaReranker::rerank(TasteProfile::empty(1, SeekerRole::Buyer), $candidates);

        $this->assertSame(['a', 'b', 'c'], $result->orderedKeys);
        $this->assertFalse($result->personalized);
        $this->assertFalse($result->changedOrder());
    }

    /** @test */
    public function insufficient_evidence_moves_nothing(): void
    {
        $profile = $this->profile([
            $this->tag('natural_light', TasteDirection::Positive, TasteConfidence::Insufficient, [TasteSource::StatedReason]),
        ]);

        $result = TasteDnaReranker::rerank($profile, $this->candidates([['a', 90, []], ['b', 90, ['natural_light']]]));

        $this->assertSame(['a', 'b'], $result->orderedKeys);
        $this->assertFalse($result->personalized);
    }

    /** @test */
    public function an_incomplete_profile_moves_nothing(): void
    {
        $result = TasteDnaReranker::rerank(
            TasteProfile::incomplete(1, SeekerRole::Buyer),
            $this->candidates([['a', 90, []], ['b', 90, ['natural_light']]]),
        );

        $this->assertSame(['a', 'b'], $result->orderedKeys);
        $this->assertFalse($result->personalized);
    }

    /** @test */
    public function mixed_and_uncertain_signals_carry_no_direction(): void
    {
        $profile = $this->profile([
            $this->tag('natural_light', TasteDirection::Mixed, TasteConfidence::Emerging, [TasteSource::StatedReason]),
            $this->tag('private_pool', TasteDirection::Uncertain, TasteConfidence::Emerging, [TasteSource::StatedReason]),
        ]);

        $result = TasteDnaReranker::rerank($profile, $this->candidates([
            ['a', 90, []],
            ['b', 90, ['natural_light', 'private_pool']],
        ]));

        $this->assertSame(['a', 'b'], $result->orderedKeys);
        $this->assertSame([], $result->influence('b')->contributions);
    }

    // ----------------------------------------------------------- direction

    /** @test */
    public function a_strong_positive_taste_can_reorder_near_tied_results(): void
    {
        $profile = $this->profile([
            $this->tag('natural_light', TasteDirection::Positive, TasteConfidence::Established, [TasteSource::StatedReason]),
            $this->tag('updated_kitchen', TasteDirection::Positive, TasteConfidence::Established, [TasteSource::StatedReason]),
        ]);

        $result = TasteDnaReranker::rerank($profile, $this->candidates([
            ['a', 91, []],
            ['b', 90, ['natural_light', 'updated_kitchen']],
        ]));

        $this->assertSame(['b', 'a'], $result->orderedKeys);
        $this->assertTrue($result->influence('b')->raised());
    }

    /** @test */
    public function a_strong_negative_taste_can_reorder_near_tied_results(): void
    {
        $profile = $this->profile([
            $this->tag('needs_complete_update', TasteDirection::Negative, TasteConfidence::Established, [TasteSource::StatedReason]),
            $this->tag('carport', TasteDirection::Negative, TasteConfidence::Established, [TasteSource::StatedReason]),
        ]);

        $result = TasteDnaReranker::rerank($profile, $this->candidates([
            ['a', 91, ['needs_complete_update', 'carport']],
            ['b', 90, []],
        ]));

        $this->assertSame(['b', 'a'], $result->orderedKeys);
        $this->assertFalse($result->influence('a')->raised());
        $this->assertTrue($result->influence('a')->moved());
    }

    /** @test */
    public function a_single_signal_only_breaks_an_exact_tie(): void
    {
        $profile = $this->profile([
            $this->tag('natural_light', TasteDirection::Positive, TasteConfidence::Established, [TasteSource::StatedReason]),
        ]);

        $tie = TasteDnaReranker::rerank($profile, $this->candidates([['a', 90, []], ['b', 90, ['natural_light']]]));
        $this->assertSame(['b', 'a'], $tie->orderedKeys);

        // One point apart: one established stated signal (0.625) is not enough.
        $gap = TasteDnaReranker::rerank($profile, $this->candidates([['a', 91, []], ['b', 90, ['natural_light']]]));
        $this->assertSame(['a', 'b'], $gap->orderedKeys);
    }

    /** @test */
    public function an_absent_characteristic_is_not_evidence(): void
    {
        // They Pass on pools; a listing WITHOUT a pool is not thereby favoured,
        // and one with no facts at all is not touched.
        $profile = $this->profile([
            $this->tag('private_pool', TasteDirection::Negative, TasteConfidence::Established, [TasteSource::StatedReason]),
        ]);

        $result = TasteDnaReranker::rerank($profile, [
            new TasteRerankCandidate('a', 90, new TasteListingFacts(tagKeys: [])),
            new TasteRerankCandidate('b', 90, null),
        ]);

        $this->assertSame(['a', 'b'], $result->orderedKeys);
        $this->assertSame(0.0, $result->influence('a')->adjustment);
        $this->assertSame(0.0, $result->influence('b')->adjustment);
    }

    // ------------------------------------------------------------ sources

    /** @test */
    public function a_stated_reason_outweighs_an_observed_correlation(): void
    {
        $stated   = $this->tag('natural_light', TasteDirection::Positive, TasteConfidence::Emerging, [TasteSource::StatedReason]);
        $observed = $this->tag('fireplace', TasteDirection::Positive, TasteConfidence::Emerging, [TasteSource::ListingCharacteristic]);

        $this->assertGreaterThan(TasteDnaReranker::signalWeight($observed), TasteDnaReranker::signalWeight($stated));

        // Tied base scores; the observed candidate arrives first and still yields.
        $result = TasteDnaReranker::rerank($this->profile([$stated, $observed]), $this->candidates([
            ['observed', 90, ['fireplace']],
            ['stated', 90, ['natural_light']],
        ]));

        $this->assertSame(['stated', 'observed'], $result->orderedKeys);
    }

    /** @test */
    public function established_outweighs_emerging(): void
    {
        $emerging    = $this->tag('natural_light', TasteDirection::Positive, TasteConfidence::Emerging, [TasteSource::StatedReason]);
        $established = $this->tag('fireplace', TasteDirection::Positive, TasteConfidence::Established, [TasteSource::StatedReason]);

        $this->assertGreaterThan(TasteDnaReranker::signalWeight($emerging), TasteDnaReranker::signalWeight($established));
    }

    // ---------------------------------------------------- bounded influence

    /** @test */
    public function a_materially_better_match_is_never_leapfrogged(): void
    {
        $profile = $this->maximalProfile();

        // The maximum positive on the weaker listing, the maximum negative on
        // the stronger one: still no crossing of a 3-point gap — or of 88 → 98.
        foreach ([[98, 88], [93, 90], [91, 88]] as [$high, $low]) {
            $result = TasteDnaReranker::rerank($profile, $this->candidates([
                ['strong', $high, ['needs_complete_update', 'carport']],
                ['weak', $low, ['natural_light', 'updated_kitchen', 'fireplace']],
            ]));

            $this->assertSame(['strong', 'weak'], $result->orderedKeys, "{$low} must not pass {$high}");
        }
    }

    /** @test */
    public function the_largest_crossable_gap_is_two_points(): void
    {
        $profile = $this->maximalProfile();

        $result = TasteDnaReranker::rerank($profile, $this->candidates([
            ['strong', 92, ['needs_complete_update', 'carport']],
            ['weak', 90, ['natural_light', 'updated_kitchen', 'fireplace']],
        ]));

        $this->assertSame(['weak', 'strong'], $result->orderedKeys);
        $this->assertSame(TasteDnaReranker::MAX_INFLUENCE, $result->influence('weak')->adjustment);
        $this->assertSame(-TasteDnaReranker::MAX_INFLUENCE, $result->influence('strong')->adjustment);
    }

    /**
     * The bound holds for every pair in every ordering produced, over a large
     * deterministic sample — not just the cases written out above.
     *
     * @test
     */
    public function no_listing_is_ever_placed_above_one_scored_three_or_more_points_higher(): void
    {
        $profile = $this->maximalProfile();
        $tags    = ['natural_light', 'updated_kitchen', 'fireplace', 'needs_complete_update', 'carport', 'private_pool'];

        mt_srand(20260923);

        for ($run = 0; $run < 200; $run++) {
            $rows = [];

            for ($i = 0; $i < 25; $i++) {
                $picked = array_values(array_filter($tags, static fn () => mt_rand(0, 2) === 0));
                $rows[] = ["c{$i}", mt_rand(60, 100), $picked];
            }

            // The matcher hands over a list already sorted by base score.
            usort($rows, static fn ($a, $b) => $b[1] <=> $a[1]);

            $candidates = $this->candidates($rows);
            $base       = array_column($rows, 1, 0);
            $order      = TasteDnaReranker::rerank($profile, $candidates)->orderedKeys;

            foreach ($order as $i => $upper) {
                foreach (array_slice($order, $i + 1) as $lower) {
                    $this->assertLessThan(
                        3,
                        $base[$lower] - $base[$upper],
                        "run {$run}: {$upper} ({$base[$upper]}) placed above {$lower} ({$base[$lower]})"
                    );
                }
            }
        }
    }

    // ------------------------------------------------ membership / scores

    /** @test */
    public function membership_is_a_permutation_and_scores_are_untouched(): void
    {
        $candidates = $this->candidates([
            ['a', 95, []], ['b', 94, ['natural_light', 'updated_kitchen']], ['c', 94, ['needs_complete_update']], ['d', 60, []],
        ]);
        $scoresBefore = array_map(static fn (TasteRerankCandidate $c) => $c->baseScore, $candidates);

        $result = TasteDnaReranker::rerank($this->maximalProfile(), $candidates);

        $keys = $result->orderedKeys;
        sort($keys);
        $this->assertSame(['a', 'b', 'c', 'd'], $keys);
        $this->assertSame($scoresBefore, array_map(static fn (TasteRerankCandidate $c) => $c->baseScore, $candidates));
    }

    /** @test */
    public function duplicate_candidate_keys_are_refused(): void
    {
        $this->expectException(LogicException::class);

        TasteDnaReranker::rerank($this->maximalProfile(), $this->candidates([['a', 90, []], ['a', 89, []]]));
    }

    // ---------------------------------------------------------- stability

    /** @test */
    public function equal_influence_preserves_the_existing_order(): void
    {
        $result = TasteDnaReranker::rerank($this->maximalProfile(), $this->candidates([
            ['a', 90, ['natural_light']],
            ['b', 90, ['natural_light']],
            ['c', 90, []],
            ['d', 90, []],
        ]));

        $this->assertSame(['a', 'b', 'c', 'd'], $result->orderedKeys);
    }

    /** @test */
    public function the_same_inputs_always_give_the_same_order(): void
    {
        $signals = $this->maximalProfile()->signals;
        $rows    = [['a', 92, ['needs_complete_update']], ['b', 91, ['fireplace']], ['c', 91, ['natural_light', 'carport']], ['d', 90, ['updated_kitchen', 'natural_light']]];

        $first    = TasteDnaReranker::rerank($this->profile($signals), $this->candidates($rows))->orderedKeys;
        $again    = TasteDnaReranker::rerank($this->profile($signals), $this->candidates($rows))->orderedKeys;
        $shuffled = TasteDnaReranker::rerank($this->profile(array_reverse($signals)), $this->candidates($rows))->orderedKeys;

        $this->assertSame($first, $again);
        $this->assertSame($first, $shuffled, 'the order of signals inside a profile must not matter');
    }

    // --------------------------------------------------------- governance

    /** @test */
    public function excluded_and_pending_tags_never_influence_ordering(): void
    {
        // The deriver never produces these; built by hand to prove the reranker
        // asks the taxonomy itself rather than trusting its input.
        foreach (['accessible_features', 'playground', 'gated_community', 'not_a_real_tag'] as $key) {
            $profile = $this->profile([
                $this->tag($key, TasteDirection::Positive, TasteConfidence::Established, [TasteSource::StatedReason]),
            ]);

            $this->assertSame([], TasteDnaReranker::rankingSignals($profile), "{$key} must not be a ranking signal");

            $result = TasteDnaReranker::rerank($profile, $this->candidates([['a', 90, []], ['b', 90, [$key]]]));
            $this->assertSame(['a', 'b'], $result->orderedKeys);
        }
    }

    /** @test */
    public function natural_light_participates_although_it_is_not_derivable(): void
    {
        $definition = SmartTagTaxonomy::get('natural_light');
        $this->assertNotNull($definition);
        $this->assertTrue($definition->isSeekerSelectable());

        $profile = $this->profile([
            $this->tag('natural_light', TasteDirection::Positive, TasteConfidence::Established, [TasteSource::StatedReason]),
        ]);

        $this->assertCount(1, TasteDnaReranker::rankingSignals($profile));
    }

    /** @test */
    public function numeric_and_criteria_reason_dimensions_never_influence_ordering(): void
    {
        $band = new TasteNumericBand(2.0, 2.0, 6);

        $profile = $this->profile([
            new TasteSignal(TasteDimension::Bedrooms, 'bedrooms', 'Bedrooms', TasteDirection::Positive, TasteConfidence::Established,
                6.0, 1.0, 6.0, 0.0, 0.0, 6, 6, 0, 0, null, null, [TasteSource::ListingCharacteristic], $band, null),
            new TasteSignal(TasteDimension::Reason, 'too_expensive', 'Too expensive', TasteDirection::Negative, TasteConfidence::Established,
                4.0, 1.0, 0.0, 4.0, 0.0, 4, 0, 0, 4, null, null, [TasteSource::StatedReason]),
        ]);

        $this->assertSame([], TasteDnaReranker::rankingSignals($profile));
        $this->assertSame([TasteDimension::SmartTag, TasteDimension::PropertySubtype], TasteDnaReranker::RANKING_DIMENSIONS);

        // A buyer who asked for 3+ bedrooms keeps the matcher's order even
        // though their Saves "usually have 2 bedrooms".
        $result = TasteDnaReranker::rerank($profile, [
            new TasteRerankCandidate('three', 90, new TasteListingFacts(bedrooms: 3.0)),
            new TasteRerankCandidate('two', 90, new TasteListingFacts(bedrooms: 2.0)),
        ]);

        $this->assertSame(['three', 'two'], $result->orderedKeys);
    }

    /** @test */
    public function subtype_matches_through_the_deriver_normaliser(): void
    {
        $profile = $this->profile([
            new TasteSignal(TasteDimension::PropertySubtype, 'condominium', 'Condominium', TasteDirection::Positive, TasteConfidence::Established,
                3.0, 1.0, 3.0, 0.0, 0.0, 3, 3, 0, 0, null, null, [TasteSource::StatedReason]),
        ]);

        $this->assertSame('condominium', TasteDnaDeriver::subtypeKey('  Condominium '));
        $this->assertNull(TasteDnaDeriver::subtypeKey('Other'));

        $result = TasteDnaReranker::rerank($profile, [
            new TasteRerankCandidate('house', 90, new TasteListingFacts(subtypes: ['Single Family Residence'])),
            new TasteRerankCandidate('condo', 90, new TasteListingFacts(subtypes: ['  Condominium '])),
        ]);

        $this->assertSame(['condo', 'house'], $result->orderedKeys);
    }

    // -------------------------------------------------------- explanations

    /** @test */
    public function a_raised_listing_is_explained_in_the_customers_terms(): void
    {
        $profile = $this->profile([
            $this->tag('natural_light', TasteDirection::Positive, TasteConfidence::Established, [TasteSource::StatedReason], 'Natural light'),
            $this->tag('fireplace', TasteDirection::Positive, TasteConfidence::Established, [TasteSource::ListingCharacteristic], 'Fireplace'),
        ]);

        $result = TasteDnaReranker::rerank($profile, $this->candidates([['a', 90, []], ['b', 90, ['natural_light', 'fireplace']]]));
        $text   = TasteRerankExplanation::for($result->influence('b'));

        $this->assertSame(TasteRerankExplanation::HEADLINE_RAISED, $text['headline']);
        $this->assertSame([
            'Natural light is a reason you have picked when Saving homes.',
            'Fireplace appears often in homes you Save.',
        ], $text['lines']);

        // A correlation is never claimed as something the customer said.
        $this->assertStringNotContainsString('reason', $text['lines'][1]);
    }

    /** @test */
    public function a_lowered_listing_is_explained_by_what_they_pass_on(): void
    {
        $profile = $this->profile([
            $this->tag('needs_complete_update', TasteDirection::Negative, TasteConfidence::Emerging, [TasteSource::ListingCharacteristic], 'Needs complete update'),
        ]);

        $result = TasteDnaReranker::rerank($profile, $this->candidates([['a', 90, ['needs_complete_update']], ['b', 90, []]]));
        $text   = TasteRerankExplanation::for($result->influence('a'));

        $this->assertSame(TasteRerankExplanation::HEADLINE_LOWERED, $text['headline']);
        $this->assertSame(['Needs complete update appears regularly in homes you Pass on.'], $text['lines']);
    }

    /** @test */
    public function unmoved_listings_and_listings_moved_only_by_a_neighbour_are_not_explained(): void
    {
        $profile = $this->profile([
            $this->tag('natural_light', TasteDirection::Positive, TasteConfidence::Established, [TasteSource::StatedReason]),
        ]);

        $result = TasteDnaReranker::rerank($profile, $this->candidates([['a', 90, []], ['b', 90, ['natural_light']], ['c', 80, ['natural_light']]]));

        $this->assertNull(TasteRerankExplanation::for($result->influence('a')), 'moved down only because b moved up');
        $this->assertNull(TasteRerankExplanation::for($result->influence('c')), 'has evidence but did not move');
        $this->assertNotNull(TasteRerankExplanation::for($result->influence('b')));
    }

    /** @test */
    public function explanations_never_expose_numbers_percentages_or_keys(): void
    {
        $result = TasteDnaReranker::rerank($this->maximalProfile(), $this->candidates([
            ['a', 92, ['needs_complete_update', 'carport']],
            ['b', 90, ['natural_light', 'updated_kitchen', 'fireplace']],
        ]));

        foreach (['a', 'b'] as $key) {
            $text = TasteRerankExplanation::for($result->influence($key));
            $this->assertNotNull($text);

            $flat = $text['headline'] . ' ' . implode(' ', $text['lines']);
            $this->assertDoesNotMatchRegularExpression('/\d|%|_|score|weight|point/i', $flat);
        }
    }

    // ------------------------------------------------------------- helpers

    /** Two strong positives, three stated; two strong negatives. Saturates both ways. */
    private function maximalProfile(): TasteProfile
    {
        return $this->profile([
            $this->tag('natural_light', TasteDirection::Positive, TasteConfidence::Established, [TasteSource::StatedReason], 'Natural light'),
            $this->tag('updated_kitchen', TasteDirection::Positive, TasteConfidence::Established, [TasteSource::StatedReason], 'Updated kitchen'),
            $this->tag('fireplace', TasteDirection::Positive, TasteConfidence::Established, [TasteSource::StatedReason], 'Fireplace'),
            $this->tag('needs_complete_update', TasteDirection::Negative, TasteConfidence::Established, [TasteSource::StatedReason], 'Needs complete update'),
            $this->tag('carport', TasteDirection::Negative, TasteConfidence::Established, [TasteSource::StatedReason], 'Carport'),
        ]);
    }

    /** @param list<TasteSignal> $signals */
    private function profile(array $signals): TasteProfile
    {
        return new TasteProfile(1, SeekerRole::Buyer, $signals, 6, 6, TasteDnaDeriver::RULES_VERSION);
    }

    /** @param list<TasteSource> $sources */
    private function tag(string $key, TasteDirection $direction, TasteConfidence $confidence, array $sources, ?string $label = null): TasteSignal
    {
        $positive = $direction === TasteDirection::Positive;

        return new TasteSignal(
            dimension:       TasteDimension::SmartTag,
            key:             $key,
            label:           $label ?? $key,
            direction:       $direction,
            confidence:      $confidence,
            strength:        3.0,
            agreement:       1.0,
            positiveWeight:  $positive ? 3.0 : 0.0,
            negativeWeight:  $positive ? 0.0 : 3.0,
            uncertainWeight: 0.0,
            supportCount:    3,
            saveCount:       $positive ? 3 : 0,
            maybeCount:      0,
            passCount:       $positive ? 0 : 3,
            firstAt:         null,
            lastAt:          null,
            sources:         $sources,
        );
    }

    /**
     * @param  list<array{0: string, 1: int, 2: list<string>}> $rows
     * @return list<TasteRerankCandidate>
     */
    private function candidates(array $rows): array
    {
        return array_map(
            static fn (array $r): TasteRerankCandidate => new TasteRerankCandidate($r[0], $r[1], new TasteListingFacts(tagKeys: $r[2])),
            $rows,
        );
    }
}
