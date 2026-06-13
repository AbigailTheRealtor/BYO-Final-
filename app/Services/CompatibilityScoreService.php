<?php

namespace App\Services;

/**
 * CompatibilityScoreService
 *
 * Computes a readiness-gated compatibility score between a listing and an agent bid.
 * Uses MatchReadinessService to determine the active readiness state, then scores
 * only the fields defined in config/match_readiness.php for that state and role.
 *
 * Service response shape:
 *   [
 *       'readiness_state' => 'not_ready|quick_match_ready|full_match_ready',
 *       'score_type'      => 'quick_match|full_match|none',
 *       'score'           => 78,   // null when score_type is 'none'
 *   ]
 *
 * Scoring rules:
 *   - Missing fields (null/''/whitespace/global_placeholders) are skipped — not treated
 *     as mismatches — in both listing and bid.
 *   - If no comparable fields remain after skipping, returns score_type 'none'.
 *   - Services (array field) contribute a fractional weight: matched/listing_total.
 *     If either side has no services the field is skipped entirely.
 *   - All scalar fields contribute 1.0 weight; match = 1.0, mismatch = 0.0.
 *   - Score = round(sum(matched_weight) / sum(total_weight) * 100).
 *   - Score display rule:
 *       full_match_ready  → score_type 'full_match'  (Full Match fields used)
 *       quick_match_ready → score_type 'quick_match' (Quick Match fields used)
 *       not_ready         → score_type 'none', score null
 *   - Both scores are never returned simultaneously.
 *   - Configuration drives all field participation — no hardcoded field lists here.
 *
 * Service parsing delegates to the role helper's parseServices() method when available
 * (Buyer, Tenant) so that alias resolution and canonical normalization match existing
 * helper behavior exactly. Seller and Landlord use the helper's normalizeService().
 *
 * Roles supported: seller, buyer, landlord, tenant.
 * No database access, no side effects. Pure transformation.
 */
class CompatibilityScoreService
{
    /**
     * Compute the compatibility score between a listing and an agent bid.
     *
     * @param  array       $listingData  Decoded listing/criteria data array.
     * @param  array       $bidData      Decoded bid data array.
     * @param  string      $role         One of: 'seller', 'buyer', 'landlord', 'tenant'.
     * @param  string|null $propertyType Property type string for catalog filtering (optional).
     * @return array{
     *     readiness_state: string,
     *     score_type: string,
     *     score: int|null,
     * }
     */
    public static function score(
        array $listingData,
        array $bidData,
        string $role,
        ?string $propertyType = null
    ): array {
        $readiness = MatchReadinessService::evaluate($bidData, $role);
        $state     = $readiness['state'];

        if ($state === 'not_ready') {
            return [
                'readiness_state' => 'not_ready',
                'score_type'      => 'none',
                'score'           => null,
            ];
        }

        $roleKey = strtolower($role);
        $config  = config('match_readiness.' . $roleKey);

        if (empty($config)) {
            return [
                'readiness_state' => $state,
                'score_type'      => 'none',
                'score'           => null,
            ];
        }

        $globalPlaceholders = config('match_readiness.global_placeholders', []);
        $arrayFields        = $config['array_fields'] ?? [];

        if ($state === 'full_match_ready') {
            $fields    = $config['full_match'] ?? [];
            $scoreType = 'full_match';
        } else {
            $fields    = $config['quick_match'] ?? [];
            $scoreType = 'quick_match';
        }

        $matchedWeight = 0.0;
        $totalWeight   = 0.0;

        foreach ($fields as $field) {
            if (in_array($field, $arrayFields, true)) {
                $result = self::scoreArrayField($field, $listingData, $bidData, $roleKey, $propertyType);
                if ($result !== null) {
                    $matchedWeight += $result;
                    $totalWeight   += 1.0;
                }
            } else {
                $listingVal = $listingData[$field] ?? null;
                $bidVal     = $bidData[$field] ?? null;

                if (!self::isPopulated($listingVal, $globalPlaceholders)
                    || !self::isPopulated($bidVal, $globalPlaceholders)) {
                    continue;
                }

                $totalWeight += 1.0;
                if (self::normalizeForMatch($listingVal, $roleKey)
                    === self::normalizeForMatch($bidVal, $roleKey)) {
                    $matchedWeight += 1.0;
                }
            }
        }

        if ($totalWeight <= 0.0) {
            return [
                'readiness_state' => $state,
                'score_type'      => 'none',
                'score'           => null,
            ];
        }

        $score = (int) round($matchedWeight / $totalWeight * 100);

        return [
            'readiness_state' => $state,
            'score_type'      => $scoreType,
            'score'           => $score,
        ];
    }

