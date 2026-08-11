<?php

namespace Tests\Unit\Services\LocationDna\Criteria\Search;

use App\Services\LocationDna\Criteria\GeographyOption;
use App\Services\LocationDna\Criteria\Rules\GeographyTier;
use App\Services\LocationDna\Criteria\Search\FakeGeographySearchRepository;
use App\Services\LocationDna\Criteria\Search\GeographyQuery;
use App\Services\LocationDna\Criteria\Search\MatchType;
use PHPUnit\Framework\TestCase;

/**
 * M1 — the fake honours the same contract the census implementation does.
 *
 * The fake exists so that consumers (M2's UI, and the cascade rules already) can be tested without
 * a corpus. That is only worth anything if it BEHAVES the same, so these assertions are
 * deliberately the contract's assertions rather than the fake's implementation details.
 */
class FakeGeographySearchRepositoryTest extends TestCase
{
    private function repo(): FakeGeographySearchRepository
    {
        return (new FakeGeographySearchRepository())
            ->with(GeographyOption::state('12', 'Florida', '12', 'FL'))
            ->with(GeographyOption::county('12103', 'Pinellas County', '12', '12103'), 'Florida')
            ->with(GeographyOption::city('1212925', 'Clearwater', '12103'), 'Pinellas County, FL')
            ->with(GeographyOption::city('1200002', 'Clearwater Beach', '12103'), 'Pinellas County, FL')
            ->with(GeographyOption::neighborhood('9', 'Island Estates', '1212925'), 'Clearwater, FL')
            ->with(GeographyOption::zip('33756', '12103'), 'Pinellas County, FL', ['12057']);
    }

    /** @test */
    public function an_unusable_term_returns_the_empty_result(): void
    {
        $this->assertTrue($this->repo()->search(GeographyQuery::for('c'))->isEmpty());
    }

    /** @test */
    public function an_exact_city_outranks_a_longer_prefix_match(): void
    {
        $result = $this->repo()->search(GeographyQuery::for('Clearwater'));

        $this->assertSame('Clearwater', $result->matches[0]->label());
        $this->assertSame(MatchType::Exact, $result->matches[0]->matchType);
    }

    /** @test */
    public function a_tier_filter_is_honoured(): void
    {
        $result = $this->repo()->search(GeographyQuery::for('Clearwater', [GeographyTier::Cities]));

        foreach ($result->matches as $match) {
            $this->assertSame(GeographyOption::KIND_CITY, $match->option->kind);
        }
    }

    /**
     * ZIPs match on digits, not through the name classifier — "337" is a prefix of 33756 in a sense
     * the word-boundary rule would never see.
     *
     * @test
     */
    public function a_zip_matches_by_digits_only(): void
    {
        $zips = $this->repo()->search(GeographyQuery::for('337'))->ofKind(GeographyOption::KIND_ZIP);

        $this->assertCount(1, $zips);
        $this->assertSame(MatchType::Prefix, $zips[0]->matchType);

        $this->assertEmpty(
            $this->repo()->search(GeographyQuery::for('Clearwater'))->ofKind(GeographyOption::KIND_ZIP),
            'a place name must not match a ZIP'
        );
    }

    /** @test */
    public function extra_parents_are_carried_with_the_canonical_one_first(): void
    {
        $zips = $this->repo()->search(GeographyQuery::for('33756'))->ofKind(GeographyOption::KIND_ZIP);

        $this->assertSame(['12103', '12057'], $zips[0]->parentIds);
        $this->assertSame('12103', $zips[0]->option->parentId);
    }

    /** @test */
    public function a_word_inside_a_name_is_found(): void
    {
        $result = $this->repo()->search(GeographyQuery::for('Beach'));

        $this->assertSame('Clearwater Beach', $result->matches[0]->label());
        $this->assertSame(MatchType::Word, $result->matches[0]->matchType);
    }

    /** @test */
    public function truncation_is_reported(): void
    {
        $result = $this->repo()->search(GeographyQuery::for('Clearwater', limit: 1));

        $this->assertSame(1, $result->count());
        $this->assertTrue($result->truncated);
    }
}
