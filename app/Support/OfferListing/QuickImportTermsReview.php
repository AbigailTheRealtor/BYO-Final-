<?php

namespace App\Support\OfferListing;

/**
 * The Your Terms summary on the MLS Quick Import review step.
 *
 * THE DEFECTS THIS CLOSES
 * -----------------------
 * The review list was built by looping every canonical terms field and printing
 * any non-empty value. That published three kinds of thing that are not answers:
 *
 *   1. `$` / `%` selectors. They default to a symbol and are never blank, so a
 *      seller reviewing a cash sale was shown "Assumable fee type  $",
 *      "Down payment type  %", "Seller financing type  $" and four more.
 *
 *   2. Follow-ups whose parent is no longer chosen. A seller who picked
 *      Assumable, typed a fee, then deselected Assumable still saw
 *      "Assumable fee amount 2500" — a branch the finished listing hides.
 *
 *   3. Storage keys and raw numbers: "Maximum budget  385000" on the screen
 *      where the seller confirms the price the form called "Desired Sale Price".
 *
 * HOW EACH IS DECIDED — AND WHY NOTHING IS DECIDED HERE
 * -----------------------------------------------------
 * {@see ConditionalTerms} is the one place that knows how a Your Terms value is
 * published: applies() for whether a follow-up's parent currently opens it,
 * answered() / toList() / withOther() for what counts as an answer and how
 * "Other" reads, amount() for money and percentages. This class selects which
 * fields to consider and which formatter each takes; every judgement is
 * ConditionalTerms'. The lists below are the only knowledge kept here, and each
 * is read off the canonical markup and asserted against it by
 * {@see \Tests\Feature\ListingImport\MlsQuickImportReviewPresentationTest}:
 *
 *   UNITS    a `*_type` select whose only options are `$` and `%` (or
 *            flat / percent), and the amount input beside it that it formats.
 *            Never a row of its own.
 *   MONEY    inputs the tab prefixes with a fixed `$`.
 *   PERCENT  inputs the tab suffixes with a fixed `%`.
 *
 * Landlord Leasing Terms has NO unit selectors. `pet_fee_type` looks like one
 * by name and is not: it offers "One Time Fee Refundable", "Monthly Pet Fee" and
 * so on, which is an answer, and it is the PARENT of the pet fee amount and
 * details. Suppressing it would have hidden the landlord's answer.
 */
final class QuickImportTermsReview
{
    /**
     * `$` / `%` selector => the amount it formats.
     *
     * `seller_financing_type` sits beside `seller_down_payment_amount` (the tab's
     * "Seller Financing" amount) — it is not an orphan and is not dropped.
     *
     * @var array<string, string>
     */
    private const SELLER_UNITS = [
        'assignment_fee_type'     => 'assignment_fee_amount',
        'assumable_fee_type'      => 'assumable_fee_amount',
        'gap_payment_type'        => 'gap_payment_amount',
        'down_payment_type'       => 'down_payment_amount',
        'seller_financing_type'   => 'seller_down_payment_amount',
        'initial_deposit_type'    => 'initial_deposit_requested',
        'additional_deposit_type' => 'additional_deposit_requested',
    ];

    /** @var array<string, string> */
    private const LANDLORD_UNITS = [];

    /** Inputs the Sale Terms tab prefixes with a fixed `$`. */
    private const SELLER_MONEY = [
        'starting_price', 'reserve_price', 'buy_now_price', 'maximum_budget',
        'max_monthly_payment', 'assumable_monthly_escrow', 'outstanding_balance',
        'exchange_item_value', 'additional_cash',
        'lease_option_price', 'lease_option_payment', 'option_fee_amount',
        'lease_purchase_price', 'lease_purchase_payment', 'lease_purchase_rent_credit_amount', 'lease_purchase_deposit',
        'purchase_price', 'prepayment_penalty_amount', 'balloon_payment_amount',
        'payment_annual_property_taxes', 'payment_monthly_insurance', 'payment_hoa_fee_amount',
    ];

    /** Inputs the Sale Terms tab suffixes with a fixed `%`. */
    private const SELLER_PERCENT = [
        'max_assumable_rate', 'crypto_percentage', 'cash_percentage_crypto',
        'lease_option_fee_credit_percentage', 'nft_percentage', 'cash_percentage_nft',
        'interest_rate', 'payment_down_payment_pct', 'payment_interest_rate', 'payment_pmi_rate',
    ];

    /** Inputs the Leasing Terms tab prefixes with a fixed `$`. */
    private const LANDLORD_MONEY = [
        'starting_rent', 'reserve_rent', 'lease_now_price', 'desired_rental_amount',
        'security_deposit_amount', 'total_move_in_funds_required', 'pet_fee_amount',
    ];

    private const LANDLORD_PERCENT = [];

