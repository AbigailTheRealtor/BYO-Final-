<?php

namespace Tests\Unit\Services\LocationDna\Criteria\Rules;

use App\Services\LocationDna\Criteria\Rules\GeographyRule;
use App\Services\LocationDna\Criteria\Rules\GeographySelection;
use App\Services\LocationDna\Criteria\Rules\GeographySelectionResolver;
use App\Services\LocationDna\Criteria\Rules\GeographySelectionValidator;
use App\Services\LocationDna\Criteria\Rules\GeographyTier;
use PHPUnit\Framework\TestCase;

/**
 * Phase 1b — the cascade clearing rules C1–C6.
 *
 * No database: every one of these runs against the in-memory fake, which is why Phase 1a kept it.
 */
class GeographySelectionResolverTest extends TestCase
{
    use CriteriaGeographyFixture;

    private function resolver(): GeographySelectionResolver
    {
        return new GeographySelectionResolver($this->geography());
    }

    // ═════════════════════════════════════════════════════════════════════════
    // C1 · A STATE CHANGE CLEARS EVERYTHING BENEATH IT
    // ═════════════════════════════════════════════════════════════════════════

    public function test_changing_state_clears_all_three_lower_tiers(): void
    {
        $selection = GeographySelection::of(
            self::NEW_YORK,
            [self::SUFFOLK, self::NASSAU],
            [self::BABYLON, self::HEMPSTEAD],
            [self::ZIP_SHARED, self::ZIP_SUFFOLK],
        )->withState(self::LOUISIANA);

        $resolution = $this->resolver()->resolve($selection);

        $this->assertSame(self::LOUISIANA, $resolution->selection->stateId, 'the state itself is never cleared');
        $this->assertSame([], $resolution->selection->countyIds);
        $this->assertSame([], $resolution->selection->cityIds);
        $this->assertSame([], $resolution->selection->zipCodes);
    }

    /**
     * C4 — transitivity, and the reason it needs its own test.
     *
     * A dropped county must take its cities and ZIPs in the SAME pass. An implementation that
     * cleared counties and left the lower tiers for "the next interaction" would pass the test
     * above (which only inspects the end state after one call) if it happened to clear them for an
     * unrelated reason. This asserts the lower tiers were cleared BECAUSE the county went, by
     * checking the reported reasons.
     */
    public function test_clearing_is_transitive_within_a_single_pass(): void
    {
        $selection = GeographySelection::of(
            self::LOUISIANA,
            [self::SUFFOLK],                 // a New York county under a Louisiana state
            [self::BABYLON],
            [self::ZIP_SUFFOLK],
        );

        $resolution = $this->resolver()->resolve($selection);

        $this->assertSame([self::SUFFOLK], $resolution->clearedIdsFor(GeographyTier::Counties));
        $this->assertSame([self::BABYLON], $resolution->clearedIdsFor(GeographyTier::Cities));
        $this->assertSame([self::ZIP_SUFFOLK], $resolution->clearedIdsFor(GeographyTier::ZipCodes));

        $reasons = array_map(fn ($c) => $c->reason, $resolution->cleared);

        $this->assertContains(GeographyRule::CountyNotInState, $reasons);
        $this->assertContains(GeographyRule::CityNotInSelectedCounty, $reasons);
        $this->assertContains(GeographyRule::ZipNotInSelectedCounties, $reasons);
    }

    public function test_a_selection_with_no_state_justifies_nothing_beneath_it(): void
    {
        $resolution = $this->resolver()->resolve(GeographySelection::of(
            null,
            [self::SUFFOLK],
            [self::BABYLON],
            [self::ZIP_SUFFOLK],
        ));

        $this->assertNull($resolution->selection->stateId);
        $this->assertSame([], $resolution->selection->countyIds);
        $this->assertSame([], $resolution->selection->cityIds);
        $this->assertSame([], $resolution->selection->zipCodes);
    }

