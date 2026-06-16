<?php

namespace App\Traits;

/**
 * AgentMatchSubScorer
 *
 * Shared trait for all four *BidMatchScoreHelper classes.
 * Provides scoring sub-components for:
 *   - Service Area (15% weight, currently disabled)
 *   - Experience (10% weight, currently disabled)
 *   - Availability & Communication (5% weight, currently disabled)
 *   - Compatibility (0% weight, deferred — no agent-side data)
 *
 * Also replaces the hardcoded 50/50 Services/Terms split with a
 * config-driven weighted average via computeWeightedOverall().
 * When all new dimensions are disabled (current state), the formula
 * produces identical results to the original 50/50 formula.
 *
 * @see config/match_scoring.php
 * @see docs/audits/MATCHING_ENGINE_PHASE1_AUDIT.md
 */
trait AgentMatchSubScorer
{
    // ─────────────────────────────────────────────────────────────────────────
    // OVERALL WEIGHTED SCORE
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Compute the overall match score using the config-driven weight table.
     *
     * Implements the partial-activation model:
     *   overall = Σ(enabled_weight × score) / Σ(enabled_weights)
     *
     * A dimension's weight is zeroed out when:
     *   - Its 'enabled' flag is false in config, OR
     *   - The client has no comparable data (hasServices=false, hasTerms=false)
     *
     * When all new dimensions are disabled (current config), the formula
     * reduces to (services + terms) / 2 — identical to the legacy formula.
     */
    protected static function computeWeightedOverall(
        int $servicesPercent,
        int $termsPercent,
        bool $hasServices,
        bool $hasTerms,
        int $serviceAreaScore,
        int $experienceScore,
        int $availabilityScore,
        int $compatibilityScore
    ): int {
        $dims = config('match_scoring.dimensions', []);

        $svcEnabled    = ($dims['services']['enabled']      ?? true)  && $hasServices;
        $termsEnabled  = ($dims['terms']['enabled']         ?? true)  && $hasTerms;
        $saEnabled     = ($dims['service_area']['enabled']  ?? false);
        $expEnabled    = ($dims['experience']['enabled']    ?? false);
        $availEnabled  = ($dims['availability']['enabled']  ?? false);
        $compatEnabled = ($dims['compatibility']['enabled'] ?? false);

        // Weights come exclusively from config/match_scoring.php.
        // Fallback is 0 (safe degradation) — a missing config key is a config bug,
        // not a reason to silently inject a hardcoded constant.
        $svcWeight    = $svcEnabled    ? (int)($dims['services']['weight']      ?? 0) : 0;
        $termsWeight  = $termsEnabled  ? (int)($dims['terms']['weight']         ?? 0) : 0;
        $saWeight     = $saEnabled     ? (int)($dims['service_area']['weight']  ?? 0) : 0;
        $expWeight    = $expEnabled    ? (int)($dims['experience']['weight']    ?? 0) : 0;
        $availWeight  = $availEnabled  ? (int)($dims['availability']['weight']  ?? 0) : 0;
        $compatWeight = $compatEnabled ? (int)($dims['compatibility']['weight'] ?? 0) : 0;

        $totalWeight = $svcWeight + $termsWeight + $saWeight + $expWeight + $availWeight + $compatWeight;

        if ($totalWeight === 0) {
            // Fallback if no dimensions are active (should not happen in practice)
            if ($hasTerms && $hasServices) {
                return (int) round(($servicesPercent + $termsPercent) / 2);
            }
            if ($hasTerms)    return $termsPercent;
            if ($hasServices) return $servicesPercent;
            return 100;
        }

        $weighted  = $svcWeight    * $servicesPercent;
        $weighted += $termsWeight  * $termsPercent;
        $weighted += $saWeight     * $serviceAreaScore;
        $weighted += $expWeight    * $experienceScore;
        $weighted += $availWeight  * $availabilityScore;
        $weighted += $compatWeight * $compatibilityScore;

        return (int) round($weighted / $totalWeight);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SERVICE AREA SCORING
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Score service-area overlap between agent's served areas and client's
     * target location.
     *
     * Returns 0–100.
     * Returns the configured neutral score (default 50) when:
     *   - Client has no location data in $baselineData
     *   - Role is 'seller' (city_id / county_id FK requires a DB join; helpers
     *     must remain pure — the call site must enrich $baselineData with
     *     city_name / county_name keys if city-level matching is desired)
     *
     * @param string $role 'seller'|'buyer'|'landlord'|'tenant'
     */
    protected static function scoreServiceArea(
        array $baselineData,
        array $agentProfileData,
        string $role
    ): int {
        $cfg         = config('match_scoring.service_area', []);
        $neutralScore = (int)($cfg['no_client_location_default_score'] ?? 50);

        // Explicitly inactive roles — e.g. 'seller', whose city_id/county_id are
        // integer FKs that require a DB join to resolve to names. Helpers must
        // remain pure (no DB calls). Return neutral until an enrichment path exists.
        // Controlled via config('match_scoring.service_area.inactive_for_roles').
        $inactiveRoles = $cfg['inactive_for_roles'] ?? ['seller'];
        if (in_array($role, $inactiveRoles, true)) {
            return $neutralScore;
        }

        // Agent served areas — comma-separated strings in profile_data
        $agentCities   = self::splitServedList((string)($agentProfileData['cities_served']   ?? ''), $cfg);
        $agentCounties = self::splitServedList((string)($agentProfileData['counties_served'] ?? ''), $cfg);

        [$clientCities, $clientCounties] = self::parseClientLocation($baselineData, $role, $cfg);

        // No client location — award neutral score
        if (empty($clientCities) && empty($clientCounties)) {
            return $neutralScore;
        }

        // Agent has no served areas — hard 0
        if (empty($agentCities) && empty($agentCounties)) {
            return 0;
        }

        $hasCities   = !empty($clientCities);
        $hasCounties = !empty($clientCounties);

        $cityScore   = $hasCities   ? self::locationOverlapScore($clientCities,   $agentCities)   : $neutralScore;
        $countyScore = $hasCounties ? self::locationOverlapScore($clientCounties, $agentCounties) : $neutralScore;

        if ($hasCities && $hasCounties) {
            return (int) round(($cityScore + $countyScore) / 2);
        }
        return $hasCities ? $cityScore : $countyScore;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // EXPERIENCE SCORING
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Score agent experience based on year_licensed and
     * transactions_last_12_months from profile_data.
     *
     * Returns 0–100.
     * Null / empty fields contribute 0 to that sub-component (not inferred).
     */
    protected static function scoreExperience(array $agentProfileData): int
    {
        $caps        = config('match_scoring.experience_caps', []);
        $yearsCap    = max(1, (int)($caps['years_cap']           ?? 20));
        $txnCap      = max(1, (int)($caps['transactions_cap']    ?? 30));
        $yearsWeight = (float)($caps['years_weight']             ?? 0.70);
        $txnWeight   = (float)($caps['transactions_weight']      ?? 0.30);

        // Years since licensed
        $yearLicensed = trim((string)($agentProfileData['year_licensed'] ?? ''));
        $yearsScore   = 0.0;
        if ($yearLicensed !== '' && is_numeric($yearLicensed)) {
            $currentYear = (int) date('Y');
            $yearsExp    = max(0, $currentYear - (int)$yearLicensed);
            $yearsScore  = min($yearsExp, $yearsCap) / $yearsCap;
        }

        // Transactions in last 12 months
        $txns     = trim((string)($agentProfileData['transactions_last_12_months'] ?? ''));
        $txnScore = 0.0;
        if ($txns !== '' && is_numeric($txns)) {
            $txnScore = min((int)$txns, $txnCap) / $txnCap;
        }

        return (int) round(($yearsScore * $yearsWeight + $txnScore * $txnWeight) * 100);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AVAILABILITY & COMMUNICATION SCORING
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Score availability and communication alignment.
     *
     * Blends two sub-components (weights from config):
     *   1. Communication method match — client vs. agent preferred method
     *   2. Scheduling availability — agent-side only (evenings, weekends, status)
     *
     * Returns 0–100.
     */
    protected static function scoreAvailability(
        array $baselineData,
        array $agentProfileData
    ): int {
        $cfg          = config('match_scoring.availability', []);
        $commWeight   = (float)($cfg['comm_method_weight'] ?? 0.50);
        $schedWeight  = (float)($cfg['scheduling_weight']  ?? 0.50);

        // ── Sub-component 1: communication method ─────────────────────────
        $clientMethod = strtolower(trim((string)($baselineData['client_preferred_comm_method'] ?? '')));
        $agentMethod  = strtolower(trim((string)($agentProfileData['preferred_contact_method'] ?? '')));

        if ($clientMethod === '' || $agentMethod === '') {
            // Missing data — award agent_any_score (neutral)
            $commScore = (int)($cfg['agent_any_score'] ?? 80);
        } elseif ($agentMethod === 'any') {
            $commScore = (int)($cfg['agent_any_score'] ?? 80);
        } elseif ($clientMethod === 'other') {
            // "Other" cannot be resolved — treat as neutral
            $commScore = (int)($cfg['agent_any_score'] ?? 80);
        } elseif ($clientMethod === $agentMethod) {
            $commScore = (int)($cfg['method_match_score'] ?? 100);
        } else {
            $commScore = (int)($cfg['method_no_match_score'] ?? 0);
        }

        // ── Sub-component 2: scheduling availability (agent-only) ─────────
        $eveningsPts = (int)($cfg['evenings_points'] ?? 33);
        $weekendsPts = (int)($cfg['weekends_points'] ?? 33);
        $statusPts   = (int)($cfg['status_points']   ?? 34);
        $statusMap   = $cfg['availability_status_scores'] ?? [];

        $schedScore = 0;
        if (strtolower(trim((string)($agentProfileData['evenings_available'] ?? ''))) === 'yes') {
            $schedScore += $eveningsPts;
        }
        if (strtolower(trim((string)($agentProfileData['weekends_available'] ?? ''))) === 'yes') {
            $schedScore += $weekendsPts;
        }
        $status      = trim((string)($agentProfileData['availability_status'] ?? ''));
        $schedScore += (int)($statusMap[$status] ?? 0);

        return (int) round($commScore * $commWeight + $schedScore * $schedWeight);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // COMPATIBILITY SCORING (DEFERRED)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Compatibility dimension — always returns 0.
     *
     * Deferred: agent-side compatibility data is absent in all profiles
     * and bid meta tables. Schema reconciliation required first.
     * Weight is 0% in config; this stub satisfies the interface.
     *
     * @see config/match_scoring.php (compatibility block for full explanation)
     */
    protected static function scoreCompatibility(
        array $baselineData,
        array $agentProfileData
    ): int {
        return 0;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // INTERNAL HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Split a comma-separated served-area string and normalize each token.
     *
     * @return string[]
     */
    private static function splitServedList(string $raw, array $cfg = []): array
    {
        $trimmed = trim($raw);
        if ($trimmed === '') return [];

        $parts = array_map('trim', explode(',', $trimmed));
        $result = [];
        foreach ($parts as $part) {
            $normalized = self::normalizeAreaName($part, $cfg);
            if ($normalized !== '') {
                $result[] = $normalized;
            }
        }
        return $result;
    }

    /**
     * Normalize a geographic area name:
     *   - Strip configured city/county state suffixes
     *   - Lowercase and trim
     */
    private static function normalizeAreaName(string $name, array $cfg = []): string
    {
        if ($cfg === []) {
            $cfg = config('match_scoring.service_area', []);
        }
        $suffixes = array_merge(
            $cfg['city_suffix_to_strip']   ?? [', FL'],
            $cfg['county_suffix_to_strip'] ?? [' County, FL', ' County']
        );

        $name = trim($name);
        foreach ($suffixes as $sfx) {
            if (str_ends_with($name, $sfx)) {
                $name = substr($name, 0, strlen($name) - strlen($sfx));
                break;
            }
        }
        return strtolower(trim($name));
    }

    /**
     * Parse client location (cities and counties) from baseline data.
     *
     * Returns [string[] $cities, string[] $counties] — both normalized.
     *
     * Notes:
     *   - Seller: city_id / county_id are integer FKs requiring a DB join.
     *     Helpers must remain pure (no DB calls). Returns empty arrays unless
     *     the call site enriches $baselineData with 'city_name' / 'county_name'
     *     keys.
     *   - Buyer / Tenant: cities and counties are JSON arrays in meta.
     *   - Landlord: property_city / property_county are plain strings in meta.
     */
    private static function parseClientLocation(array $baselineData, string $role, array $cfg): array
    {
        $locKeys = $cfg['client_location_keys'][$role] ?? null;
        if (!$locKeys) return [[], []];

        $source    = $locKeys['source'] ?? 'meta';
        $cityKey   = $locKeys['city']   ?? 'cities';
        $countyKey = $locKeys['county'] ?? 'counties';

        if ($source === 'native_columns') {
            // Seller: integer FK IDs — DB join required to resolve names.
            // Check whether the call site already resolved names and passed them.
            $cityName   = trim((string)($baselineData['city_name']   ?? ''));
            $countyName = trim((string)($baselineData['county_name'] ?? ''));

            $cities   = $cityName   !== '' ? [self::normalizeAreaName($cityName,   $cfg)] : [];
            $counties = $countyName !== '' ? [self::normalizeAreaName($countyName, $cfg)] : [];
            return [$cities, $counties];
        }

        // 'meta' source: JSON array or plain string
        $rawCity   = $baselineData[$cityKey]   ?? null;
        $rawCounty = $baselineData[$countyKey] ?? null;

        $cities   = self::parseLocationValue($rawCity);
        $counties = self::parseLocationValue($rawCounty);

        return [
            array_map(fn(string $n) => self::normalizeAreaName($n, $cfg), $cities),
            array_map(fn(string $n) => self::normalizeAreaName($n, $cfg), $counties),
        ];
    }

    /**
     * Parse a location value that may be a JSON array, plain string, or null.
     *
     * @return string[]
     */
    private static function parseLocationValue(mixed $raw): array
    {
        if ($raw === null || $raw === '') return [];

        if (is_array($raw)) {
            return array_values(array_filter(array_map('strval', $raw), fn($s) => trim($s) !== ''));
        }

        $decoded = json_decode((string)$raw, true);
        if (is_array($decoded)) {
            return array_values(array_filter(array_map('strval', $decoded), fn($s) => trim($s) !== ''));
        }

        $trimmed = trim((string)$raw);
        return $trimmed !== '' ? [$trimmed] : [];
    }

    /**
     * Compute overlap score: how many client locations appear in agent's list.
     *
     * Uses substring matching as a fallback for name-form mismatches
     * (e.g. "st. pete" vs "st. petersburg").
     *
     * overlap_count / client_count × 100
     */
    private static function locationOverlapScore(array $clientList, array $agentList): int
    {
        if (empty($clientList)) return 50;

        $overlap = 0;
        foreach ($clientList as $clientItem) {
            foreach ($agentList as $agentItem) {
                if ($clientItem === $agentItem
                    || str_contains($agentItem,  $clientItem)
                    || str_contains($clientItem, $agentItem)
                ) {
                    $overlap++;
                    break;
                }
            }
        }

        return (int) round($overlap / count($clientList) * 100);
    }
}