    /**
     * Field name => the label the person answering it actually saw.
     *
     * Only fields whose storage key reads as something else to a human; the
     * derived label is otherwise kept, because a second full label list that
     * could disagree with the tab is worse than a plainly-derived one.
     *
     * `maximum_budget` is the headline case: it is the meta key behind the
     * Seller's "Desired Sale Price" input. `desired_rental_amount` is the key
     * behind the Landlord's "Desired Lease Price", the label both the tab and the
     * published listing use.
     *
     * @var array<string, string>
     */
    private const LABELS = [
        'maximum_budget'               => 'Desired Sale Price',
        'desired_rental_amount'        => 'Desired Lease Price',
        'starting_price'               => 'Starting Price',
        'reserve_price'                => 'Reserve Price',
        'buy_now_price'                => 'Buy Now Price',
        'starting_rent'                => 'Starting Rent',
        'reserve_rent'                 => 'Reserve Rent',
        'lease_now_price'              => 'Lease Now Price',
        'assignment_fee_amount'        => 'Assignment Fee',
        'assumable_fee_amount'         => 'Assumption Fee',
        'additional_deposit_requested' => 'Additional Deposit',
        'initial_deposit_requested'    => 'Initial Deposit',
        'down_payment_amount'          => 'Down Payment',
        'seller_down_payment_amount'   => 'Seller Financing Amount',
        'gap_payment_amount'           => 'Gap Payment',
        'security_deposit_amount'      => 'Security Deposit',
        'total_move_in_funds_required' => 'Total Move-In Funds',
        'pet_fee_amount'               => 'Pet Fee',
        'pet_fee_other'                => 'Pet Fee Details',
    ];

    /** @return array<string, string> selector => amount */
    public static function unitsFor(string $role): array
    {
        return $role === 'landlord' ? self::LANDLORD_UNITS : self::SELLER_UNITS;
    }

    /** @return list<string> */
    public static function moneyFor(string $role): array
    {
        return $role === 'landlord' ? self::LANDLORD_MONEY : self::SELLER_MONEY;
    }

    /** @return list<string> */
    public static function percentFor(string $role): array
    {
        return $role === 'landlord' ? self::LANDLORD_PERCENT : self::SELLER_PERCENT;
    }

    /**
     * Build the review rows for one role.
     *
     * @param  list<string>           $fields  the canonical field list for this role
     * @param  callable(string):mixed $read    field name → the component's value
     * @return array<string, string>           label => published value
     */
    public static function rows(string $role, array $fields, callable $read): array
    {
        $units     = self::unitsFor($role);
        $unitOf    = array_flip($units);                    // amount => its selector
        $writeIns  = ConditionalTerms::writeIns($role);     // write-in => parent
        $writeInOf = array_flip($writeIns);                 // parent => its write-in
        $money     = self::moneyFor($role);
        $percent   = self::percentFor($role);

        $rows = [];

        foreach ($fields as $field) {
            // Not answers: a panel toggle, a unit (it formats its amount), and the
            // "Other" text (it is published inside its parent's answer).
            if (in_array($field, ConditionalTerms::DISCLOSURE_TOGGLES, true)
                || isset($units[$field])
                || isset($writeIns[$field])) {
                continue;
            }

            // The parent decides. A value behind a closed branch is kept and is
            // not published — the same rule the finished listing follows.
            if (! ConditionalTerms::applies($role, $field, $read)) {
                continue;
            }

            $value = $read($field);

            if (isset($unitOf[$field])) {
                $shown = ConditionalTerms::amount($value, self::normaliseUnit($read($unitOf[$field])));
            } elseif (in_array($field, $money, true)) {
                $shown = ConditionalTerms::amount($value);
            } elseif (in_array($field, $percent, true)) {
                $shown = ConditionalTerms::amount($value, '%');
            } else {
                $shown = self::answer($role, $field, $value, $writeInOf, $read);
            }

            if ($shown === null || $shown === '') {
                continue;
            }

            $rows[self::label($field)] = $shown;
        }

        return $rows;
    }

    /**
     * An ordinary answer as one printable string, or null for no answer.
     *
     * A parent with an "Other" write-in reads through withOther(), so "Other"
     * becomes what was typed for it — and only while that write-in is itself
     * open.
     *
     * @param array<string, string> $writeInOf
     */
    private static function answer(string $role, string $field, mixed $value, array $writeInOf, callable $read): ?string
    {
        if (is_bool($value)) {
            return $value ? 'Yes' : null;
        }

        if (isset($writeInOf[$field])) {
            $writeIn = $writeInOf[$field];
            $typed   = ConditionalTerms::applies($role, $writeIn, $read) ? $read($writeIn) : null;
            $list    = ConditionalTerms::withOther($value, $typed);
        } else {
            $list = ConditionalTerms::toList($value);
        }

        return $list === [] ? null : implode(', ', $list);
    }

    /**
     * A unit token in the spelling ConditionalTerms understands.
     *
     * Six of the seven seller toggles store the symbol itself (`$` / `%`). ONE
     * does not: `gap_payment_type` stores `flat` / `percent`, and
     * ConditionalTerms::amount() tests for the literal `'%'`. Passed through
     * unchanged, a 3% gap payment would publish as "$3".
     *
     * Normalised here rather than in ConditionalTerms because that class is
     * consumed by the live listing pages, and widening what it accepts is a
     * change to published listings.
     */
    private static function normaliseUnit(mixed $type): mixed
    {
        return match (strtolower(trim((string) $type))) {
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
        $label = preg_replace('/\bcam nnn\b/i', 'CAM/NNN', $label) ?? $label;
        $label = preg_replace('/\bcom\b/i', 'commercial', $label) ?? $label;
        $label = preg_replace('/\bres\b/i', 'residential', $label) ?? $label;
        $label = preg_replace('/\bll\b/i', 'Landlord', $label) ?? $label;
        $label = preg_replace('/\baccess 24 7\b/i', '24/7 access', $label) ?? $label;

        return ucfirst($label);
    }
}
