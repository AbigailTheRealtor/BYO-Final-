<?php

namespace Tests\Unit\Dna;

use App\Models\DnaScore;
use App\Services\Dna\Relevance\BatchRelevanceMatcher;
use App\Services\Dna\Relevance\MatchTier;
use App\Services\Dna\Relevance\MatchTierResult;
use App\Services\Dna\Relevance\RankedMatch;
use Tests\TestCase;

/**
 * Matching V2 C6 — additive counterpart_type on RankedMatch. Backward-compatible
 * (null omitted from toArray) and threaded through the §F6 matcher so a mixed-type
 * candidate pool with colliding ids stays unambiguous.
 */
class RankedMatchTypeTest extends TestCase
{
    private function tierResult(): MatchTierResult
    {
        return new MatchTierResult(MatchTier::Strong, 80, 90, 100, ['pet_friendliness'], [], [], 'seed');
    }

    private function score(string $side, int $value): DnaScore
    {
        return new DnaScore([
            'listing_type'      => 'x',
            'listing_id'        => 1,
            'score_key'         => 'pet_friendliness',
            'side'              => $side,
            'value'             => $value,
            'data_completeness' => 100,
            'confidence'        => 90,
        ]);
    }

    public function test_vo_carries_type_and_toarray_includes_it(): void
    {
        $m = new RankedMatch(5, $this->tierResult(), 'seller_agent');

        $this->assertSame('seller_agent', $m->counterpartType());
        $this->assertSame('seller_agent', $m->toArray()['counterpart_type']);
        $this->assertSame(5, $m->toArray()['counterpart_id']);
    }

    public function test_vo_without_type_is_backward_compatible(): void
    {
        $m = new RankedMatch(5, $this->tierResult());

        $this->assertNull($m->counterpartType());
        $this->assertArrayNotHasKey('counterpart_type', $m->toArray());
    }

    public function test_matcher_threads_type_and_disambiguates_colliding_ids(): void
    {
        $subject = [$this->score('property', 100)];

        // Two counterparts with the SAME id (5) but different types + identical scores.
        $counterparts = [
            ['id' => 5, 'type' => 'seller_agent',   'scores' => [$this->score('demand', 80)]],
            ['id' => 5, 'type' => 'landlord_agent', 'scores' => [$this->score('demand', 80)]],
        ];

        $set = app(BatchRelevanceMatcher::class)->matchListingAgainstDemands($subject, $counterparts);

        $this->assertSame(2, $set->determinedCount());
        // Identical tier/value → tie-break by id (equal) then type ascending.
        $this->assertSame('landlord_agent', $set->matches[0]->counterpartType());
        $this->assertSame('seller_agent', $set->matches[1]->counterpartType());
        $this->assertSame(5, $set->matches[0]->counterpartId);
        $this->assertSame(5, $set->matches[1]->counterpartId);
    }

    public function test_matcher_without_type_stays_null(): void
    {
        $subject = [$this->score('property', 100)];
        $set = app(BatchRelevanceMatcher::class)->matchListingAgainstDemands($subject, [
            ['id' => 7, 'scores' => [$this->score('demand', 80)]],
        ]);

        $this->assertSame(1, $set->determinedCount());
        $this->assertNull($set->matches[0]->counterpartType());
        $this->assertArrayNotHasKey('counterpart_type', $set->matches[0]->toArray());
    }
}
