<?php

namespace App\Services\AskAi;

use App\Services\AskAi\Snapshot\SnapshotFactVisibility;
use App\Support\Listing\ListingPriceDisplay;

/**
 * AskAiPublicPropertyQuestionService — "Questions About This Property" (Batches 1, 2b)
 *
 * GOVERNANCE BLOCK:
 * ==================================================================================
 * ROLE: Turns the approved catalog in
 * AskAiFieldQuestionRegistryService::publicPropertyQuestionRegistry() into verified,
 * precomputed question/answer pairs for the PUBLIC Seller and Landlord listing pages,
 * rendered in the Ask AI card. Every answer is a fixed sentence built from structured
 * listing values the page already assembled into its Ask AI chip context.
 *
 * This service MUST NEVER:
 *   - Call a language model, the free-text classifier, the intent normaliser, the
 *     knowledge search, or any HTTP service. It has no constructor dependencies, so it
 *     cannot reach any of them.
 *   - Read a database. Its inputs are the chip context and the decoded meta array the
 *     controller already holds.
 *   - Answer from an owner-authored Knowledge Base answer (faq_answers.*). Those remain
 *     owner-only under P0 / P0.1 / P0.2.
 *   - Answer for a buyer or tenant listing. Those are search criteria (decision D2).
 *   - Make a fact public that is not already public. Visibility is SnapshotFactVisibility's
 *     decision, with exactly one narrow exception: PUBLIC_QUESTION_ADMISSIONS names a
 *     source this surface may restate because the listing page itself already publishes
 *     it. An admission applies to this surface only — it never touches
 *     SnapshotFactVisibility, snapshots, the Ask AI redactor or Agent AI.
 * ==================================================================================
 *
 * THE AVAILABILITY RULE (evaluate()) — a question is shown only when ALL hold:
 *   1. its role is seller or landlord, and the entry is in the catalog for that role;
 *   2. source_path is exactly one 'listing.<key>' path, and <key> is a context key the
 *      context builder defines for that role (CANONICAL_SOURCE_MAP);
 *   3. SnapshotFactVisibility::classify(<key>, role) is 'public_allowed' for the source
 *      AND every supporting path — or, for the SOURCE only, the entry is
 *      source_kind 'admitted_listing' and the key is in PUBLIC_QUESTION_ADMISSIONS;
 *   4. the source value is present and meaningful;
 *   5. the named formatter exists and accepts the value (a formatter returns null for
 *      anything it cannot state exactly, which hides the question);
 *   6. every named guard passes. Guards only ever HIDE — they resolve ambiguity between
 *      the context value and what the page itself publishes, and never expose a value.
 * Anything unrecognised — role, path, key, source kind, formatter, guard — fails closed.
 *
 * COMPOSITES (Batch 2c) answer one question from several declared structured sources and
 * need every required part; a narrower entry naming the composite in 'narrower_of' stands in
 * when the composite is unavailable and is suppressed when it is available, so near-identical
 * questions never both show.
 *
 * META IS READ FOR TWO THINGS ONLY: hide-only guards, and an entry's declared
 * 'other_companion' / 'other_companions' — the exact stored selections of a field (the key
 * named in 'selected_in') and the free-text "Other" value beside it (the key named in 'meta_key').
 * Both keys are named by the catalog entry; nothing else in $meta is ever read into an
 * answer. The companion text is used only when "Other" is actually selected, the same
 * substitution the listing page performs, and only when it is a short, single-line,
 * non-placeholder value; text that cannot be restated safely hides the whole question.
 */
class AskAiPublicPropertyQuestionService
{
    /** Roles whose listings describe a property. Mirrors SnapshotFactVisibility. */
    private const ELIGIBLE_ROLES = ['seller', 'landlord'];

    /**
     * Sources this surface may restate although SnapshotFactVisibility keeps them owner-only
     * for the AI context. Each is already published on the listing page. Used ONLY by
     * entries declaring source_kind 'admitted_listing', and only as the source_path — never
     * as a supporting path.
     */
    private const PUBLIC_QUESTION_ADMISSIONS = [
        'seller' => [
            // The page's "Offered Financing" row and hero badges publish the financing
            // types (product decision 2026-09-15). Seller-financing TERMS — down payment,
            // interest rate, term, balloon — stay RESTRICTED and are never read here.
            'offered_financing' => 'Offered financing types are published on the seller listing page.',
        ],
    ];

