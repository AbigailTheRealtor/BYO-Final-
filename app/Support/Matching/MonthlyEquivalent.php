<?php

namespace App\Support\Matching;

use App\Services\ListingImport\MlsNormalizer;

/**
 * The one place a periodic amount becomes a per-MONTH amount.
 *
 * WHY THIS EXISTS
 * ---------------
 * Two comparisons in the match engine were reading a number and assuming its
 * period:
 *
 *   · A tenant's monthly budget was compared straight to `ListPrice`. On a lease
 *     record ListPrice IS the periodic rent and the period is `LeaseAmountFrequency`.
 *     A weekly $2,500 rental therefore passed a $3,000/month budget as though it
 *     cost $2,500 a month, when it costs roughly $10,800.
 *
 *   · A buyer's monthly fee ceiling was compared to `AssociationFee`, which is
 *     stored verbatim with `AssociationFeeFrequency` left behind. An annually
 *     billed $2,400 fee and a monthly $2,400 fee were the same number.
 *
 * Both are the same mistake, so both are answered here rather than in two places
 * that would drift.
 *
 * NULL MEANS UNKNOWN, AND UNKNOWN IS NEVER MONTHLY
 * ------------------------------------------------
 * Every method returns `null` when the period cannot be established. That is the
 * whole point: assuming "monthly" is exactly the defect. A caller must decide
 * what to do with an unknown — it must not fabricate a figure, and it must not
 * present an unverified amount as though it had been checked against a budget.
 *
 * WHAT IS DELIBERATELY UNKNOWN, AND WHY
 * -------------------------------------
 *   · lease `seasonal` — a season has no defined length. Stellar publishes it as
 *     a frequency (78 of the live rental rows at the time of the audit) and there
 *     is no honest number of months to divide by. Guessing three or six months
 *     would be inventing the exact kind of conversion this class exists to refuse.
 *   · lease `12_months` / `24_months` / `6_to_12_months` / `short_term` — these
 *     describe the lease TERM, not the payment period. A $2,000 "24 Months" value
 *     is almost certainly $2,000 a month for two years, but "almost certainly" is
 *     not a basis for a hard budget filter.
 *   · association `bi-monthly` — genuinely ambiguous in US usage: every two months
 *     (÷2) or twice a month (×2). The two readings differ by a factor of four.
 *   · association `one_time` — a real charge with no monthly equivalent at all. It
 *     is not zero and it is not a recurring cost; it is a different kind of number.
 *   · anything blank, malformed or unrecognised.
 *
 * VOCABULARY IS NOT RESTATED HERE
 * -------------------------------
 * Token normalisation is {@see MlsNormalizer::normalizeLeaseFrequency()} and
 * {@see MlsNormalizer::normalizeHoaFeeFrequency()}, which the MLS import has used
 * since before this class existed. Re-listing the spellings here would be a second
 * vocabulary, and two vocabularies for one concept is how they come to disagree.
 * This class only maps an already-normalised token to a factor.
 *
 * Pure and container-free: no config, no facades, no database. Safe to call from a
 * scorer, a query builder, a console command or a unit test with no application.
 */
final class MonthlyEquivalent
{
    /**
     * Normalised lease-frequency token => the factor that turns one payment into
     * a per-month amount. A token absent from this map is UNKNOWN by definition.
     *
     * @var array<string,float>
     */
    private const LEASE_FACTORS = [
        'monthly'        => 1.0,
        'month_to_month' => 1.0,
        'annually'       => 1.0 / 12.0,
        'weekly'         => 52.0 / 12.0,
        'daily'          => 365.0 / 12.0,
    ];

    /**
     * Normalised association-fee-frequency token => per-month factor.
     *
     * @var array<string,float>
     */
    private const ASSOCIATION_FACTORS = [
        'monthly'       => 1.0,
        'quarterly'     => 1.0 / 3.0,
        'semi_annually' => 1.0 / 6.0,
        'annually'      => 1.0 / 12.0,
    ];

    /**
     * The per-month factor for a lease frequency, or null when it cannot be known.
     */
    public static function leaseFactor(?string $frequency): ?float
    {
        return self::factor($frequency, self::LEASE_FACTORS, true);
    }

    /**
     * The per-month factor for an association-fee frequency, or null when unknown.
     */
    public static function associationFeeFactor(?string $frequency): ?float
    {
        return self::factor($frequency, self::ASSOCIATION_FACTORS, false);
    }

    /**
     * A lease amount expressed per month, or null when the amount or the period
     * is unknown. Never assumes monthly.
     */
    public static function lease(?float $amount, ?string $frequency): ?float
    {
        $factor = self::leaseFactor($frequency);

        if ($amount === null || $factor === null) {
            return null;
        }

        return $amount * $factor;
    }

    /**
     * An association fee expressed per month, or null when the amount or the
     * period is unknown. Never assumes monthly.
     */
    public static function associationFee(?float $amount, ?string $frequency): ?float
    {
        $factor = self::associationFeeFactor($frequency);

        if ($amount === null || $factor === null) {
            return null;
        }

        return $amount * $factor;
    }

    /**
     * The largest number of periods a single month can contain for any lease
     * frequency this class can convert.
     *
     * A coarse SQL pre-filter on a raw `ListPrice` cannot see the frequency,
     * because the frequency lives in `raw_json`. Multiplying a monthly budget by
     * this value gives a ceiling that cannot exclude any listing whose true
     * monthly rent is within budget — an annual listing at twelve times the
     * budget is the widest case — while still bounding the query. The exact
     * comparison happens afterwards, in PHP, where the frequency is readable.
     *
     * Derived from LEASE_FACTORS rather than written down, so a frequency added
     * there can never leave the pre-filter too narrow.
     */
    public static function widestLeaseCeilingMultiplier(): float
    {
        $widest = 1.0;

        foreach (self::LEASE_FACTORS as $factor) {
            if ($factor <= 0.0) {
                continue;
            }

            $widest = max($widest, 1.0 / $factor);
        }

        return $widest;
    }

    /**
     * @param array<string,float> $factors
     * @param bool $lease which normaliser owns this vocabulary
     */
    private static function factor(?string $frequency, array $factors, bool $lease): ?float
    {
        if (!is_string($frequency)) {
            return null;
        }

        $raw = trim($frequency);

        if ($raw === '') {
            return null;
        }

        $token = $lease
            ? MlsNormalizer::normalizeLeaseFrequency($raw)
            : MlsNormalizer::normalizeHoaFeeFrequency($raw);

        return $factors[$token] ?? null;
    }
}
