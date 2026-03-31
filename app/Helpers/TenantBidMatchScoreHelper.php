<?php

namespace App\Helpers;

/**
 * TenantBidMatchScoreHelper
 *
 * Provides a single, deterministic, baseline-driven match score calculation
 * for Hire a Tenant's Agent bid comparisons. Used by:
 *   - hire_tenant_agent/view.blade.php (bid card, Private Data Modal, Limited Bid Modal)
 *   - hire_tenant_agent/view_counter_terms.blade.php
 *   - App\Services\CompetingBidsService
 *
 * SCORING RULES
 * =============
 * Services:
 *   baseline_total = count(baseline services)          // only Tenant's selections
 *   matched        = baseline items also in compared   // Agent kept them
 *   missing        = baseline items not in compared    // Agent removed them
 *   extra          = compared items not in baseline    // Agent added — NOT in denominator
 *   services_match_percent = matched / baseline_total * 100
 *
 * Terms:
 *   baseline_total = count(fields where baseline value is filled)
 *   matched        = baseline-filled fields with same normalized value in compared
 *   changed        = baseline-filled fields with different value in compared (includes blank compared)
 *   added_by_agent = compared-filled fields where baseline was blank — NOT in denominator
 *   terms_match_percent = matched / baseline_total * 100
 *
 * Overall = 50 % Services + 50 % Terms (only non-zero components averaged)
 */
class TenantBidMatchScoreHelper
{
    const BROKER_FIELDS = [
        'commission_structure',
        'lease_fee_type',
        'payment_timing',
        'days_to_pay',
        'broker_fee_timing',
        'broker_fee_days_from_rent',
        'interested_purchase_fee_type',
        'purchase_fee_type',
        'interested_lease_option_agreement',
        'lease_type',
        'lease_value',
        'purchase_type',
        'purchase_value',
        'protection_period',
        'early_termination_fee_option',
        'early_termination_fee_amount',
        'retainer_fee_option',
        'retainer_fee_amount',
        'retainer_fee_application',
        'agency_agreement_timeframe',
        'brokerage_relationship',
        'lease_fee_flat',
        'lease_fee_percentage',
        'lease_fee_percentage_monthly_rent',
        'lease_fee_percentage_monthly_number',
        'lease_fee_flat_combo',
        'lease_fee_percentage_combo',
        'lease_fee_percentage_net',
        'lease_fee_flat_combo_net',
        'lease_fee_percentage_combo_net',
        'lease_fee_other',
        'purchase_fee_flat',
        'purchase_fee_percentage',
        'purchase_fee_flat_combo',
        'purchase_fee_percentage_combo',
        'purchase_fee_other',
        'flat_fee_amount',
        'percent_gross_lease',
        'purchase_flat_fee_amount',
        'purchase_percent_value',
    ];

    /**
     * Normalize a scalar value for comparison (strips whitespace, $, %, commas).
     */
    public static function normalizeForMatch($v): string
    {
        if (is_null($v) || $v === '') return '';
        if (is_array($v) || is_object($v)) return json_encode($v);
        $v = trim((string) $v);
        return preg_replace('/[\s$,%]/', '', strtolower($v));
    }

    /**
     * Normalize a service string (curly quotes → straight, lowercase, trim).
     */
    public static function normalizeService(string $s): string
    {
        $s = preg_replace('/[\x{2018}\x{2019}]/u', "'", $s);
        $s = preg_replace('/[\x{201C}\x{201D}]/u', '"', $s);
        return strtolower(trim($s));
    }

    /**
     * Parse services + other_services from a data array into a flat, normalized list.
     */
    public static function parseServices(array $data): array
    {
        $services = $data['services'] ?? [];
        if (is_string($services)) $services = json_decode($services, true) ?? [];
        $services = is_array($services) ? array_values(array_filter($services)) : [];

        $other = $data['other_services'] ?? [];
        if (is_string($other)) $other = json_decode($other, true) ?? [];
        $other = is_array($other)
            ? array_values(array_filter($other, fn($s) => is_string($s) && !empty(trim($s))))
            : [];

        $all = array_merge($services, $other);
        return array_unique(array_map([self::class, 'normalizeService'], $all));
    }

    /**
     * Whether a value is considered "filled" (not blank).
     */
    private static function isFilled($v): bool
    {
        if (is_null($v)) return false;
        if (is_bool($v)) return $v;
        if (is_array($v)) return !empty($v);
        return $v !== '';
    }

