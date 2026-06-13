<?php

namespace App\Services;

/**
 * ScoreBreakdownService
 *
 * Wraps CompatibilityScoreService to produce a field-level breakdown explaining
 * why a score was produced. Each evaluated field is classified into one of four
 * result categories:
 *
 *   strong  — both sides populated; values match
 *   weak    — both sides populated; values do NOT match (poor fit)
 *   partial — array field (services) where some but not all listing values match
 *   missing — one or both sides did not provide this data
 *
 * IMPORTANT: "missing" and "weak" are intentionally distinct.
 *   - missing means the data was not provided — it does not imply the agent is a
 *     poor fit. It is not counted against the compatibility score.
 *   - weak means the agent did provide data but it conflicts with the listing.
 *
 * Service response shape:
 *   [
 *       'score_data'       => [...],  // Full result from CompatibilityScoreService::score()
 *       'field_breakdown'  => [
 *           [
 *               'field'         => 'commission_structure',
 *               'label'         => 'Commission Structure',
 *               'result'        => 'strong|weak|partial|missing',
 *               'listing_value' => mixed,  // null when not provided
 *               'bid_value'     => mixed,  // null when not provided
 *               'note'          => string|null,
 *           ],
 *           ...
 *       ],
 *       'summary' => [
 *           'strong'  => int,
 *           'weak'    => int,
 *           'partial' => int,
 *           'missing' => int,
 *           'total'   => int,
 *       ],
 *       'active_field_set' => 'quick_match|full_match|none',
 *   ]
 *
 * No database access, no side effects. Pure transformation.
 */
