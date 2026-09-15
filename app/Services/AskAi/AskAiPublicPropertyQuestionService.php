<?php

namespace App\Services\AskAi;

use App\Services\AskAi\Snapshot\SnapshotFactVisibility;
use App\Support\Listing\ListingPriceDisplay;

/**
 * AskAiPublicPropertyQuestionService — "Questions About This Property" (Batch 1)
 *
 * GOVERNANCE BLOCK:
 * ==================================================================================
 * ROLE: Turns the approved catalog in
 * AskAiFieldQuestionRegistryService::publicPropertyQuestionRegistry() into verified,
 * precomputed question/answer pairs for the PUBLIC Seller and Landlord listing pages.
 * Every answer is a fixed sentence built from one listing value the page already
 * assembled into its Ask AI chip context.
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
 *   - Make any fact public. Visibility is SnapshotFactVisibility's decision alone.
 * ==================================================================================
 *
 * THE AVAILABILITY RULE (evaluate()) — a question is shown only when ALL hold:
 *   1. its role is seller or landlord, and the entry is in the catalog for that role;
 *   2. source_path is exactly one 'listing.<key>' path, and <key> is a context key the
 *      context builder defines for that role (CANONICAL_SOURCE_MAP);
 *   3. SnapshotFactVisibility::classify(<key>, role) is 'public_allowed' — for the
 *      source AND every supporting path the formatter reads;
 *   4. the source value is present and meaningful;
 *   5. the named formatter exists and accepts the value (a formatter returns null for
 *      anything it cannot state exactly, which hides the question);
 *   6. every named guard passes. Guards only ever HIDE — they resolve ambiguity between
 *      the context value and what the page itself publishes, and never expose a value.
 * Anything unrecognised — role, path, key, formatter, guard — fails closed.
 */
class AskAiPublicPropertyQuestionService
{
    /** Roles whose listings describe a property. Mirrors SnapshotFactVisibility. */
    private const ELIGIBLE_ROLES = ['seller', 'landlord'];

    /** Frequencies with an unambiguous English phrase. Anything else gets no suffix. */
    private const HOA_FREQUENCY_PHRASES = [
        'monthly'       => 'per month',
        'quarterly'     => 'per quarter',
        'semi-annually' => 'every six months',
        'annually'      => 'per year',
    ];

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

        $questions = [];
        foreach (AskAiFieldQuestionRegistryService::publicPropertyQuestionRegistry() as $id => $entry) {
            if (($entry['role'] ?? null) !== $role) {
                continue;
            }

            $result = $this->evaluate($entry, $role, $context, $meta);
            if (!$result['available']) {
                continue;
            }

            $questions[] = [
                'id'          => (string) $id,
                'question'    => (string) $entry['question'],
                'answer'      => $result['answer'],
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

        // 2 + 3. One exact, defined, public source — and the same for every supporting path.
        $sourceKey = $this->publicListingKey($entry['source_path'] ?? null, $role);
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

        // 5. A deterministic formatter that accepts this exact value.
        $formatter = (string) ($entry['formatter'] ?? '');
        if (!$this->hasFormatter($formatter)) {
            return $this->hidden('formatter_missing');
        }
        $answer = $this->format($formatter, $value, $supporting);
        if ($answer === null) {
            return $this->hidden('formatter_rejected_value');
        }

        return ['available' => true, 'reason' => 'available', 'answer' => $answer];
    }

    // =========================================================================
    // Source resolution
    // =========================================================================

    /**
     * Resolve 'listing.<key>' to a key that is defined for the role AND public.
     *
     * @return array{key: string|null, reason: string|null}
     */
    private function publicListingKey(mixed $path, string $role): array
    {
        if (!is_string($path) || preg_match('/^listing\.([a-z0-9_]+)$/', $path, $m) !== 1) {
            return ['key' => null, 'reason' => 'source_path_invalid'];
        }

        $key = $m[1];

        if (!array_key_exists($key, AskAiContextBuilderService::CANONICAL_SOURCE_MAP[$role] ?? [])) {
            return ['key' => null, 'reason' => 'source_not_in_context_map'];
        }

        if (SnapshotFactVisibility::classify($key, $role) !== SnapshotFactVisibility::PUBLIC_ALLOWED) {
            return ['key' => null, 'reason' => 'not_public_allowed'];
        }

        return ['key' => $key, 'reason' => null];
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
        ], true);
    }

    /** @param array<string,mixed> $supporting */
    private function format(string $formatter, mixed $value, array $supporting): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }
        $text = trim((string) $value);

        return match ($formatter) {
            'asking_price'          => $this->withMoney($text, fn (string $m) => "The asking price is {$m}."),
            'bedroom_count'         => $this->bedrooms($text),
            'bathroom_count'        => $this->bathrooms($text),
            'heated_square_feet'    => $this->heatedSquareFeet($text),
            'year_built'            => $this->yearBuilt($text),
            'annual_property_taxes' => $this->annualTaxes($text, $supporting['tax_year'] ?? null),
            'hoa_fee'               => $this->hoaFee($text, $supporting['hoa_association'] ?? null, $supporting['hoa_payment_schedule'] ?? null),
            'acreage_band'          => $this->acreageBand($text),
            'appliance_list'        => $this->list($text, 'Appliances listed for this property'),
            'utility_list'          => $this->list($text, 'Utilities listed for this property'),
            'pets_allowed'          => $this->petsAllowed($text),
            default                 => null,
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
    private function hoaFee(string $text, mixed $hasHoa, mixed $frequency): ?string
    {
        if (!is_scalar($hasHoa) || strtolower(trim((string) $hasHoa)) !== 'yes') {
            return null;
        }

        return $this->withMoney($text, function (string $money) use ($frequency): string {
            $key    = is_scalar($frequency) ? strtolower(trim((string) $frequency)) : '';
            $phrase = self::HOA_FREQUENCY_PHRASES[$key] ?? null;

            return $phrase !== null
                ? "The HOA fee is {$money} {$phrase}."
                : "The HOA fee is {$money}.";
        });
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

    private function petsAllowed(string $text): ?string
    {
        return match (strtolower($text)) {
            'yes' => 'Pets are allowed at this property.',
            'no'  => "Pets are not allowed under the property's pet policy. Assistance animals are handled separately under applicable law.",
            default => null,
        };
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