    public function test_an_unknown_state_is_left_alone_but_justifies_no_counties(): void
    {
        $resolution = $this->resolver()->resolve(
            GeographySelection::of('9999', [self::SUFFOLK])
        );

        $this->assertSame(
            '9999',
            $resolution->selection->stateId,
            'silently deleting the state the user chose is the worst available outcome; the '
            .'validator reports it instead'
        );
        $this->assertSame([], $resolution->selection->countyIds);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // C2 · CITIES ARE CONTAINMENT — ONE PARENT, CLEARED UNCONDITIONALLY
    // ═════════════════════════════════════════════════════════════════════════

    public function test_a_city_is_cleared_the_moment_its_only_county_is_deselected(): void
    {
        $resolution = $this->resolver()->resolve(GeographySelection::of(
            self::NEW_YORK,
            [self::NASSAU],                                  // Suffolk deselected
            [self::BABYLON, self::HUNTINGTON, self::HEMPSTEAD],
        ));

        $this->assertSame(
            [self::HEMPSTEAD],
            $resolution->selection->cityIds,
            'Hempstead is in Nassau, which survives'
        );
        $this->assertSame(
            [self::BABYLON, self::HUNTINGTON],
            $resolution->clearedIdsFor(GeographyTier::Cities),
            'both Suffolk cities are orphaned — us_cities.county_id is a real FK'
        );
    }

    public function test_a_city_from_an_unselected_county_is_cleared(): void
    {
        $resolution = $this->resolver()->resolve(GeographySelection::of(
            self::NEW_YORK,
            [self::SUFFOLK],
            [self::HEMPSTEAD],           // Nassau city, Nassau not selected
        ));

        $this->assertSame([], $resolution->selection->cityIds);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // C3 · ZIPS ARE ASSOCIATION — SURVIVE WHILE ANY PARENT SURVIVES
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * THE test of this phase.
     *
     * ZIP 11001 is associated with both Suffolk and Nassau. Deselecting Suffolk must NOT remove it,
     * because Nassau still justifies it. The naive rule — "county removed, so remove its ZIPs" —
     * destroys it here, and destroys real user data every time a ZCTA crosses a county line.
     */
    public function test_a_cross_county_zip_survives_partial_county_removal(): void
    {
        $resolution = $this->resolver()->resolve(GeographySelection::of(
            self::NEW_YORK,
            [self::NASSAU],                                              // Suffolk deselected
            [],
            [self::ZIP_SHARED, self::ZIP_SUFFOLK, self::ZIP_NASSAU],
        ));

        $this->assertContains(
            self::ZIP_SHARED,
            $resolution->selection->zipCodes,
            '11001 is associated with Nassau too — removing Suffolk must not take it'
        );
        $this->assertContains(self::ZIP_NASSAU, $resolution->selection->zipCodes);
        $this->assertNotContains(self::ZIP_SUFFOLK, $resolution->selection->zipCodes);

        $this->assertSame(
            [self::ZIP_SUFFOLK],
            $resolution->clearedIdsFor(GeographyTier::ZipCodes),
            'only the ZIP with no surviving parent is cleared'
        );
    }

    /** The other half of the same rule: when the LAST parent goes, the ZIP goes. */
    public function test_a_cross_county_zip_is_cleared_when_its_last_county_is_deselected(): void
    {
        $resolution = $this->resolver()->resolve(GeographySelection::of(
            self::NEW_YORK,
            [self::KINGS],                       // neither Suffolk nor Nassau
            [],
            [self::ZIP_SHARED],
        ));

        $this->assertSame([], $resolution->selection->zipCodes);
        $this->assertSame([self::ZIP_SHARED], $resolution->clearedIdsFor(GeographyTier::ZipCodes));
    }

    public function test_a_shared_zip_is_kept_once_not_once_per_parent(): void
    {
        $resolution = $this->resolver()->resolve(GeographySelection::of(
            self::NEW_YORK,
            [self::SUFFOLK, self::NASSAU],      // both parents selected
            [],
            [self::ZIP_SHARED],
        ));

        $this->assertSame(
            [self::ZIP_SHARED],
            $resolution->selection->zipCodes,
            'a selection holds ZIP CODES, so two associations still mean one selected ZIP'
        );
    }

    public function test_cities_and_zips_are_cleared_by_different_rules_in_one_pass(): void
    {
        $resolution = $this->resolver()->resolve(GeographySelection::of(
            self::NEW_YORK,
            [self::NASSAU],
            [self::BABYLON],                    // Suffolk city — containment, dies
            [self::ZIP_SHARED],                 // Suffolk+Nassau ZIP — association, lives
        ));

        $this->assertSame([], $resolution->selection->cityIds, 'containment: one parent, gone');
        $this->assertSame(
            [self::ZIP_SHARED],
            $resolution->selection->zipCodes,
            'association: another parent survives'
        );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // C5 · IDEMPOTENCE  ·  C6 · SUBTRACTIVE
    // ═════════════════════════════════════════════════════════════════════════

    public function test_resolution_is_idempotent(): void
    {
        $resolver = $this->resolver();

        $once  = $resolver->resolve(GeographySelection::of(
            self::NEW_YORK,
            [self::SUFFOLK, self::ORLEANS],
            [self::BABYLON, self::NEW_ORLEANS],
            [self::ZIP_SHARED, self::ZIP_ORLEANS],
        ));
        $twice = $resolver->resolve($once->selection);

        $this->assertTrue($once->selection->equals($twice->selection));
        $this->assertSame([], $twice->cleared, 'a resolved selection has nothing left to clear');
        $this->assertFalse($twice->changed());
    }

    /**
     * C6 — resolution is subtractive.
     *
     * Both the state-only and the county-selected cases are exercised, and the FIRST is the one
     * that carries the weight. An earlier version of this test only checked the second, and a
     * mutation that auto-selected every county of the chosen state survived it untouched: with a
     * county already selected there was nothing for the mutation to fill in. "Picking a state must
     * not silently select all 254 of its counties" is only pinned by a selection that has none.
     *
     * @dataProvider subtractiveSelections
     */
    public function test_resolution_never_adds_a_selection(GeographySelection $selection): void
    {
        $resolution = $this->resolver()->resolve($selection);

        foreach ([GeographyTier::Counties, GeographyTier::Cities, GeographyTier::ZipCodes] as $tier) {
            $this->assertEmpty(
                array_diff($resolution->selection->idsFor($tier), $selection->idsFor($tier)),
                "resolution introduced an id into {$tier->value} that was not in the input"
            );
        }
    }

    /** @return array<string, array{GeographySelection}> */
    public static function subtractiveSelections(): array
    {
        return [
            // The load-bearing case: a state and nothing else must stay a state and nothing else.
            'state only'            => [GeographySelection::of(self::NEW_YORK)],
            'state and one county'  => [GeographySelection::of(self::NEW_YORK, [self::SUFFOLK])],
            'counties but no city'  => [GeographySelection::of(self::NEW_YORK, [self::SUFFOLK, self::NASSAU])],
            'city but no zip'       => [GeographySelection::of(self::NEW_YORK, [self::SUFFOLK], [self::BABYLON])],
        ];
    }

    /** Picking a county must not auto-select the cities or ZIPs beneath it either. */
    public function test_selecting_a_county_does_not_populate_the_tiers_below_it(): void
    {
        $resolution = $this->resolver()->resolve(
            GeographySelection::of(self::NEW_YORK, [self::SUFFOLK])
        );

        $this->assertSame([self::SUFFOLK], $resolution->selection->countyIds);
        $this->assertSame([], $resolution->selection->cityIds);
        $this->assertSame([], $resolution->selection->zipCodes);
    }

    public function test_a_fully_justified_selection_is_returned_untouched(): void
    {
        $selection = GeographySelection::of(
            self::NEW_YORK,
            [self::SUFFOLK, self::NASSAU],
            [self::BABYLON, self::HEMPSTEAD],
            [self::ZIP_SHARED, self::ZIP_SUFFOLK, self::ZIP_NASSAU],
        );

        $resolution = $this->resolver()->resolve($selection);

        $this->assertTrue($resolution->selection->equals($selection));
        $this->assertFalse($resolution->changed());
    }

    // ═════════════════════════════════════════════════════════════════════════
    // NORMALISATION, AND THE INVARIANT THAT TIES THIS CLASS TO THE VALIDATOR
    // ═════════════════════════════════════════════════════════════════════════

    public function test_duplicates_are_collapsed_and_reported(): void
    {
        $resolution = $this->resolver()->resolve(GeographySelection::of(
            self::NEW_YORK,
            [self::SUFFOLK, self::SUFFOLK],
        ));

        $this->assertSame([self::SUFFOLK], $resolution->selection->countyIds);
        $this->assertSame(
            GeographyRule::DuplicateSelection,
            $resolution->clearedFor(GeographyTier::Counties)[0]->reason
        );
    }

    public function test_a_blank_id_is_cleared_as_malformed(): void
    {
        $resolution = $this->resolver()->resolve(GeographySelection::of(
            self::NEW_YORK,
            [self::SUFFOLK, ''],
        ));

        $this->assertSame([self::SUFFOLK], $resolution->selection->countyIds);
        $this->assertSame(
            GeographyRule::MalformedId,
            $resolution->clearedFor(GeographyTier::Counties)[0]->reason
        );
    }

    /**
     * The load-bearing invariant: whatever the resolver returns, the validator calls valid.
     *
     * This is what makes the two classes a layer rather than two utilities. It is asserted over a
     * deliberately filthy input — wrong state, orphan city, orphan ZIP, duplicate, blank.
     */
    public function test_a_resolved_selection_never_carries_a_referential_violation(): void
    {
        $geography = $this->geography();

        $resolution = (new GeographySelectionResolver($geography))->resolve(GeographySelection::of(
            self::NEW_YORK,
            [self::SUFFOLK, self::ORLEANS, self::SUFFOLK, ''],
            [self::NEW_ORLEANS, self::BABYLON],
            [self::ZIP_ORLEANS, self::ZIP_SHARED, '501'],
        ));

        $result = (new GeographySelectionValidator($geography))->validate($resolution->selection);

        $this->assertTrue(
            $result->isValid(),
            'resolved selection still invalid: '.json_encode($result->toArray())
        );
    }

    public function test_the_resolution_reports_what_it_cleared_for_a_surface_to_explain(): void
    {
        $resolution = $this->resolver()->resolve(GeographySelection::of(
            self::NEW_YORK,
            [self::NASSAU],
            [self::BABYLON, self::HUNTINGTON],
            [self::ZIP_SUFFOLK],
        ));

        $this->assertTrue($resolution->changed());
        $this->assertCount(2, $resolution->clearedFor(GeographyTier::Cities));
        $this->assertCount(1, $resolution->clearedFor(GeographyTier::ZipCodes));
        $this->assertSame(
            [
                'tier'   => 'zip_codes',
                'id'     => self::ZIP_SUFFOLK,
                'reason' => 'zip_not_in_selected_counties',
            ],
            $resolution->clearedFor(GeographyTier::ZipCodes)[0]->toArray()
        );
    }
}
