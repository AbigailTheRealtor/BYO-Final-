<?php

namespace App\Support\OfferListing;

/**
 * The Your Terms summary on the MLS Quick Import review step.
 *
 * THE DEFECT THIS CLOSES
 * ----------------------
 * The review list was built by looping every canonical terms field and printing
 * any non-empty value. That is wrong for one specific shape of field: the `$` /
 * `%` selector that sits BESIDE an amount. Those controls are never blank — they
 * default to `$` or `%` — so they printed as their own rows, with the toggle's
 * symbol as the "answer", whether or not their branch was ever chosen:
 *
 *     Additional deposit type    $
 *     Assumable fee type         $
 *     Down payment type          %
 *     Gap payment type           $
 *     Initial deposit type       $
 *     Seller financing type      $
 *
 * A seller reviewing a cash sale was shown six rows about assumable mortgages
 * and seller financing they had not selected.
 *
 * WHAT A `*_type` FIELD ACTUALLY IS — AND WHY THE LIST BELOW IS NOT A NEW MAP
 * ---------------------------------------------------------------------------
 * Not every `*_type` field is a toggle. The canonical partial has ten of them and
 * they are two different kinds of thing:
 *
 *   MODIFIERS — the select offers exactly `$` and `%`. It is not an answer, it is
 *   the unit of the amount next to it. The finished listing page already treats
 *   them this way and never gives one a row of its own; it passes them as the
 *   second argument to {@see ConditionalTerms::amount()}:
 *
 *       $row('Assumption Fee', $terms::amount($str('assumable_fee_amount'),
 *                                             $str('assumable_fee_type')))
 *
 *   ANSWERS — `assumable_loan_type` offers FHA/VA/…, `seller_amortization_type`
 *   offers "Fully Amortizing", `cryptocurrency_type` is free text ("Bitcoin").
 *   These are real answers and keep their rows.
 *
 * So MODIFIERS below is not an invented classification. It is read off the
 * canonical markup — a `*_type` control whose only options are `$` and `%` — and
 * {@see \Tests\Feature\ListingImport\MlsQuickImportReviewPresentationTest}
 * asserts exactly that against the partial, so a control that changes shape
 * breaks the build instead of silently reappearing as a bare `$`.
 *
 * `seller_financing_type` is a modifier with no amount beside it in the canonical
 * field set, so it has nothing to format and is simply suppressed. That is the
 * honest answer: printing "$" on its own says nothing.
 *
 * WHY THIS DEFERS EVERY DECISION TO ConditionalTerms
 * --------------------------------------------------
 * `ConditionalTerms` is already the one place that knows how a Your Terms value
 * is published — `answered()` for whether a value counts, `amount()` for the
 * money/percentage pairing, `valueWithOther()` for an "Other" free-text
 * companion. The review screen and the finished listing must not be able to
 * disagree about any of that, so this class decides nothing itself: it selects
 * which fields to consider and hands every actual judgement to ConditionalTerms.
 */
final class QuickImportTermsReview
{
    /**
     * `$` / `%` selectors, mapped to the amount field they format.
     *
     * A null partner means the modifier has no amount in the canonical set and
     * is dropped outright.
     *
     * @var array<string, string|null>
     */
    private const SELLER_MODIFIERS = [
        'additional_deposit_type' => 'additional_deposit_requested',
        'assignment_fee_type'     => 'assignment_fee_amount',
        'assumable_fee_type'      => 'assumable_fee_amount',
        'down_payment_type'       => 'down_payment_amount',
        'gap_payment_type'        => 'gap_payment_amount',
        'initial_deposit_type'    => 'initial_deposit_requested',
        'seller_financing_type'   => null,
    ];

    /** @var array<string, string|null> */
    private const LANDLORD_MODIFIERS = [
        'pet_fee_type' => 'pet_fee_amount',
    ];

    /**
     * Field name → the label the person answering it actually saw.
     *
     * Only fields whose storage key reads as something else to a human. The
     * derived label is otherwise kept, because inventing a second label list that
     * could disagree with the tab is worse than a plainly-derived one.
     *
     * `maximum_budget` is the headline case: it is the meta key behind the
     * Seller's "Desired Sale Price" input, and showing a seller "Maximum budget
     * 385000" on the screen where they confirm their own asking price is the
     * review contradicting the form.
     *
     * @var array<string, string>
     */
    private const LABELS = [
        'maximum_budget'              => 'Desired Sale Price',
        'desired_rental_amount'       => 'Desired Rental Amount',
        'starting_price'              => 'Starting Price',
        'reserve_price'               => 'Reserve Price',
        'buy_now_price'               => 'Buy Now Price',
        'starting_rent'               => 'Starting Rent',
        'reserve_rent'                => 'Reserve Rent',
        'lease_now_price'             => 'Lease Now Price',
        'assignment_fee_amount'       => 'Assignment Fee',
        'assumable_fee_amount'        => 'Assumption Fee',
        'additional_deposit_requested' => 'Additional Deposit',
        'initial_deposit_requested'   => 'Initial Deposit',
        'down_payment_amount'         => 'Down Payment',
        'gap_payment_amount'          => 'Gap Payment',
        'security_deposit_amount'     => 'Security Deposit',
        'total_move_in_funds_required' => 'Total Move-In Funds',
        'pet_fee_amount'              => 'Pet Fee',
    ];