    /**
     * Frequencies with an unambiguous English phrase, keyed by the normalised spelling
     * (lower case, '_' and spaces as '-'), so 'Semi-Annually', 'semi_annually' and
     * 'semi-annually' all agree. Anything else — 'Bi-Monthly' included, which reads as both
     * twice a month and every two months — gets no period at all.
     */
    private const HOA_FREQUENCY_PHRASES = [
        'monthly'       => 'per month',
        'quarterly'     => 'per quarter',
        'semi-annually' => 'every six months',
        'annually'      => 'per year',
    ];

    /** Stored values that say nothing; never published as an answer or an "Other" text. */
    private const PLACEHOLDER_VALUES = [
        'other', 'n/a', 'na', 'none', 'unknown', 'tbd', 't.b.d.', 'not applicable',
        'not available', 'see remarks', 'see private remarks', 'per remarks', '-', '--', '?', '.',
    ];

    /** Longest free-text value ("Other" text, zoning code) restated verbatim. */
    private const MAX_VERBATIM_LENGTH = 60;

    /** Deterministic range for a plausible construction year. */
    private const YEAR_BUILT_MIN = 1600;
    private const YEAR_BUILT_MAX = 2100;

    /**
     * The available questions for one listing, in catalog order.
     *
     * @param  string               $role     'seller' | 'landlord' (anything else returns [])
     * @param  array                $context  AskAiContextBuilderService::buildChipContext() output
     * @param  array<string,mixed>  $meta     The listing's decoded meta array (guards only)
     * @return list<array{id: string, question: string, answer: string, source_path: string}>
     */
    public function forListing(string $role, array $context, array $meta): array
    {
        $role = strtolower(trim($role));
        if (!in_array($role, self::ELIGIBLE_ROLES, true)) {
            return [];
        }

        $catalog = array_filter(
            AskAiFieldQuestionRegistryService::publicPropertyQuestionRegistry(),
            static fn ($entry): bool => is_array($entry) && ($entry['role'] ?? null) === $role
        );
        // Display order; usort is stable (PHP 8), so equal orders keep catalog order.
        $ids = array_keys($catalog);
        usort($ids, static fn ($a, $b): int => ((int) ($catalog[$a]['order'] ?? PHP_INT_MAX)) <=> ((int) ($catalog[$b]['order'] ?? PHP_INT_MAX)));

        $available = [];
        foreach ($ids as $id) {
            $result = $this->evaluate($catalog[$id], $role, $context, $meta);
            if ($result['available']) {
                $available[$id] = $result['answer'];
            }
        }

        $questions = [];
        foreach ($available as $id => $answer) {
            $entry = $catalog[$id];

            // narrower_of: this entry is the narrower fallback of a richer composite. When that
            // composite is itself available for this listing it answers the same question more
            // completely, so the narrower one is not shown beside it. A narrower_of that names
            // nothing in this role's catalog suppresses nothing.
            $richer = $entry['narrower_of'] ?? null;
            if (is_string($richer) && $richer !== $id && isset($available[$richer])) {
                continue;
            }

            $questions[] = [
                'id'          => (string) $id,
                'question'    => (string) $entry['question'],
                'answer'      => $answer,
                'source_path' => (string) $entry['source_path'],
            ];
        }

        return $questions;
    }

