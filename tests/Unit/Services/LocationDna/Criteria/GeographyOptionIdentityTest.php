<?php

namespace Tests\Unit\Services\LocationDna\Criteria;

use App\Services\LocationDna\Criteria\GeographyOption;
use PHPUnit\Framework\TestCase;

/**
 * Phase 1b — the two identity helpers added to the Phase 1a DTO (D6).
 *
 * WHY `matches()` EXISTS AT ALL
 * -----------------------------
 * It is the written-down definition of option identity, and the point of writing it down is that
 * the answer is NOT the obvious one. A ZIP option's identity is the pair (id, parentId), because
 * the same ZIP is emitted once per associated county — so two options carrying id "11001" can be
 * genuinely different options rather than a duplicate.
 *
 * Selection deliberately does NOT use that definition: it keys ZIPs on the code alone, which is
 * what makes a shared ZIP survive the loss of one parent. Having both notions written down and
 * tested is what stops a later reader from "fixing" one into the other.
 */
class GeographyOptionIdentityTest extends TestCase
{
    public function test_is_recognises_its_own_kind(): void
    {
        $county = GeographyOption::county('10', 'Suffolk County', '1');

        $this->assertTrue($county->is(GeographyOption::KIND_COUNTY));
        $this->assertFalse($county->is(GeographyOption::KIND_CITY));
        $this->assertFalse($county->is(GeographyOption::KIND_STATE));
        $this->assertFalse($county->is(GeographyOption::KIND_ZIP));
    }

    public function test_is_recognises_every_kind(): void
    {
        $this->assertTrue(GeographyOption::state('1', 'New York')->is(GeographyOption::KIND_STATE));
        $this->assertTrue(GeographyOption::county('10', 'Suffolk County', '1')->is(GeographyOption::KIND_COUNTY));
        $this->assertTrue(GeographyOption::city('100', 'Babylon', '10')->is(GeographyOption::KIND_CITY));
        $this->assertTrue(GeographyOption::zip('11001', '10')->is(GeographyOption::KIND_ZIP));
    }

    public function test_matches_is_true_for_the_same_option(): void
    {
        $a = GeographyOption::zip('11001', '10');
        $b = GeographyOption::zip('11001', '10');

        $this->assertTrue($a->matches($b));
        $this->assertTrue($b->matches($a), 'identity must be symmetric');
    }

    /**
     * THE case this helper exists for: one ZIP, two counties, two DISTINCT options.
     *
     * If `matches()` compared ids alone it would call these the same option, and a future reader
     * would be entitled to deduplicate one of them away — losing the association that lets a shared
     * ZIP survive the removal of one of its counties.
     */
    public function test_the_same_zip_under_two_counties_is_two_distinct_options(): void
    {
        $underSuffolk = GeographyOption::zip('11001', '10');
        $underNassau  = GeographyOption::zip('11001', '11');

        $this->assertSame($underSuffolk->id, $underNassau->id, 'same ZIP code');
        $this->assertFalse(
            $underSuffolk->matches($underNassau),
            'but different options — identity is (kind, id, parentId)'
        );
    }

    public function test_matches_distinguishes_kind(): void
    {
        $city = GeographyOption::city('11001', 'Oddly Numbered', '10');
        $zip  = GeographyOption::zip('11001', '10');

        $this->assertFalse($city->matches($zip), 'a shared id across kinds is not a match');
    }

    public function test_matches_distinguishes_parent(): void
    {
        $this->assertFalse(
            GeographyOption::city('100', 'Babylon', '10')
                ->matches(GeographyOption::city('100', 'Babylon', '11'))
        );
    }

    public function test_matches_ignores_display_name_and_code(): void
    {
        $a = GeographyOption::county('10', 'Suffolk County', '1', '36103');
        $b = GeographyOption::county('10', 'Suffolk', '1', null);

        $this->assertTrue(
            $a->matches($b),
            'identity is the id triple; a display name is never part of it — free-text names are '
            .'what made the legacy criteria data unsafe in the first place'
        );
    }

    public function test_states_match_on_id_alone_since_they_have_no_parent(): void
    {
        $this->assertTrue(
            GeographyOption::state('1', 'New York', '36')->matches(GeographyOption::state('1', 'NY'))
        );
        $this->assertFalse(
            GeographyOption::state('1', 'New York')->matches(GeographyOption::state('2', 'Louisiana'))
        );
    }
}