    /** Money fields with no `$`/`%` control beside them — always currency. */
    private const PLAIN_MONEY = [
        'maximum_budget', 'starting_price', 'reserve_price', 'buy_now_price',
        'desired_rental_amount', 'starting_rent', 'reserve_rent', 'lease_now_price',
        'security_deposit_amount', 'total_move_in_funds_required',
    ];

    /** @return array<string, string|null> */
    public static function modifiersFor(string $role): array
    {
        return $role === 'landlord' ? self::LANDLORD_MODIFIERS : self::SELLER_MODIFIERS;
    }

    /**
     * Build the review rows for one role.
     *
     * @param  list<string>          $fields  the canonical field list for this role
     * @param  callable(string):mixed $read   field name → the component's value
     * @return array<string, string>          label => published value
     */
    public static function rows(string $role, array $fields, callable $read): array
    {
        $modifiers = self::modifiersFor($role);

        // Every amount that a modifier formats. Their rows are produced by the
        // modifier pass so the unit travels with the figure; emitting them again
        // from the plain pass would print the number twice, once unformatted.
        $pairedAmounts = array_values(array_filter($modifiers));

        $rows = [];

        foreach ($fields as $field) {
            if ($field === 'showPaymentAssumptions') {
                continue; // a disclosure toggle, not an answer
            }

            // A `$` / `%` selector. Never its own row — it is the unit of the
            // amount beside it, and ConditionalTerms::amount() applies it.
            if (array_key_exists($field, $modifiers)) {
                $partner = $modifiers[$field];

                if ($partner === null) {
                    continue;
                }

                $formatted = ConditionalTerms::amount(
                    $read($partner),
                    self::normaliseUnit($read($field)),
                );

                if ($formatted !== null) {
                    $rows[self::label($partner)] = $formatted;
                }

                continue;
            }

            if (in_array($field, $pairedAmounts, true)) {
                continue; // emitted above, with its unit
            }

            $value = $read($field);

            if (! ConditionalTerms::answered($value)) {
                continue;
            }

            if (in_array($field, self::PLAIN_MONEY, true)) {
                $formatted = ConditionalTerms::amount($value);

                if ($formatted === null) {
                    continue;
                }

                $rows[self::label($field)] = $formatted;

                continue;
            }

            $rows[self::label($field)] = self::flatten($value);
        }

        return $rows;
    }

    /**
     * A unit token in the spelling ConditionalTerms understands.
     *
     * Six of the seven seller toggles store the symbol itself (`$` / `%`). ONE
     * does not: `gap_payment_type` stores `flat` / `percent`, and
     * ConditionalTerms::amount() tests for the literal `'%'`. Passing `percent`
     * through unchanged would fall to the dollar branch and publish a 3% gap
     * payment as "$3" — the exact defect amount() was written to fix, reaching it
     * through a vocabulary it does not speak.
     *
     * Normalised here rather than in ConditionalTerms because that class is
     * consumed by the live listing pages, and widening what it accepts is a
     * change to published listings. This is the narrow adapter for the one
     * control that spells the unit as a word; the markup binding in
     * MlsQuickImportReviewPresentationTest pins which controls those are.
     */
    private static function normaliseUnit(mixed $type): mixed
    {
        $token = strtolower(trim((string) $type));

        return match ($token) {
            'percent' => '%',
            'flat'    => '$',
            default   => $type,
        };
    }

    /** The label a person saw for this field. */
    public static function label(string $field): string
    {
        if (isset(self::LABELS[$field])) {
            return self::LABELS[$field];
        }

        $label = str_replace('_', ' ', $field);
        $label = preg_replace('/\bhoa\b/i', 'HOA', $label) ?? $label;
        $label = preg_replace('/\bnft\b/i', 'NFT', $label) ?? $label;
        $label = preg_replace('/\bpmi\b/i', 'PMI', $label) ?? $label;
        $label = preg_replace('/\bpct\b/i', '%', $label) ?? $label;

        return ucfirst($label);
    }

    /**
     * A stored value as one printable string.
     *
     * Routed through ConditionalTerms::toList() so the three shapes a
     * multi-select reaches a view in — array, JSON string, empty string — all
     * flatten the same way, which is the same normalisation the listing page
     * applies.
     */
    private static function flatten(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        $list = ConditionalTerms::toList($value);

        return $list === [] ? trim((string) $value) : implode(', ', $list);
    }
}
