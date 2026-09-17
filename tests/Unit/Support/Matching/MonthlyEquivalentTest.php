<?php

namespace Tests\Unit\Support\Matching;

use App\Support\Matching\MonthlyEquivalent;
use PHPUnit\Framework\TestCase;

/**
 * MonthlyEquivalent — the centralized period conversion (P0-2, P0-5).
 *
 * Extends PHPUnit's TestCase directly, with no application: the class must be
 * callable from a scorer, a query builder or a console command without a booted
 * container, and a test that boots one would not prove that.
 */
class MonthlyEquivalentTest extends TestCase
{
    // =========================================================================
    // Lease — known periods convert
    // =========================================================================

    /** @test */
    public function monthly_rent_is_itself(): void
    {
        $this->assertSame(2500.0, MonthlyEquivalent::lease(2500.0, 'Monthly'));
    }

    /** @test */
    public function month_to_month_rent_is_monthly(): void
    {
        $this->assertSame(2500.0, MonthlyEquivalent::lease(2500.0, 'Month to Month'));
    }

    /** @test */
    public function annual_rent_divides_by_twelve(): void
    {
        $this->assertEqualsWithDelta(3000.0, MonthlyEquivalent::lease(36000.0, 'Annually'), 0.001);
    }

    /** @test */
    public function weekly_rent_multiplies_by_fifty_two_twelfths(): void
    {
        // $2,500/week is about $10,833/month — the exact case that used to pass a
        // $3,000 monthly budget by comparing the raw number.
        $this->assertEqualsWithDelta(10833.33, MonthlyEquivalent::lease(2500.0, 'Weekly'), 0.01);
    }

    /** @test */
    public function daily_rent_multiplies_by_three_hundred_sixty_five_twelfths(): void
    {
        $this->assertEqualsWithDelta(3041.67, MonthlyEquivalent::lease(100.0, 'Daily'), 0.01);
    }

    /** @test */
    public function frequency_casing_and_padding_do_not_matter(): void
    {
        $this->assertEqualsWithDelta(3000.0, MonthlyEquivalent::lease(36000.0, '  annual  '), 0.001);
    }

    // =========================================================================
    // Lease — unknown stays unknown, and is never monthly
    // =========================================================================

    /** @test */
    public function seasonal_rent_is_unknown_because_a_season_has_no_length(): void
    {
        $this->assertNull(MonthlyEquivalent::leaseFactor('Seasonal'));
        $this->assertNull(MonthlyEquivalent::lease(2500.0, 'Seasonal'));
    }

    /**
     * @test
     * @dataProvider termNotPeriodProvider
     */
    public function a_lease_term_is_not_a_payment_period(string $frequency): void
    {
        $this->assertNull(
            MonthlyEquivalent::lease(2000.0, $frequency),
            "'{$frequency}' describes the term, not the period — it must not convert."
        );
    }

    public function termNotPeriodProvider(): array
    {
        return [
            ['12 Months'],
            ['24 Months'],
            ['>6 Months <12'],
            ['Short Term Lease'],
        ];
    }

    /** @test */
    public function a_missing_lease_frequency_is_unknown_not_monthly(): void
    {
        $this->assertNull(MonthlyEquivalent::lease(2500.0, null));
        $this->assertNull(MonthlyEquivalent::lease(2500.0, ''));
        $this->assertNull(MonthlyEquivalent::lease(2500.0, '   '));
    }

    /** @test */
    public function an_unrecognised_lease_frequency_is_unknown_not_monthly(): void
    {
        $this->assertNull(MonthlyEquivalent::lease(2500.0, 'Per Fortnight'));
    }

    /** @test */
    public function a_missing_amount_is_unknown_even_with_a_known_period(): void
    {
        $this->assertNull(MonthlyEquivalent::lease(null, 'Monthly'));
    }

    // =========================================================================
    // Association fee — known periods convert
    // =========================================================================