    /**
     * Score a services (array) field.
     *
     * Delegates to the role helper's parseServices() when available (Buyer, Tenant)
     * so alias resolution and canonical normalization match existing helper behavior.
     *
     * Returns a fractional weight (0.0–1.0) representing matched/listing_total,
     * or null if either side has no services (field skipped).
     */
    private static function scoreArrayField(
        string $field,
        array $listingData,
        array $bidData,
        string $role,
        ?string $propertyType
    ): ?float {
        $listingServices = self::parseServices($listingData, $role, $propertyType);
        $bidServices     = self::parseServices($bidData, $role, $propertyType);

        if (empty($listingServices) || empty($bidServices)) {
            return null;
        }

        $listingCount = count($listingServices);
        $matchedCount = count(array_intersect($listingServices, $bidServices));

        return $listingCount > 0 ? $matchedCount / $listingCount : null;
    }

    /**
     * Parse and normalize services from a data array for a given role/propertyType.
     *
     * Delegation rules per role:
     *   - Buyer  → BuyerBidMatchScoreHelper::parseServices()  (alias + prefix resolution)
     *   - Tenant → TenantBidMatchScoreHelper::parseServices()  (catalog membership filter)
     *   - Seller / Landlord → inline parsing using each helper's normalizeService()
     *
     * @return string[]  Normalized service strings.
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

        // Buyer and Tenant expose parseServices() with full alias/canonical logic
        if ($helperClass !== null && method_exists($helperClass, 'parseServices')) {
            $catalog = ($propertyType !== null && method_exists($helperClass, 'getCatalog'))
                ? $helperClass::getCatalog($propertyType)
                : null;
            return $helperClass::parseServices($data, $catalog);
        }

        // Seller and Landlord: inline parsing with the helper's normalizeService()
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

        $servicesNorm = array_map($normFn, $services);
        $otherNorm    = array_values(array_filter(
            array_map($normFn, $other),
            fn($s) => $s !== ''
        ));

        return array_values(array_unique(array_merge($servicesNorm, $otherNorm)));
    }

    /**
     * Return the normalized services catalog for the given role and property type.
     * Delegates to the role-specific helper's getCatalog() when available.
     * Returns empty array (no catalog filtering) otherwise.
     */
    private static function getCatalog(string $role, string $propertyType): array
    {
        $helperMap = [
            'seller'   => \App\Helpers\SellerBidMatchScoreHelper::class,
            'buyer'    => \App\Helpers\BuyerBidMatchScoreHelper::class,
            'landlord' => \App\Helpers\LandlordBidMatchScoreHelper::class,
            'tenant'   => \App\Helpers\TenantBidMatchScoreHelper::class,
        ];

        $helperClass = $helperMap[$role] ?? null;
        if ($helperClass === null || !method_exists($helperClass, 'getCatalog')) {
            return [];
        }

        return $helperClass::getCatalog($propertyType);
    }

    /**
     * Normalize a scalar value for comparison.
     * Delegates to the role helper's normalizeForMatch() when public and available.
     * Falls back to inline normalization otherwise.
     */
    private static function normalizeForMatch(mixed $value, string $role = ''): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        if (is_array($value)) {
            $parts = array_map(fn($v) => self::normalizeForMatch($v, $role), $value);
            sort($parts);
            return implode(',', $parts);
        }
        $v = trim((string) $value);
        $v = preg_replace('/[\x{2018}\x{2019}]/u', "'", $v);
        $v = preg_replace('/[\x{201C}\x{201D}]/u', '"', $v);
        return preg_replace('/[\s$,%]/', '', strtolower($v));
    }

    /**
     * Default service normalization: lowercase, trim, normalize smart quotes.
     * Used only when the role helper does not expose normalizeService().
     */
    private static function defaultNormalizeService(string $s): string
    {
        $s = preg_replace('/[\x{2018}\x{2019}]/u', "'", $s);
        $s = preg_replace('/[\x{201C}\x{201D}]/u', '"', $s);
        $s = preg_replace('/\s+/', ' ', $s);
        return strtolower(trim($s));
    }

    /**
     * Return true when a scalar value is considered "populated" for scoring purposes.
     * Mirrors MatchReadinessService population rules.
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
}
