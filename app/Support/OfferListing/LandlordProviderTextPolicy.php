<?php

namespace App\Support\OfferListing;

/**
 * LandlordProviderTextPolicy — the single semantic boundary for landlord-authored prose.
 *
 * ==============================================================================
 * WHAT THIS IS FOR
 *
 * Phase 2 closed the landlord screening DROPDOWNS: `LandlordScreeningPolicy` is an
 * intersection against an allowlist, so a value is stored because it is named.
 * Prose has no finite allowlist, so the free-text fields stayed exactly as the
 * audit found them — public Livewire properties with NO validation rule, written
 * verbatim by `saveMeta()`, rendered on two anonymous routes and fed to Ask AI.
 *
 * This class is the other half of that boundary. It answers one question, the same
 * way for every consumer:
 *
 *     stored value -> decide() -> allowed (use it) | blocked (suppress it)
 *
 * ==============================================================================
 * THREE PROPERTIES THAT ARE LOAD-BEARING
 *
 * 1. IT NEVER MUTATES THE TEXT. `decide()` returns a verdict; it does not return a
 *    "cleaned" string, and there is no redaction path. A landlord's stated terms
 *    must never be silently rewritten into something they did not write — they are
 *    preserved and the landlord is asked to revise them. The ONLY transformation
 *    applied anywhere here is a hard length cap on the write, which is a storage
 *    bound and is applied to safe and unsafe text alike.
 *
 * 2. IT IS DETERMINISTIC AND CONTAINER-FREE. No LLM, no network, no database. And
 *    `conf()` reads the config file directly when no container is bound — this is
 *    the Phase 2 lesson, learned the expensive way: `LandlordScreeningPolicy` is
 *    called from the Ask AI landlord extractor, whose unit test extends PHPUnit's
 *    TestCase with no application. A bare `config()` there raises,
 *    `AskAiContextBuilderService::buildForListing()` catches every Throwable, and
 *    the symptom is not a missing field but an ENTIRELY EMPTY listing context with
 *    the real error swallowed several frames away. This class is reachable from the
 *    same extractor and must not reintroduce that failure.
 *
 * 3. IT IS AUTHOR-AWARE BY CONSTRUCTION. There is no "moderate this string" entry
 *    point. Every public method takes a FIELD KEY, and only landlord provider fields
 *    are named in `config/landlord_provider_text.php`. An unknown key is `allowed`
 *    and untouched, so pointing this class at consumer text is not merely
 *    discouraged — it does nothing. That matters because provider and consumer text
 *    meet in `AskAiContextBuilderService`, and the same words carry opposite meaning
 *    depending on who wrote them:
 *
 *        landlord: "No emotional support animals"        -> blocked
 *        tenant:   "I have an emotional support animal"  -> never seen by this class
 *
 * ==============================================================================
 * WHAT IT IS NOT
 *
 * Not `AskAiComplianceGuardrailService`. That service sanitises GENERATED OUTPUT by
 * dropping offending sentences and neutralising superlatives in place. Its taxonomy
 * informed the categories here; its mechanism is wrong for authored input, where
 * dropping half of what someone typed is the silent rewrite property 1 forbids.
 *
 * Not a sentiment classifier and not a word filter. See the header of
 * `config/landlord_provider_text.php` for why vocabulary-based rules are actively
 * harmful on exactly these fields.
 */
class LandlordProviderTextPolicy
{
    /** Verdict: the text may be stored, published and sent to AI. */
    public const ALLOWED = 'allowed';

    /** Verdict: the text is preserved for its author, but is not published and not sent to AI. */
    public const BLOCKED = 'blocked';

    /** Cached config, so a Blade loop does not re-read the file per row. */
    private static ?array $conf = null;

