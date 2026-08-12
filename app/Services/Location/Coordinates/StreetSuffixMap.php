<?php

namespace App\Services\Location\Coordinates;

/**
 * The street-token folding vocabulary shared by every address in this codebase.
 *
 * WHY THIS MOVED OUT OF PropertyAddress
 * -------------------------------------
 * It used to be a 16-entry `static $map` inside `PropertyAddress::normalizeStreet()`,
 * with a comment explaining that it was deliberately small because "a wrong
 * expansion is worse than a missed one". That reasoning was right, and it had a
 * consequence nobody had to live with until now: the map folds long forms to
 * short ones and nothing else, so it only converges a pair when the *long* form
 * is the one it knows.
 *
 * That is fine while both sides of a comparison are humans typing. It stops
 * being fine the moment one side is an imported corpus. A published address
 * point writes `Xing`; a person types `Crossing`; `crossing` was not in the map,
 * so the two never met and the corpus silently failed to answer for that street.
 * The gap was invisible precisely because the normalizer was working correctly
 * on both inputs.
 *
 * So the vocabulary is now the USPS Publication 28 Appendix C1 suffix set, kept
 * in one place where it can be read and extended, rather than a short list
 * embedded in a method. Same folding rule, larger dictionary.
 *
 * SYMMETRY IS THE WHOLE POINT
 * ---------------------------
 * Both the corpus importer and the user-typed address run through the same
 * function. A pair converges when *either* form appears here, so every entry
 * added closes a gap in both directions at once. An entry that maps a token to
 * itself would be a no-op, which is why types whose USPS abbreviation is the
 * word itself — Loop, Run, Way, Path, Pass, Mall, Park, Oval, Row, Spur, Walk —
 * are absent rather than listed. They already agree.
 *
 * POSITION DECIDES WHICH VOCABULARY APPLIES
 * -----------------------------------------
 * The two constants below are not one dictionary wearing two hats, and the
 * distinction is load-bearing. Applying the whole vocabulary to every token —
 * which is what this class did when the map first grew to the full C1 set —
 * turns `4600 Silver Hill Rd` into `4600 silver hl rd`, because `hill` is a
 * USPS suffix word and nothing was asking *where* it stood. `Hill` there is
 * half the street's name; `Rd` is the suffix. Same for `Mountain View Drive`,
 * which became `mtn vw dr`.
 *
 * That was defended as harmless on the grounds that both sides of a comparison
 * fold identically, so a mangled string still matches itself. It does — right
 * up to the first source that does not run through this code. A geocoder query,
 * a published address point spelled the way the county spells it, an operator
 * reading a log line: all of them see `silver hl rd`, and none of them wrote it.
 * Symmetry inside our own process is not the same as being right about the
 * address, and the smaller the map was, the longer that difference stayed
 * invisible.
 *
 * So {@see self::foldDirectional()} applies anywhere in the line — a directional
 * is unambiguous wherever it stands, which is what keeps "N Green Bay Rd" and
 * "North Green Bay Road" one string — while {@see self::foldSuffix()} is applied
 * by the caller to the suffix position only. Deciding which token that is
 * belongs to {@see PropertyAddress::normalizeStreet()}, the single place a
 * street line is parsed.
 *
 * WHAT THIS IS NOT
 * ----------------
 * Not a USPS CASS implementation, and not address validation. It is the
 * vocabulary; it holds no opinion about a line's structure.
 *
 * IDENTITY IMPACT — READ BEFORE ADDING AN ENTRY
 * ---------------------------------------------
 * These strings are property identity. Adding an entry changes
 * {@see PropertyAddress::propertyIdentityLine()} for any address containing that
 * token, so anything already stored keyed on the old value stops matching. Today
 * that is safe: the corpus is empty, and stored coordinates are re-resolved
 * rather than looked up by this string. It will not always be safe — treat an
 * addition as a data migration question, not a dictionary edit.
 */
final class StreetSuffixMap
{
    /**
     * Directional words → their standard abbreviation.
     *
     * Kept separate from the suffix set because they are a closed, unambiguous
     * list, and because a directional can appear on either side of the street
     * name while a suffix type generally cannot.
     */
    public const DIRECTIONALS = [
        'north'     => 'n',
        'south'     => 's',
        'east'      => 'e',
        'west'      => 'w',
        'northeast' => 'ne',
        'northwest' => 'nw',
        'southeast' => 'se',
        'southwest' => 'sw',
    ];

