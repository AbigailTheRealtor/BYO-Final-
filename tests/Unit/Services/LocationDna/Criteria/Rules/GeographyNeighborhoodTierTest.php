<?php

namespace Tests\Unit\Services\LocationDna\Criteria\Rules;

use App\Services\LocationDna\Criteria\GeographyOption;
use App\Services\LocationDna\Criteria\Rules\GeographyRule;
use App\Services\LocationDna\Criteria\Rules\GeographySelection;
use App\Services\LocationDna\Criteria\Rules\GeographyTier;
use PHPUnit\Framework\TestCase;

/**
 * Phase 1d-5, slice 1 — the neighbourhood tier's domain vocabulary.
 *
 * PURE. No database, no container, no repository — this is the tier, the option kind, the rule and
 * the DTO, and nothing else exists yet. Justification, validation, projection and the Livewire
 * surface are later slices, and asserting anything about them here would be asserting behaviour
 * that has not been written.
 *
 * THE TOTALITY GUARDS ARE THE POINT OF THIS FILE
 * ----------------------------------------------
 * `GeographyTier::optionKind()`, `GeographyRule::defaultTier()` and `GeographyRule::describe()` are
 * `match` expressions with no default arm. That is the right design — a missing arm should be loud
 * rather than silently mapped to something plausible — but it means adding an enum case without
 * updating them throws `UnhandledMatchError` AT RUNTIME, in whatever request happens to touch the
 * new case first. Slice 1 adds a case to both enums, so the failure mode is live right now.
 *
 * Iterating `cases()` converts that from a production error into a test failure. Any future tier or
 * rule is covered the moment it is declared, with no test to remember to write.
 */
class GeographyNeighborhoodTierTest extends TestCase
{
    // ─────────────────────────────────────────────────────────────────────
    // Totality
    // ─────────────────────────────────────────────────────────────────────

    /** @test */
    public function every_tier_maps_to_an_option_kind(): void
    {
        foreach (GeographyTier::cases() as $tier) {
            $this->assertNotSame(
                '',
                $tier->optionKind(),
                "GeographyTier::{$tier->name} has no option kind."
            );
        }
    }

    /** @test */
    public function every_rule_has_a_description_and_a_resolvable_tier(): void
    {
        foreach (GeographyRule::cases() as $rule) {
            $this->assertNotSame('', $rule->describe(), "GeographyRule::{$rule->name} has no description.");

            // defaultTier() legitimately returns null for the two tier-agnostic rules; the
            // assertion is that the call RESOLVES rather than throwing on a missing arm.
            $tier = $rule->defaultTier();

            $this->assertTrue(
                $tier === null || $tier instanceof GeographyTier,
                "GeographyRule::{$rule->name} has no default tier arm."
            );
        }
    }

    /** @test */
    public function every_tier_can_be_read_off_a_selection(): void
    {
        $selection = GeographySelection::of('1', ['10'], ['100'], ['11001'], ['900']);

        foreach (GeographyTier::cases() as $tier) {
            $this->assertIsArray(
                $selection->idsFor($tier),
                "GeographySelection::idsFor() has no arm for {$tier->name}."
            );
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // The tier itself
    // ─────────────────────────────────────────────────────────────────────

    /** @test */
    public function the_neighbourhood_tier_is_optional(): void
    {
        $this->assertFalse(
            GeographyTier::Neighborhoods->isRequired(),
            'A neighbourhood is a refinement; requiring one would make most selections incomplete.'
        );
    }

    /** @test */
    public function the_neighbourhood_tier_is_populated_by_neighbourhood_options(): void
    {
        $this->assertSame(
            GeographyOption::KIND_NEIGHBORHOOD,
            GeographyTier::Neighborhoods->optionKind()
        );
    }

    /**
     * @test
     *
     * The tier's backing value is a TIER IDENTIFIER, not a storage key. The four original cases are
     * named after canonical blob keys, which makes it natural to assume this one is too — and it is
     * not. A selected neighbourhood is projected into the existing `cities` array; there is no
     * `neighborhoods` key in a stored document. Pinning the value here keeps the identifier stable
     * for UI state without endorsing it as somewhere to write.
     */
    public function the_tier_identifier_is_stable(): void
    {
        $this->assertSame('neighborhoods', GeographyTier::Neighborhoods->value);
    }

    /** @test */
    public function the_four_original_tiers_are_unchanged(): void
    {
        $this->assertSame('state', GeographyTier::State->value);
        $this->assertSame('counties', GeographyTier::Counties->value);
        $this->assertSame('cities', GeographyTier::Cities->value);
        $this->assertSame('zip_codes', GeographyTier::ZipCodes->value);

        $this->assertTrue(GeographyTier::State->isRequired());
        $this->assertTrue(GeographyTier::Counties->isRequired());
        $this->assertFalse(GeographyTier::Cities->isRequired());
        $this->assertFalse(GeographyTier::ZipCodes->isRequired());
    }

    // ─────────────────────────────────────────────────────────────────────
    // The rule
    // ─────────────────────────────────────────────────────────────────────

    /** @test */
    public function the_orphan_rule_belongs_to_the_neighbourhood_tier(): void
    {
        $this->assertSame(
            GeographyTier::Neighborhoods,
            GeographyRule::NeighborhoodNotInSelectedCity->defaultTier()
        );
    }

    /** @test */
    public function the_orphan_rule_describes_a_selection_that_is_wrong_not_merely_incomplete(): void
    {
        $this->assertFalse(
            GeographyRule::NeighborhoodNotInSelectedCity->governsCompletenessOnly(),
            'An orphaned neighbourhood is invalid, not incomplete — more picking will not repair it.'
        );
    }

    /** @test */
    public function the_orphan_rule_names_the_city_as_the_parent(): void
    {
        // Containment by CITY, not by county — the distinction the rule exists to express.
        $this->assertStringContainsString(
            'city',
            strtolower(GeographyRule::NeighborhoodNotInSelectedCity->describe())
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // Option identity
    // ─────────────────────────────────────────────────────────────────────

    /** @test */
    public function a_neighbourhood_option_carries_its_city_as_parent(): void
    {
        $option = GeographyOption::neighborhood('900', 'Clearwater Beach', '1212875');

        $this->assertSame(GeographyOption::KIND_NEIGHBORHOOD, $option->kind);
        $this->assertSame('1212875', $option->parentId);
        $this->assertNull($option->code);
    }

    /** @test */
    public function a_neighbourhood_option_may_not_carry_a_state_abbreviation(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new GeographyOption(GeographyOption::KIND_NEIGHBORHOOD, '900', 'Clearwater Beach', null, '100', 'FL');
    }

    /** @test */
    public function a_neighbourhood_option_rejects_an_empty_id_or_name(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        GeographyOption::neighborhood('', 'Clearwater Beach', '100');
    }

    /** @test */
    public function the_option_array_form_exposes_the_new_kind(): void
    {
        $this->assertSame(
            [
                'kind'         => 'neighborhood',
                'id'           => '900',
                'name'         => 'Clearwater Beach',
                'code'         => null,
                'parent_id'    => '1212875',
                'abbreviation' => null,
            ],
            GeographyOption::neighborhood('900', 'Clearwater Beach', '1212875')->toArray()
        );
    }
}