    /**
     * Resolve configuration through the container when one is bound, and from the
     * file when it is not. See property 2 in the class docblock — this is not
     * defensive programming, it is a fix for a specific swallowed-exception bug.
     */
    private static function conf(): array
    {
        if (self::$conf !== null) {
            return self::$conf;
        }

        if (function_exists('app') && function_exists('config')) {
            try {
                if (app()->bound('config')) {
                    $fromContainer = config('landlord_provider_text');
                    if (is_array($fromContainer) && $fromContainer !== []) {
                        return self::$conf = $fromContainer;
                    }
                }
            } catch (\Throwable $e) {
                // Fall through to the file. A container that cannot answer must not
                // become an exception three frames below a catch-all.
            }
        }

        $path = __DIR__ . '/../../../config/landlord_provider_text.php';

        if (is_file($path)) {
            $fromFile = require $path;
            if (is_array($fromFile)) {
                return self::$conf = $fromFile;
            }
        }

        // An unreadable config must not silently disable the boundary, but it also
        // must not take down a listing page. Empty config => no governed fields =>
        // every field is unknown => `decide()` returns allowed and untouched, which
        // is the pre-Phase-3 behaviour rather than a new failure mode.
        return self::$conf = ['fields' => [], 'categories' => []];
    }

    /** Test seam. Never called by application code. */
    public static function flushCache(): void
    {
        self::$conf = null;
    }

    /** @return array<string,array> field key => field definition */
    public static function fields(): array
    {
        $f = self::conf()['fields'] ?? [];

        return is_array($f) ? $f : [];
    }

    /** Is this field key landlord-authored prose that Phase 3 governs? */
    public static function isGovernedField(string $key): bool
    {
        return array_key_exists($key, self::fields());
    }

    /** @return array<string,string> Bridge/Stellar field => governed field whose semantics apply */
    public static function mlsProseAliases(): array
    {
        $map = self::conf()['mls_prose_aliases'] ?? [];

        return is_array($map) ? $map : [];
    }

    /**
     * The governed field whose semantics an imported MLS field inherits, or null
     * when that MLS field is not provider prose we moderate.
     */
    public static function mlsAliasFor(string $mlsField): ?string
    {
        $target = self::mlsProseAliases()[$mlsField] ?? null;

        return is_string($target) && self::isGovernedField($target) ? $target : null;
    }

    /**
     * Publication eligibility for one row of imported MLS prose.
     *
     * Returns the value when it may be published and NULL when it may not, exactly
     * like `displayValue()` — a suppressed MLS row reads as "the feed says nothing
     * here", which is what the renderer already does with an empty value.
     *
     * AN UNMAPPED FIELD IS RETURNED UNCHANGED. That is the whole design: the 300-odd
     * structured RESO enums that make up the rest of the payload are never examined,
     * so no property fact can be suppressed by a rule written for prose. Which fields
     * are prose is a decision recorded in `mls_prose_aliases`, made against real
     * fixture values rather than field names.
     *
     * This never writes. The imported payload stays complete in storage; only what
     * a page may render is decided here.
     */
    public static function mlsDisplayValue(string $mlsField, $stored): ?string
    {
        if (! is_string($stored)) {
            return null;
        }

        $trimmed = trim($stored);

        if ($trimmed === '') {
            return null;
        }

        $target = self::mlsAliasFor($mlsField);

        return $target === null ? $trimmed : self::displayValue($target, $trimmed);
    }

    /** Storage bound for a governed field. Applied to safe and unsafe text alike. */
    public static function maxLength(string $key): int
    {
        $max = self::fields()[$key]['max_length'] ?? 0;

        return is_int($max) && $max > 0 ? $max : 1000;
    }

    /** Human label, for error messages. */
    public static function label(string $key): string
    {
        return (string) (self::fields()[$key]['label'] ?? $key);
    }