    /** @test */
    public function monthly_association_fee_is_itself(): void
    {
        $this->assertSame(300.0, MonthlyEquivalent::associationFee(300.0, 'Monthly'));
    }

    /** @test */
    public function quarterly_association_fee_divides_by_three(): void
    {
        $this->assertEqualsWithDelta(100.0, MonthlyEquivalent::associationFee(300.0, 'Quarterly'), 0.001);
    }

    /** @test */
    public function semi_annual_association_fee_divides_by_six(): void
    {
        $this->assertEqualsWithDelta(100.0, MonthlyEquivalent::associationFee(600.0, 'Semi-Annually'), 0.001);
    }

    /** @test */
    public function annual_association_fee_divides_by_twelve(): void
    {
        // The defect: $2,400 billed annually is $200/month, not $2,400/month.
        $this->assertEqualsWithDelta(200.0, MonthlyEquivalent::associationFee(2400.0, 'Annually'), 0.001);
    }

    // =========================================================================
    // Association fee — unknown stays unknown
    // =========================================================================

    /** @test */
    public function bi_monthly_is_unknown_because_it_is_genuinely_ambiguous(): void
    {
        // "Every two months" and "twice a month" differ by a factor of four.
        $this->assertNull(MonthlyEquivalent::associationFee(300.0, 'Bi-Monthly'));
    }

    /** @test */
    public function a_one_time_fee_has_no_monthly_equivalent(): void
    {
        $this->assertNull(MonthlyEquivalent::associationFee(500.0, 'One-Time'));
    }

    /** @test */
    public function a_missing_association_frequency_is_unknown_not_monthly(): void
    {
        $this->assertNull(MonthlyEquivalent::associationFee(2400.0, null));
        $this->assertNull(MonthlyEquivalent::associationFee(2400.0, ''));
    }

    /** @test */
    public function an_unrecognised_association_frequency_is_unknown_not_monthly(): void
    {
        $this->assertNull(MonthlyEquivalent::associationFee(2400.0, 'Other'));
        $this->assertNull(MonthlyEquivalent::associationFee(2400.0, 'Per Decade'));
    }

    // =========================================================================
    // The two vocabularies do not leak into each other
    // =========================================================================

    /** @test */
    public function a_lease_only_period_does_not_convert_an_association_fee(): void
    {
        $this->assertNull(MonthlyEquivalent::associationFee(100.0, 'Weekly'));
        $this->assertNull(MonthlyEquivalent::associationFee(100.0, 'Daily'));
    }

    /** @test */
    public function an_association_only_period_does_not_convert_a_rent(): void
    {
        $this->assertNull(MonthlyEquivalent::lease(300.0, 'Quarterly'));
        $this->assertNull(MonthlyEquivalent::lease(600.0, 'Semi-Annually'));
    }

    // =========================================================================
    // The SQL pre-filter multiplier
    // =========================================================================

    /** @test */
    public function the_widest_lease_multiplier_covers_the_annual_case(): void
    {
        // Annual is the widest convertible period: a listing quoted annually is
        // twelve times the monthly figure, so the coarse SQL ceiling must be at
        // least 12x the budget or an affordable annual rental is excluded.
        $this->assertSame(12.0, MonthlyEquivalent::widestLeaseCeilingMultiplier());
    }

    /** @test */
    public function every_convertible_period_survives_the_widest_multiplier(): void
    {
        $budget   = 3000.0;
        $ceiling  = $budget * MonthlyEquivalent::widestLeaseCeilingMultiplier();

        foreach (['Monthly', 'Month to Month', 'Annually', 'Weekly', 'Daily'] as $frequency) {
            // The largest raw amount that is still within budget for this period.
            $factor  = MonthlyEquivalent::leaseFactor($frequency);
            $rawAtMax = $budget / $factor;

            $this->assertLessThanOrEqual(
                $ceiling,
                $rawAtMax,
                "A {$frequency} listing exactly at budget would be excluded by the SQL pre-filter."
            );
        }
    }
}