    /**
     * Calculate baseline-driven match score.
     *
     * @param array $baselineData   The source-of-truth (Tenant's listing / counter / viewer's bid)
     * @param array $comparedData   The data being evaluated (Agent's bid / counter / competing bid)
     * @param array|null $brokerFields  Override the default broker fields list
     * @return array                Rich result object (see keys below)
     */
    public static function calculate(
        array $baselineData,
        array $comparedData,
        ?array $brokerFields = null
    ): array {
        $brokerFields = $brokerFields ?? self::BROKER_FIELDS;

        // ----------------------------------------------------------------
        // TERMS (Broker Compensation & Agency Agreement)
        // ----------------------------------------------------------------
        $matchedTerms      = [];   // baseline filled, compared same value
        $changedTerms      = [];   // baseline filled, compared different/blank
        $addedByAgentTerms = [];   // baseline blank, compared filled

        foreach ($brokerFields as $field) {
            $bv = $baselineData[$field] ?? null;
            $cv = $comparedData[$field] ?? null;

            $bFilled = self::isFilled($bv);
            $cFilled = self::isFilled($cv);

            if ($bFilled) {
                if ($cFilled && self::normalizeForMatch($cv) === self::normalizeForMatch($bv)) {
                    $matchedTerms[$field] = ['baseline' => $bv, 'compared' => $cv];
                } else {
                    $changedTerms[$field] = ['baseline' => $bv, 'compared' => $cv];
                }
            } elseif ($cFilled) {
                $addedByAgentTerms[$field] = ['baseline' => $bv, 'compared' => $cv];
            }
            // both blank → skip
        }

        $termsBaselineTotal  = count($matchedTerms) + count($changedTerms);
        $termsMatchedCount   = count($matchedTerms);
        $termsChangedCount   = count($changedTerms);
        $termsAddedCount     = count($addedByAgentTerms);
        $termsMatchPercent   = $termsBaselineTotal > 0
            ? (int) round(($termsMatchedCount / $termsBaselineTotal) * 100)
            : 100;

        // ----------------------------------------------------------------
        // SERVICES
        // ----------------------------------------------------------------
        $baselineNorm  = self::parseServices($baselineData);
        $comparedNorm  = self::parseServices($comparedData);

        $matchedServices = [];
        $missingServices = [];
        $extraServices   = [];

        foreach ($baselineNorm as $svc) {
            if (in_array($svc, $comparedNorm, true)) {
                $matchedServices[] = $svc;
            } else {
                $missingServices[] = $svc;
            }
        }
        foreach ($comparedNorm as $svc) {
            if (!in_array($svc, $baselineNorm, true)) {
                $extraServices[] = $svc;
            }
        }

        $servicesBaselineTotal = count($matchedServices) + count($missingServices);
        $servicesMatchedCount  = count($matchedServices);
        $servicesMissingCount  = count($missingServices);
        $servicesExtraCount    = count($extraServices);
        $servicesMatchPercent  = $servicesBaselineTotal > 0
            ? (int) round(($servicesMatchedCount / $servicesBaselineTotal) * 100)
            : 100;

        // ----------------------------------------------------------------
        // OVERALL (50 % Services + 50 % Terms, only active components)
        // ----------------------------------------------------------------
        $hasTerms    = $termsBaselineTotal > 0;
        $hasServices = $servicesBaselineTotal > 0;

        if ($hasTerms && $hasServices) {
            $overallPercent = (int) round(($termsMatchPercent + $servicesMatchPercent) / 2);
        } elseif ($hasTerms) {
            $overallPercent = $termsMatchPercent;
        } elseif ($hasServices) {
            $overallPercent = $servicesMatchPercent;
        } else {
            $overallPercent = 100;
        }

        return [
            // Overall
            'overall_percent'         => $overallPercent,

            // Terms
            'terms_baseline_total'    => $termsBaselineTotal,
            'terms_matched_count'     => $termsMatchedCount,
            'terms_changed_count'     => $termsChangedCount,
            'terms_added_count'       => $termsAddedCount,
            'terms_match_percent'     => $termsMatchPercent,
            'matched_terms'           => $matchedTerms,
            'changed_terms'           => $changedTerms,
            'added_terms'             => $addedByAgentTerms,

            // Services
            'services_baseline_total' => $servicesBaselineTotal,
            'services_matched_count'  => $servicesMatchedCount,
            'services_missing_count'  => $servicesMissingCount,
            'services_extra_count'    => $servicesExtraCount,
            'services_match_percent'  => $servicesMatchPercent,
            'matched_services'        => $matchedServices,
            'missing_services'        => $missingServices,
            'extra_services'          => $extraServices,

            // Legacy-compat aliases (for CompetingBidsService return shape)
            'broker_comp_percent'     => $termsMatchPercent,
            'broker_comp_matched'     => $termsMatchedCount,
            'broker_comp_total'       => $termsBaselineTotal,
            'services_percent'        => $servicesMatchPercent,
            'services_matched'        => $servicesMatchedCount,
            'services_total'          => $servicesBaselineTotal,
        ];
    }

    /**
     * Return a CSS color string for a given score percentage.
     */
    public static function scoreColor(int $score): string
    {
        if ($score >= 80) return '#28a745';
        if ($score >= 50) return '#ffc107';
        return '#dc3545';
    }
}
