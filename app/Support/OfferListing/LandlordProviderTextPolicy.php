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
     * The categories that apply to one field: the shared set, plus any the field
     * opts into. `pet_restrictions` is the only field today that adds one —
     * assistance-animal-as-pet is meaningless on a field that is not a pet policy.
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
            // A category listed as "extra" on some field is opt-in ONLY: it applies
            // to the fields that name it, never to every field by default.
            $isExtraSomewhere = self::isOptInCategory($name);

            if ($isExtraSomewhere && ! in_array($name, $extra, true)) {
                continue;
            }

            $out[$name] = $definition;
        }

        return $out;
    }

    /** True when some field opts into this category, making it opt-in everywhere. */
    private static function isOptInCategory(string $category): bool
    {
        foreach (self::fields() as $field) {
            $extra = $field['extra_categories'] ?? [];
            if (is_array($extra) && in_array($category, $extra, true)) {
                return true;
            }
        }

        return false;
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
     * The write projection. Length is bounded here for every governed field, safe or
     * not, because a 40KB paragraph is a storage problem independent of its content.
     *
     * NOTE WHAT THIS DOES NOT DO: it does not drop, redact or rewrite unsafe text.
     * Save Draft must keep exactly what the landlord typed so they can come back and
     * revise it; publication is refused separately, by validation. Suppression at
     * read time (`displayValue()`) is what keeps unsafe prose off the page and out of
     * the prompt in the meantime — including for rows that predate Phase 3.
     */
    public static function projectForStorage(string $field, $text): string
    {
        if (! is_string($text)) {
            return '';
        }

        $text = trim($text);

        if ($text === '' || ! self::isGovernedField($field)) {
            return $text;
        }

        return mb_substr($text, 0, self::maxLength($field));
    }

    /**
     * Publish-time message for one field, or null when it may be published.
     * Names the offending phrase so "what do I change?" has an answer.
     */
    public static function publishError(string $field, $text): ?string
    {
        $decision = self::decide($field, $text);

        if ($decision['allowed']) {
            return null;
        }

        $matched = $decision['matched'] ?? '';
        $where   = self::label($field);

        return $matched === ''
            ? sprintf('%s: %s', $where, $decision['message'])
            : sprintf('%s: please revise “%s”. %s', $where, $matched, $decision['message']);
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
