<?php

namespace App\Presenters;

use App\Helpers\ListingDisplayHelper;

/**
 * Display formatting for the allow-listed offer terms in the bidding feed.
 *
 * ---------------------------------------------------------------------------
 * WHY THIS EXISTS
 * ---------------------------------------------------------------------------
 * `_competing-bids.blade.php` rendered every term with `{{ $bid['terms'][$k] }}`,
 * so an offer price of 525000 displayed as "525000" and an earnest deposit of
 * 1.5% displayed as a bare "1.5". This class is the single place that turns a
 * stored term into its display string, shared by the Seller and Landlord feeds
 * so the rule is written once.
 *
 * All numeric formatting delegates to ListingDisplayHelper — the project's
 * existing centralized formatter. Nothing here reimplements number_format.
 *
 * ---------------------------------------------------------------------------
 * STORAGE CONVENTIONS (verified against resources/views/offers/_offer_terms_form
 * .blade.php and _offer_terms_display.blade.php — do not change without
 * re-checking both)
 * ---------------------------------------------------------------------------
 *   offer_price, monthly_rent, security_deposit, move_in_funds
 *       Plain numeric dollars. `nullable|numeric|min:0`.
 *
 *   earnest_deposit + earnest_deposit_unit
 *   down_payment_value + down_payment_unit
 *       The unit column stores the LITERAL character '$' or '%'. When it is
 *       '%', the paired value is a WHOLE PERCENTAGE — the form placeholders
 *       read "Enter down payment % (e.g., 20)" and "Enter earnest deposit %
 *       (e.g., 1.5)". It is NOT a decimal ratio, so it must never be multiplied
 *       by 100. When the unit is missing we format as dollars, matching
 *       _offer_terms_display.blade.php:23.
 *
 *   *_contingency_days      whole day counts
 *   lease_term_months       whole month count
 *
 *   financing_type, maintenance_responsibility, last_month_rent_offered and the
 *   *_contingency flags are free-form or Yes/No text and are passed through
 *   untouched — appending $, % or a unit to them would be wrong.
 *
 * A missing or empty value renders as an em dash, never "$0". Zero renders as
 * "$0" only when zero is genuinely what is stored.
 */
final class OfferTermPresenter
{
    /** Plain dollar amounts. */
    private const MONEY_KEYS = [
        'offer_price',
        'monthly_rent',
        'security_deposit',
        'move_in_funds',
    ];

    /** Value key => the term key holding its '$' or '%' unit. */
    private const UNIT_PAIRED_KEYS = [
        'earnest_deposit'    => 'earnest_deposit_unit',
        'down_payment_value' => 'down_payment_unit',
    ];

    /** Whole day counts, rendered "N days". */
    private const DAY_KEYS = [
        'financing_contingency_days',
        'inspection_contingency_days',
        'appraisal_contingency_days',
        'sale_of_buyer_property_contingency_days',
    ];

    /** Whole month counts, rendered "N months". */
    private const MONTH_KEYS = [
        'lease_term_months',
    ];

    public const EMPTY_DISPLAY = '—';

    /**
     * Is this key merely the unit companion of another term?
     *
     * The unit is folded into its paired value ("$5,000", "1.5%"), so rendering
     * it as its own column would produce a meaningless "Earnest Deposit Unit: $"
     * cell. The key stays in the service's allow-list — this only affects
     * presentation.
     */
    public function isUnitCompanion(string $key): bool
    {
        return in_array($key, self::UNIT_PAIRED_KEYS, true);
    }

    /**
     * Which allow-listed keys should become table columns, in allow-list order.
     *
     * A column appears only when at least one bid filled it in, and unit
     * companions never get their own column.
     *
     * @param  array<int, string>                $allowedKeys
     * @param  array<int, array<string, mixed>>  $rows  feed rows from PublicOfferFeedService::build()
     * @return array<int, string>
     */
    public function columnKeys(array $allowedKeys, array $rows): array
    {
        $columns = [];

        foreach ($allowedKeys as $key) {
            if ($this->isUnitCompanion($key)) {
                continue;
            }

            foreach ($rows as $row) {
                if (array_key_exists($key, $row['terms'] ?? [])) {
                    $columns[] = $key;
                    break;
                }
            }
        }

        return $columns;
    }

    /**
     * Render one term for display.
     *
     * Formatting only — the caller's $terms array is never modified and no
     * persisted value is touched.
     *
     * @param  array<string, mixed>  $terms  one feed row's 'terms'
     */
    public function display(string $key, array $terms): string
    {
        $value = $terms[$key] ?? null;

        // Missing is missing. Never render an absent amount as "$0".
        if ($value === null || $value === '' || $value === []) {
            return self::EMPTY_DISPLAY;
        }

        if (in_array($key, self::MONEY_KEYS, true)) {
            return ListingDisplayHelper::fmtMoneyWhole($value);
        }

        if (array_key_exists($key, self::UNIT_PAIRED_KEYS)) {
            $unit = trim((string) ($terms[self::UNIT_PAIRED_KEYS[$key]] ?? '$'));

            return $unit === '%'
                ? ListingDisplayHelper::fmtPercent($value)
                : ListingDisplayHelper::fmtMoneyWhole($value);
        }

        if (in_array($key, self::DAY_KEYS, true)) {
            return $this->withCountUnit($value, 'day', 'days');
        }

        if (in_array($key, self::MONTH_KEYS, true)) {
            return $this->withCountUnit($value, 'month', 'months');
        }

        // Free text, Yes/No flags and dates pass through untouched.
        return (string) $value;
    }

    /**
     * "1 day" / "30 days". Non-numeric input is returned unchanged rather than
     * being given a unit it may not deserve.
     */
    private function withCountUnit(mixed $value, string $singular, string $plural): string
    {
        $clean = str_replace(',', '', (string) $value);

        if (! is_numeric($clean)) {
            return (string) $value;
        }

        $unit = abs((float) $clean) === 1.0 ? $singular : $plural;

        return ListingDisplayHelper::fmtNumber($clean) . ' ' . $unit;
    }
}