    /**
     * Apply the availability rule to one catalog entry.
     *
     * @return array{available: bool, reason: string, answer: string|null}
     */
    public function evaluate(array $entry, string $role, array $context, array $meta): array
    {
        $role = strtolower(trim($role));

        // 1. Role and catalog membership.
        if (!in_array($role, self::ELIGIBLE_ROLES, true)) {
            return $this->hidden('role_not_public_eligible');
        }
        if (($entry['role'] ?? null) !== $role || !is_string($entry['question'] ?? null) || trim($entry['question']) === '') {
            return $this->hidden('not_in_catalog_for_role');
        }

        // 2 + 3. One exact, defined, public (or explicitly admitted) source — and every
        // supporting path public in its own right.
        $sourceKind = $entry['source_kind'] ?? 'listing';
        if (!in_array($sourceKind, ['listing', 'admitted_listing'], true)) {
            return $this->hidden('source_kind_unknown');
        }
        $sourceKey = $this->publicListingKey($entry['source_path'] ?? null, $role, $sourceKind === 'admitted_listing');
        if ($sourceKey['reason'] !== null) {
            return $this->hidden($sourceKey['reason']);
        }

        $supportingPaths = $entry['supporting_paths'] ?? [];
        if (!is_array($supportingPaths)) {
            return $this->hidden('supporting_paths_invalid');
        }
        $supporting = [];
        foreach ($supportingPaths as $path) {
            $check = $this->publicListingKey($path, $role);
            if ($check['reason'] !== null) {
                return $this->hidden('supporting_' . $check['reason']);
            }
            $supporting[$check['key']] = $this->listingValue($context, $check['key']);
        }

        // 4. A meaningful value.
        $value = $this->listingValue($context, $sourceKey['key']);
        if ($this->isEmpty($value)) {
            return $this->hidden('value_missing');
        }

        // 6 (before formatting, so a guard never depends on formatter output).
        $guards = $entry['guards'] ?? [];
        if (!is_array($guards)) {
            return $this->hidden('guards_invalid');
        }
        foreach ($guards as $guard) {
            $passed = $this->guardPasses((string) $guard, $context, $meta);
            if ($passed === null) {
                return $this->hidden('guard_unknown:' . $guard);
            }
            if ($passed === false) {
                return $this->hidden('guard_failed:' . $guard);
            }
        }

        // The declared "Other" companion(s): the stored selections and the "Other" text. One
        // field declares 'other_companion'; a composite reading several declares
        // 'other_companions' (name => spec). Declaring both is malformed.
        if (array_key_exists('other_companion', $entry) && array_key_exists('other_companions', $entry)) {
            return $this->hidden('other_companion_invalid');
        }
        $specs = array_key_exists('other_companions', $entry)
            ? $entry['other_companions']
            : ['default' => $entry['other_companion'] ?? null];
        if (!is_array($specs) || $specs === []) {
            return $this->hidden('other_companion_invalid');
        }
        $companions = [];
        foreach ($specs as $name => $spec) {
            $resolved = $this->companion($spec, $meta);
            if ($resolved === false || !is_string($name)) {
                return $this->hidden('other_companion_invalid');
            }
            if (($resolved['unsafe'] ?? false) === true) {
                return $this->hidden('other_companion_unsafe');
            }
            $companions[$name] = $resolved;
        }
        $companion = $companions['default'] ?? null;

        // 5. A deterministic formatter that accepts this exact value.
        $formatter = (string) ($entry['formatter'] ?? '');
        if (!$this->hasFormatter($formatter)) {
            return $this->hidden('formatter_missing');
        }
        $answer = $this->format($formatter, $value, $supporting, $companion, $companions);
        if ($answer === null) {
            return $this->hidden('formatter_rejected_value');
        }

        return ['available' => true, 'reason' => 'available', 'answer' => $answer];
    }

    // =========================================================================
    // Source resolution
    // =========================================================================

    /**
     * The sources this surface may restate beyond SnapshotFactVisibility's public tier.
     *
     * @return array<string, array<string, string>> role => [key => reason]
     */
    public static function publicQuestionAdmissions(): array
    {
        return self::PUBLIC_QUESTION_ADMISSIONS;
    }

    /**
     * Resolve 'listing.<key>' to a key that is defined for the role AND public — or, when
     * $allowAdmission is set (an 'admitted_listing' source), explicitly admitted.
     *
     * @return array{key: string|null, reason: string|null}
     */
    private function publicListingKey(mixed $path, string $role, bool $allowAdmission = false): array
    {
        if (!is_string($path) || preg_match('/^listing\.([a-z0-9_]+)$/', $path, $m) !== 1) {
            return ['key' => null, 'reason' => 'source_path_invalid'];
        }

        $key = $m[1];

        if (!array_key_exists($key, AskAiContextBuilderService::CANONICAL_SOURCE_MAP[$role] ?? [])) {
            return ['key' => null, 'reason' => 'source_not_in_context_map'];
        }

        if (SnapshotFactVisibility::classify($key, $role) === SnapshotFactVisibility::PUBLIC_ALLOWED) {
            return ['key' => $key, 'reason' => null];
        }

        // RESTRICTED keys are never admitted, whatever the admission list says.
        if (
            $allowAdmission
            && SnapshotFactVisibility::classify($key, $role) === SnapshotFactVisibility::OWNER_ONLY
            && isset(self::PUBLIC_QUESTION_ADMISSIONS[$role][$key])
        ) {
            return ['key' => $key, 'reason' => null];
        }

        return ['key' => null, 'reason' => 'not_public_allowed'];
    }

