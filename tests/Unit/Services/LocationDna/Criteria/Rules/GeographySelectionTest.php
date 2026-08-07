<?php

namespace Tests\Unit\Services\LocationDna\Criteria\Rules;

use App\Services\LocationDna\Criteria\Rules\GeographySelection;
use App\Services\LocationDna\Criteria\Rules\GeographyTier;
use PHPUnit\Framework\TestCase;

/**
 * Phase 1b — the selection DTO's invariants.
 *
 * The theme running through these: the DTO is DUMB on purpose. It applies no cascade rule and
 * repairs no bad input, because both of those belong to classes that can report what they did.
 */
class GeographySelectionTest extends TestCase
{
    public function test_an_empty_selection_holds_nothing(): void
    {
        $selection = GeographySelection::empty();

        $this->assertNull($selection->stateId);
        $this->assertSame([], $selection->countyIds);
        $this->assertSame([], $selection->cityIds);
        $this->assertSame([], $selection->zipCodes);
        $this->assertTrue($selection->isEmpty());
        $this->assertFalse($selection->hasState());
    }

    public function test_every_transition_returns_a_new_instance(): void
    {
        $original = GeographySelection::of('1', ['10']);

        $this->assertNotSame($original, $original->withState('2'));
        $this->assertNotSame($original, $original->withCounties(['11']));
        $this->assertNotSame($original, $original->withCities(['100']));
        $this->assertNotSame($original, $original->withZipCodes(['11001']));
    }

    public function test_the_original_is_unchanged_by_a_transition(): void
    {
        $original = GeographySelection::of('1', ['10'], ['100'], ['11001']);

        $original->withState('2')->withCounties([])->withCities([])->withZipCodes([]);

        $this->assertSame('1', $original->stateId);
        $this->assertSame(['10'], $original->countyIds);
        $this->assertSame(['100'], $original->cityIds);
        $this->assertSame(['11001'], $original->zipCodes);
    }

    /**
     * Changing the state does NOT clear the tiers beneath it.
     *
     * That is the resolver's job. If the DTO did it too, rule C1 would exist in two places and a
     * caller could no longer hold an unresolved selection — which is exactly what a form post is.
     */
    public function test_changing_state_does_not_clear_lower_tiers(): void
    {
        $selection = GeographySelection::of('1', ['10'], ['100'], ['11001'])->withState('2');

        $this->assertSame('2', $selection->stateId);
        $this->assertSame(['10'], $selection->countyIds, 'clearing belongs to the resolver, not here');
        $this->assertSame(['100'], $selection->cityIds);
        $this->assertSame(['11001'], $selection->zipCodes);
    }

    /** Duplicates survive construction so the validator can SEE and report them. */
    public function test_duplicates_are_preserved_not_silently_collapsed(): void
    {
        $selection = GeographySelection::of('1', ['10', '10']);

        $this->assertSame(['10', '10'], $selection->countyIds);
    }

    /** Likewise an unpadded ZIP: reported downstream, never quietly repaired here. */
    public function test_an_unpadded_zip_is_preserved_verbatim(): void
    {
        $selection = GeographySelection::of('1', ['10'], [], ['501']);

        $this->assertSame(['501'], $selection->zipCodes, 'padding happens on the way OUT of the repository');
    }

    public function test_integer_ids_are_normalised_to_strings(): void
    {
        $selection = GeographySelection::of(1, [10, 11], [100], [11001]);

        $this->assertSame('1', $selection->stateId);
        $this->assertSame(['10', '11'], $selection->countyIds);
        $this->assertSame(['100'], $selection->cityIds);
        $this->assertSame(['11001'], $selection->zipCodes);
    }

    public function test_a_blank_state_is_normalised_to_null(): void
    {
        $this->assertNull(GeographySelection::of('')->stateId);
        $this->assertNull(GeographySelection::of('   ')->stateId);
        $this->assertFalse(GeographySelection::of('  ')->hasState());
    }

    public function test_ids_for_returns_the_right_tier(): void
    {
        $selection = GeographySelection::of('1', ['10'], ['100'], ['11001']);

        $this->assertSame(['1'], $selection->idsFor(GeographyTier::State));
        $this->assertSame(['10'], $selection->idsFor(GeographyTier::Counties));
        $this->assertSame(['100'], $selection->idsFor(GeographyTier::Cities));
        $this->assertSame(['11001'], $selection->idsFor(GeographyTier::ZipCodes));
    }

    public function test_ids_for_state_is_empty_when_no_state_is_chosen(): void
    {
        $this->assertSame([], GeographySelection::empty()->idsFor(GeographyTier::State));
    }

