<?php

namespace Tests\Unit\Services\LocationDna\Places;

use App\Services\LocationDna\Criteria\FakeCriteriaGeographyRepository;
use App\Services\LocationDna\Criteria\Projection\GeographySelectionHydrator;
use App\Services\LocationDna\Places\PlaceNameKey;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Phase 1d-3 — the guard that keeps two normalisations from drifting apart.
 *
 * WHY THIS TEST EXISTS INSTEAD OF A REFACTOR
 * ------------------------------------------
 * {@see GeographySelectionHydrator} normalises a stored label before matching it against the
 * corpus. {@see PlaceNameKey} normalises a place name before storing it as `location_places.name_key`.
 * They must produce the SAME string for the same input, or a label the cascade resolves will fail
 * to resolve in the supplemental layer — and that failure would look like missing geography rather
 * than like a normalisation bug, which is the expensive kind of wrong.
 *
 * The obvious fix is to make the hydrator call the new class. It is deliberately not done: the
 * hydrator is on the live Hire Buyer path, it is covered by its own characterisation tests, and
 * editing working matching code to share a helper is a change with real downside and no
 * user-visible upside. Pinning the invariant by test buys the same safety for none of the risk —
 * if anyone changes either normalisation, this fails loudly and immediately.
 *
 * Reflection is used on purpose. The hydrator's `key()` is private because nothing outside it
 * should call it, and that remains true — a test asserting an internal invariant is not the same
 * thing as widening an API for production callers.
 */
class PlaceNameKeyParityTest extends TestCase
{
    private ReflectionMethod $hydratorKey;

    private GeographySelectionHydrator $hydrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hydrator    = new GeographySelectionHydrator(new FakeCriteriaGeographyRepository());
        $this->hydratorKey = new ReflectionMethod(GeographySelectionHydrator::class, 'key');
        $this->hydratorKey->setAccessible(true);
    }

    private function hydratorKeyOf(string $value): string
    {
        return (string) $this->hydratorKey->invoke($this->hydrator, $value);
    }

    /**
     * @test
     *
     * The awkward names are the point. Anything would agree on "Clearwater"; these are the inputs
     * where a difference of one character changes which place a stored label means.
     */
    public function the_two_normalisations_agree_across_the_corpus_awkward_names(): void
    {
        $names = [
            // The Saint/St. disagreement between the two corpora.
            'St. Petersburg', 'Saint Petersburg', 'St Petersburg', 'ST. PETERSBURG',
            'St. Pete Beach', 'Saint Pete Beach',
            // Deliberately NOT folded — different places, or no word boundary.
            'Sainte Genevieve', 'Ste. Genevieve', 'Stevensville', 'Staten Island',
            // Mid-name Saint is left alone by both.
            'Port Saint Joe', 'Mount Saint Francis',
            // Whitespace, case and padding.
            '  Clearwater   Beach  ', 'clearwater beach', 'CLEARWATER BEACH',
            // Punctuation the corpus really contains.
            "Clark's Point", 'Opa-locka', 'McGrath', 'Mc Grath', 'Winston-Salem',
            // County forms.
            'Pinellas County', 'Orleans Parish', 'Nome Census Area', 'Fairfax city',
            // Degenerate input.
            '', '   ', 'A',
        ];

        foreach ($names as $name) {
            $this->assertSame(
                $this->hydratorKeyOf($name),
                PlaceNameKey::of($name),
                "Normalisation drifted for: '{$name}'"
            );
        }
    }

    /** @test */
    public function the_state_suffix_strip_agrees_with_the_hydrators(): void
    {
        $strip = new ReflectionMethod(GeographySelectionHydrator::class, 'stripStateSuffix');
        $strip->setAccessible(true);

        $labels = [
            'Clearwater Beach, FL', 'Pinellas County, FL', 'St. Petersburg,FL',
            'Clearwater Beach , fl ', 'Clearwater Beach', 'Washington, DC',
            // No suffix to strip — a three-letter tail is not a state code.
            'Lake Mary, USA', '', 'FL',
        ];

        foreach ($labels as $label) {
            $this->assertSame(
                (string) $strip->invoke($this->hydrator, $label),
                PlaceNameKey::stripStateSuffix($label),
                "Suffix strip drifted for: '{$label}'"
            );
        }
    }

    /** @test */
    public function the_leading_saint_fold_is_prefix_only_and_whole_word(): void
    {
        $this->assertSame('st petersburg', PlaceNameKey::of('Saint Petersburg'));
        $this->assertSame('st petersburg', PlaceNameKey::of('St. Petersburg'));
        $this->assertSame('st petersburg', PlaceNameKey::of('St Petersburg'));

        // Not folded: no word boundary, or a distinct French feminine form, or mid-name.
        $this->assertSame('stevensville', PlaceNameKey::of('Stevensville'));
        $this->assertSame('sainte genevieve', PlaceNameKey::of('Sainte Genevieve'));
        $this->assertSame('port saint joe', PlaceNameKey::of('Port Saint Joe'));
    }
}