    /**
     * Resolve an entry's declared "Other" companion from meta.
     *
     * Returns null when the entry declares none; false when the declaration is malformed;
     * otherwise the stored selections, the usable "Other" text (null when "Other" is not
     * selected or its text is blank or a placeholder) and whether that text is unsafe to
     * restate (too long, multi-line, or — with reject_figures — carrying figures).
     *
     * @return array{selections: list<string>, other: string|null, unsafe: bool}|false|null
     */
    private function companion(mixed $spec, array $meta): array|false|null
    {
        if ($spec === null) {
            return null;
        }
        if (
            !is_array($spec)
            || !is_string($spec['selected_in'] ?? null) || preg_match('/^[a-z0-9_]+$/', $spec['selected_in']) !== 1
            || !is_string($spec['meta_key'] ?? null) || preg_match('/^[a-z0-9_]+$/', $spec['meta_key']) !== 1
        ) {
            return false;
        }

        $selections    = $this->storedSelections($meta[$spec['selected_in']] ?? null);
        $otherSelected = in_array('other', array_map('strtolower', $selections), true);

        $other  = null;
        $unsafe = false;
        if ($otherSelected) {
            $raw = $meta[$spec['meta_key']] ?? null;
            $text = is_scalar($raw) && !is_bool($raw) ? (string) $raw : '';
            $verdict = $this->verbatim($text, (bool) ($spec['reject_figures'] ?? false));
            if ($verdict === false) {
                $unsafe = true;
            } else {
                $other = $verdict;
            }
        }

        return ['selections' => $selections, 'other' => $other, 'unsafe' => $unsafe];
    }

