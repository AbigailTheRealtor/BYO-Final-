<?php

namespace App\Services\LocationDna\Criteria\Projection;

/**
 * Phase 1d-3 — the deterministic compatibility form of a place name.
 *
 * WHAT THIS IS FOR, AND WHY IT IS A SEPARATE CLASS
 * ------------------------------------------------
 * {@see GeographySelectionHydrator} already normalises a stored label before comparing it to the
 * corpus: it lowercases, collapses whitespace, drops a `, ST` suffix and tolerates a missing county
 * class word. That is enough for the reference tables, whose names are ASCII and whose class words
 * are the four this codebase has always seen.
 *
 * It is NOT enough for published Census geography, which the source switch will eventually serve.
 * The corpus there spells `Bayamón Municipio`, `Prince of Wales-Hyder Census Area` and `DeKalb
 * County`, while the labels already stored in production say `Bayamon County`, `Prince of Wales
 * Hyder` and `De Kalb County`. Every one of those is the same place written by a different editor,
 * and every one of them currently fails to match and is preserved as dead history.
 *
 * The obvious fix is to widen the hydrator's `key()`. That is the wrong move and this class exists
 * to avoid it: `key()` is the comparison form of the EXISTING exact rung, three phases of stored
 * data and four test suites depend on exactly what it does, and widening it changes what already
 * matches rather than adding to it. So compatibility is a SEPARATE, LATER rung over a SEPARATE
 * index, and the exact rung keeps answering first and keeps answering the same way.
 *
 * WHAT IT NORMALISES, IN ORDER
 * ----------------------------
 *   1. ACCENTS FOLD. `bayamón` → `bayamon`, `añasco` → `anasco`, `la cañada` → `la canada`.
 *      Via an explicit character table rather than `iconv('ASCII//TRANSLIT')`, whose output depends
 *      on the runtime locale — the same input producing a different key on a different host is not
 *      a thing a matching layer may do.
 *   2. PUNCTUATION GOES. Periods, hyphens and apostrophes become spaces, so `St. Petersburg`,
 *      `Winston-Salem` and a curly apostrophe pasted out of a listing all reduce the same way.
 *   3. ONE GEOGRAPHY-CLASS WORD IS REMOVED, at the county tier only. This is the rung that makes
 *      `Adjuntas County` reach `Adjuntas Municipio` — the stored label carries the class the old
 *      editor knew, the corpus carries the class the Census publishes, and neither is wrong.
 *   4. `saint` BECOMES `st`. Whole-word, so `Saint Petersburg`, `St Petersburg` and `St. Petersburg`
 *      converge and a name that merely contains the letters does not.
 *   5. SPACES GO ENTIRELY. This is what makes `DeKalb` and `De Kalb`, `LaSalle` and `La Salle` the
 *      same key. Removing spaces is used rather than inserting them because the input has already
 *      been lowercased by the time it arrives — the capital that told you where `DeKalb` splits is
 *      gone, so a splitting rule would have to guess, and guessing is what this phase forbids.
 *
 * IT NEVER GUESSES
 * ----------------
 * There is no edit distance here, no phonetic key, no prefix match and no "nearest" anything. Two
 * names either reduce to the identical string or they do not match. That is the whole contract, and
 * it is what makes the layer safe to run over stored data nobody has looked at in years.
 *
 * AMBIGUITY IS ANSWERED WITH SILENCE
 * ----------------------------------
 * Normalisation is lossy on purpose, so two distinct corpus entries can land on one key —
 * `La Salle County` and `LaSalle County` both reduce to `lasalle`. {@see self::register()} answers
 * that by setting the entry to null rather than keeping the first arrival, and
 * {@see self::lookup()} reads null as no match. The stored label is then preserved verbatim, which
 * is the same outcome the hydrator has always produced for something it could not resolve. Taking
 * the first result would silently attach a listing to the wrong county, in a different part of the
 * state, with nothing anywhere to show it happened.
 *
 * READ-ONLY and dependency-free, like everything in this namespace: it takes strings and returns
 * strings.
 */
final class GeographyNameCompatibility
{
    /**
     * Accented characters and their unaccented equivalents.
     *
     * Latin-1 Supplement and the parts of Latin Extended-A that appear in published US place names,
     * plus the three ligatures that expand rather than fold. Lowercase only — every caller passes a
     * value that has already been lowercased, and {@see self::fold()} lowercases again defensively
     * so the class is correct when used on its own.
     *
     * A character NOT in this table is treated as punctuation and becomes a space, which is the
     * safe direction: it can only make a name fail to match, never match the wrong thing.
     *
     * @var array<string, string>
     */
    private const ACCENTS = [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a',
        'ā' => 'a', 'ă' => 'a', 'ą' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'ē' => 'e', 'ĕ' => 'e',
        'ė' => 'e', 'ę' => 'e', 'ě' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'ī' => 'i', 'ĭ' => 'i',
        'į' => 'i', 'ı' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o', 'ø' => 'o',
        'ō' => 'o', 'ŏ' => 'o', 'ő' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ū' => 'u', 'ŭ' => 'u',
        'ů' => 'u', 'ű' => 'u', 'ų' => 'u',
        'ñ' => 'n', 'ń' => 'n', 'ň' => 'n', 'ņ' => 'n',
        'ç' => 'c', 'ć' => 'c', 'č' => 'c',
        'ý' => 'y', 'ÿ' => 'y',
        'š' => 's', 'ś' => 's', 'ş' => 's',
        'ž' => 'z', 'ź' => 'z', 'ż' => 'z',
        'ł' => 'l', 'ĺ' => 'l', 'ľ' => 'l',
        'ř' => 'r', 'ŕ' => 'r',
        'ť' => 't', 'ţ' => 't',
        'ď' => 'd', 'đ' => 'd', 'ð' => 'd',
        'ğ' => 'g', 'ģ' => 'g',
        'ķ' => 'k',
        'æ' => 'ae', 'œ' => 'oe', 'ß' => 'ss', 'þ' => 'th',
    ];