    /**
     * USPS Publication 28 Appendix C1 — street suffix long form → standard
     * abbreviation.
     *
     * Only entries where the abbreviation differs from the word. Common spelling
     * variants are included for the high-frequency types, because those are the
     * ones people actually type differently.
     */
    public const SUFFIXES = [
        'alley'      => 'aly',   'allee'      => 'aly',   'ally'      => 'aly',
        'annex'      => 'anx',
        'arcade'     => 'arc',
        'avenue'     => 'ave',   'aven'       => 'ave',   'avenu'     => 'ave',
        'avn'        => 'ave',   'avnue'      => 'ave',   'av'        => 'ave',
        'bayou'      => 'byu',
        'beach'      => 'bch',
        'bend'       => 'bnd',
        'bluff'      => 'blf',   'bluffs'     => 'blfs',
        'bottom'     => 'btm',
        'boulevard'  => 'blvd',  'boul'       => 'blvd',  'boulv'     => 'blvd',
        'branch'     => 'br',
        'bridge'     => 'brg',
        'brook'      => 'brk',   'brooks'     => 'brks',
        'burg'       => 'bg',    'burgs'      => 'bgs',
        'bypass'     => 'byp',
        'camp'       => 'cp',
        'canyon'     => 'cyn',
        'cape'       => 'cpe',
        'causeway'   => 'cswy',
        'center'     => 'ctr',   'centre'     => 'ctr',   'centers'   => 'ctrs',
        'circle'     => 'cir',   'circles'    => 'cirs',
        'cliff'      => 'clf',   'cliffs'     => 'clfs',
        'club'       => 'clb',
        'common'     => 'cmn',   'commons'    => 'cmns',
        'corner'     => 'cor',   'corners'    => 'cors',
        'course'     => 'crse',
        'court'      => 'ct',    'courts'     => 'cts',
        'cove'       => 'cv',    'coves'      => 'cvs',
        'creek'      => 'crk',
        'crescent'   => 'cres',
        'crest'      => 'crst',
        'crossing'   => 'xing',
        'crossroad'  => 'xrd',   'crossroads' => 'xrds',
        'curve'      => 'curv',
        'dale'       => 'dl',
        'dam'        => 'dm',
        'divide'     => 'dv',
        'drive'      => 'dr',    'drives'     => 'drs',   'driv'      => 'dr',
        'estate'     => 'est',   'estates'    => 'ests',
        'expressway' => 'expy',  'express'    => 'expy',
        'extension'  => 'ext',   'extensions' => 'exts',
        'falls'      => 'fls',
        'ferry'      => 'fry',
        'field'      => 'fld',   'fields'     => 'flds',
        'flat'       => 'flt',   'flats'      => 'flts',
        'ford'       => 'frd',   'fords'      => 'frds',
        'forest'     => 'frst',
        'forge'      => 'frg',   'forges'     => 'frgs',
        'fork'       => 'frk',   'forks'      => 'frks',
        'fort'       => 'ft',
        'freeway'    => 'fwy',
        'garden'     => 'gdn',   'gardens'    => 'gdns',
        'gateway'    => 'gtwy',
        'glen'       => 'gln',   'glens'      => 'glns',
        'green'      => 'grn',   'greens'     => 'grns',
        'grove'      => 'grv',   'groves'     => 'grvs',
        'harbor'     => 'hbr',   'harbour'    => 'hbr',   'harbors'   => 'hbrs',
        'haven'      => 'hvn',
        'heights'    => 'hts',
        'highway'    => 'hwy',   'highwy'     => 'hwy',   'hiway'     => 'hwy',
        'hill'       => 'hl',    'hills'      => 'hls',
        'hollow'     => 'holw',
        'inlet'      => 'inlt',
        'island'     => 'is',    'islands'    => 'iss',
        'junction'   => 'jct',   'junctions'  => 'jcts',
        'key'        => 'ky',    'keys'       => 'kys',
        'knoll'      => 'knl',   'knolls'     => 'knls',
        'lake'       => 'lk',    'lakes'      => 'lks',
        'landing'    => 'lndg',
        'lane'       => 'ln',
        'light'      => 'lgt',   'lights'     => 'lgts',
        'loaf'       => 'lf',
        'lock'       => 'lck',   'locks'      => 'lcks',
        'lodge'      => 'ldg',
        'manor'      => 'mnr',   'manors'     => 'mnrs',
        'meadow'     => 'mdw',   'meadows'    => 'mdws',
        'mill'       => 'ml',    'mills'      => 'mls',
        'mission'    => 'msn',
        'motorway'   => 'mtwy',
        'mount'      => 'mt',
        'mountain'   => 'mtn',   'mountains'  => 'mtns',
        'neck'       => 'nck',
        'orchard'    => 'orch',
        'overpass'   => 'opas',
        'parkway'    => 'pkwy',  'parkways'   => 'pkwy',  'parkwy'    => 'pkwy',
        'passage'    => 'psge',
        'pine'       => 'pne',   'pines'      => 'pnes',
        'place'      => 'pl',
        'plain'      => 'pln',   'plains'     => 'plns',
        'plaza'      => 'plz',
        'point'      => 'pt',    'points'     => 'pts',
        'port'       => 'prt',   'ports'      => 'prts',
        'prairie'    => 'pr',
        'radial'     => 'radl',
        'ranch'      => 'rnch',  'ranches'    => 'rnch',
        'rapid'      => 'rpd',   'rapids'     => 'rpds',
        'rest'       => 'rst',
        'ridge'      => 'rdg',   'ridges'     => 'rdgs',
        'river'      => 'riv',
        'road'       => 'rd',    'roads'      => 'rds',
        'route'      => 'rte',
        'shoal'      => 'shl',   'shoals'     => 'shls',
        'shore'      => 'shr',   'shores'     => 'shrs',
        'skyway'     => 'skwy',
        'spring'     => 'spg',   'springs'    => 'spgs',
        'square'     => 'sq',    'squares'    => 'sqs',
        'station'    => 'sta',
        'stravenue'  => 'stra',
        'stream'     => 'strm',
        'street'     => 'st',    'streets'    => 'sts',   'strt'      => 'st',
        'summit'     => 'smt',
        'terrace'    => 'ter',   'terr'       => 'ter',
        'throughway' => 'trwy',
        'trace'      => 'trce',
        'track'      => 'trak',
        'trafficway' => 'trfy',
        'trail'      => 'trl',   'trails'     => 'trl',
        'trailer'    => 'trlr',
        'tunnel'     => 'tunl',
        'turnpike'   => 'tpke',  'turnpk'     => 'tpke',
        'underpass'  => 'upas',
        'union'      => 'un',    'unions'     => 'uns',
        'valley'     => 'vly',   'valleys'    => 'vlys',
        'viaduct'    => 'via',
        'view'       => 'vw',    'views'      => 'vws',
        'village'    => 'vlg',   'villages'   => 'vlgs',
        'ville'      => 'vl',
        'vista'      => 'vis',
        'well'       => 'wl',    'wells'      => 'wls',
    ];