    /**
     * The categories that apply to one field: every UNIVERSAL category, plus any
     * opt-in category the field names in `extra_categories`.
     *
     * SCOPE IS DECLARED, NOT INFERRED. This method used to treat a category as
     * opt-in because some field happened to name it — so adding
     * `assistance_animal_as_pet` to `pet_restrictions` silently removed that whole
     * category from the other two fields, and "No emotional support animals" in
     * Landlord approval conditions or Additional details published untouched. The
     * pre-PR audit caught it. A category is narrow now only by saying
     * `'opt_in' => true` about itself, which is visible in the config diff.
     */
    private static function categoriesFor(string $key): array
    {
        $all   = self::conf()['categories'] ?? [];
        $field = self::fields()[$key] ?? [];

        if (! is_array($all)) {
            return [];
        }

        $extra = $field['extra_categories'] ?? [];
        $extra = is_array($extra) ? $extra : [];

        $out = [];
        foreach ($all as $name => $definition) {
            if (self::isOptInCategory($name) && ! in_array($name, $extra, true)) {
                continue;
            }

            $out[$name] = $definition;
        }

        return $out;
    }

    /**
     * True only when the category DECLARES itself opt-in. Anything else is
     * universal and is evaluated on every governed field.
     */
    private static function isOptInCategory(string $category): bool
    {
        $definition = self::conf()['categories'][$category] ?? [];

        return is_array($definition) && ($definition['opt_in'] ?? false) === true;
    }

    /**
     * The whole contract, in one deterministic answer.
     *
     * @return array{
     *   verdict:string, field:string, category:?string,
     *   pattern:?string, matched:?string, message:?string, allowed:bool
     * }
     */
    public static function decide(string $field, $text): array
    {
        $base = [
            'verdict'  => self::ALLOWED,
            'field'    => $field,
            'category' => null,
            'pattern'  => null,
            'matched'  => null,
            'message'  => null,
            'allowed'  => true,
        ];

        // Not a governed provider field, or nothing to judge. Consumer text and
        // every ungoverned key land here and are returned untouched.
        if (! self::isGovernedField($field) || ! is_string($text)) {
            return $base;
        }

        $normalised = self::normaliseForMatching($text);

        if ($normalised === '') {
            return $base;
        }

        foreach (self::categoriesFor($field) as $category => $definition) {
            $patterns = $definition['patterns'] ?? [];
            if (! is_array($patterns)) {
                continue;
            }

            foreach ($patterns as $pattern) {
                if (! is_string($pattern) || $pattern === '') {
                    continue;
                }

                $matches = [];
                // A malformed pattern must not take down a listing page: preg_match
                // returns false on error and we simply move on.
                if (@preg_match($pattern, $normalised, $matches) === 1) {
                    return [
                        'verdict'  => self::BLOCKED,
                        'field'    => $field,
                        'category' => $category,
                        'pattern'  => $pattern,
                        'matched'  => trim((string) ($matches[0] ?? '')),
                        'message'  => (string) ($definition['message'] ?? ''),
                        'allowed'  => false,
                    ];
                }
            }
        }

        return $base;
    }

    /** Convenience: is this value publishable as written? */
    public static function isAllowed(string $field, $text): bool
    {
        return self::decide($field, $text)['allowed'];
    }

    /**
     * THE ONE ACCESSOR EVERY CONSUMER USES — public Blade, the qualification pages,
     * the Ask AI context builder, Agent AI.
     *
     * Returns the value when it is publishable and NULL when it is not, so a
     * suppressed value reads as "this listing states nothing here" exactly as a
     * Phase 2 retired dropdown value does. Consumers must call this rather than
     * reading the meta key, or the page and the prompt can disagree — which is
     * precisely how the Phase 2 review page ended up scoring applicants against a
     * criterion the listing page had already stopped displaying.
     */
    public static function displayValue(string $field, $stored): ?string
    {
        if (! is_string($stored)) {
            return null;
        }

        $trimmed = trim($stored);

        if ($trimmed === '') {
            return null;
        }

        if (! self::isGovernedField($field)) {
            return $trimmed;
        }

        return self::isAllowed($field, $trimmed) ? $trimmed : null;
    }

