<?php

namespace Tests\Unit\Services\Location\Coordinates;

use App\Services\Location\Coordinates\PropertyAddress;
use App\Services\Location\Coordinates\StreetSuffixMap;
use Tests\TestCase;

/**
 * The suffix vocabulary expansion, and the behaviour it must not disturb.
 *
 * Three obligations, tested separately because they fail for different reasons:
 * everything the 16-entry map already folded must keep folding to the same
 * string (a regression here silently re-identifies every stored property), the
 * corpus-vs-human gaps the research identified must now close, and — the
 * correction that motivated all of this — a suffix word standing in the street's
 * *name* must be left alone.
 *
 * That third one is not hypothetical. Expanding the vocabulary to the full USPS
 * C1 set while still folding every token turned `4600 Silver Hill Rd` into
 * `4600 silver hl rd`, because C1 contains `hill`. The rest of C1 contains
 * `mountain`, `view`, `forest`, `garden`, `lake` and `center` too, so the
 * failure was not a single unlucky street.
 */
class StreetSuffixNormalizationTest extends TestCase
{
    private function line(string $street): string
    {
        return (new PropertyAddress(
            address: $street,
            city:    'Tampa',
            state:   'FL',
            zip:     '33602',
        ))->coordinateLookupLine();
    }

    /** Street portion only, so the assertions read as the thing under test. */
    private function street(string $street): string
    {
        return trim(str_replace('tampa fl 33602', '', $this->line($street)));
    }

    // ── the old vocabulary still folds exactly as it did ────────────────────

    /** @dataProvider preExistingFolds */
    public function test_the_original_sixteen_entries_are_unchanged(string $input, string $expected): void
    {
        $this->assertSame($expected, $this->street($input));
    }

    public static function preExistingFolds(): array
    {
        return [
            // directionals
            'north'     => ['123 North Main Street',   '123 n main st'],
            'south'     => ['123 South Main St',       '123 s main st'],
            'east'      => ['123 East Main St',        '123 e main st'],
            'west'      => ['123 West Main St',        '123 w main st'],
            'northeast' => ['123 Northeast Main St',   '123 ne main st'],
            'northwest' => ['123 Northwest Main St',   '123 nw main st'],
            'southeast' => ['123 Southeast Main St',   '123 se main st'],
            'southwest' => ['123 Southwest Main St',   '123 sw main st'],
            // the original suffix list
            'street'    => ['123 Main Street',         '123 main st'],
            'avenue'    => ['123 Main Avenue',         '123 main ave'],
            'boulevard' => ['123 Main Boulevard',      '123 main blvd'],
            'road'      => ['123 Main Road',           '123 main rd'],
            'drive'     => ['123 Main Drive',          '123 main dr'],
            'lane'      => ['123 Main Lane',           '123 main ln'],
            'court'     => ['123 Main Court',          '123 main ct'],
            'place'     => ['123 Main Place',          '123 main pl'],
            'terrace'   => ['123 Main Terrace',        '123 main ter'],
            'parkway'   => ['123 Main Parkway',        '123 main pkwy'],
            'circle'    => ['123 Main Circle',         '123 main cir'],
            'highway'   => ['123 Main Highway',        '123 main hwy'],
            'trail'     => ['123 Main Trail',          '123 main trl'],
            'square'    => ['123 Main Square',         '123 main sq'],
        ];
    }

    public function test_the_pre_existing_map_is_still_wholly_contained_in_the_new_one(): void
    {
        // The literal 16 entries this replaced. If an entry is ever dropped or
        // its target changed, every address containing that word silently
        // re-identifies — so the old table is pinned here rather than trusted.
        $original = [
            'north' => 'n', 'south' => 's', 'east' => 'e', 'west' => 'w',
            'northeast' => 'ne', 'northwest' => 'nw',
            'southeast' => 'se', 'southwest' => 'sw',
            'street' => 'st', 'avenue' => 'ave', 'boulevard' => 'blvd',
            'road' => 'rd', 'drive' => 'dr', 'lane' => 'ln', 'court' => 'ct',
            'place' => 'pl', 'terrace' => 'ter', 'parkway' => 'pkwy',
            'circle' => 'cir', 'highway' => 'hwy', 'trail' => 'trl',
            'square' => 'sq',
        ];

        foreach ($original as $long => $short) {
            $this->assertSame($short, StreetSuffixMap::fold($long), "fold('{$long}') changed");
        }
    }

