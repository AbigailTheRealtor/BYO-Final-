<?php

namespace Tests\Unit\Services\LocationDna\Criteria\Rules;

use App\Services\LocationDna\Criteria\Rules\GeographyRule;
use App\Services\LocationDna\Criteria\Rules\GeographySelection;
use App\Services\LocationDna\Criteria\Rules\GeographySelectionValidator;
use App\Services\LocationDna\Criteria\Rules\GeographyTier;
use PHPUnit\Framework\TestCase;

/**
 * Phase 1b — validation rules S1–S5 (structural) and R1–R4 (referential), plus the
 * validity/completeness split.
 */
class GeographySelectionValidatorTest extends TestCase
{
    use CriteriaGeographyFixture;

    private function validator(): GeographySelectionValidator
    {
        return new GeographySelectionValidator($this->geography());
    }

    // ═════════════════════════════════════════════════════════════════════════
    // THE VALIDITY / COMPLETENESS SPLIT (D5)
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * A state chosen and nothing else: VALID, not COMPLETE.
     *
     * This is the mid-cascade state every user passes through. If it reported invalid, the form
     * would show an error for something the user has not had the chance to do yet.
     */
    public function test_a_state_only_selection_is_valid_but_incomplete(): void
    {
        $result = $this->validator()->validate(GeographySelection::of(self::NEW_YORK));

        $this->assertTrue($result->isValid());
        $this->assertFalse($result->isComplete());
        $this->assertTrue($result->hasRule(GeographyRule::CountyRequired));
    }

    public function test_an_empty_selection_is_valid_but_incomplete(): void
    {
        $result = $this->validator()->validate(GeographySelection::empty());

        $this->assertTrue($result->isValid(), 'nothing selected is not illegal');
        $this->assertFalse($result->isComplete());
        $this->assertTrue($result->hasRule(GeographyRule::StateRequired));
        $this->assertTrue($result->hasRule(GeographyRule::CountyRequired));
    }

    public function test_a_state_with_one_county_is_complete(): void
    {
        $result = $this->validator()->validate(
            GeographySelection::of(self::NEW_YORK, [self::SUFFOLK])
        );

        $this->assertTrue($result->isValid());
        $this->assertTrue($result->isComplete());
        $this->assertSame([], $result->violations());
    }

    public function test_cities_and_zips_are_optional_for_completeness(): void
    {
        $result = $this->validator()->validate(GeographySelection::of(
            self::NEW_YORK,
            [self::SUFFOLK, self::NASSAU],
        ));

        $this->assertTrue($result->isComplete(), 'ZIPs and cities are optional refinements');
    }

    /** A real error makes it invalid — completeness-only rules never do. */
    public function test_an_orphan_city_makes_the_selection_invalid_not_merely_incomplete(): void
    {
        $result = $this->validator()->validate(GeographySelection::of(
            self::NEW_YORK,
            [self::SUFFOLK],
            [self::HEMPSTEAD],       // Nassau city, Nassau not selected
        ));

        $this->assertFalse($result->isValid());
        $this->assertFalse($result->isComplete());
    }

    // ═════════════════════════════════════════════════════════════════════════
    // S1–S5 · STRUCTURAL
    // ═════════════════════════════════════════════════════════════════════════

    public function test_s1_a_missing_state_is_reported(): void
    {
        $result = $this->validator()->validate(GeographySelection::of(null, [self::SUFFOLK]));

        $this->assertTrue($result->hasRule(GeographyRule::StateRequired));
    }

    /**
     * S2 — counties are required, and this must be the ONLY thing separating complete from
     * incomplete when a valid state is chosen.
     *
     * Stated that precisely on purpose: with a missing state the selection is already incomplete
     * for an unrelated reason, so a weaker assertion would still pass if the county rule were
     * deleted outright. See the mutation notes.
     */
    public function test_s2_a_selection_without_counties_is_incomplete(): void
    {
        $withCounty    = $this->validator()->validate(GeographySelection::of(self::NEW_YORK, [self::SUFFOLK]));
        $withoutCounty = $this->validator()->validate(GeographySelection::of(self::NEW_YORK, []));

        $this->assertTrue($withCounty->isComplete(), 'control: a state plus a county is complete');

        $this->assertTrue($withoutCounty->isValid(), 'an empty county list is not an ERROR');
        $this->assertFalse($withoutCounty->isComplete(), 'but it is not COMPLETE either');
        $this->assertSame(
            [GeographyRule::CountyRequired],
            $withoutCounty->rules(),
            'and county-required must be the only rule separating the two'
        );
    }

