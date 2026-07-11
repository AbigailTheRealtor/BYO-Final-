<?php

namespace App\Services\Pets;

/**
 * Canonical pet-fee normalization (#2 Part B).
 *
 * ONE canonical representation — pet_fee_type + pet_fee_amount + pet_fee_other — read
 * through a deterministic precedence over five retired legacy fee fields. This class is
 * PURE: it never writes, and it never mutates stored historical values. Every reader
 * (detail views, agent views, AskAi, AgentAi, the canonical adapter) goes through it so
 * legacy records keep displaying exactly what they displayed before.
 *
 * PRECEDENCE
 *   1. pet_fee_type populated                    → canonical
 *   2. structured legacy amounts                 → derived
 *   3. pet_deposit_fee_rent free text            → passed through verbatim, never parsed
 *   4. nothing                                   → no fee information
 *
 * LEGACY DERIVATION
 *   - exactly one charge bucket populated  → that bucket's canonical type + amount
 *   - pet_rent and pet_monthly_fee equal   → Monthly Pet Fee, single amount (both are a
 *                                            recurring monthly pet charge for this product)
 *   - pet_rent and pet_monthly_fee differ  → Other, BOTH values preserved in the text
 *   - >1 materially different bucket       → Other, EVERY label + value preserved
 *   - any of the above coexisting with a
 *     pet_deposit_fee_rent free-text value → Other, the free text appended verbatim
 *
 * Nothing is ever silently dropped: if a historical record carries several amounts, they
 * all survive into the explanatory text rather than one being arbitrarily chosen.
 *
 * pet_deposit_fee_rent is an unconstrained legacy string with no option vocabulary and no
 * input control anywhere in the app. It is NEVER parsed and NEVER written from here — it
 * is surfaced verbatim as explanatory text and left untouched in storage.
 *
 * NOT pet-fee data, and deliberately untouched by this class: pet_policy,
 * pet_max_weight_lbs, pet_species_allowed, pet_policy_requirement, pet_restrictions.
 * Those are pet POLICY / RESTRICTION fields, not charges.
 */
class PetFeeNormalizer
{
    public const TYPE_ONE_TIME_REFUNDABLE = 'One Time Fee Refundable';
    public const TYPE_NON_REFUNDABLE      = 'Non Refundable';
    public const TYPE_MONTHLY             = 'Monthly Pet Fee';
    public const TYPE_NONE                = 'No Pet Fee';
    public const TYPE_OTHER               = 'Other';

    /** The approved dropdown vocabulary, in display order. */
    public const TYPES = [
        self::TYPE_ONE_TIME_REFUNDABLE,
        self::TYPE_NON_REFUNDABLE,
        self::TYPE_MONTHLY,
        self::TYPE_NONE,
        self::TYPE_OTHER,
    ];

    /** Canonical meta keys. */
    public const KEY_TYPE   = 'pet_fee_type';
    public const KEY_AMOUNT = 'pet_fee_amount';
    public const KEY_OTHER  = 'pet_fee_other';

    /** The five retired legacy fee fields. Read-only: never written, never deleted. */
    public const LEGACY_AMOUNT_KEYS = [
        'pet_deposit_amount',
        'pet_monthly_fee',
        'pet_rent',
        'pet_fee',
    ];
    public const LEGACY_FREE_TEXT_KEY = 'pet_deposit_fee_rent';

    /** Human labels used when several legacy amounts must be preserved in one string. */
    private const LEGACY_LABELS = [
        'pet_deposit_amount' => 'refundable deposit',
        'pet_fee'            => 'non-refundable fee',
        'pet_monthly_fee'    => 'monthly pet fee',
        'pet_rent'           => 'pet rent',
    ];

    /**
     * Normalize from a *AgentAuction model exposing info($key) (returns false when absent).
     */
    public function fromModel(object $model): array
    {
        $keys = array_merge(
            [self::KEY_TYPE, self::KEY_AMOUNT, self::KEY_OTHER],
            self::LEGACY_AMOUNT_KEYS,
            [self::LEGACY_FREE_TEXT_KEY]
        );

        $meta = [];
        foreach ($keys as $key) {
            $raw = $model->info($key);
            $meta[$key] = ($raw === false) ? null : $raw;
        }

        return $this->normalize($meta);
    }

