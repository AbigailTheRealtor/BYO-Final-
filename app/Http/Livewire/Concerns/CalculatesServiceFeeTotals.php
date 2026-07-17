<?php

namespace App\Http\Livewire\Concerns;

/**
 * Shared, precision-safe computation of Landlord/Tenant service-fee totals.
 *
 * Batch B1.3 (Money Precision). The Landlord/Tenant "Class-A" fee totals
 * (total_flat_fee / total_marketing_fee) are the only landlord/tenant EAV
 * financial values that feed live arithmetic. The legacy inline implementation
 * summed `(float) $service['fee']` directly, which had two defects:
 *
 *   1. Comma truncation — the custom_services[].fee / fees[] inputs are NOT
 *      comma-stripped on entry, so `(float) "1,000"` silently collapsed to 1.0,
 *      dropping magnitude from a live sum.
 *   2. Float accumulation drift — repeated `+=` on binary floats produced
 *      artifacts such as 30.299999999996.
 *
 * This trait centralises the fix: every monetary input is normalised (currency
 * symbols, thousands separators and stray whitespace removed) and the sum is
 * accumulated in integer cents, so the result is exact to two decimal places.
 * The returned totals are numerically identical to the old behaviour for clean
 * input, so downstream UI/display is preserved.
 */
trait CalculatesServiceFeeTotals
{
    /**
     * Normalise a user-entered money value to a float.
     *
     * Accepts null, '', numeric strings, and strings containing '$', ','
     * or whitespace (e.g. "$1,200.50"). Non-numeric junk resolves to 0.0.
     */
    protected function normalizeMoney($value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        $clean = str_replace(['$', ',', ' '], '', (string) $value);

        return is_numeric($clean) ? (float) $clean : 0.0;
    }

    /**
     * Convert a user-entered money value to an integer number of cents,
     * rounded to the nearest cent. This is the unit of accumulation used to
     * avoid binary-float drift when summing many fees.
     */
    protected function moneyToCents($value): int
    {
        return (int) round($this->normalizeMoney($value) * 100);
    }

    /**
     * Compute the flat-fee and marketing-fee totals from the custom services
     * array and the enabled fee-structure entries, exact to two decimals.
     *
     * @param  array  $customServices  list of ['fee' => ..., 'marketing_fee' => ...]
     * @param  array  $fees            fee-structure map [key => value]
     * @param  array  $enable          [key => bool] gate for each fee-structure entry
     * @return array{total_flat_fee: float, total_marketing_fee: float}
     */
    protected function calculateServiceFeeTotals(array $customServices, array $fees, array $enable): array
    {
        $flatCents = 0;
        $marketingCents = 0;

        foreach ($customServices as $service) {
            if (is_array($service) && array_key_exists('fee', $service)) {
                $flatCents += $this->moneyToCents($service['fee']);
            }
            if (is_array($service) && array_key_exists('marketing_fee', $service)) {
                $marketingCents += $this->moneyToCents($service['marketing_fee']);
            }
        }

        foreach ($fees as $feeKey => $feeValue) {
            if (isset($enable[$feeKey]) && $enable[$feeKey] && $feeValue !== null) {
                $flatCents += $this->moneyToCents($feeValue);
            }
        }

        return [
            'total_flat_fee' => (float) $flatCents / 100,
            'total_marketing_fee' => (float) $marketingCents / 100,
        ];
    }
}