    public function test_non_suffix_words_are_left_alone(): void
    {
        $this->assertSame('123 magnolia blossom way', $this->street('123 Magnolia Blossom Way'));
    }

    public function test_unit_and_identity_behaviour_is_untouched(): void
    {
        $condo = new PropertyAddress(
            address: '123 North Main Street', unitAddress: 'Unit 4A',
            city: 'Tampa', state: 'FL', zip: '33602',
        );

        $this->assertSame('123 n main st tampa fl 33602', $condo->coordinateLookupLine());
        $this->assertSame('123 n main st 4a tampa fl 33602', $condo->propertyIdentityLine());
    }

    // ── the gaps the research identified are closed ─────────────────────────

    /** @dataProvider corpusVersusHumanPairs */
    public function test_a_corpus_abbreviation_and_a_typed_long_form_converge(
        string $corpusForm,
        string $humanForm,
    ): void {
        // The failure this closes: the old map folded long → short only, so a
        // corpus writing "Xing" and a person typing "Crossing" produced two
        // different strings and the rung silently never matched that street.
        $this->assertSame(
            $this->street($corpusForm),
            $this->street($humanForm),
            "'{$corpusForm}' and '{$humanForm}' must normalize to one string"
        );
    }

    public static function corpusVersusHumanPairs(): array
    {
        return [
            'crossing' => ['4820 Bayshore Xing', '4820 Bayshore Crossing'],
            'landing'  => ['12 Harbor Lndg',     '12 Harbor Landing'],
            'ridge'    => ['30 Oak Rdg',         '30 Oak Ridge'],
            'creek'    => ['77 Cypress Crk',     '77 Cypress Creek'],
            'point'    => ['5 Sunset Pt',        '5 Sunset Point'],
            'bend'     => ['9 River Bnd',        '9 River Bend'],
            'cove'     => ['14 Palm Cv',         '14 Palm Cove'],
            'harbor'   => ['21 Safety Hbr',      '21 Safety Harbor'],
            'key'      => ['3 Sunset Ky',        '3 Sunset Key'],
            'island'   => ['8 Treasure Is',      '8 Treasure Island'],
            'beach'    => ['60 Madeira Bch',     '60 Madeira Beach'],
            'springs'  => ['2 Tarpon Spgs',      '2 Tarpon Springs'],
            'heights'  => ['4 Palm Hts',         '4 Palm Heights'],
            'plaza'    => ['6 Center Plz',       '6 Center Plaza'],
            'village'  => ['7 Palm Vlg',         '7 Palm Village'],
            'turnpike' => ['1 Sunshine Tpke',    '1 Sunshine Turnpike'],
        ];
    }

    /** @dataProvider selfAbbreviatingTypes */
    public function test_types_that_are_their_own_abbreviation_already_agree(string $type): void
    {
        // Loop, Run, Way, Path, Pass have no distinct USPS abbreviation, which
        // is why StreetSuffixMap deliberately holds no entry for them. They must
        // still round-trip identically — the absence is by design, not an
        // omission to be "fixed" later with a self-mapping entry.
        $this->assertSame(
            strtolower("15 quail {$type}"),
            $this->street("15 Quail {$type}")
        );

        $this->assertArrayNotHasKey(strtolower($type), StreetSuffixMap::foldingTable());
    }

    public static function selfAbbreviatingTypes(): array
    {
        return [['Loop'], ['Run'], ['Way'], ['Path'], ['Pass'], ['Mall'], ['Park'], ['Row'], ['Walk']];
    }

    // ── only the suffix position is suffix-folded ───────────────────────────

    /** @dataProvider suffixWordsInsideTheName */
    public function test_a_suffix_word_in_the_street_name_is_left_alone(
        string $input,
        string $expected,
    ): void {
        // The regression this pins. Every left-hand side here contains a word
        // that IS in the USPS C1 table, standing where a name stands. Folding it
        // produces a string that is symmetric within our own process and wrong
        // everywhere outside it — the geocoder, the county address file and the
        // log line all say "silver hill rd".
        $this->assertSame($expected, $this->street($input));
    }