    public function test_s3_a_duplicate_id_is_reported_in_its_own_tier(): void
    {
        $result = $this->validator()->validate(GeographySelection::of(
            self::NEW_YORK,
            [self::SUFFOLK, self::SUFFOLK],
        ));

        $this->assertFalse($result->isValid());
        $this->assertTrue($result->hasRule(GeographyRule::DuplicateSelection));
        $this->assertCount(1, $result->violationsFor(GeographyTier::Counties));
    }

    public function test_s3_duplicates_are_detected_in_every_tier(): void
    {
        $result = $this->validator()->validate(GeographySelection::of(
            self::NEW_YORK,
            [self::SUFFOLK, self::SUFFOLK],
            [self::BABYLON, self::BABYLON],
            [self::ZIP_SUFFOLK, self::ZIP_SUFFOLK],
        ));

        foreach ([GeographyTier::Counties, GeographyTier::Cities, GeographyTier::ZipCodes] as $tier) {
            $rules = array_map(fn ($v) => $v->rule, $result->violationsFor($tier));

            $this->assertContains(GeographyRule::DuplicateSelection, $rules, "no duplicate reported for {$tier->value}");
        }
    }

    public function test_s4_a_blank_id_is_malformed(): void
    {
        $result = $this->validator()->validate(GeographySelection::of(self::NEW_YORK, [self::SUFFOLK, '']));

        $this->assertTrue($result->hasRule(GeographyRule::MalformedId));
        $this->assertFalse($result->isValid());
    }

    /**
     * S5 — an unpadded ZIP is REPORTED, never silently padded.
     *
     * 3,000 of the 34,741 reference rows are stored short ("501" is 00501). Phase 1a pads on the way
     * out of the repository, so an unpadded value inside a selection means it came from somewhere
     * that bypassed the repository — a caller bug, not a formatting nit.
     */
    public function test_s5_an_unpadded_zip_is_a_violation(): void
    {
        $result = $this->validator()->validate(GeographySelection::of(
            self::NEW_YORK,
            [self::SUFFOLK],
            [],
            ['501'],
        ));

        $this->assertTrue($result->hasRule(GeographyRule::MalformedZip));
        $this->assertFalse($result->isValid());
        $this->assertSame(
            ['501'],
            array_values(array_map(
                fn ($v) => $v->offendingId,
                array_filter($result->violations(), fn ($v) => $v->rule === GeographyRule::MalformedZip)
            )),
            'the violation must name the offending value so a surface can highlight it'
        );
    }

    public function test_s5_a_non_numeric_zip_is_a_violation(): void
    {
        $result = $this->validator()->validate(
            GeographySelection::of(self::NEW_YORK, [self::SUFFOLK], [], ['ABCDE'])
        );

        $this->assertTrue($result->hasRule(GeographyRule::MalformedZip));
    }

    public function test_s5_a_canonical_five_digit_zip_is_accepted(): void
    {
        $result = $this->validator()->validate(
            GeographySelection::of(self::NEW_YORK, [self::SUFFOLK], [], [self::ZIP_SUFFOLK])
        );

        $this->assertFalse($result->hasRule(GeographyRule::MalformedZip));
        $this->assertTrue($result->isComplete());
    }

    // ═════════════════════════════════════════════════════════════════════════
    // R1–R4 · REFERENTIAL
    // ═════════════════════════════════════════════════════════════════════════

    public function test_r1_an_unknown_state_is_reported(): void
    {
        $result = $this->validator()->validate(GeographySelection::of('9999', [self::SUFFOLK]));

        $this->assertTrue($result->hasRule(GeographyRule::StateUnknown));
        $this->assertFalse($result->isValid());
    }

    public function test_r2_a_county_from_another_state_is_rejected(): void
    {
        $result = $this->validator()->validate(GeographySelection::of(
            self::NEW_YORK,
            [self::SUFFOLK, self::ORLEANS],     // Orleans Parish is Louisiana
        ));

        $this->assertTrue($result->hasRule(GeographyRule::CountyNotInState));
        $this->assertFalse($result->isValid());

        $offenders = array_map(fn ($v) => $v->offendingId, $result->violationsFor(GeographyTier::Counties));

        $this->assertSame([self::ORLEANS], $offenders, 'only the foreign county is flagged');
    }

    public function test_r3_a_city_outside_the_selected_counties_is_rejected(): void
    {
        $result = $this->validator()->validate(GeographySelection::of(
            self::NEW_YORK,
            [self::NASSAU],
            [self::BABYLON],                    // Suffolk city
        ));

        $this->assertTrue($result->hasRule(GeographyRule::CityNotInSelectedCounty));
    }

    /** R4 — a ZIP is satisfied by ANY associated county, not by a particular one. */
    public function test_r4_a_shared_zip_is_accepted_under_either_of_its_counties(): void
    {
        foreach ([self::SUFFOLK, self::NASSAU] as $countyId) {
            $result = $this->validator()->validate(GeographySelection::of(
                self::NEW_YORK,
                [$countyId],
                [],
                [self::ZIP_SHARED],
            ));

            $this->assertTrue(
                $result->isComplete(),
                "11001 must be accepted under county {$countyId} — association, not containment"
            );
        }
    }