class ScoreBreakdownService
{
    /**
     * Produce a field-level score breakdown for a listing/bid pair.
     *
     * @param  array       $listingData  Decoded listing/criteria data array.
     * @param  array       $bidData      Decoded bid data array.
     * @param  string      $role         One of: 'seller', 'buyer', 'landlord', 'tenant'.
     * @param  string|null $propertyType Property type string (optional).
     * @return array
     */
    public static function breakdown(
        array $listingData,
        array $bidData,
        string $role,
        ?string $propertyType = null
    ): array {
        $scoreData  = CompatibilityScoreService::score($listingData, $bidData, $role, $propertyType);
        $scoreType  = $scoreData['score_type'];

        $emptySummary = ['strong' => 0, 'weak' => 0, 'partial' => 0, 'missing' => 0, 'total' => 0];

        if ($scoreType === 'none') {
            return [
                'score_data'       => $scoreData,
                'field_breakdown'  => [],
                'summary'          => $emptySummary,
                'active_field_set' => 'none',
            ];
        }

        $roleKey            = strtolower($role);
        $config             = config('match_readiness.' . $roleKey, []);
        $globalPlaceholders = config('match_readiness.global_placeholders', []);
        $arrayFields        = $config['array_fields'] ?? [];
        $fields             = ($scoreType === 'full_match')
            ? ($config['full_match'] ?? [])
            : ($config['quick_match'] ?? []);
        $labels             = self::fieldLabels($roleKey);

        $fieldBreakdown = [];
        $summary        = $emptySummary;

        foreach ($fields as $field) {
            $label = $labels[$field] ?? ucwords(str_replace('_', ' ', $field));

            if (in_array($field, $arrayFields, true)) {
                [$result, $note, $listingVal, $bidVal] = self::evaluateArrayField(
                    $field, $listingData, $bidData, $roleKey, $propertyType
                );
            } else {
                [$result, $note, $listingVal, $bidVal] = self::evaluateScalarField(
                    $field, $listingData, $bidData, $globalPlaceholders
                );
            }

            $fieldBreakdown[] = [
                'field'         => $field,
                'label'         => $label,
                'result'        => $result,
                'listing_value' => $listingVal,
                'bid_value'     => $bidVal,
                'note'          => $note,
            ];

            $summary[$result]++;
            $summary['total']++;
        }

        return [
            'score_data'       => $scoreData,
            'field_breakdown'  => $fieldBreakdown,
            'summary'          => $summary,
            'active_field_set' => $scoreType,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Field evaluation
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Evaluate a scalar field and return [result, note, listing_value, bid_value].
     *
     * @param  string[] $placeholders  Global placeholder values treated as "not populated".
     * @return array{string, string|null, mixed, mixed}
     */
    private static function evaluateScalarField(
        string $field,
        array $listingData,
        array $bidData,
        array $placeholders
    ): array {
        $listingRaw = $listingData[$field] ?? null;
        $bidRaw     = $bidData[$field] ?? null;

        $listingPopulated = self::isPopulated($listingRaw, $placeholders);
        $bidPopulated     = self::isPopulated($bidRaw, $placeholders);

        $listingVal = $listingPopulated ? $listingRaw : null;
        $bidVal     = $bidPopulated     ? $bidRaw     : null;

        if (!$listingPopulated || !$bidPopulated) {
            $note = 'Not provided — does not affect compatibility score.';
            return ['missing', $note, $listingVal, $bidVal];
        }

        $listingNorm = self::normalizeForMatch($listingRaw);
        $bidNorm     = self::normalizeForMatch($bidRaw);

        if ($listingNorm === $bidNorm) {
            return ['strong', null, $listingRaw, $bidRaw];
        }

        return ['weak', 'Data provided but differs from the listing terms.', $listingRaw, $bidRaw];
    }

    /**
     * Evaluate an array field (services) and return [result, note, listing_services, bid_services].
     *
     * Result rules:
     *   - Either side empty           → missing
     *   - 0 of N listing services met → weak
     *   - All N listing services met  → strong
     *   - Some met (1..N-1)           → partial
     *
     * @return array{string, string|null, array, array}
     */
    private static function evaluateArrayField(
        string $field,
        array $listingData,
        array $bidData,
        string $role,
        ?string $propertyType
    ): array {
        $listingServices = self::parseServices($listingData, $role, $propertyType);
        $bidServices     = self::parseServices($bidData,     $role, $propertyType);

        if (empty($listingServices) || empty($bidServices)) {
            return ['missing', 'Not provided — does not affect compatibility score.', $listingServices, $bidServices];
        }

        $listingCount = count($listingServices);
        $matchedCount = count(array_intersect($listingServices, $bidServices));

        if ($matchedCount === 0) {
            $note = "0 of {$listingCount} requested " . ($listingCount === 1 ? 'service' : 'services') . ' matched.';
            return ['weak', $note, $listingServices, $bidServices];
        }

        if ($matchedCount === $listingCount) {
            $note = $listingCount === 1
                ? 'Requested service matched.'
                : "All {$listingCount} requested services matched.";
            return ['strong', $note, $listingServices, $bidServices];
        }

        $note = "{$matchedCount} of {$listingCount} requested " . ($listingCount === 1 ? 'service' : 'services') . ' matched.';
        return ['partial', $note, $listingServices, $bidServices];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Field labels — human-readable, role-appropriate
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Return human-readable field labels for a given role.
     * Labels are intentionally role-specific to surface the right terminology
     * (e.g. "Commission Rate" for Seller, "Lease Fee Rate" for Landlord).
     *
     * @return array<string,string>
     */
    public static function fieldLabels(string $role): array
    {
        $shared = [
            'services'                     => 'Included Services',
            'commission_structure'         => 'Commission Structure',
            'purchase_fee_type'            => 'Commission Fee Type',
            'purchase_fee_percentage'      => 'Commission Rate',
            'purchase_fee_flat'            => 'Flat Commission Amount',
            'protection_period'            => 'Protection Period (Days)',
            'agency_agreement_timeframe'   => 'Agreement Timeframe',
            'brokerage_relationship'       => 'Brokerage Relationship',
            'early_termination_fee_option' => 'Early Termination Fee',
            'retainer_fee_option'          => 'Retainer Fee',
        ];

        $roleLabels = [
            'seller' => array_merge($shared, [
                'nominal'                     => 'Nominal Fee',
                'commission_structure_type'   => 'Commission Type',
                'seller_leasing_fee_type'     => 'Seller Leasing Fee Type',
            ]),

            'buyer' => array_merge($shared, [
                'purchase_fee_percentage'   => 'Buyer Fee Rate',
                'lease_fee_type'            => 'Lease Fee Type',
                'lease_fee_percentage'      => 'Lease Fee Rate',
            ]),

            'landlord' => array_merge($shared, [
                'purchase_fee_type'                   => 'Leasing Fee Type',
                'purchase_fee_percentage'             => 'Leasing Commission Rate',
                'purchase_fee_flat'                   => 'Flat Leasing Commission',
                'renewal_fee_type'                    => 'Renewal Fee Type',
                'broker_fee_timing'                   => 'Broker Fee Timing',
                'tenant_broker_commission_structure'  => 'Tenant Broker Commission',
                'expansion_commission_percentage'     => 'Expansion Commission Rate',
                'interested_in_property_management'   => 'Property Management Interest',
                'interested_in_selling'               => 'Interest in Selling',
            ]),

            'tenant' => array_merge($shared, [
                'purchase_fee_percentage'   => 'Tenant Fee Rate',
                'lease_fee_type'            => 'Lease Fee Type',
                'lease_fee_percentage'      => 'Lease Fee Rate',
                'broker_fee_timing'         => 'Broker Fee Timing',
            ]),
        ];

        return $roleLabels[$role] ?? $shared;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Utilities (mirrors CompatibilityScoreService internals)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Parse and normalize services from a data array for a given role.
     * Delegates to role helpers when available (matching CompatibilityScoreService behavior).
     *
     * @return string[]
     */
    private static function parseServices(array $data, string $role, ?string $propertyType): array
    {
        $helperMap = [
            'seller'   => \App\Helpers\SellerBidMatchScoreHelper::class,
            'buyer'    => \App\Helpers\BuyerBidMatchScoreHelper::class,
            'landlord' => \App\Helpers\LandlordBidMatchScoreHelper::class,
            'tenant'   => \App\Helpers\TenantBidMatchScoreHelper::class,
        ];

        $helperClass = $helperMap[$role] ?? null;

        if ($helperClass !== null && method_exists($helperClass, 'parseServices')) {
            $catalog = ($propertyType !== null && method_exists($helperClass, 'getCatalog'))
                ? $helperClass::getCatalog($propertyType)
                : null;
            return $helperClass::parseServices($data, $catalog);
        }

        $normFn = ($helperClass !== null && method_exists($helperClass, 'normalizeService'))
            ? [$helperClass, 'normalizeService']
            : [self::class, 'defaultNormalizeService'];

        $services = $data['services'] ?? [];
        if (is_string($services)) {
            $services = json_decode($services, true) ?? [];
        }
        $services = is_array($services) ? array_values(array_filter($services)) : [];

        $other = $data['other_services'] ?? [];
        if (is_string($other)) {
            $other = json_decode($other, true) ?? [];
        }
        $other = is_array($other)
            ? array_values(array_filter($other, fn($s) => is_string($s) && trim($s) !== ''))
            : [];

        $norm      = array_map($normFn, $services);
        $otherNorm = array_values(array_filter(
            array_map($normFn, $other),
            fn($s) => $s !== ''
        ));

        return array_values(array_unique(array_merge($norm, $otherNorm)));
    }

    /**
     * True when a value is considered "populated" (mirrors CompatibilityScoreService).
     */
    private static function isPopulated(mixed $value, array $placeholders = []): bool
    {
        if ($value === null) {
            return false;
        }
        if (is_array($value)) {
            return !empty($value);
        }
        $trimmed = trim((string) $value);
        if ($trimmed === '') {
            return false;
        }
        if (!empty($placeholders) && in_array($trimmed, $placeholders, true)) {
            return false;
        }
        return true;
    }

    /**
     * Normalize a scalar value for comparison (mirrors CompatibilityScoreService).
     */
    private static function normalizeForMatch(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        if (is_array($value)) {
            $parts = array_map([self::class, 'normalizeForMatch'], $value);
            sort($parts);
            return implode(',', $parts);
        }
        $v = trim((string) $value);
        $v = preg_replace('/[\x{2018}\x{2019}]/u', "'", $v);
        $v = preg_replace('/[\x{201C}\x{201D}]/u', '"', $v);
        return preg_replace('/[\s$,%]/', '', strtolower($v));
    }

    /**
     * Fallback service normalization when no role helper is available.
     */
    private static function defaultNormalizeService(string $s): string
    {
        $s = preg_replace('/[\x{2018}\x{2019}]/u', "'", $s);
        $s = preg_replace('/[\x{201C}\x{201D}]/u', '"', $s);
        $s = preg_replace('/\s+/', ' ', $s);
        return strtolower(trim($s));
    }
}