    public static function suffixWordsInsideTheName(): array
    {
        return [
            // The address that surfaced this: "Hill" names the street, "Rd" types it.
            'hill'     => ['4600 Silver Hill Rd',       '4600 silver hill rd'],
            'hill+road'=> ['300 Church Hill Road',      '300 church hill rd'],
            'mountain' => ['100 Mountain View Drive',   '100 mountain view dr'],
            'garden'   => ['200 Spring Garden Street',  '200 spring garden st'],
            'lake'     => ['400 Lake View Terrace',     '400 lake view ter'],
            'forest'   => ['500 Forest Lane',           '500 forest ln'],
            'circle'   => ['600 Circle Drive',          '600 circle dr'],
            'center'   => ['700 Center Point Road',     '700 center point rd'],
            'creek'    => ['800 Cypress Creek Boulevard', '800 cypress creek blvd'],
        ];
    }

    /** @dataProvider suffixPositions */
    public function test_the_suffix_slot_is_the_trailing_token_or_the_one_before_a_directional(
        string $input,
        string $expected,
    ): void {
        $this->assertSame($expected, $this->street($input));
    }

    public static function suffixPositions(): array
    {
        return [
            'plain'                  => ['123 Main Street',        '123 main st'],
            'already abbreviated'    => ['123 Main St',            '123 main st'],
            'leading directional'    => ['123 North Main Street',  '123 n main st'],
            'both abbreviated'       => ['123 N Main St',          '123 n main st'],
            // The quadrant form: DC, Minneapolis, much of the Midwest. The
            // suffix sits one place left of the end.
            'trailing directional'   => ['123 North Main Street NW', '123 n main st nw'],
            'trailing dir, abbrev'   => ['123 N Main St NW',       '123 n main st nw'],
            'trailing dir, long'     => ['123 N Main Street Northwest', '123 n main st nw'],
            // A directional trailing a name with no type of its own.
            'no suffix at all'       => ['123 Broadway',           '123 broadway'],
        ];
    }

    public function test_all_four_written_forms_of_one_address_converge(): void
    {
        // The property the whole normalizer exists for, restated against the
        // position rule: however a person or a corpus wrote it, one string.
        $forms = [
            '123 North Main Street',
            '123 N Main Street',
            '123 North Main St',
            '123 n main st',
        ];

        $lines = array_map(fn (string $f): string => $this->street($f), $forms);

        $this->assertSame(['123 n main st'], array_values(array_unique($lines)));
    }

    public function test_a_short_line_never_folds_its_only_name_token(): void
    {
        // "123 N" has no name left in front of the candidate slot. Folding there
        // could only consume the house number or the name itself, so the rule
        // declines rather than guessing.
        $this->assertSame('123 n', $this->street('123 North'));
        $this->assertSame('main', $this->street('Main'));
    }

    public function test_a_corpus_and_a_human_still_converge_with_a_name_side_suffix_word(): void
    {
        // Position-awareness must not reopen the gap it was added alongside: the
        // suffix still folds, it just folds in one place.
        $this->assertSame(
            $this->street('4820 Church Hill Xing'),
            $this->street('4820 Church Hill Crossing'),
        );
    }

    // ── the table itself ────────────────────────────────────────────────────

    public function test_no_entry_maps_a_token_to_itself(): void
    {
        foreach (StreetSuffixMap::foldingTable() as $from => $to) {
            $this->assertNotSame($from, $to, "'{$from}' maps to itself — a no-op entry");
        }
    }

    public function test_folding_is_idempotent(): void
    {
        // An abbreviation must not fold again into something else, or the string
        // would depend on how many times it had been normalized.
        foreach (StreetSuffixMap::foldingTable() as $short) {
            $this->assertSame($short, StreetSuffixMap::fold($short), "'{$short}' folds further");
        }
    }

    public function test_the_table_is_lowercase_throughout(): void
    {
        // normalizeToken() lowercases before folding, so an uppercase key would
        // simply never match and would look like a missing entry.
        foreach (StreetSuffixMap::foldingTable() as $from => $to) {
            $this->assertSame(strtolower($from), $from);
            $this->assertSame(strtolower($to), $to);
        }
    }
}