    /**
     * @param array<string,mixed> $meta raw meta values keyed by meta_key
     * @return array{
     *     type: ?string, amount: ?float, other_text: ?string, source: string,
     *     legacy_source_fields: list<string>, has_fee: bool, recurring: ?bool, display: string
     * }
     */
    public function normalize(array $meta): array
    {
        // ── 1. Canonical wins outright, even over stale legacy rows left in storage. ──
        $type = $this->str($meta[self::KEY_TYPE] ?? null);
        if ($type !== null && in_array($type, self::TYPES, true)) {
            return $this->canonical(
                $type,
                $this->money($meta[self::KEY_AMOUNT] ?? null),
                $this->str($meta[self::KEY_OTHER] ?? null)
            );
        }

        // ── 2. Structured legacy amounts. ──
        $amounts = [];
        foreach (self::LEGACY_AMOUNT_KEYS as $key) {
            $value = $this->money($meta[$key] ?? null);
            if ($value !== null && $value > 0) {
                $amounts[$key] = $value;
            }
        }

        $freeText = $this->str($meta[self::LEGACY_FREE_TEXT_KEY] ?? null);

        if ($amounts !== []) {
            return $this->fromLegacyAmounts($amounts, $freeText);
        }

        // ── 3. Free-text fallback, surfaced verbatim and never parsed. ──
        if ($freeText !== null) {
            return $this->result(
                self::TYPE_OTHER,
                null,
                $freeText,
                'legacy_free_text',
                [self::LEGACY_FREE_TEXT_KEY],
                true,
                null,
                $freeText
            );
        }

        // ── 4. No fee information at all. ──
        return $this->result(null, null, null, 'none', [], false, null, '');
    }

    /** @return list<string> the five retired legacy keys, for inventory/tests. */
    public static function legacyKeys(): array
    {
        return array_merge(self::LEGACY_AMOUNT_KEYS, [self::LEGACY_FREE_TEXT_KEY]);
    }

    private function canonical(string $type, ?float $amount, ?string $otherText): array
    {
        if ($type === self::TYPE_NONE) {
            // "No Pet Fee" is a definite statement: no amount, no explanatory text.
            return $this->result($type, null, null, 'canonical', [], false, null, self::TYPE_NONE);
        }

        if ($type === self::TYPE_OTHER) {
            // Other carries required explanatory text and an OPTIONAL amount. Its
            // recurring/one-time nature is genuinely unknown — we do not guess it.
            $display = $otherText ?? '';
            if ($amount !== null && $amount > 0 && $display !== '') {
                $display = $this->fmt($amount) . ' — ' . $display;
            } elseif ($amount !== null && $amount > 0) {
                $display = $this->fmt($amount);
            }

            return $this->result(
                $type,
                $amount,
                $otherText,
                'canonical',
                [],
                ($amount !== null && $amount > 0) || $otherText !== null,
                null, // recurring: deliberately unclassified
                $display
            );
        }

        $recurring = ($type === self::TYPE_MONTHLY);
        $display   = $amount !== null && $amount > 0
            ? $this->fmt($amount) . ' ' . $this->lowerLabel($type)
            : $type;

        return $this->result(
            $type,
            $amount,
            null,
            'canonical',
            [],
            $amount !== null && $amount > 0,
            $recurring,
            $display
        );
    }

