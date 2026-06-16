<?php

namespace App\Services;

/**
 * AgentMatchExplanationBuilder
 *
 * Converts a raw score result array (from a *BidMatchScoreHelper::calculate() call)
 * plus optional agent profile data into a human-readable label and a list of
 * plain-language reason strings suitable for display in bid card views.
 *
 * CONTRACT
 * ════════
 * Input:
 *   $scoreResult      — array returned by any *BidMatchScoreHelper::calculate()
 *   $agentProfileData — agent's profile_data array from AgentDefaultProfile (or null)
 *
 * Output: [
 *   'label'   => '87% Match',
 *   'reasons' => [
 *       'Services: all requested services covered',
 *       'Terms: compensation terms fully aligned',
 *       'Experience: 12 years as a licensed agent',     ← only when dim enabled
 *       'Availability: actively taking new clients',    ← only when dim enabled
 *   ],
 * ]
 *
 * Design rules:
 *   1. No raw numeric fractions ("2/3 services") — qualitative language only.
 *   2. Reason lines for disabled dimensions are omitted entirely. A disabled
 *      dimension does not affect the score and must not be presented as if it does.
 *   3. If a piece of data is absent, that reason line is omitted entirely.
 *   4. Pure transformation: no DB calls, no side effects.
 */
class AgentMatchExplanationBuilder
{
    /**
     * Build a human-readable explanation for a match score result.
     *
     * @param  array      $scoreResult      Full result from *BidMatchScoreHelper::calculate().
     * @param  array|null $agentProfileData Agent's profile_data (may be null for legacy callers).
     * @return array{label: string, reasons: string[]}
     */
    public static function build(array $scoreResult, ?array $agentProfileData = null): array
    {
        $overall = (int)($scoreResult['overall_percent'] ?? 0);
        $label   = $overall . '% Match';

        // Load dimension enabled flags once — used to gate reason lines.
        $dims         = config('match_scoring.dimensions', []);
        $saEnabled    = (bool)($dims['service_area']['enabled']  ?? false);
        $expEnabled   = (bool)($dims['experience']['enabled']    ?? false);
        $availEnabled = (bool)($dims['availability']['enabled']  ?? false);

        $reasons = [];

        // ── Services ─────────────────────────────────────────────────────
        $reasons[] = self::buildServicesReason($scoreResult);

        // ── Terms ─────────────────────────────────────────────────────────
        $reasons[] = self::buildTermsReason($scoreResult);

        // ── Experience (only when dimension is enabled) ────────────────────
        if ($expEnabled && $agentProfileData !== null) {
            $expLine = self::buildExperienceLine($agentProfileData);
            if ($expLine !== null) {
                $reasons[] = $expLine;
            }
        }

        // ── Availability (only when dimension is enabled) ──────────────────
        if ($availEnabled && $agentProfileData !== null) {
            $availLine = self::buildAvailabilityLine($agentProfileData);
            if ($availLine !== null) {
                $reasons[] = $availLine;
            }
        }

        // ── Service area (only when dimension is enabled) ──────────────────
        if ($saEnabled && $agentProfileData !== null) {
            $saLine = self::buildServiceAreaLine($agentProfileData);
            if ($saLine !== null) {
                $reasons[] = $saLine;
            }
        }

        return [
            'label'   => $label,
            'reasons' => $reasons,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Services reason
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Build a qualitative services reason string.
     * Never exposes raw counts or fractions.
     */
    private static function buildServicesReason(array $scoreResult): string
    {
        $total   = (int)($scoreResult['services_baseline_total'] ?? 0);
        $matched = (int)($scoreResult['services_matched_count']  ?? 0);
        $extra   = (int)($scoreResult['services_extra_count']    ?? 0);

        if ($total === 0) {
            return 'Services: no specific services required by this listing';
        }

        $ratio = $matched / $total;

        if ($matched === $total) {
            $line = 'Services: all requested services covered';
        } elseif ($ratio >= 0.75) {
            $line = 'Services: most requested services covered';
        } elseif ($ratio >= 0.50) {
            $line = 'Services: roughly half the requested services covered';
        } elseif ($ratio > 0) {
            $line = 'Services: some requested services covered; others not offered';
        } else {
            $line = 'Services: none of the requested services are offered';
        }

        if ($extra > 0) {
            $line .= ' (additional services also offered)';
        }

        return $line;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Terms reason
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Build a qualitative terms reason string.
     * Never exposes raw counts or fractions.
     */
    private static function buildTermsReason(array $scoreResult): string
    {
        $total   = (int)($scoreResult['terms_baseline_total'] ?? 0);
        $matched = (int)($scoreResult['terms_matched_count']  ?? 0);
        $changed = (int)($scoreResult['terms_changed_count']  ?? 0);
        $added   = (int)($scoreResult['terms_added_count']    ?? 0);

        if ($total === 0) {
            return 'Terms: no compensation terms on file to compare';
        }

        $ratio = $matched / $total;

        if ($matched === $total) {
            return 'Terms: compensation terms fully aligned';
        }

        if ($ratio >= 0.75) {
            $line = 'Terms: compensation terms largely aligned';
        } elseif ($ratio >= 0.50) {
            $line = 'Terms: compensation terms partially aligned';
        } else {
            $line = 'Terms: significant differences in compensation terms';
        }

        $notes = [];
        if ($changed > 0) {
            $notes[] = 'some terms differ';
        }
        if ($added > 0) {
            $notes[] = 'additional terms proposed by this agent';
        }
        if (!empty($notes)) {
            $line .= ' (' . implode('; ', $notes) . ')';
        }

        return $line;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Experience reason (only rendered when experience dimension is enabled)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Build an experience reason string.
     * Returns null when no experience data is present.
     */
    private static function buildExperienceLine(array $profileData): ?string
    {
        $yearLicensed = trim((string)($profileData['year_licensed'] ?? ''));
        $txns         = trim((string)($profileData['transactions_last_12_months'] ?? ''));

        $parts = [];

        if ($yearLicensed !== '' && is_numeric($yearLicensed)) {
            $yearsExp = max(0, (int) date('Y') - (int)$yearLicensed);
            if ($yearsExp === 0) {
                $parts[] = 'newly licensed';
            } elseif ($yearsExp === 1) {
                $parts[] = '1 year as a licensed agent';
            } else {
                $parts[] = "{$yearsExp} years as a licensed agent";
            }
        }

        if ($txns !== '' && is_numeric($txns) && (int)$txns > 0) {
            $n = (int)$txns;
            $parts[] = $n === 1 ? 'active in the past year' : 'active in the past year with recent closings';
        }

        if (empty($parts)) {
            return null;
        }

        return 'Experience: ' . implode('; ', $parts);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Availability reason (only rendered when availability dimension is enabled)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Build an availability reason string.
     * Returns null when no availability data is present.
     */
    private static function buildAvailabilityLine(array $profileData): ?string
    {
        $status   = trim((string)($profileData['availability_status']    ?? ''));
        $evenings = strtolower(trim((string)($profileData['evenings_available'] ?? '')));
        $weekends = strtolower(trim((string)($profileData['weekends_available'] ?? '')));
        $commMeth = trim((string)($profileData['preferred_contact_method'] ?? ''));

        $parts = [];

        // Status line
        $statusMap = [
            'Actively Taking New Clients' => 'actively taking new clients',
            'Limited Availability'         => 'limited availability',
            'Not Available'                => 'not currently available',
        ];
        if (isset($statusMap[$status])) {
            $parts[] = $statusMap[$status];
        }

        // Scheduling
        $schedule = [];
        if ($evenings === 'yes') $schedule[] = 'evenings';
        if ($weekends === 'yes') $schedule[] = 'weekends';
        if (!empty($schedule)) {
            $parts[] = 'available ' . implode(' and ', $schedule);
        }

        // Communication preference (only if agent specified something non-generic)
        $commLower = strtolower($commMeth);
        if ($commMeth !== '' && $commLower !== 'any') {
            $parts[] = 'prefers ' . $commMeth;
        }

        if (empty($parts)) {
            return null;
        }

        return 'Availability: ' . implode('; ', $parts);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Service area reason (only rendered when service_area dimension is enabled)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Build a service area reason string.
     * Returns null when no area data is present.
     */
    private static function buildServiceAreaLine(array $profileData): ?string
    {
        $cities   = trim((string)($profileData['cities_served']   ?? ''));
        $counties = trim((string)($profileData['counties_served'] ?? ''));

        if ($cities === '' && $counties === '') {
            return null;
        }

        $parts = [];
        if ($cities !== '') {
            $parts[] = $cities;
        }
        if ($counties !== '') {
            $parts[] = $counties . ' county';
        }

        return 'Serves: ' . implode(' and ', $parts);
    }
}