    /**
     * The whole vocabulary in one table: directionals plus suffixes.
     *
     * Built once per process. Suffixes are applied over directionals so a word
     * appearing in both would resolve to its suffix form; none currently does,
     * and this states which side would win if one ever did.
     *
     * This is the dictionary, not the algorithm. It answers "is this word in the
     * vocabulary, and what does it abbreviate to" — which is what the vocabulary
     * tests assert against. Normalizing a street line goes through
     * {@see PropertyAddress::normalizeStreet()}, which chooses per position which
     * of the two halves to apply.
     *
     * @return array<string, string>
     */
    public static function foldingTable(): array
    {
        static $table = null;

        // `+` keeps the left operand on a key collision, so SUFFIXES first is
        // what makes the sentence above true.
        return $table ??= self::SUFFIXES + self::DIRECTIONALS;
    }

    /**
     * The abbreviation for one already-lowercased token under the *whole*
     * vocabulary, or the token itself.
     *
     * Position-blind by definition. Do not reach for this to normalize a street
     * line — applying it to every token is exactly the bug that made
     * `Silver Hill Rd` read `silver hl rd`. It exists for dictionary-level
     * questions.
     */
    public static function fold(string $token): string
    {
        return self::foldingTable()[$token] ?? $token;
    }

    /**
     * Fold a token as a directional. Safe anywhere in a street line: `north`
     * means north whether it leads the name or trails the suffix.
     */
    public static function foldDirectional(string $token): string
    {
        return self::DIRECTIONALS[$token] ?? $token;
    }

    /**
     * Fold a token as a street-type suffix. Only ever applied to the token the
     * caller has established *is* the suffix.
     */
    public static function foldSuffix(string $token): string
    {
        return self::SUFFIXES[$token] ?? $token;
    }

    /**
     * True when a token is a directional in either its long or short form.
     *
     * Both forms count because this is asked after directional folding, of a
     * line that may have arrived already abbreviated — `123 Main St NW` never
     * spelled "northwest" out to begin with.
     */
    public static function isDirectional(string $token): bool
    {
        static $set = null;

        $set ??= array_flip(array_merge(
            array_keys(self::DIRECTIONALS),
            array_values(self::DIRECTIONALS)
        ));

        return isset($set[$token]);
    }
}
