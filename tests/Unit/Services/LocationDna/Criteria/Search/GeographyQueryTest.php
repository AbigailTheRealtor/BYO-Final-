<?php

namespace Tests\Unit\Services\LocationDna\Criteria\Search;

use App\Services\LocationDna\Criteria\Rules\GeographyTier;
use App\Services\LocationDna\Criteria\Search\GeographyQuery;
use PHPUnit\Framework\TestCase;

/**
 * M1 — what the user meant, decided without a database.
 *
 * Intent detection lives on the query object precisely so it can be tested like this: no schema, no
 * fixture, no corpus. Every rule here is a rule both implementations obey, so a disagreement
 * between census and fake about what a term means becomes impossible rather than merely unlikely.
 */
class GeographyQueryTest extends TestCase
{
    /** @test */
    public function a_term_shorter_than_the_floor_is_not_worth_a_query(): void
    {
        $this->assertFalse(GeographyQuery::for('c')->isUsable());
        $this->assertFalse(GeographyQuery::for('  ')->isUsable());
        $this->assertTrue(GeographyQuery::for('cl')->isUsable());
    }

    /**
     * A zero limit is unusable rather than "successfully empty".
     *
     * A caller that computed its limit from a misread config should get the same answer as one that
     * typed nothing, and no query should be issued for a result that cannot hold a row.
     *
     * @test
     */
    public function a_zero_limit_makes_the_query_unusable(): void
    {
        $this->assertFalse(GeographyQuery::for('clearwater', limit: 0)->isUsable());
        $this->assertFalse(GeographyQuery::for('clearwater', limit: -5)->isUsable());
    }

    /**
     * The cascade stores "Clearwater, FL". A user who types back the format the system showed them
     * must not get zero results — that is the worst possible first impression.
     *
     * @test
     */
    public function a_trailing_state_suffix_becomes_scope_and_leaves_the_name(): void
    {
        $query = GeographyQuery::for('Clearwater, FL');

        $this->assertSame('Clearwater', $query->searchableTerm());
        $this->assertSame('clearwater', $query->normalizedTerm());
        $this->assertSame('FL', $query->stateAbbreviationHint());
    }

    /** @test */
    public function a_term_without_a_suffix_has_no_state_hint(): void
    {
        $this->assertNull(GeographyQuery::for('Clearwater')->stateAbbreviationHint());
        $this->assertNull(GeographyQuery::for('Winston-Salem')->stateAbbreviationHint());
    }

    /**
     * Normalisation is borrowed from PlaceNameKey, which is what `location_places.name_key` is
     * stored in. A second normaliser here would silently stop matching that column.
     *
     * @test
     */
    public function normalisation_folds_the_saint_variants_and_collapses_whitespace(): void
    {
        $this->assertSame('st petersburg', GeographyQuery::for('Saint Petersburg')->normalizedTerm());
        $this->assertSame('st petersburg', GeographyQuery::for('St. Petersburg')->normalizedTerm());
        $this->assertSame('st petersburg', GeographyQuery::for('  St   Petersburg  ')->normalizedTerm());
    }

    /**
     * A PARTIAL zip counts. A typeahead is read after every keystroke, so "337" must already be
     * offering ZIPs — waiting for the fifth digit means the tier never appears while the user is
     * still looking at the box.
     *
     * @test
     */
    public function a_partial_zip_still_looks_like_a_zip(): void
    {
        $this->assertTrue(GeographyQuery::for('33')->looksLikeZip());
        $this->assertTrue(GeographyQuery::for('337')->looksLikeZip());
        $this->assertTrue(GeographyQuery::for('33756')->looksLikeZip());

        $this->assertFalse(GeographyQuery::for('337561')->looksLikeZip(), 'six digits is not a ZIP');
        $this->assertFalse(GeographyQuery::for('337ab')->looksLikeZip());
        $this->assertFalse(GeographyQuery::for('clearwater')->looksLikeZip());
    }

    /** @test */
    public function a_named_county_is_recognised_across_the_regional_variants(): void
    {
        $this->assertTrue(GeographyQuery::for('Pinellas County')->looksLikeCounty());
        $this->assertTrue(GeographyQuery::for('Orleans Parish')->looksLikeCounty());
        $this->assertTrue(GeographyQuery::for('Kodiak Island Borough')->looksLikeCounty());

        $this->assertFalse(GeographyQuery::for('Pinellas')->looksLikeCounty());
        $this->assertFalse(GeographyQuery::for('County Line Road')->looksLikeCounty());
    }

    /** @test */
    public function every_tier_is_searched_unless_the_caller_narrows_it(): void
    {
        $all = GeographyQuery::for('clearwater');

        foreach (GeographyQuery::allTiers() as $tier) {
            $this->assertTrue($all->wantsTier($tier), $tier->value.' should be searched by default');
        }

        $narrow = GeographyQuery::for('clearwater', [GeographyTier::Cities]);

        $this->assertTrue($narrow->wantsTier(GeographyTier::Cities));
        $this->assertFalse($narrow->wantsTier(GeographyTier::ZipCodes));
    }

    /** An empty tier list means "no preference", not "search nothing". */
    /** @test */
    public function an_empty_tier_list_means_every_tier(): void
    {
        $this->assertTrue(GeographyQuery::for('clearwater', [])->wantsTier(GeographyTier::ZipCodes));
    }

    /** @test */
    public function county_scope_is_deduplicated_and_blanks_are_dropped(): void
    {
        $query = GeographyQuery::for('clearwater', countyIds: ['12103', ' 12103 ', '', '12057']);

        $this->assertSame(['12103', '12057'], $query->countyIds);
        $this->assertTrue($query->hasCountyScope());
        $this->assertFalse(GeographyQuery::for('clearwater')->hasCountyScope());
    }

    /** @test */
    public function a_blank_state_scope_is_no_scope(): void
    {
        $this->assertNull(GeographyQuery::for('clearwater', stateId: '')->stateId);
        $this->assertNull(GeographyQuery::for('clearwater', stateId: '   ')->stateId);
        $this->assertSame('12', GeographyQuery::for('clearwater', stateId: ' 12 ')->stateId);
    }
}
