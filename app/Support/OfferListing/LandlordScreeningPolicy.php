<?php

namespace App\Support\OfferListing;

/**
 * Landlord Applicant-Requirements screening boundary (Fair Housing Phase 2).
 *
 * WHY THIS EXISTS, AND WHY VALIDATION COULD NOT DO IT.
 *
 * Every screening key on the landlord Applicant Requirements tab is a public
 * Livewire property that `saveMeta()` writes verbatim. The Phase 2 audit found
 * no validation rule referencing any of them — not a loose one, none at all.
 * So a client can set `employment_requirement` to any string over the wire and
 * the save persists it, whether or not the Blade file still offers that option.
 * Deleting an <option> is a change to the form, not to the write.
 *
 * Adding `rules()` entries would not close it either, for the reason recorded
 * on the Phase 1 Hire Agent boundary: `validate()` checks the paths named in
 * the rules and leaves everything else sitting on the property, and a draft
 * save does not run full validation at all. The gate therefore has to be at the
 * write, and it has to be an INTERSECTION — a value survives by appearing in
 * the allowlist, never by failing to appear on a deny-list. A new unsafe option
 * added upstream is then inert here by default rather than admitted by default.
 *
 * Everything is read from config/landlord_screening_options.php, which the
 * Blade partial also renders from, so the form and the gate cannot drift.
 *
 * The class is pure: no database, no request, no side effects. It takes values
 * and returns values, which is what makes it testable without a listing.
 */
class LandlordScreeningPolicy
{
    /** Canonical "no answer". */
    public const NONE = '';

    /** @var array<string, mixed>|null File-loaded config, when no container is available. */
    private static ?array $fileConfig = null;

    /**
     * The option definitions, with or without a booted application.
     *
     * WHY THIS IS NOT JUST `config()`. This class is called from Blade, from two
     * Livewire components, and from the Ask AI landlord extractor — and that
     * extractor is covered by a unit test that extends PHPUnit's TestCase
     * directly, so no application is booted and no config repository exists.
     * `config()` there raises, `AskAiContextBuilderService::buildForListing()`
     * catches every Throwable and returns a failed payload, and the symptom is
     * not "screening key missing" but an entirely empty listing context — every
     * unrelated key gone, with the real error swallowed several frames away.
     *
     * A boundary that silently deletes its caller's data when it happens to be
     * invoked outside a framework boot is not a boundary. So: use the container
     * when one is there, read the file when it is not.
     *
     * @return array<string, mixed>
     */
    private static function conf(): array
    {
        if (function_exists('app')) {
            try {
                $container = app();
                if (is_object($container) && method_exists($container, 'bound') && $container->bound('config')) {
                    $fromContainer = config('landlord_screening_options');
                    if (is_array($fromContainer) && $fromContainer !== []) {
                        return $fromContainer;
                    }
                }
            } catch (\Throwable) {
                // Fall through to the file.
            }
        }

        if (self::$fileConfig === null) {
            $path = __DIR__ . '/../../../config/landlord_screening_options.php';
            $loaded = is_file($path) ? require $path : [];
            self::$fileConfig = is_array($loaded) ? $loaded : [];
        }

        return self::$fileConfig;
    }

