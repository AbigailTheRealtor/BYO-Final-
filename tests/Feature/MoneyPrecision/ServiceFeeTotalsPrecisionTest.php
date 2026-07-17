<?php

namespace Tests\Feature\MoneyPrecision;

use App\Http\Livewire\Concerns\CalculatesServiceFeeTotals;
use App\Http\Livewire\OfferListing\Landlord\LandlordOfferListing;
use App\Http\Livewire\OfferListing\Tenant\TenantOfferListing;
use Tests\TestCase;

/**
 * Batch B1.3 (Money Precision) — Part 2 regression test.
 *
 * The Landlord/Tenant "Class-A" fee totals are the only landlord/tenant EAV
 * financial values that feed live arithmetic. The legacy inline implementation
 * (a) truncated comma-formatted input — `(float) "1,000"` collapsed to 1.0 —
 * and (b) accumulated binary floats, producing drift such as 0.1 + 0.2 = 0.300…4.
 *
 * These tests lock in the fix at two levels: the shared calculator, and the
 * live component methods that delegate to it.
 */
class ServiceFeeTotalsPrecisionTest extends TestCase
{
    /** An anonymous harness that exposes the trait's protected helpers. */
    private function calculator(): object
    {
        return new class {
            use CalculatesServiceFeeTotals;

            public function money($value): float
            {
                return $this->normalizeMoney($value);
            }

            public function totals(array $custom, array $fees, array $enable): array
            {
                return $this->calculateServiceFeeTotals($custom, $fees, $enable);
            }
        };
    }

    /** @return array<string, array{0:mixed,1:float}> */
    public static function moneyNormalizationProvider(): array
    {
        return [
            'plain integer string'   => ['1000', 1000.0],
            'thousands separator'    => ['1,000', 1000.0],
            'currency + separators'  => ['$1,200.50', 1200.5],
            'decimal string'         => ['42.75', 42.75],
            'numeric float'          => [15.5, 15.5],
            'null'                   => [null, 0.0],
            'empty string'           => ['', 0.0],
            'non-numeric junk'       => ['abc', 0.0],
            'whitespace padded'      => [' 2 000 ', 2000.0],
        ];
    }

    /**
     * @dataProvider moneyNormalizationProvider
     */
    public function test_normalize_money_strips_symbols_and_separators($input, float $expected): void
    {
        $this->assertSame($expected, $this->calculator()->money($input));
    }

    public function test_comma_formatted_fees_are_not_truncated(): void
    {
        // Legacy behaviour: (float) "1,000" === 1.0 — magnitude silently lost.
        $totals = $this->calculator()->totals(
            [['fee' => '1,000', 'marketing_fee' => '2,500.50']],
            [],
            []
        );

        $this->assertSame(1000.0, $totals['total_flat_fee']);
        $this->assertSame(2500.5, $totals['total_marketing_fee']);
    }

    public function test_summation_is_free_of_binary_float_drift(): void
    {
        // Legacy behaviour: 0.1 + 0.2 === 0.30000000000000004.
        $totals = $this->calculator()->totals(
            [['fee' => '0.10'], ['fee' => '0.20']],
            [],
            []
        );

        $this->assertSame(0.3, $totals['total_flat_fee']);
    }

    public function test_enabled_fee_structure_gating_is_preserved(): void
    {
        $totals = $this->calculator()->totals(
            [],
            ['listing' => '100', 'admin' => '200'],
            ['listing' => true, 'admin' => false]
        );

        // Only the enabled 'listing' fee counts.
        $this->assertSame(100.0, $totals['total_flat_fee']);
        $this->assertSame(0.0, $totals['total_marketing_fee']);
    }

    public function test_custom_services_and_enabled_fees_combine_exactly(): void
    {
        $totals = $this->calculator()->totals(
            [['fee' => '1,000.25', 'marketing_fee' => '10']],
            ['listing' => '0.75'],
            ['listing' => true]
        );

        $this->assertSame(1001.0, $totals['total_flat_fee']);      // 1000.25 + 0.75
        $this->assertSame(10.0, $totals['total_marketing_fee']);
    }

    /** @return array<string, array{0:class-string}> */
    public static function componentProvider(): array
    {
        return [
            'Landlord offer listing' => [LandlordOfferListing::class],
            'Tenant offer listing'   => [TenantOfferListing::class],
        ];
    }

    /**
     * The live components must produce the corrected totals through their own
     * calculateTotals() method (proving the trait is wired in correctly).
     *
     * @dataProvider componentProvider
     */
    public function test_component_calculate_totals_uses_precise_math(string $componentClass): void
    {
        $component = new $componentClass();
        $component->custom_services = [['fee' => '1,000', 'marketing_fee' => '250.50']];
        $component->fees = ['listing' => '0.10'];
        $component->enable = ['listing' => true];

        $component->calculateTotals();

        $this->assertSame(1000.10, $component->total_flat_fee, 'comma-formatted custom fee + enabled fee');
        $this->assertSame(250.50, $component->total_marketing_fee);
    }
}
