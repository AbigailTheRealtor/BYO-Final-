<?php

namespace App\Services\Dna\Scores;

use App\Services\Canonical\CanonicalListing;
use App\Services\Dna\Scores\Contracts\SymmetricScoreService;
use App\Services\Dna\Scores\Support\ScalarScoreHelpers;

/**
 * LockAndLeaveScoreService — Beyond-MLS Wave 1 scalar score (§8 Lock-and-Leave).
 *
 * Deterministic, symmetric on the shared 0–100 axis:
 *   - PROPERTY: how low-maintenance / secure / "leave for months" a listing is
 *     (condo/villa structure, HOA-maintained exterior, minimal yard, gated,
 *     turnkey).
 *   - DEMAND: how much a searcher values lock-and-leave (seasonal / second-home
 *     / snowbird / retiree / downsizer intent).
 *
 * GOVERNANCE: deterministic; no AI, no external calls, no DB writes (persistence
 * lives in the generator); reads ONLY canonical fields (§F1); carries F4
 * confidence + F5 explanation. Fair Housing (§F3): uses only objective property
 * attributes and self-declared intent — no protected-class inputs. In particular,
 * 55+/age (demand.age_targeted) is DELIBERATELY NOT read here: it does not affect
 * the value, and it is never written into inputs_json or the explanation. All 55+
 * handling lives solely in the Matching V2 Slice 2B compliance gate
 * (SeniorCommunityComplianceGate). See docs/matching-v2-55plus-leak-remediation-scope.md.
 *
 * VERSION history:
 *   - V1: demand score folded a +15 "55+ targeted" bump and persisted
 *         inputs.age_targeted (the leak).
 *   - V2: age removed entirely from the demand computation; completeness
 *         rebalanced across the two remaining self-declared signals.
 */
class LockAndLeaveScoreService implements SymmetricScoreService
{
    use ScalarScoreHelpers;

    public const VERSION  = 'LOCK_AND_LEAVE_V2';
    public const SCORE_KEY = 'lock_and_leave';

    private const LOW_MAINT_STRUCTURES = ['condo', 'condominium', 'villa', 'townhouse', 'townhome', 'co-op', 'apartment'];
    private const MAINTAINED_INCLUDES  = ['lawn', 'ground', 'exterior', 'maintenance', 'landscap', 'roof', 'building'];
    private const SECURE_AMENITIES      = ['gated', 'guard', 'security', '24', 'doorman', 'concierge'];
    private const TURNKEY_CONDITIONS    = ['turnkey', 'move', 'updated', 'renovated', 'new', 'excellent'];

    private const DEMAND_STRONG = ['seasonal', 'snowbird', 'second', 'vacation', 'retire', 'downsiz', 'relocat', 'lock'];

    public function scoreKey(): string
    {
        return self::SCORE_KEY;
    }

    public function version(): string
    {
        return self::VERSION;
    }

    public function scoreProperty(CanonicalListing $listing): array
    {
        $structure = $listing->get('property.structure_type');
        $includes  = $listing->get('property.hoa_fee_includes');
        $amenities = $listing->get('property.community_amenities');
        $acreage   = $listing->get('property.lot_acreage');   // ?float
        $condition = $listing->get('property.condition');

        $completeness = 0;
        if ($listing->present('property.structure_type'))      $completeness += 25;
        if ($listing->present('property.hoa_fee_includes'))    $completeness += 25;
        if ($listing->present('property.lot_acreage'))         $completeness += 20;
        if ($listing->present('property.community_amenities')) $completeness += 15;
        if ($listing->present('property.condition'))           $completeness += 15;

        $inputs = [
            'structure_type'   => $structure,
            'hoa_fee_includes' => $includes,
            'community_amenities' => $amenities,
            'lot_acreage'      => $acreage,
            'condition'        => $condition,
        ];

        // Not enough to say anything meaningful.
        if ($completeness === 0) {
            return $this->result(self::SCORE_KEY, 'property', self::VERSION, null, 0,
                'Insufficient data to compute a Lock-and-Leave score.', $inputs);
        }

        $value = 25;
        $clauses = [];

        if ($this->containsAny($structure, self::LOW_MAINT_STRUCTURES)) {
            $value += 25;
            $clauses[] = 'low-maintenance structure';
        } elseif ($listing->present('property.structure_type')) {
            $value += 5;
            $clauses[] = 'single-family structure';
        }

        if ($this->containsAny($includes, self::MAINTAINED_INCLUDES)) {
            $value += 20;
            $clauses[] = 'HOA-maintained exterior/grounds';
        } elseif ($listing->present('property.hoa_fee_includes')) {
            $value += 5;
        }

        if ($acreage !== null) {
            if ($acreage <= 0.15) { $value += 15; $clauses[] = 'minimal yard'; }
            elseif ($acreage <= 0.35) { $value += 10; $clauses[] = 'small yard'; }
            elseif ($acreage <= 1.0) { $value += 5; }
        }

        if ($this->containsAny($amenities, self::SECURE_AMENITIES)) {
            $value += 10;
            $clauses[] = 'gated/secured community';
        } elseif ($listing->present('property.community_amenities')) {
            $value += 3;
        }

        if ($this->containsAny($condition, self::TURNKEY_CONDITIONS)) {
            $value += 10;
            $clauses[] = 'turnkey condition';
        } elseif ($listing->present('property.condition')) {
            $value += 3;
        }

        $value = max(0, min(100, $value));
        $summary = $clauses === [] ? 'limited lock-and-leave features' : implode('; ', $clauses);

        return $this->result(self::SCORE_KEY, 'property', self::VERSION, $value, $completeness,
            'Lock-and-Leave ' . $value . ': ' . $summary . '.', $inputs);
    }

    public function scoreDemand(CanonicalListing $listing): array
    {
        $status  = $listing->get('demand.current_status');
        $purpose = $listing->get('demand.purchase_purpose');

        // §F3: scored ONLY from self-declared, non-protected intent signals. Age/55+
        // (demand.age_targeted) is intentionally not read — see class docblock.
        // Completeness is rebalanced across the two remaining signals to sum to 100.
        $completeness = 0;
        if ($listing->present('demand.purchase_purpose')) $completeness += 55;
        if ($listing->present('demand.current_status'))   $completeness += 45;

        $inputs = ['current_status' => $status, 'purchase_purpose' => $purpose];

        if ($completeness === 0) {
            return $this->result(self::SCORE_KEY, 'demand', self::VERSION, null, 0,
                'Insufficient data to compute a lock-and-leave preference weight.', $inputs);
        }

        $value = 20;
        $clauses = [];

        if ($this->containsAny($purpose, self::DEMAND_STRONG)) {
            $value += 40;
            $clauses[] = 'second-home / seasonal purpose';
        } elseif ($listing->present('demand.purchase_purpose')) {
            $value += 5;
        }

        if ($this->containsAny($status, self::DEMAND_STRONG)) {
            $value += 30;
            $clauses[] = 'snowbird / retiree / downsizing status';
        } elseif ($listing->present('demand.current_status')) {
            $value += 5;
        }

        $value = max(0, min(100, $value));
        $summary = $clauses === [] ? 'lock-and-leave is a low priority' : implode('; ', $clauses);

        return $this->result(self::SCORE_KEY, 'demand', self::VERSION, $value, $completeness,
            'Lock-and-Leave priority ' . $value . ': ' . $summary . '.', $inputs);
    }
}