    /**
     * @param array<string,float> $amounts non-empty, positive legacy amounts
     */
    private function fromLegacyAmounts(array $amounts, ?string $freeText): array
    {
        $sources = array_keys($amounts);

        $monthly = $amounts['pet_monthly_fee'] ?? null;
        $rent    = $amounts['pet_rent'] ?? null;

        // pet_rent and pet_monthly_fee are both a recurring monthly pet charge. Equal
        // values are the same charge recorded twice → collapse to one Monthly Pet Fee.
        // Different values are a genuine conflict → Other, preserving BOTH.
        $recurringConflict = $monthly !== null && $rent !== null && $monthly !== $rent;

        $buckets = [];
        if (isset($amounts['pet_deposit_amount'])) {
            $buckets['refundable'] = true;
        }
        if (isset($amounts['pet_fee'])) {
            $buckets['non_refundable'] = true;
        }
        if ($monthly !== null || $rent !== null) {
            $buckets['monthly'] = true;
        }

        $mustUseOther = $recurringConflict
            || count($buckets) > 1
            || $freeText !== null;

        if ($mustUseOther) {
            $text = $this->describe($amounts, $freeText);

            return $this->result(
                self::TYPE_OTHER,
                null, // several materially different charges — no single canonical amount
                $text,
                'legacy_structured',
                $freeText !== null ? array_merge($sources, [self::LEGACY_FREE_TEXT_KEY]) : $sources,
                true,
                null, // deliberately unclassified
                $text
            );
        }

        // Exactly one charge bucket.
        if (isset($buckets['refundable'])) {
            return $this->legacySingle(self::TYPE_ONE_TIME_REFUNDABLE, $amounts['pet_deposit_amount'], $sources, false);
        }
        if (isset($buckets['non_refundable'])) {
            return $this->legacySingle(self::TYPE_NON_REFUNDABLE, $amounts['pet_fee'], $sources, false);
        }

        // Monthly: either field alone, or both carrying the identical value.
        return $this->legacySingle(self::TYPE_MONTHLY, $monthly ?? $rent, $sources, true);
    }

    /** @param list<string> $sources */
    private function legacySingle(string $type, float $amount, array $sources, bool $recurring): array
    {
        return $this->result(
            $type,
            $amount,
            null,
            'legacy_structured',
            $sources, // diagnostic: preserves whether it came from pet_rent or pet_monthly_fee
            true,
            $recurring,
            $this->fmt($amount) . ' ' . $this->lowerLabel($type)
        );
    }

    /**
     * Preserve EVERY meaningful legacy value in one explanatory string, e.g.
     * "$100 refundable deposit and $200 non-refundable fee".
     *
     * @param array<string,float> $amounts
     */
    private function describe(array $amounts, ?string $freeText): string
    {
        $parts = [];
        foreach (self::LEGACY_LABELS as $key => $label) {
            if (isset($amounts[$key])) {
                $parts[] = $this->fmt($amounts[$key]) . ' ' . $label;
            }
        }

        if ($freeText !== null) {
            $parts[] = $freeText;
        }

        if (count($parts) <= 1) {
            return $parts[0] ?? '';
        }

        $last = array_pop($parts);

        return implode(', ', $parts) . ' and ' . $last;
    }

    private function lowerLabel(string $type): string
    {
        return [
            self::TYPE_ONE_TIME_REFUNDABLE => 'refundable deposit',
            self::TYPE_NON_REFUNDABLE      => 'non-refundable fee',
            self::TYPE_MONTHLY             => 'monthly pet fee',
        ][$type] ?? $type;
    }

    /** @param list<string> $sources */
    private function result(
        ?string $type,
        ?float $amount,
        ?string $otherText,
        string $source,
        array $sources,
        bool $hasFee,
        ?bool $recurring,
        string $display
    ): array {
        return [
            'type'                 => $type,
            'amount'               => $amount,
            'other_text'           => $otherText,
            'source'               => $source,
            'legacy_source_fields' => $sources,
            'has_fee'              => $hasFee,
            'recurring'            => $recurring,
            'display'              => $display,
        ];
    }

    private function fmt(float $amount): string
    {
        return '$' . (floor($amount) == $amount
            ? number_format($amount, 0)
            : number_format($amount, 2));
    }

    /** @param mixed $raw */
    private function str($raw): ?string
    {
        if ($raw === null || $raw === false || is_array($raw)) {
            return null;
        }
        $value = trim((string) $raw);

        return $value === '' ? null : $value;
    }

    /** @param mixed $raw */
    private function money($raw): ?float
    {
        $value = $this->str($raw);
        if ($value === null) {
            return null;
        }
        $value = str_replace([',', '$', ' '], '', $value);

        return is_numeric($value) ? (float) $value : null;
    }
}
