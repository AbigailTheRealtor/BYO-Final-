<?php

namespace Tests\Unit\Services\LocationDna\Criteria\Projection;

use App\Services\LocationDna\Criteria\FakeCriteriaGeographyRepository;
use App\Services\LocationDna\Criteria\Projection\GeographySelectionHydrator;
use PHPUnit\Framework\TestCase;

/**
 * Phase 1d-3 — the compatibility rungs, through the hydrator that uses them.
 *
 * WHAT THIS PHASE WAS FOR
 * -----------------------
 * Published Census geography spells places the way their own governments do. The labels already
 * stored in production were written by an autocomplete that did not. `Adjuntas Municipio` is stored
 * as `Adjuntas County`; `Bayamón` is stored as `Bayamon`; `DeKalb County` is stored as
 * `De Kalb County`. Every one of those is the same place, and every one of them failed to match —
 * so it was preserved as a label the user could see but the cascade could not act on.
 *
 * Nothing here enables the Census source. The corpus in these tests is a fixture that SPELLS names
 * the way the Census does, which is the only part of the switch this phase has to be ready for.
 *
 * THE ORDER IS THE POINT
 * ----------------------
 * Four rungs, each consulted only when the one above found nothing:
 *
 *   1. exact  →  2. deterministic normalisation  →  3. explicit alias  →  4. preserve verbatim
 *
 * {@see GeographySelectionHydratorTest} is the standing proof that rung 1 and rung 4 still behave
 * exactly as Phase 1c shipped them; it was not modified by this phase and passing it unchanged is
 * half of this phase's safety argument. This suite covers the two rungs added in between, and the
 * cases where they must decline to answer.
 *
 * NO DATABASE. The fake repository stands in for the corpus.
 */
class GeographyCompatibilityHydrationTest extends TestCase
{
    /**
     * A corpus spelled the way published Census geography spells things.
     *
     * The Illinois entries are the ambiguity fixture: `LaSalle County` and `La Salle County` are
     * distinct exact names that reduce to one compatibility key, which is what makes them a
     * collision rather than a duplicate.
     */
    private function corpus(): FakeCriteriaGeographyRepository
    {
        return (new FakeCriteriaGeographyRepository())
            // ── Puerto Rico: accents, and the municipio/county class gap ────────
            ->withState('72', 'Puerto Rico')
            ->withCounty('72021', 'Bayamón Municipio', '72')
            ->withCounty('72001', 'Adjuntas Municipio', '72')
            ->withCounty('72011', 'Añasco Municipio', '72')
            // ── Georgia and Louisiana: fused versus spaced ──────────────────────
            ->withState('13', 'Georgia')
            ->withCounty('13089', 'DeKalb County', '13')
            ->withState('22', 'Louisiana')
            ->withCounty('22059', 'LaSalle Parish', '22')
            // ── Florida: saint forms, an alias target, and ZIPs ─────────────────
            ->withState('12', 'Florida')
            ->withCounty('12103', 'Pinellas County', '12')
            ->withCity('1245025', 'St. Petersburg', '12103')
            ->withCity('1212925', 'Clearwater', '12103')
            ->withZip('33708', '12103')
            // ── California: the alias target with two neighbourhood aliases ─────
            ->withState('06', 'California')
            ->withCounty('06037', 'Los Angeles County', '06')
            ->withCity('0644000', 'Los Angeles', '06037')
            // ── Missouri: one place enumerated under two counties ───────────────
            ->withState('29', 'Missouri')
            ->withCounty('29095', 'Jackson County', '29')
            ->withCounty('29047', 'Clay County', '29')
            ->withCity('2938000', 'Kansas City', '29095')
            ->withCity('2938000', 'Kansas City', '29047')
            // ── Illinois: two names, one compatibility key ──────────────────────
            ->withState('17', 'Illinois')
            ->withCounty('17099', 'LaSalle County', '17')
            ->withCounty('17133', 'La Salle County', '17');
    }

