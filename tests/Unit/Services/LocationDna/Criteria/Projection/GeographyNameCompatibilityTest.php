<?php

namespace Tests\Unit\Services\LocationDna\Criteria\Projection;

use App\Services\LocationDna\Criteria\Projection\GeographyNameCompatibility;
use PHPUnit\Framework\TestCase;

/**
 * Phase 1d-3 — the normaliser, on its own.
 *
 * WHY THE PRIMITIVES ARE TESTED SEPARATELY FROM THE HYDRATOR
 * ----------------------------------------------------------
 * {@see GeographyCompatibilityHydrationTest} proves the rungs are wired in the right order and that
 * a stored blob comes out the way it should. It cannot prove much about the reduction itself: an
 * end-to-end assertion says "these two names met", not "they met for the reason intended". Both
 * matter here, because a reduction that is too aggressive matches the wrong county and a reduction
 * that is too timid quietly does nothing at all — and end-to-end both look like a passing suite.
 *
 * The load-bearing assertions are therefore the NEGATIVE ones. Anything can be made to match if the
 * key is reduced far enough; the value of this layer is entirely in what it still refuses.
 */
class GeographyNameCompatibilityTest extends TestCase
{
    private GeographyNameCompatibility $compatibility;

    protected function setUp(): void
    {
        parent::setUp();

        $this->compatibility = new GeographyNameCompatibility();
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 1 · ACCENTS FOLD
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * @dataProvider accentedNames
     */
    public function test_an_accented_name_reduces_to_its_unaccented_spelling(
        string $accented,
        string $plain,
    ): void {
        $this->assertSame(
            $this->compatibility->countyKey($plain),
            $this->compatibility->countyKey($accented),
            "`{$accented}` and `{$plain}` are the same place written two ways."
        );
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function accentedNames(): array
    {
        return [
            'Puerto Rico, acute'  => ['Bayamón', 'Bayamon'],
            'Puerto Rico, tilde'  => ['Añasco', 'Anasco'],
            'California, tilde'   => ['La Cañada', 'La Canada'],
            'New Mexico, tilde'   => ['Doña Ana County', 'Dona Ana County'],
            'accent plus class'   => ['Bayamón Municipio', 'Bayamon County'],
        ];
    }

    /** Folding is one-way and total: the reduced form contains no accented character. */
    public function test_a_reduced_key_is_plain_ascii(): void
    {
        $this->assertSame('bayamon', $this->compatibility->countyKey('Bayamón Municipio'));
        $this->assertSame('anasco', $this->compatibility->countyKey('Añasco Municipio'));
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 2 · SAINT AND ST
    // ═════════════════════════════════════════════════════════════════════════

    /** The three spellings the stored data actually contains all converge. */
    public function test_saint_st_and_st_dot_converge(): void
    {
        $expected = $this->compatibility->placeKey('St. Petersburg');

        $this->assertSame($expected, $this->compatibility->placeKey('Saint Petersburg'));
        $this->assertSame($expected, $this->compatibility->placeKey('St Petersburg'));
        $this->assertSame($expected, $this->compatibility->placeKey('SAINT PETERSBURG'));
    }

    /** `Sainte` is handled before `Saint`, so it does not become `st` plus a stray letter. */
    public function test_sainte_reduces_to_ste_rather_than_st(): void
    {
        $this->assertSame(
            $this->compatibility->placeKey('Ste. Genevieve'),
            $this->compatibility->placeKey('Sainte Genevieve')
        );
        $this->assertNotSame(
            $this->compatibility->placeKey('St. Genevieve'),
            $this->compatibility->placeKey('Sainte Genevieve')
        );
    }

    /**
     * The replacement is whole-word.
     *
     * A name that merely contains the letters is not a saint, and rewriting it would invent a place.
     */
    public function test_a_name_merely_containing_the_letters_is_untouched(): void
    {
        $this->assertSame('saintly', $this->compatibility->placeKey('Saintly'));
        $this->assertSame('toussaint', $this->compatibility->placeKey('Toussaint'));
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 3 · SPACING
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * @dataProvider spacingVariants
     */
    public function test_spacing_stops_mattering(string $fused, string $spaced): void
    {
        $this->assertSame(
            $this->compatibility->countyKey($spaced),
            $this->compatibility->countyKey($fused)
        );
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function spacingVariants(): array
    {
        return [
            'DeKalb'  => ['DeKalb County', 'De Kalb County'],
            'LaSalle' => ['LaSalle Parish', 'La Salle Parish'],
            'LaPorte' => ['LaPorte County', 'La Porte County'],
            'padding' => ['  DeKalb   County  ', 'De Kalb County'],
        ];
    }

    /**
     * Spaces are REMOVED rather than inserted, and that is the only rule available here.
     *
     * By the time a name reaches this class it has been lowercased, so the capital that told a
     * reader where `DeKalb` splits is gone. A splitting rule would have to guess where the seam is;
     * removal needs no seam and is symmetric.
     */
    public function test_the_reduction_removes_spaces_rather_than_guessing_where_to_insert_them(): void
    {
        $this->assertSame('dekalb', $this->compatibility->countyKey('De Kalb County'));
        $this->assertSame('dekalb', $this->compatibility->countyKey('DeKalb County'));
    }

    /** Punctuation is neutral too — hyphens, periods and apostrophes all disappear. */
    public function test_punctuation_is_neutral(): void
    {
        $this->assertSame(
            $this->compatibility->countyKey('Winston Salem'),
            $this->compatibility->countyKey('Winston-Salem')
        );
        $this->assertSame(
            $this->compatibility->countyKey('OBrien County'),
            $this->compatibility->countyKey("O'Brien County")
        );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 4 · GEOGRAPHY CLASSES
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Every class in the vocabulary reduces to the bare name, so any two of them meet.
     *
     * This is the rung that was missing. `Adjuntas County` is what the old editor stored; `Adjuntas
     * Municipio` is what the Census publishes; neither is a mistake, and until now neither could
     * find the other.
     *
     * @dataProvider classWords
     */
    public function test_a_geography_class_word_is_removed(string $spelled): void
    {
        $this->assertSame('adjuntas', $this->compatibility->countyKey($spelled));
    }

    /** @return array<string, array{0: string}> */
    public static function classWords(): array
    {
        return [
            'County'           => ['Adjuntas County'],
            'Municipio'        => ['Adjuntas Municipio'],
            'Municipality'     => ['Adjuntas Municipality'],
            'Parish'           => ['Adjuntas Parish'],
            'Borough'          => ['Adjuntas Borough'],
            'Census Area'      => ['Adjuntas Census Area'],
            'District'         => ['Adjuntas District'],
            'Island'           => ['Adjuntas Island'],
            'City'             => ['Adjuntas City'],
            'City and Borough' => ['Adjuntas City and Borough'],
            'no class at all'  => ['Adjuntas'],
        ];
    }

    /**
     * A multi-word class is removed whole.
     *
     * Tried longest-first, or `Sitka City and Borough` would lose only its `Borough` and reduce to
     * `sitkacityand`.
     */
    public function test_a_multi_word_class_is_removed_whole(): void
    {
        $this->assertSame('sitka', $this->compatibility->countyKey('Sitka City and Borough'));
        $this->assertSame(
            'princeofwaleshyder',
            $this->compatibility->countyKey('Prince of Wales-Hyder Census Area')
        );
    }

    /**
     * Exactly one class word is removed, and the removal never recurses.
     *
     * `Island County` is a real county in Washington. A second pass would eat the name itself and
     * leave an empty key, which is both wrong and — since an empty key is refused at lookup —
     * silently unmatched.
     */
    public function test_only_one_class_word_is_removed(): void
    {
        $this->assertSame('island', $this->compatibility->countyKey('Island County'));
        $this->assertSame('city', $this->compatibility->countyKey('City County'));
    }

    /**
     * A PLACE key removes no class word at all.
     *
     * City names legitimately end in these words. Stripping one would reduce `Kansas City` to
     * `kansas`, which is a state, and `Ocean City` to `ocean`, which is nothing.
     */
    public function test_a_place_key_keeps_its_class_word(): void
    {
        $this->assertSame('kansascity', $this->compatibility->placeKey('Kansas City'));
        $this->assertSame('oceancity', $this->compatibility->placeKey('Ocean City'));
        $this->assertNotSame(
            $this->compatibility->placeKey('Kansas City'),
            $this->compatibility->placeKey('Kansas')
        );
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 5 · IT NEVER GUESSES
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Names that are merely SIMILAR stay distinct.
     *
     * There is no edit distance, no prefix rule and no phonetic key here, and these assertions are
     * what stops one being added later without anyone noticing.
     *
     * @dataProvider distinctNames
     */
    public function test_similar_names_do_not_converge(string $left, string $right): void
    {
        $this->assertNotSame(
            $this->compatibility->countyKey($right),
            $this->compatibility->countyKey($left),
            "`{$left}` and `{$right}` are different places and must stay different keys."
        );
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function distinctNames(): array
    {
        return [
            'prefix'      => ['Pinellas County', 'Pinellas Park County'],
            'one letter'  => ['Adams County', 'Addams County'],
            'plural'      => ['Adjuntas County', 'Adjunta County'],
            'transposed'  => ['Sitka Borough', 'Skita Borough'],
        ];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 6 · THE COLLISION RULE
    // ═════════════════════════════════════════════════════════════════════════

    /** A key nothing has claimed maps to the id that claims it. */
    public function test_a_fresh_key_maps_to_its_id(): void
    {
        $index = [];

        $this->compatibility->register($index, 'lasalle', '17099');

        $this->assertSame('17099', $this->compatibility->lookup($index, 'lasalle'));
    }

    /**
     * Two DIFFERENT ids on one key resolve to nothing. The first arrival is not preferred.
     *
     * This is the whole safety argument for the rung. `La Salle County` and `LaSalle County` reduce
     * identically; picking whichever the corpus happened to enumerate first would attach a listing
     * to a county in another part of the state, with nothing anywhere to show it happened.
     */
    public function test_a_collision_resolves_to_nothing_rather_than_to_the_first_arrival(): void
    {
        $index = [];

        $this->compatibility->register($index, 'lasalle', '17099');
        $this->compatibility->register($index, 'lasalle', '48283');

        $this->assertNull($this->compatibility->lookup($index, 'lasalle'));
    }

    /** A third arrival cannot revive a collided key. */
    public function test_a_collided_key_stays_collided(): void
    {
        $index = [];

        foreach (['17099', '48283', '22059'] as $id) {
            $this->compatibility->register($index, 'lasalle', $id);
        }

        $this->assertNull($this->compatibility->lookup($index, 'lasalle'));
    }

    /**
     * The SAME id twice is not a collision.
     *
     * The Census repository emits a place once per county it spans, so a city straddling two
     * selected counties arrives here twice carrying one id. Reading that as ambiguity would refuse
     * to match a place that is not ambiguous — the exact failure the many-to-many corpus fixes.
     */
    public function test_the_same_id_registered_twice_is_not_a_collision(): void
    {
        $index = [];

        $this->compatibility->register($index, 'kansascity', '2938000');
        $this->compatibility->register($index, 'kansascity', '2938000');

        $this->assertSame('2938000', $this->compatibility->lookup($index, 'kansascity'));
    }

    /** An empty key is never registered and never resolves — degenerate names must not meet. */
    public function test_an_empty_key_is_refused_at_both_ends(): void
    {
        $index = [];

        $this->compatibility->register($index, '', '17099');

        $this->assertSame([], $index);
        $this->assertNull($this->compatibility->lookup($index, ''));
    }

    /** An unknown key resolves to nothing, which is the same answer a collision gives. */
    public function test_an_unknown_key_resolves_to_nothing(): void
    {
        $this->assertNull($this->compatibility->lookup(['lasalle' => '17099'], 'dekalb'));
    }
}