    public function test_equality_is_order_insensitive(): void
    {
        $a = GeographySelection::of('1', ['10', '11'], ['100'], ['11001', '11701']);
        $b = GeographySelection::of('1', ['11', '10'], ['100'], ['11701', '11001']);

        $this->assertTrue($a->equals($b));
        $this->assertTrue($b->equals($a));
    }

    public function test_equality_distinguishes_a_different_state(): void
    {
        $this->assertFalse(
            GeographySelection::of('1', ['10'])->equals(GeographySelection::of('2', ['10']))
        );
    }

    public function test_equality_distinguishes_a_different_tier_membership(): void
    {
        $this->assertFalse(
            GeographySelection::of('1', ['10'])->equals(GeographySelection::of('1', ['10', '11']))
        );
    }

    /**
     * Phase 1d-5 — `neighborhoods` joins this array, and it is NOT a storage key.
     *
     * `toArray()` is a DTO dump keyed by tier. What gets persisted is the projector's four-key
     * output, which folds neighbourhoods into `cities`. Asserting the shape here pins the tier
     * vocabulary; it does not describe the stored document. See {@see GeographyTier::Neighborhoods}.
     */
    public function test_to_array_uses_the_canonical_tier_keys(): void
    {
        $selection = GeographySelection::of('1', ['10'], ['100'], ['11001'], ['900']);

        $this->assertSame(
            [
                'state'         => '1',
                'counties'      => ['10'],
                'cities'        => ['100'],
                'neighborhoods' => ['900'],
                'zip_codes'     => ['11001'],
            ],
            $selection->toArray()
        );
    }

    public function test_an_old_four_argument_call_leaves_the_neighbourhood_tier_empty(): void
    {
        // The parameter was appended rather than inserted precisely so this stays true: a caller
        // written before the tier existed must not have its ZIPs land in it.
        $selection = GeographySelection::of('1', ['10'], ['100'], ['11001']);

        $this->assertSame(['11001'], $selection->zipCodes);
        $this->assertSame([], $selection->neighborhoodIds);
    }

    public function test_neighbourhoods_round_trip_through_the_dto(): void
    {
        $selection = GeographySelection::of('1', ['10'], ['100'], [], ['900', '901']);

        $this->assertSame(['900', '901'], $selection->neighborhoodIds);
        $this->assertSame(['900', '901'], $selection->idsFor(GeographyTier::Neighborhoods));
    }

    public function test_with_neighborhoods_replaces_only_that_tier(): void
    {
        $original = GeographySelection::of('1', ['10'], ['100'], ['11001'], ['900']);
        $changed  = $original->withNeighborhoods(['901']);

        $this->assertSame(['901'], $changed->neighborhoodIds);
        $this->assertSame(['900'], $original->neighborhoodIds, 'the DTO is immutable');
        $this->assertSame(['10'], $changed->countyIds);
        $this->assertSame(['100'], $changed->cityIds);
        $this->assertSame(['11001'], $changed->zipCodes);
        $this->assertSame('1', $changed->stateId);
    }

    public function test_every_other_wither_preserves_the_neighbourhood_tier(): void
    {
        $selection = GeographySelection::of('1', ['10'], ['100'], ['11001'], ['900']);

        $this->assertSame(['900'], $selection->withState('2')->neighborhoodIds);
        $this->assertSame(['900'], $selection->withCounties(['11'])->neighborhoodIds);
        $this->assertSame(['900'], $selection->withCities(['101'])->neighborhoodIds);
        $this->assertSame(['900'], $selection->withZipCodes(['11002'])->neighborhoodIds);
    }

    public function test_a_selection_with_only_a_neighbourhood_is_not_empty(): void
    {
        $this->assertFalse(GeographySelection::of(null, [], [], [], ['900'])->isEmpty());
    }

    public function test_equality_distinguishes_neighbourhood_membership(): void
    {
        $a = GeographySelection::of('1', ['10'], ['100'], [], ['900']);
        $b = GeographySelection::of('1', ['10'], ['100'], [], ['901']);

        $this->assertFalse($a->equals($b));
        $this->assertTrue($a->equals(GeographySelection::of('1', ['10'], ['100'], [], ['900'])));
    }

    public function test_equality_ignores_neighbourhood_order(): void
    {
        $a = GeographySelection::of('1', ['10'], [], [], ['900', '901']);
        $b = GeographySelection::of('1', ['10'], [], [], ['901', '900']);

        $this->assertTrue($a->equals($b));
    }

    public function test_a_selection_with_only_a_state_is_not_empty(): void
    {
        $this->assertFalse(GeographySelection::of('1')->isEmpty());
    }
}