    private function hydrate(array $blob): object
    {
        return (new GeographySelectionHydrator($this->corpus()))->fromLabels($blob);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 1 · ACCENT FOLDING
    // ═════════════════════════════════════════════════════════════════════════

    /** `Bayamon County` finds `Bayamón Municipio` — an accent AND a class word apart. */
    public function test_an_unaccented_stored_county_finds_its_accented_corpus_name(): void
    {
        $result = $this->hydrate([
            'state'    => 'Puerto Rico',
            'counties' => ['Bayamon County, PR'],
        ]);

        $this->assertSame(['72021'], $result->selection->countyIds);
        $this->assertTrue($result->preserved->isEmpty());
    }

    /** And the other direction: a stored label that kept its accent still matches. */
    public function test_an_accented_stored_county_still_matches(): void
    {
        $result = $this->hydrate([
            'state'    => 'Puerto Rico',
            'counties' => ['Añasco County, PR'],
        ]);

        $this->assertSame(['72011'], $result->selection->countyIds);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 2 · SAINT AND ST
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * @dataProvider saintSpellings
     */
    public function test_every_stored_saint_spelling_finds_the_corpus_city(string $stored): void
    {
        $result = $this->hydrate([
            'state'    => 'Florida',
            'counties' => ['Pinellas County, FL'],
            'cities'   => [$stored],
        ]);

        $this->assertSame(['1245025'], $result->selection->cityIds, "`{$stored}` must resolve.");
        $this->assertTrue($result->preserved->isEmpty());
    }

    /** @return array<string, array{0: string}> */
    public static function saintSpellings(): array
    {
        return [
            'exact, as the corpus spells it' => ['St. Petersburg, FL'],
            'abbreviated without the period' => ['St Petersburg, FL'],
            'written out'                    => ['Saint Petersburg, FL'],
            'written out, no suffix'         => ['Saint Petersburg'],
        ];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 3 · SPACING
    // ═════════════════════════════════════════════════════════════════════════

    /** `De Kalb County` finds `DeKalb County`. */
    public function test_a_spaced_stored_county_finds_the_fused_corpus_name(): void
    {
        $result = $this->hydrate([
            'state'    => 'Georgia',
            'counties' => ['De Kalb County, GA'],
        ]);

        $this->assertSame(['13089'], $result->selection->countyIds);
        $this->assertTrue($result->preserved->isEmpty());
    }

    /** `La Salle Parish` finds `LaSalle Parish`, class word and all. */
    public function test_a_spaced_stored_parish_finds_the_fused_corpus_name(): void
    {
        $result = $this->hydrate([
            'state'    => 'Louisiana',
            'counties' => ['La Salle Parish, LA'],
        ]);

        $this->assertSame(['22059'], $result->selection->countyIds);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 4 · THE MISSING COUNTY RUNG — `Adjuntas County` ⇄ `Adjuntas Municipio`
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * The whole resolution path, not just the class list.
     *
     * The stored label is `Adjuntas County`, the corpus publishes `Adjuntas Municipio`, and the two
     * meet only because BOTH sides have their class word removed before either is compared. Adding
     * `Municipio` to the hydrator's existing suffix list would not have done this: that list strips
     * the suffix from the STORED label and looks the remainder up among corpus names that still
     * carry theirs, so `adjuntas` would have been searched for among `adjuntas municipio` and found
     * nothing.
     */
    public function test_a_stored_county_resolves_to_the_published_municipio(): void
    {
        $result = $this->hydrate([
            'state'    => 'Puerto Rico',
            'counties' => ['Adjuntas County, PR'],
        ]);

        $this->assertSame(['72001'], $result->selection->countyIds);
        $this->assertTrue($result->preserved->isEmpty());
    }

    /** The bare name resolves too, from either side of the class gap. */
    public function test_a_stored_county_without_any_class_word_resolves(): void
    {
        $result = $this->hydrate([
            'state'    => 'Puerto Rico',
            'counties' => ['Adjuntas, PR'],
        ]);

        $this->assertSame(['72001'], $result->selection->countyIds);
    }

    /** Cities and ZIPs below a county matched at rung 2 are enumerable, not orphaned. */
    public function test_the_tiers_below_a_compatibility_matched_county_still_resolve(): void
    {
        $result = $this->hydrate([
            'state'     => 'Florida',
            'counties'  => ['Pinellas, FL'],
            'cities'    => ['Saint Petersburg, FL'],
            'zip_codes' => ['33708'],
        ]);

        $this->assertSame(['12103'], $result->selection->countyIds);
        $this->assertSame(['1245025'], $result->selection->cityIds);
        $this->assertSame(['33708'], $result->selection->zipCodes);
        $this->assertTrue($result->preserved->isEmpty());
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 5 · EXPLICIT ALIASES
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * @dataProvider aliasedCities
     */
    public function test_an_aliased_neighbourhood_resolves_to_its_place(
        string $state,
        string $county,
        string $stored,
        string $expectedId,
    ): void {
        $result = $this->hydrate([
            'state'    => $state,
            'counties' => [$county],
            'cities'   => [$stored],
        ]);

        $this->assertSame([$expectedId], $result->selection->cityIds);
        $this->assertTrue($result->preserved->isEmpty());
    }

    /** @return array<string, array{0: string, 1: string, 2: string, 3: string}> */
    public static function aliasedCities(): array
    {
        return [
            'North Hollywood'  => ['California', 'Los Angeles County, CA', 'North Hollywood, CA', '0644000'],
            'Sherman Oaks'     => ['California', 'Los Angeles County, CA', 'Sherman Oaks, CA', '0644000'],
            'Clearwater Beach' => ['Florida', 'Pinellas County, FL', 'Clearwater Beach, FL', '1212925'],
        ];
    }

    /**
     * An alias is a redirection, not a promise that the destination exists.
     *
     * `Clearwater Beach` aliases to `Clearwater`, which is in Pinellas. Hydrate it under a county
     * that does not enumerate Clearwater and the alias resolves to nothing — the label is preserved
     * rather than attached to a city outside the selection.
     */
    public function test_an_alias_whose_target_is_not_enumerated_preserves_the_label(): void
    {
        $result = $this->hydrate([
            'state'    => 'California',
            'counties' => ['Los Angeles County, CA'],
            'cities'   => ['Clearwater Beach, CA'],
        ]);

        $this->assertSame([], $result->selection->cityIds);
        $this->assertSame(['Clearwater Beach, CA'], $result->preserved->cities);
    }

    /** A neighbourhood with no alias entry is preserved. Nothing is inferred from the name. */
    public function test_an_unlisted_neighbourhood_is_preserved_not_inferred(): void
    {
        $result = $this->hydrate([
            'state'    => 'California',
            'counties' => ['Los Angeles County, CA'],
            'cities'   => ['Silver Lake, CA'],
        ]);

        $this->assertSame([], $result->selection->cityIds);
        $this->assertSame(['Silver Lake, CA'], $result->preserved->cities);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 6 · AMBIGUITY RESOLVES TO NOTHING
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Two corpus counties reduce to one key, so a label that reaches only that key matches neither.
     *
     * `La-Salle County` is exactly none of the stored spellings and exactly the shape a hand-typed
     * value takes. It reaches rung 2, finds `lasalle` claimed by both Illinois entries, and is
     * preserved. Selecting the first would attach the listing to a county the user never chose.
     */
    public function test_an_ambiguous_normalised_county_matches_nothing_and_is_preserved(): void
    {
        $result = $this->hydrate([
            'state'    => 'Illinois',
            'counties' => ['La-Salle County, IL'],
        ]);

        $this->assertSame([], $result->selection->countyIds);
        $this->assertSame(['La-Salle County, IL'], $result->preserved->counties);
    }

    /** The exact rung is unaffected by the collision below it — a precise label still resolves. */
    public function test_an_exact_label_still_resolves_despite_the_collision_beneath_it(): void
    {
        $result = $this->hydrate([
            'state'    => 'Illinois',
            'counties' => ['LaSalle County, IL', 'La Salle County, IL'],
        ]);

        $this->assertSame(['17099', '17133'], $result->selection->countyIds);
        $this->assertTrue($result->preserved->isEmpty());
    }

    /**
     * One place enumerated under two counties is NOT a collision.
     *
     * The Census corpus emits a place once per county it spans. Reading the repeat as ambiguity
     * would refuse to match a place that is not ambiguous at all.
     */
    public function test_a_place_spanning_two_counties_is_not_treated_as_ambiguous(): void
    {
        $result = $this->hydrate([
            'state'    => 'Missouri',
            'counties' => ['Jackson County, MO', 'Clay County, MO'],
            'cities'   => ['KansasCity, MO'],
        ]);

        $this->assertSame(['2938000'], $result->selection->cityIds);
        $this->assertTrue($result->preserved->isEmpty());
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 7 · UNKNOWN LEGACY VALUES ARE STILL PRESERVED
    // ═════════════════════════════════════════════════════════════════════════

    /** The rung that mattered before this phase still matters, and still runs last. */
    public function test_an_unknown_county_is_preserved_verbatim(): void
    {
        $result = $this->hydrate([
            'state'    => 'Florida',
            'counties' => ['Pinellas County, FL', 'Ye Olde County, FL'],
        ]);

        $this->assertSame(['12103'], $result->selection->countyIds);
        $this->assertSame(['Ye Olde County, FL'], $result->preserved->counties);
    }

    /**
     * A name that merely RESEMBLES a corpus entry is preserved, not matched.
     *
     * `Pinellas Park` is a real, different place. Nothing in this phase is allowed to reach it from
     * `Pinellas`, and nothing is allowed to reach `Pinellas` from it.
     */
    public function test_a_similar_but_different_county_is_preserved(): void
    {
        $result = $this->hydrate([
            'state'    => 'Florida',
            'counties' => ['Pinellas Park County, FL'],
        ]);

        $this->assertSame([], $result->selection->countyIds);
        $this->assertSame(['Pinellas Park County, FL'], $result->preserved->counties);
    }

    /** An unmatched county still preserves everything hanging below it. */
    public function test_the_tiers_below_an_unmatched_county_are_preserved(): void
    {
        $result = $this->hydrate([
            'state'     => 'Florida',
            'counties'  => ['Ye Olde County, FL'],
            'cities'    => ['Saint Petersburg, FL'],
            'zip_codes' => ['33708'],
        ]);

        $this->assertSame([], $result->selection->countyIds);
        $this->assertSame([], $result->selection->cityIds);
        $this->assertSame([], $result->selection->zipCodes);
        $this->assertSame(['Saint Petersburg, FL'], $result->preserved->cities);
        $this->assertSame(['33708'], $result->preserved->zipCodes);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 8 · ZIPS ARE UNTOUCHED BY ANY OF THIS
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * A ZIP with no counterpart in the corpus is preserved, NOT mapped to a nearby one.
     *
     * 33758 is a PO-box ZIP in Clearwater. The ZCTA that surrounds it is a different geography that
     * happens to contain the building, so mapping one onto the other would rewrite a stored value
     * into one the user never entered. The Census ZCTA is authoritative where it matches; where it
     * does not, the legacy ZIP survives untouched.
     */
    public function test_a_po_box_zip_is_preserved_rather_than_mapped_to_a_zcta(): void
    {
        $result = $this->hydrate([
            'state'     => 'Florida',
            'counties'  => ['Pinellas County, FL'],
            'zip_codes' => ['33708', '33758'],
        ]);

        $this->assertSame(['33708'], $result->selection->zipCodes);
        $this->assertSame(['33758'], $result->preserved->zipCodes);
    }

    /** Nothing about a ZIP is normalised beyond the padding Phase 1c already did. */
    public function test_a_non_numeric_zip_is_still_preserved_rather_than_repaired(): void
    {
        $result = $this->hydrate([
            'state'     => 'Florida',
            'counties'  => ['Pinellas County, FL'],
            'zip_codes' => ['33708', '33708-1234', 'FL 33708'],
        ]);

        $this->assertSame(['33708'], $result->selection->zipCodes);
        $this->assertSame(['33708-1234', 'FL 33708'], $result->preserved->zipCodes);
    }
}