    /**
     * Read one dotted path out of the definitions.
     */
    private static function confGet(string $path, $default = null)
    {
        $value = self::conf();

        foreach (explode('.', $path) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Fields Phase 2 retired outright.
     *
     * @return string[]
     */
    public static function retiredFields(): array
    {
        return (array) self::confGet('retired_fields', []);
    }

    public static function isRetiredField(string $key): bool
    {
        return in_array($key, self::retiredFields(), true);
    }

    /**
     * Full field definitions.
     *
     * @return array<string, array>
     */
    public static function fields(): array
    {
        return (array) self::confGet('fields', []);
    }

    public static function isGovernedField(string $key): bool
    {
        return array_key_exists($key, self::fields());
    }

    /**
     * The values a landlord may currently choose for one field.
     *
     * @return string[]
     */
    public static function optionsFor(string $key): array
    {
        return (array) (self::fields()[$key]['options'] ?? []);
    }

    public static function copy(string $key): string
    {
        return (string) self::confGet('copy.' . $key, '');
    }

    public static function customTextMaxLength(): int
    {
        return (int) self::confGet('custom_text_max_length', 500);
    }

    /**
     * Resolve one stored or submitted value to a current valid one.
     *
     * Returns self::NONE for anything that cannot be expressed as a current
     * option — a retired value, an unknown value, a crafted value. NONE is a
     * real answer here: it means "this listing states no policy", which is
     * exactly the truthful reading of a value we will not stand behind.
     *
     * Order matters. Suppression is checked before normalization so that a
     * value listed in both is suppressed, and the sentinel is folded first so
     * 'No Requirement' and 'No requirement' cannot take different paths.
     */
    public static function normalize(string $key, $value): string
    {
        if (self::isRetiredField($key) || ! self::isGovernedField($key)) {
            return self::NONE;
        }

        if (! is_string($value)) {
            return self::NONE;
        }

        $value = trim($value);

        if ($value === '') {
            return self::NONE;
        }

        $field = self::fields()[$key];

        // Sentinel folding. The components shipped 'No Requirement' as a
        // default for years; it matched no option, so the control rendered
        // blank and the public view compared against two spellings in two
        // places. Everything below sees one spelling.
        if (strcasecmp($value, 'No requirement') === 0) {
            $value = 'No requirement';
        }

        if (in_array($value, (array) ($field['suppress'] ?? []), true)) {
            return self::NONE;
        }

        $normalized = (array) ($field['normalize'] ?? []);
        if (array_key_exists($value, $normalized)) {
            $value = (string) $normalized[$value];
        }

        return in_array($value, self::optionsFor($key), true) ? $value : self::NONE;
    }

    /**
     * Multi-select equivalent of normalize().
     *
     * Accepts the array the Livewire property holds or the JSON string the meta
     * row holds, and returns a de-duplicated list of current valid options in
     * config order. Order is taken from config rather than from the input so
     * two listings with the same policy always render the same string.
     *
     * @return string[]
     */
    public static function normalizeMulti(string $key, $value): array
    {
        if (self::isRetiredField($key) || ! self::isGovernedField($key)) {
            return [];
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : ($value === '' ? [] : [$value]);
        }

        if (! is_array($value)) {
            return [];
        }

        $kept = [];
        foreach ($value as $item) {
            $resolved = self::normalize($key, is_string($item) ? $item : '');
            if ($resolved !== self::NONE) {
                $kept[$resolved] = true;
            }
        }

        return array_values(array_filter(
            self::optionsFor($key),
            static fn (string $option): bool => isset($kept[$option])
        ));
    }

    /**
     * Free text for a field's "Other" branch.
     *
     * Returned only while the parent field actually resolves to the value that
     * unlocks it. A stored custom string whose parent has moved to a listed
     * option is stale by definition, and a crafted request that sets the text
     * without the parent never had a branch to fill.
     *
     * This is a length bound and a reachability check, not content moderation
     * — that is Phase 3.
     */
    public static function normalizeCustomText(string $key, $parentValue, $text): string
    {
        if (! self::isGovernedField($key)) {
            return self::NONE;
        }

        $field = self::fields()[$key];
        $unlockedBy = $field['custom_when'] ?? null;

        if ($unlockedBy === null || ! is_string($text)) {
            return self::NONE;
        }

        if (self::normalize($key, $parentValue) !== $unlockedBy) {
            return self::NONE;
        }

        $text = trim($text);

        return $text === ''
            ? self::NONE
            : mb_substr($text, 0, self::customTextMaxLength());
    }

    /**
     * Project a whole submitted screening payload down to what may be stored.
     *
     * This is the method the Livewire components call immediately before
     * `saveMeta()`. The returned array is authoritative: retired keys are
     * absent, governed keys hold a current valid value or NONE, and the
     * multi-select key is returned already JSON-encoded in the shape the meta
     * row has always used.
     *
     * @param  array<string, mixed>  $input  raw Livewire state, keyed by meta key
     * @return array<string, string>
     */
    public static function project(array $input): array
    {
        $out = [];

        foreach (self::fields() as $key => $field) {
            $raw = $input[$key] ?? null;

            if (($field['type'] ?? 'single') === 'multi') {
                $out[$key] = json_encode(self::normalizeMulti($key, $raw));
                continue;
            }

            $out[$key] = self::normalize($key, $raw);

            $customKey = $field['custom_key'] ?? null;
            if ($customKey !== null) {
                $out[$customKey] = self::normalizeCustomText($key, $raw, $input[$customKey] ?? null);
            }
        }

        return $out;
    }

    /**
     * What the public listing page and Ask AI may show for one field.
     *
     * Null means "say nothing". A suppressed or unknown stored value is not a
     * quieter version of itself — it is absent, so callers must render no row
     * at all rather than an empty one.
     */
    public static function displayValue(string $key, $stored, $storedCustom = null): ?string
    {
        if (self::isRetiredField($key) || ! self::isGovernedField($key)) {
            return null;
        }

        $field = self::fields()[$key];

        if (($field['type'] ?? 'single') === 'multi') {
            $values = array_values(array_filter(
                self::normalizeMulti($key, $stored),
                static fn (string $v): bool => strcasecmp($v, 'No requirement') !== 0
            ));

            return $values === [] ? null : implode(', ', $values);
        }

        $value = self::normalize($key, $stored);

        if ($value === self::NONE || strcasecmp($value, 'No requirement') === 0) {
            return null;
        }

        $customKey = $field['custom_key'] ?? null;
        if ($customKey !== null && $value === ($field['custom_when'] ?? null)) {
            $custom = self::normalizeCustomText($key, $stored, $storedCustom);

            return $custom === self::NONE ? null : $custom;
        }

        return $value;
    }

    /**
     * Does any governed field state a policy worth rendering a section for?
     *
     * The public view used to decide this with a hand-rolled chain of string
     * comparisons that spelled the sentinel two different ways, so a listing
     * holding only 'No Requirement' could still open the section. Asking the
     * same resolver the rows use keeps the header honest.
     *
     * @param  callable(string): mixed  $get  reads one stored meta value
     */
    public static function hasAnyDisplayableValue(callable $get): bool
    {
        foreach (array_keys(self::fields()) as $key) {
            $customKey = self::fields()[$key]['custom_key'] ?? null;

            if (self::displayValue($key, $get($key), $customKey ? $get($customKey) : null) !== null) {
                return true;
            }
        }

        return false;
    }
}