    /**
     * Geography-class words, at most ONE of which is removed from a county-tier name.
     *
     * These are the words a jurisdiction's own government uses for the county level, and they differ
     * by state and territory: Louisiana publishes parishes, Alaska publishes boroughs and census
     * areas, Puerto Rico publishes municipios. The stored labels do not — the previous editor's
     * autocomplete appended `County` almost everywhere, which is how `Adjuntas Municipio` came to be
     * stored as `Adjuntas County`.
     *
     * ORDERED LONGEST-FIRST so a multi-word class is removed whole. `city and borough` must be tried
     * before `borough` and before `city`, or `Sitka City and Borough` reduces to `sitka city and`.
     *
     * ONLY ONE IS REMOVED, and the removal never recurses. `Island County` becomes `island`, not the
     * empty string, because the second pass has no way to know it is eating the name itself.
     *
     * @var list<string>
     */
    private const CLASS_WORDS = [
        ' city and borough',
        ' municipality',
        ' census area',
        ' municipio',
        ' district',
        ' borough',
        ' county',
        ' island',
        ' parish',
        ' city',
    ];

    /**
     * The compatibility key of a place name — a city, or any tier with no class vocabulary.
     *
     * No class word is removed here. City names legitimately END in the words this codebase treats
     * as classes — `Kansas City`, `Ocean City`, `Long Island City` — and stripping one would reduce
     * them to a fragment that matches something else or nothing at all.
     */
    public function placeKey(string $name): string
    {
        return $this->collapse($this->saintForm($this->fold($name)));
    }

    /**
     * The compatibility key of a county-tier name, with at most one class word removed.
     *
     * THIS IS THE RUNG THAT WAS MISSING. `Adjuntas County` and `Adjuntas Municipio` both reduce to
     * `adjuntas`, so the stored label finally reaches the published entry. It works in both
     * directions and for every pair of classes in the list, because neither side is privileged —
     * both are reduced before either is compared.
     */
    public function countyKey(string $name): string
    {
        return $this->collapse($this->saintForm($this->classless($this->fold($name))));
    }

    /**
     * Add one corpus entry to a compatibility index, answering ambiguity with null.
     *
     * THE COLLISION RULE, and the reason this is a method rather than an assignment. Three cases,
     * and only the middle one is interesting:
     *
     *   - the key is new                  → it maps to this id
     *   - the key is held by ANOTHER id   → it maps to null, permanently. A third arrival cannot
     *                                       revive it, because null is still a held key.
     *   - the key is held by the SAME id  → nothing happens
     *
     * That third case is not a formality. The Census repository emits a place once per county it
     * spans, so a city straddling two selected counties arrives here twice carrying one id. A naive
     * "seen it before ⇒ ambiguous" rule would read that as a collision and refuse to match a place
     * that is not ambiguous at all — the exact failure the many-to-many corpus was imported to fix.
     *
     * @param  array<string, string|null>  $index  by reference; the accumulating compatibility index
     */
    public function register(array &$index, string $key, string $id): void
    {
        if ($key === '' || $id === '') {
            return;
        }

        if (! array_key_exists($key, $index)) {
            $index[$key] = $id;

            return;
        }

        if ($index[$key] !== $id) {
            $index[$key] = null;
        }
    }

    /**
     * The id a compatibility key resolves to, or null for "no match".
     *
     * Null covers both "nothing has this key" and "several things do". A caller must not be able to
     * tell those apart, because the correct response to each is identical: leave the stored label
     * alone. An empty key — everything folded away — is refused before the lookup rather than
     * allowed to collide with another degenerate entry.
     *
     * @param  array<string, string|null>  $index
     */
    public function lookup(array $index, string $key): ?string
    {
        return $key === '' ? null : ($index[$key] ?? null);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // THE STEPS
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Lowercase, unaccented, punctuation-free, single-spaced.
     *
     * The lowercase pass is redundant when the caller is the hydrator, which has already keyed the
     * value, and deliberate anyway: this class is tested on its own and must be total.
     *
     * Anything left outside `[a-z0-9 ]` after folding becomes a space rather than being deleted,
     * so `Winston-Salem` gives `winston salem` and not `winstonsalem` at this stage — the class-word
     * step still needs to see word boundaries.
     */
    private function fold(string $name): string
    {
        $folded = strtr(mb_strtolower(trim($name)), self::ACCENTS);

        return trim((string) preg_replace('/\s+/', ' ', (string) preg_replace('/[^a-z0-9]+/', ' ', $folded)));
    }

    /** Remove at most one trailing geography-class word. */
    private function classless(string $folded): string
    {
        foreach (self::CLASS_WORDS as $class) {
            if (str_ends_with($folded, $class)) {
                return trim(substr($folded, 0, -strlen($class)));
            }
        }

        return $folded;
    }

    /**
     * `saint` → `st`, as whole words.
     *
     * Whole-word so `Saint Louis` converges with `St. Louis` while a name that merely contains the
     * letters is untouched. `sainte` is handled first for the same reason the class words are
     * ordered longest-first: otherwise it becomes `ste` by way of `st` plus a stray `e`.
     */
    private function saintForm(string $folded): string
    {
        return (string) preg_replace(
            ['/\bsainte\b/', '/\bsaint\b/'],
            ['ste', 'st'],
            $folded
        );
    }

    /** Remove every remaining space, so `de kalb` and `dekalb` are one key. */
    private function collapse(string $folded): string
    {
        return str_replace(' ', '', $folded);
    }
}