    /** A stored multi-select (JSON array) or single value, as trimmed, non-empty strings. */
    private function storedSelections(mixed $raw): array
    {
        if (is_array($raw)) {
            $items = $raw;
        } elseif (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            $items   = is_array($decoded) ? $decoded : [$raw];
        } else {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn ($v): string => is_scalar($v) && !is_bool($v) ? trim((string) $v) : '', $items),
            static fn (string $v): bool => $v !== ''
        ));
    }

    /**
     * A free-text value that may be restated word for word.
     *
     * @return string|null|false  string = usable; null = blank or placeholder (nothing to say);
     *                            false = present but unsafe to restate
     */
    private function verbatim(string $text, bool $rejectFigures = false): string|null|false
    {
        $clean = trim(preg_replace('/[ \t]+/', ' ', $text) ?? '');
        if ($clean === '' || in_array(strtolower($clean), self::PLACEHOLDER_VALUES, true)) {
            return null;
        }
        if (
            preg_match('/[\r\n\x00-\x1F\x7F]/', $clean) === 1
            || mb_strlen($clean) > self::MAX_VERBATIM_LENGTH
            || ($rejectFigures && preg_match('/[0-9%$]/', $clean) === 1)
        ) {
            return false;
        }

        return $clean;
    }

    private function listingValue(array $context, string $key): mixed
    {
        $listing = $context['listing'] ?? null;

        return is_array($listing) ? ($listing[$key] ?? null) : null;
    }

    private function isEmpty(mixed $value): bool
    {
        if ($value === null || is_bool($value)) {
            return true;
        }
        if (is_array($value)) {
            return $value === [];
        }
        if (!is_scalar($value)) {
            return true;
        }

        $text = trim((string) $value);

        return $text === '' || $text === '[]' || $text === '{}';
    }

    // =========================================================================
    // Guards — hide-only
    // =========================================================================

    /** @return bool|null  null = unknown guard (the caller fails closed) */
    private function guardPasses(string $guard, array $context, array $meta): ?bool
    {
        if (str_starts_with($guard, 'meta_present:')) {
            $metaKey = substr($guard, strlen('meta_present:'));

            return $metaKey !== '' && !$this->isEmpty($meta[$metaKey] ?? null);
        }

        return match ($guard) {
            'not_bidding_period'      => ($meta['auction_type'] ?? null) !== 'Bidding Period',
            'mls_price_not_divergent' => !ListingPriceDisplay::forSeller($meta)->showsSeparateTerms(),
            'acreage_not_overridden'  => $this->acreageNotOverridden($context, $meta),
            default                   => null,
        };
    }

    /**
     * The seller page prints `min_acreage ?: total_acreage`. A legacy min_acreage that
     * disagrees with total_acreage would make the answer contradict the page.
     */
    private function acreageNotOverridden(array $context, array $meta): bool
    {
        $min = $meta['min_acreage'] ?? null;
        if ($this->isEmpty($min)) {
            return true;
        }

        return is_scalar($min) && trim((string) $min) === trim((string) $this->listingValue($context, 'total_acreage'));
    }

    // =========================================================================
    // Formatters — fixed sentences; null means "cannot state this exactly"
    // =========================================================================

    private function hasFormatter(string $formatter): bool
    {
        return in_array($formatter, [
            'asking_price', 'bedroom_count', 'bathroom_count', 'heated_square_feet', 'year_built',
            'annual_property_taxes', 'hoa_fee', 'acreage_band', 'appliance_list', 'utility_list',
            'pets_allowed',
            // Batch 2b
            'has_pool', 'has_garage', 'zoning', 'roof_type_list', 'leasing_restrictions',
            'community_amenity_list', 'financing_types',
            // Batch 2c
            'hoa_fee_coverage', 'cdd_fee',
        ], true);
    }

    /**
     * @param array<string,mixed>                                                                 $supporting
     * @param array{selections: list<string>, other: string|null, unsafe: bool}|null               $companion
     * @param array<string, array{selections: list<string>, other: string|null, unsafe: bool}|null> $companions
     */
    private function format(string $formatter, mixed $value, array $supporting, ?array $companion = null, array $companions = []): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }
        $text = trim((string) $value);

        // Seller and landlord name the same HOA facts differently in context.
        $hasHoa = $supporting['hoa_association'] ?? $supporting['has_hoa'] ?? null;

        return match ($formatter) {
            'asking_price'           => $this->withMoney($text, fn (string $m) => "The asking price is {$m}."),
            'bedroom_count'          => $this->bedrooms($text),
            'bathroom_count'         => $this->bathrooms($text),
            'heated_square_feet'     => $this->heatedSquareFeet($text),
            'year_built'             => $this->yearBuilt($text),
            'annual_property_taxes'  => $this->annualTaxes($text, $supporting['tax_year'] ?? null),
            'hoa_fee'                => $this->hoaFee(
                                            $text,
                                            $hasHoa,
                                            $supporting['hoa_payment_schedule'] ?? $supporting['association_fee_frequency'] ?? null,
                                            $companion['other'] ?? null
                                        ),
            'acreage_band'           => $this->acreageBand($text),
            'appliance_list'         => $this->list($text, 'Appliances listed for this property'),
            'utility_list'           => $this->list($text, 'Utilities listed for this property'),
            'pets_allowed'           => $this->petsAllowed(
                                            $text,
                                            $supporting['number_of_pets_allowed'] ?? null,
                                            $supporting['max_pet_weight'] ?? null
                                        ),
            'has_pool'               => $this->yesNo($text, 'This property has a pool.', 'This property does not have a pool.'),
            'has_garage'             => $this->yesNo($text, 'This property has a garage.', 'This property does not have a garage.'),
            'zoning'                 => $this->zoning($text),
            'roof_type_list'         => $this->selectionList($companion, 'Roof type listed for this property', 'Roof types listed for this property'),
            'community_amenity_list' => $this->hoaOnly($hasHoa, fn () => $this->selectionList($companion, 'Community amenity listed for this property', 'Community amenities listed for this property')),
            'leasing_restrictions'   => $this->hoaOnly($hasHoa, fn () => $this->yesNo(
                                            $text,
                                            'The listing indicates there are leasing restrictions.',
                                            'The listing indicates there are no leasing restrictions.'
                                        )),
            'financing_types'        => $this->financingTypes($companion),
            'hoa_fee_coverage'       => $this->hoaFeeCoverage(
                                            $text,
                                            $hasHoa,
                                            $supporting['hoa_payment_schedule'] ?? $supporting['association_fee_frequency'] ?? null,
                                            $companions['frequency'] ?? null,
                                            $companions['includes'] ?? null
                                        ),
            'cdd_fee'                => $this->cddFee($text, $supporting['annual_cdd_fee'] ?? null),
            default                  => null,
        };
    }

    private function bedrooms(string $text): ?string
    {
        if (preg_match('/^\d+$/', $text) !== 1 || (int) $text < 1) {
            return null;
        }
        $n = (int) $text;

        return 'This property has ' . $n . ($n === 1 ? ' bedroom.' : ' bedrooms.');
    }

    private function bathrooms(string $text): ?string
    {
        $number = $this->plainNumber($text);
        if ($number === null) {
            return null;
        }

        return 'This property has ' . $number . ($number === '1' ? ' bathroom.' : ' bathrooms.');
    }

    private function heatedSquareFeet(string $text): ?string
    {
        $raw = str_replace(',', '', $text);
        if (preg_match('/^\d+(\.\d+)?$/', $raw) !== 1 || (float) $raw <= 0) {
            return null;
        }

        return 'The heated square footage is ' . $this->groupedNumber($raw, false) . ' square feet.';
    }

    private function yearBuilt(string $text): ?string
    {
        if (preg_match('/^\d{4}$/', $text) !== 1) {
            return null;
        }
        $year = (int) $text;
        if ($year < self::YEAR_BUILT_MIN || $year > self::YEAR_BUILT_MAX) {
            return null;
        }

        return "This property was built in {$year}.";
    }

    private function annualTaxes(string $text, mixed $taxYear): ?string
    {
        return $this->withMoney($text, function (string $money) use ($taxYear): string {
            $year = is_scalar($taxYear) ? trim((string) $taxYear) : '';

            return preg_match('/^\d{4}$/', $year) === 1
                ? "Annual property taxes are {$money} for tax year {$year}."
                : "Annual property taxes are {$money}.";
        });
    }

    /**
     * Only published when the listing currently says it HAS an HOA: an amount left behind
     * after the seller answered "No" or "Unknown" is a stale child value, not a fee.
     */
    private function hoaFee(string $text, mixed $hasHoa, mixed $frequency, ?string $frequencyOther): ?string
    {
        $clause = $this->hoaFeeClause($text, $hasHoa, $frequency, $frequencyOther);

        return $clause === null ? null : $clause . '.';
    }

    /**
     * "The HOA fee is $250 per month" — the fee statement without its full stop, shared by the
     * fee question and the fee-and-coverage composite so the two can never word it differently.
     */
    private function hoaFeeClause(string $text, mixed $hasHoa, mixed $frequency, ?string $frequencyOther): ?string
    {
        if (!$this->isYes($hasHoa)) {
            return null;
        }

        return $this->withMoney($text, function (string $money) use ($frequency, $frequencyOther): string {
            $key = is_scalar($frequency) && !is_bool($frequency)
                ? (string) preg_replace('/[\s_]+/', '-', strtolower(trim((string) $frequency)))
                : '';

            if ($key === 'one-time') {
                return "The HOA fee is a one-time fee of {$money}";
            }

            $phrase = self::HOA_FREQUENCY_PHRASES[$key] ?? null;
            if ($phrase !== null) {
                return "The HOA fee is {$money} {$phrase}";
            }

            // "Other" with the owner's own frequency text: restated as written, never
            // reinterpreted into a period.
            if ($key === 'other' && $frequencyOther !== null) {
                return "The HOA fee is {$money} (frequency: {$frequencyOther})";
            }

            return "The HOA fee is {$money}";
        });
    }

    /**
     * Batch 2c composite: the fee AND what it covers, from structured fields only. Both halves
     * are required — no valid fee, or nothing it covers, and this returns null so the narrower
     * fee question stands in (see narrower_of). Nothing is inferred about coverage.
     *
     * @param array{selections: list<string>, other: string|null, unsafe: bool}|null $frequency
     * @param array{selections: list<string>, other: string|null, unsafe: bool}|null $includes
     */
    private function hoaFeeCoverage(string $text, mixed $hasHoa, mixed $frequencyValue, ?array $frequency, ?array $includes): ?string
    {
        $clause = $this->hoaFeeClause($text, $hasHoa, $frequencyValue, $frequency['other'] ?? null);
        if ($clause === null) {
            return null;
        }

        $items = [];
        foreach ($this->selectionItems($includes) as $item) {
            // Form options read as ordinary words mid-sentence ("Common Area Maintenance" →
            // "common area maintenance"; "Cable TV" → "cable TV"). The owner's own "Other"
            // text is restated exactly as written.
            $items[] = ($includes !== null && $item === $includes['other'])
                ? $item
                : $this->sentenceCase($item);
        }
        if ($items === []) {
            return null;
        }

        return $clause . ' and includes ' . $this->joinList($items) . '.';
    }

    /** "A", "A and B", "A, B and C". */
    private function joinList(array $items): string
    {
        $items = array_values($items);
        if (count($items) <= 2) {
            return implode(' and ', $items);
        }
        $last = array_pop($items);

        return implode(', ', $items) . ' and ' . $last;
    }

    /** Lower-case capitalised words ("Grounds" → "grounds"); leave acronyms ("TV") and anything else. */
    private function sentenceCase(string $item): string
    {
        return implode(' ', array_map(
            static fn (string $word): string => preg_match('/^[A-Z][a-z]+$/', $word) === 1 ? strtolower($word) : $word,
            explode(' ', $item)
        ));
    }

    /**
     * Batch 2c: "Is there a CDD fee?" from has_cdd and annual_cdd_fee. A stated "No" is
     * restated as nothing listed (never "no CDD exists"); "Yes" with a valid amount states
     * the annual fee; "Yes" without one says only that a CDD is listed. Anything else hides.
     */
    private function cddFee(string $hasCdd, mixed $annualFee): ?string
    {
        return match (strtolower($hasCdd)) {
            'no'  => 'There is no CDD fee listed for this property.',
            'yes' => (is_scalar($annualFee) && !is_bool($annualFee)
                        ? $this->withMoney(trim((string) $annualFee), fn (string $m) => "The annual CDD fee is {$m}.")
                        : null)
                     ?? 'This property is listed as having a CDD.',
            default => null,
        };
    }

    private function isYes(mixed $value): bool
    {
        return is_scalar($value) && !is_bool($value) && strtolower(trim((string) $value)) === 'yes';
    }

    /** Exactly "Yes" or "No" (any case); anything else — Unknown, Not Applicable, Optional — hides. */
    private function yesNo(string $text, string $yes, string $no): ?string
    {
        return match (strtolower($text)) {
            'yes'   => $yes,
            'no'    => $no,
            default => null,
        };
    }

    /**
     * Facts the page publishes only inside its HOA / Association block — leasing restrictions
     * and community amenities — are published only while the listing says it HAS an HOA.
     *
     * @param callable(): ?string $answer
     */
    private function hoaOnly(mixed $hasHoa, callable $answer): ?string
    {
        return $this->isYes($hasHoa) ? $answer() : null;
    }

    /** A zoning designation, restated word for word when it is short, single-line and real. */
    private function zoning(string $text): ?string
    {
        $value = $this->verbatim($text);

        return is_string($value) ? "The zoning is listed as {$value}." : null;
    }

    /**
     * A multi-select answered from its stored selections: "Other" becomes the owner's "Other"
     * text when that text is usable and is dropped otherwise; placeholders are dropped.
     *
     * @param array{selections: list<string>, other: string|null, unsafe: bool}|null $companion
     */
    private function selectionItems(?array $companion): array
    {
        if ($companion === null) {
            return [];
        }

        $items = [];
        foreach ($companion['selections'] as $selection) {
            if (strtolower($selection) === 'other') {
                if ($companion['other'] !== null) {
                    $items[] = $companion['other'];
                }
                continue;
            }
            $value = $this->verbatim($selection);
            if (is_string($value)) {
                $items[] = $value;
            }
        }

        return array_values(array_unique($items));
    }

    /** @param array{selections: list<string>, other: string|null, unsafe: bool}|null $companion */
    private function selectionList(?array $companion, string $singular, string $plural): ?string
    {
        $items = $this->selectionItems($companion);
        if ($items === []) {
            return null;
        }

        return (count($items) === 1 ? $singular : $plural) . ': ' . implode(', ', $items) . '.';
    }

    /**
     * The financing types the seller will consider, exactly as selected. The "Other" text is
     * read with reject_figures, so a typed rate, amount or percentage hides the question
     * rather than publishing a financing TERM.
     *
     * @param array{selections: list<string>, other: string|null, unsafe: bool}|null $companion
     */
    private function financingTypes(?array $companion): ?string
    {
        $items = $this->selectionItems($companion);
        if ($items === []) {
            return null;
        }

        if (count($items) === 1) {
            return "The seller has indicated they will consider the following financing type: {$items[0]}.";
        }

        $last = array_pop($items);

        return 'The seller has indicated they will consider the following financing types: '
            . implode(', ', $items) . ' and ' . $last . '.';
    }

    /** Only an exact band from the form's own option list; 'Non-Applicable' is not a size. */
    private function acreageBand(string $text): ?string
    {
        $bands = array_values(array_diff(
            (array) config('property_types.acreage_options', []),
            ['Non-Applicable']
        ));

        return in_array($text, $bands, true) ? "The total acreage is {$text}." : null;
    }

    private function list(string $text, string $lead): ?string
    {
        // A value that still looks like raw JSON was not decoded by the context builder.
        if (str_starts_with($text, '[') || str_starts_with($text, '{')) {
            return null;
        }

        $items = array_values(array_unique(array_filter(
            array_map('trim', explode(',', $text)),
            static fn (string $item): bool => $item !== '' && strtolower($item) !== 'other'
        )));

        return $items === [] ? null : $lead . ': ' . rtrim(implode(', ', $items), '.') . '.';
    }

    /**
     * The Yes / No pet policy, optionally followed (for "Yes" only) by the listing's own
     * structured limits: a whole-number count of pets allowed and a maximum weight per pet.
     * A value that is not exactly a number is left out rather than interpreted, and the
     * "No" sentence is never changed. Only pet-POLICY fields of the listing are passed in —
     * never an applicant's pets, breeds, service or support animals.
     */
    private function petsAllowed(string $text, mixed $count = null, mixed $maxWeight = null): ?string
    {
        return match (strtolower($text)) {
            'yes' => trim('Pets are allowed at this property. ' . $this->petCountSentence($count) . ' ' . $this->petWeightSentence($maxWeight)),
            'no'  => "Pets are not allowed under the property's pet policy. Assistance animals are handled separately under applicable law.",
            default => null,
        };
    }

    private function petCountSentence(mixed $count): string
    {
        if (!is_scalar($count) || is_bool($count) || preg_match('/^\s*(\d{1,2})\s*$/', (string) $count, $m) !== 1) {
            return '';
        }
        $n = (int) $m[1];
        if ($n < 1) {
            return '';
        }

        return $n === 1 ? 'Up to 1 pet is permitted.' : "Up to {$n} pets are permitted.";
    }

    private function petWeightSentence(mixed $weight): string
    {
        if (!is_scalar($weight) || is_bool($weight) || preg_match('/^\s*(\d{1,3})\s*(lbs?\.?)?\s*$/i', (string) $weight, $m) !== 1) {
            return '';
        }
        $lbs = (int) $m[1];
        if ($lbs < 1) {
            return '';
        }

        return "The maximum weight per pet is {$lbs} lbs.";
    }

    // =========================================================================
    // Number helpers
    // =========================================================================

    /** @param callable(string): string $sentence */
    private function withMoney(string $text, callable $sentence): ?string
    {
        // Read the sign before stripping currency characters, or "-5000" becomes "5000".
        if (str_starts_with($text, '-')) {
            return null;
        }

        $raw = str_replace(['$', ',', ' '], '', $text);
        if (preg_match('/^\d+(\.\d+)?$/', $raw) !== 1 || (float) $raw <= 0) {
            return null;
        }

        return $sentence('$' . $this->groupedNumber($raw, true));
    }

    /** "2.50" → "2.5", "3.0" → "3"; null for anything that is not a positive number. */
    private function plainNumber(string $text): ?string
    {
        if (preg_match('/^\d+(\.\d+)?$/', $text) !== 1 || (float) $text <= 0) {
            return null;
        }

        return str_contains($text, '.') ? rtrim(rtrim($text, '0'), '.') : ltrim($text, '0');
    }

    /** "1850" → "1,850", "1856.4" → "1,856.40" as money. Never rounds a stated figure. */
    private function groupedNumber(string $raw, bool $money): string
    {
        $fraction = null;
        if (str_contains($raw, '.')) {
            [$raw, $fraction] = explode('.', $raw, 2);
            $fraction = rtrim($fraction, '0');
        }

        $whole = number_format((int) $raw);

        if ($fraction === null || $fraction === '') {
            return $whole;
        }

        return $whole . '.' . ($money ? str_pad($fraction, 2, '0') : $fraction);
    }

    /** @return array{available: false, reason: string, answer: null} */
    private function hidden(string $reason): array
    {
        return ['available' => false, 'reason' => $reason, 'answer' => null];
    }
}