    public function test_r4_a_zip_with_no_associated_selected_county_is_rejected(): void
    {
        $result = $this->validator()->validate(GeographySelection::of(
            self::NEW_YORK,
            [self::KINGS],
            [],
            [self::ZIP_SHARED],
        ));

        $this->assertTrue($result->hasRule(GeographyRule::ZipNotInSelectedCounties));
    }

    // ═════════════════════════════════════════════════════════════════════════
    // ACCUMULATION AND SHORT-CIRCUITING
    // ═════════════════════════════════════════════════════════════════════════

    /** Validation reports everything at once; a surface must not need a round trip per mistake. */
    public function test_multiple_violations_are_all_reported(): void
    {
        $result = $this->validator()->validate(GeographySelection::of(
            self::NEW_YORK,
            [self::ORLEANS],                    // wrong state
            [self::NEW_ORLEANS],                // orphan city
            ['501'],                            // malformed ZIP
        ));

        $rules = $result->rules();

        $this->assertContains(GeographyRule::CountyNotInState, $rules);
        $this->assertContains(GeographyRule::CityNotInSelectedCounty, $rules);
        $this->assertContains(GeographyRule::MalformedZip, $rules);
        $this->assertGreaterThanOrEqual(3, count($result->violations()));
    }

    /**
     * With no usable state, referential checks stop.
     *
     * Otherwise a user who picked twelve counties before picking a state would be shown thirteen
     * errors describing one problem.
     */
    public function test_referential_checks_short_circuit_below_a_missing_state(): void
    {
        $result = $this->validator()->validate(GeographySelection::of(
            null,
            [self::SUFFOLK, self::NASSAU, self::KINGS],
        ));

        $this->assertFalse($result->hasRule(GeographyRule::CountyNotInState));
        $this->assertSame([GeographyRule::StateRequired], $result->rules());
    }

    public function test_referential_checks_short_circuit_below_an_unknown_state(): void
    {
        $result = $this->validator()->validate(GeographySelection::of(
            '9999',
            [self::SUFFOLK, self::NASSAU],
        ));

        $this->assertSame([GeographyRule::StateUnknown], $result->rules());
    }

    /** A city selected with no counties really IS an orphan, so this does not short-circuit. */
    public function test_a_city_with_no_counties_selected_is_still_reported(): void
    {
        $result = $this->validator()->validate(GeographySelection::of(
            self::NEW_YORK,
            [],
            [self::BABYLON],
        ));

        $this->assertTrue($result->hasRule(GeographyRule::CityNotInSelectedCounty));
        $this->assertTrue($result->hasRule(GeographyRule::CountyRequired));
        $this->assertFalse($result->isValid());
    }

    public function test_violations_are_placed_in_the_tier_a_surface_can_render(): void
    {
        $result = $this->validator()->validate(GeographySelection::of(
            self::NEW_YORK,
            [self::ORLEANS],
            [self::NEW_ORLEANS],
            ['501'],
        ));

        $this->assertCount(1, $result->violationsFor(GeographyTier::Counties));
        $this->assertCount(1, $result->violationsFor(GeographyTier::Cities));
        $this->assertCount(1, $result->violationsFor(GeographyTier::ZipCodes));
        $this->assertCount(0, $result->violationsFor(GeographyTier::State));
    }

    /**
     * One mistake produces one message.
     *
     * A malformed ZIP can never match the corpus either, so the naive implementation reports it
     * twice — "must be five digits" AND "not associated with any selected county". The second is
     * noise the user cannot act on independently, and two messages for one bad value reads as two
     * separate problems. The structural message is the actionable one and is the only one kept.
     */
    public function test_a_malformed_zip_is_reported_once_not_also_as_unjustified(): void
    {
        $result = $this->validator()->validate(GeographySelection::of(
            self::NEW_YORK,
            [self::SUFFOLK],
            [],
            ['501'],
        ));

        $this->assertTrue($result->hasRule(GeographyRule::MalformedZip));
        $this->assertFalse(
            $result->hasRule(GeographyRule::ZipNotInSelectedCounties),
            'the same bad value must not be reported under two rules'
        );
        $this->assertCount(1, $result->violationsFor(GeographyTier::ZipCodes));
    }

    public function test_the_validator_never_throws_on_hostile_input(): void
    {
        $result = $this->validator()->validate(GeographySelection::of(
            '   ',
            ['', '  ', 'not-an-id'],
            ['-1'],
            ['', '000', '123456'],
        ));

        $this->assertFalse($result->isValid());
        $this->assertNotEmpty($result->violations());
    }
}
