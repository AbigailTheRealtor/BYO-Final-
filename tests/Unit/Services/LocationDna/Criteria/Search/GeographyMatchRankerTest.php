<?php

namespace Tests\Unit\Services\LocationDna\Criteria\Search;

use App\Services\LocationDna\Criteria\GeographyOption;
use App\Services\LocationDna\Criteria\Search\GeographyMatch;
use App\Services\LocationDna\Criteria\Search\GeographyMatchRanker;
use App\Services\LocationDna\Criteria\Search\GeographyQuery;
use App\Services\LocationDna\Criteria\Search\MatchType;
use PHPUnit\Framework\TestCase;

/**
 * M1 — ordering, deduplication and truncation, with no database in sight.
 *
 * These are the rules a user experiences as "the search is good" or "the search is broken", and
 * they are pure functions of a list. Testing them here rather than through a corpus means a
 * ranking change shows up as a ranking test failing, not as an unrelated integration test
 * returning rows in a different order.
 */
class GeographyMatchRankerTest extends TestCase
{
    private GeographyMatchRanker $ranker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ranker = new GeographyMatchRanker();
    }

    private function city(string $id, string $name, string $countyId = '12103'): GeographyOption
    {
        return GeographyOption::city($id, $name, $countyId);
    }

    /**
     * The load-bearing invariant: a weaker match NEVER outranks a stronger one, whatever bonuses
     * the weaker one collects. Collapsing the bands into one additive score is the classic way a
     * ranking function starts returning fuzzy matches above exact ones.
     *
     * @test
     */
    public function match_strength_dominates_every_other_signal(): void
    {
        $exactNeighborhood = new GeographyMatch(
            GeographyOption::neighborhood('9', 'Beach', '1218745'),
            MatchType::Exact,
        );

        // A ZIP carries the largest tier bonus there is, and it still cannot beat an exact match.
        $prefixZip = new GeographyMatch(
            GeographyOption::zip('33756', '12103'),
            MatchType::Prefix,
            '',
            ['12103'],
        );

        $result = $this->ranker->rank([$prefixZip, $exactNeighborhood], GeographyQuery::for('beach'));

        $this->assertSame('Beach', $result->matches[0]->label());
    }

    /** @test */
    public function within_one_match_strength_the_tier_bonus_decides(): void
    {
        $city = new GeographyMatch($this->city('1212925', 'Clearwater'), MatchType::Exact, '', ['12103']);
        $hood = new GeographyMatch(
            GeographyOption::neighborhood('9', 'Clearwater', '1218745'),
            MatchType::Exact,
        );

        $result = $this->ranker->rank([$hood, $city], GeographyQuery::for('clearwater'));

        $this->assertSame(GeographyOption::KIND_CITY, $result->matches[0]->option->kind);
    }

    /**
     * "Pinellas County" typed in full should surface the county, not just places whose names
     * happen to contain the word.
     *
     * @test
     */
    public function naming_the_tier_promotes_it(): void
    {
        $county = new GeographyMatch(
            GeographyOption::county('12103', 'Pinellas County', '12', '12103'),
            MatchType::Exact,
            'Florida',
            ['12'],
        );
        $city = new GeographyMatch($this->city('1256789', 'Pinellas Park'), MatchType::Exact, '', ['12103']);

        $result = $this->ranker->rank([$city, $county], GeographyQuery::for('Pinellas County'));

        $this->assertSame(GeographyOption::KIND_COUNTY, $result->matches[0]->option->kind);
    }

    /**
     * An EXACT state beats an exact city of the same name — there really is a Florida, Missouri.
     *
     * Without the exact-state bonus the city wins on tier bonus alone while tying on match
     * strength, so someone typing a state's name gets a village of 300 people first.
     *
     * @test
     */
    public function an_exact_state_outranks_an_exact_city_of_the_same_name(): void
    {
        $state = new GeographyMatch(GeographyOption::state('12', 'Florida', '12', 'FL'), MatchType::Exact);
        $city  = new GeographyMatch($this->city('2926586', 'Florida', '29137'), MatchType::Exact, 'Monroe County, MO', ['29137']);

        $result = $this->ranker->rank([$city, $state], GeographyQuery::for('Florida'));

        $this->assertSame(GeographyOption::KIND_STATE, $result->matches[0]->option->kind);
    }

    /** Same for a county sharing the name — "Iowa County", Wisconsin. */
    /** @test */
    public function an_exact_state_outranks_an_exact_county_of_the_same_name(): void
    {
        $state  = new GeographyMatch(GeographyOption::state('19', 'Iowa', '19', 'IA'), MatchType::Exact);
        $county = new GeographyMatch(
            GeographyOption::county('55049', 'Iowa County', '55', '55049'),
            MatchType::Exact,
            'Wisconsin',
            ['55']
        );

        $result = $this->ranker->rank([$county, $state], GeographyQuery::for('Iowa'));

        $this->assertSame(GeographyOption::KIND_STATE, $result->matches[0]->option->kind);
    }

    /**
     * REORDERING, NEVER FILTERING. The lower-ranked places must survive — a user looking for
     * Florida, Missouri has to be able to find it.
     *
     * @test
     */
    public function promoting_the_state_does_not_remove_the_city_or_county(): void
    {
        $state  = new GeographyMatch(GeographyOption::state('12', 'Florida', '12', 'FL'), MatchType::Exact);
        $city   = new GeographyMatch($this->city('2926586', 'Florida', '29137'), MatchType::Exact, 'Monroe County, MO', ['29137']);
        $county = new GeographyMatch(
            GeographyOption::county('36071', 'Orange County', '36', '36071'),
            MatchType::Exact,
            'New York',
            ['36']
        );

        $result = $this->ranker->rank([$city, $state, $county], GeographyQuery::for('Florida'));

        $this->assertSame(3, $result->count(), 'nothing may be dropped');
        $this->assertNotEmpty($result->ofKind(GeographyOption::KIND_CITY));
        $this->assertNotEmpty($result->ofKind(GeographyOption::KIND_COUNTY));
    }

    /**
     * THE BONUS IS EXACT-ONLY. A prefix hit like "Flo" is genuinely ambiguous between Florida and
     * every place beginning "Flo"; promoting the state there would make the common case worse in
     * order to fix the rare one.
     *
     * @test
     */
    public function a_prefix_matched_state_is_not_promoted(): void
    {
        $state = new GeographyMatch(GeographyOption::state('12', 'Florida', '12', 'FL'), MatchType::Prefix);
        $city  = new GeographyMatch($this->city('1700001', 'Flora', '17023'), MatchType::Prefix, 'Clay County, IL', ['17023']);

        $result = $this->ranker->rank([$state, $city], GeographyQuery::for('Flo'));

        $this->assertSame(
            GeographyOption::KIND_CITY,
            $result->matches[0]->option->kind,
            'a partial state name carries no special claim'
        );
    }

    /** @test */
    public function a_hit_inside_the_callers_county_scope_outranks_one_outside_it(): void
    {
        $inScope  = new GeographyMatch($this->city('1200001', 'Springfield', '12103'), MatchType::Exact, '', ['12103']);
        $outScope = new GeographyMatch($this->city('1700002', 'Springfield', '17167'), MatchType::Exact, '', ['17167']);

        $result = $this->ranker->rank(
            [$outScope, $inScope],
            GeographyQuery::for('springfield', countyIds: ['12103'])
        );

        $this->assertSame('1200001', $result->matches[0]->option->id);
    }

    /**
     * Shorter wins when everything else ties — "Clearwater" before "Clearwater Beach" for the term
     * "clearwater", where both are legitimate matches but one is plainly what was asked for.
     *
     * @test
     */
    public function the_shorter_name_breaks_a_tie(): void
    {
        $long  = new GeographyMatch($this->city('1200002', 'Clearwater Beach'), MatchType::Prefix, '', ['12103']);
        $short = new GeographyMatch($this->city('1200001', 'Clearwater'), MatchType::Prefix, '', ['12103']);

        $result = $this->ranker->rank([$long, $short], GeographyQuery::for('clearwater'));

        $this->assertSame('Clearwater', $result->matches[0]->label());
    }

    /**
     * A typeahead re-runs on every keystroke. If two candidates can compare equal their order is
     * left to the sort's internals, and the list visibly reshuffles between two keystrokes that
     * returned the same rows — which reads as flicker and destroys trust in the result.
     *
     * @test
     */
    public function the_ordering_is_total_so_identical_input_cannot_reshuffle(): void
    {
        $a = new GeographyMatch($this->city('1200001', 'Springfield', '12103'), MatchType::Exact, '', ['12103']);
        $b = new GeographyMatch($this->city('1700002', 'Springfield', '17167'), MatchType::Exact, '', ['17167']);
        $c = new GeographyMatch($this->city('2900003', 'Springfield', '29077'), MatchType::Exact, '', ['29077']);

        $query = GeographyQuery::for('springfield');

        $first  = $this->ranker->rank([$a, $b, $c], $query);
        $second = $this->ranker->rank([$c, $a, $b], $query);
        $third  = $this->ranker->rank([$b, $c, $a], $query);

        $ids = static fn ($r): array => array_map(static fn ($m): string => $m->option->id, $r->matches);

        $this->assertSame($ids($first), $ids($second));
        $this->assertSame($ids($first), $ids($third));
    }

    /**
     * Deduplication keeps the STRONGEST copy, not the first-seen one — otherwise the result would
     * depend on the order the repository happened to query its tiers in.
     *
     * @test
     */
    public function a_duplicate_option_collapses_to_its_strongest_match(): void
    {
        $weak   = new GeographyMatch($this->city('1200001', 'Clearwater'), MatchType::Word, '', ['12103']);
        $strong = new GeographyMatch($this->city('1200001', 'Clearwater'), MatchType::Exact, '', ['12103']);

        $result = $this->ranker->rank([$weak, $strong], GeographyQuery::for('clearwater'));

        $this->assertSame(1, $result->count());
        $this->assertSame(MatchType::Exact, $result->matches[0]->matchType);

        // ...and the reverse feed order gives the same answer.
        $reverse = $this->ranker->rank([$strong, $weak], GeographyQuery::for('clearwater'));
        $this->assertSame(MatchType::Exact, $reverse->matches[0]->matchType);
    }

    /**
     * The same ZIP under two counties is ONE row to a user, where enumeration correctly treats it
     * as two options. Search collapses; the parents survive on the match.
     *
     * @test
     */
    public function the_same_option_under_two_parents_is_one_row(): void
    {
        $a = new GeographyMatch(GeographyOption::zip('33756', '12103'), MatchType::Exact, '', ['12103']);
        $b = new GeographyMatch(GeographyOption::zip('33756', '12057'), MatchType::Exact, '', ['12057']);

        $result = $this->ranker->rank([$a, $b], GeographyQuery::for('33756'));

        $this->assertSame(1, $result->count());
    }

    /**
     * Truncation is reported, never silent — the same rule the cascade's own OPTION_LIMIT follows.
     * A list that quietly stops reads as "this is everything".
     *
     * @test
     */
    public function truncation_is_reported(): void
    {
        $matches = [];
        for ($i = 1; $i <= 8; $i++) {
            $matches[] = new GeographyMatch(
                $this->city('120000'.$i, 'Springfield '.$i),
                MatchType::Prefix,
                '',
                ['12103']
            );
        }

        $result = $this->ranker->rank($matches, GeographyQuery::for('springfield', limit: 3));

        $this->assertSame(3, $result->count());
        $this->assertTrue($result->truncated);

        $untruncated = $this->ranker->rank($matches, GeographyQuery::for('springfield', limit: 20));
        $this->assertFalse($untruncated->truncated);
    }
}