    /**
     * The write projection. IT PRESERVES THE LANDLORD'S BYTES EXACTLY, other than
     * trimming surrounding whitespace.
     *
     * IT USED TO TRUNCATE, AND THAT WAS A DEFECT ON TWO COUNTS. `mb_substr($text, 0,
     * max_length)` on the way to storage meant:
     *
     *   1. Silent data loss. A landlord who wrote 1,400 characters got 1,000 stored,
     *      with no error and no warning. The draft they came back to was not the
     *      draft they wrote, and nothing anywhere said so.
     *
     *   2. A moderation hole. Truncation happened at the WRITE, so a sentence
     *      beginning after character 1,000 was discarded before it was stored — and
     *      every later boundary examined the short copy. "Filler to 1,000 characters,
     *      then the unsafe sentence" is an evasion under that ordering. Both the
     *      publish gate and read-time suppression now see the FULL value, so it isn't.
     *
     * There is no schema pressure to truncate — the meta value column is `text`.
     * Length is therefore a PUBLICATION rule enforced by `publishError()`, not a
     * storage rule: an over-long draft is kept intact and simply cannot be published
     * until the landlord shortens it, which is the same shape as unsafe wording.
     *
     * NOTE WHAT THIS STILL DOES NOT DO: it does not drop, redact or rewrite unsafe
     * text. Save Draft keeps exactly what the landlord typed so they can come back
     * and revise it; publication is refused separately. Suppression at read time
     * (`displayValue()`) is what keeps unsafe prose off the page and out of the
     * prompt in the meantime — including for rows that predate Phase 3.
     */
    public static function projectForStorage(string $field, $text): string
    {
        if (! is_string($text)) {
            return '';
        }

        return trim($text);
    }

    /** Is this governed value within the length it is allowed to PUBLISH at? */
    public static function withinMaxLength(string $field, $text): bool
    {
        if (! is_string($text) || ! self::isGovernedField($field)) {
            return true;
        }

        return mb_strlen(trim($text)) <= self::maxLength($field);
    }

    /**
     * Publish-time message for one field, or null when it may be published.
     * Names the offending phrase so "what do I change?" has an answer.
     *
     * TWO INDEPENDENT REASONS TO REFUSE, both non-destructive: the wording states an
     * unlawful preference, or the value is longer than the field may publish. Wording
     * is checked FIRST, so a landlord who is both over length and using unsafe
     * wording hears about the wording — shortening would not have helped them.
     *
     * Length is evaluated against the FULL value. Nothing upstream truncates any
     * more; see `projectForStorage()` for why that ordering is load-bearing.
     */
    public static function publishError(string $field, $text): ?string
    {
        $decision = self::decide($field, $text);
        $where    = self::label($field);

        if (! $decision['allowed']) {
            $matched = $decision['matched'] ?? '';

            return $matched === ''
                ? sprintf('%s: %s', $where, $decision['message'])
                : sprintf('%s: please revise “%s”. %s', $where, $matched, $decision['message']);
        }

        if (! self::withinMaxLength($field, $text)) {
            return sprintf(
                '%s: please shorten this to %d characters or fewer to publish (currently %d). Your full text is saved — nothing has been removed.',
                $where,
                self::maxLength($field),
                mb_strlen(trim((string) $text))
            );
        }

        return null;
    }

    /**
     * Collapse whitespace and normalise the punctuation people actually type, so a
     * rule does not miss because of a curly apostrophe, a non-breaking space or a
     * line break in the middle of a phrase.
     */
    private static function normaliseForMatching(string $text): string
    {
        $text = str_replace(
            ["\xE2\x80\x99", "\xE2\x80\x98", "\xE2\x80\x93", "\xE2\x80\x94", "\xC2\xA0"],
            ["'", "'", '-', '-', ' '],
            $text
        );

        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }
}
