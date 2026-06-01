<?php

namespace App\Services\Dna\Compatibility;

use App\Models\BuyerTenantDnaProfile;
use App\Models\PropertyDnaProfile;

/**
 * CompatibilityEngine — Phase F Internal Compatibility Computation
 *
 * Computes 14 deterministic, field-to-field compatibility dimensions by comparing
 * a PropertyDnaProfile (supply side: seller or landlord) against a
 * BuyerTenantDnaProfile (demand side: buyer or tenant).
 *
 * Of the 14 approved dimensions, 8 are structurally eligible in the current Phase H
 * DNA encoding (both supply and demand generator signals exist). The remaining 6 are
 * structurally ineligible because one or both generators do not yet emit the required
 * source signals for those dimension classes. The `compatibility_coverage_metric`
 * denominator is the count of structurally eligible dimensions, not the full 14,
 * so the metric accurately reflects how many encodable dimensions were resolved
 * for a given pair — not how many of all approved dimensions were resolved.
 *
 * GOVERNANCE CONSTRAINTS (all enforced here):
 * - All logic is deterministic and rule-based. No AI, ML, probabilistic scoring,
 *   or weighted inference of any kind.
 * - `accessibility_requirements` is permanently excluded and must never be read here.
 * - No protected-class inference of any kind.
 * - No narrative generation, recommendation output, or ranking behavior.
 * - Output is structured internal metadata only — never surfaced publicly.
 * - Each dimension is isolated in its own try/catch; a failed slot never aborts computation.
 * - `compatibility_coverage_metric` is a deterministic coverage/completeness metric only —
 *   NOT a ranking score, quality score, recommendation score, or desirability indicator.
 */
class CompatibilityEngine
{
    /**
     * Dimensions for which BOTH the supply-side (PropertyDnaGenerator) and demand-side
     * (BuyerTenantDnaGenerator) generators emit the required source signals in Phase H.
     *
     * These are the only dimensions counted in the coverage metric denominator.
     * Dimensions absent from this list are structurally ineligible — they always return
     * 'unresolved' not because of missing data in a specific listing pair, but because
     * the generator architecture does not yet emit the underlying signals on one or both
     * sides. Counting them in the denominator would systematically deflate the metric
     * regardless of how complete a given listing pair's data actually is.
     *
     * When a future phase adds the missing generator signals for an ineligible dimension,
     * move it into this list and update SCORING_FRAMEWORK_VERSION in the job.
     *
     * Current Phase H eligible dimensions (8 of 14):
     */
    private const STRUCTURALLY_ELIGIBLE_DIMENSIONS = [
        'property_type_alignment',   // supply: type:* tag; demand: prefers-type:* tag
        'financing_alignment',       // supply: financing:* tags; demand: open-to:* tags
        'lease_structure_alignment', // supply: structure:* tags; demand: open-to:lease-* tags
        'pet_policy_alignment',      // supply: policy:pets-allowed tag; demand: has-pets tag
        'smoking_policy_alignment',  // supply: policy:restrictions-specified; demand: preference:restrictions-specified
        'parking_alignment',         // supply: parking:* tags; demand: requires:* / deal_breaker_flags
        'commercial_alignment',      // supply: use:commercial tag; demand: prefers-type:* tag (indirect)
        'amenity_alignment',         // supply: amenity:pool tag; demand: requires:pool / deal_breaker_flags
    ];

    /**
     * Dimensions that are structurally ineligible in Phase H because one or both generators
     * do not yet emit the required source signals for that dimension class.
     *
     * These are computed and included in dimension_match_map / unresolved_dimensions for
     * audit completeness, but excluded from the coverage metric denominator.
     *
     * Ineligible dimension → missing signal(s):
     *   occupancy_alignment    — demand side: no occupant preference lifestyle tag
     *   furnishing_alignment   — demand side: no furnishing preference lifestyle tag
     *   timeline_alignment     — demand side: no timeline_flexibility lifestyle tag
     *   budget_alignment       — supply side: no price/asking-value dimension in PropertyDnaGenerator
     *   lease_term_alignment   — demand side: no desired_lease_length deal_breaker_flag
     *   hoa_alignment          — demand side: no dedicated HOA preference field in BuyerTenantDnaGenerator.
     *                            Temporarily excluded until a dedicated HOA preference field is added to
     *                            buyer/tenant listing forms. Phase J / R-03 audited and confirmed resolved
     *                            (hoa_alignment is ineligible; coverage denominator is accurate at 8).
     */
    private const STRUCTURALLY_INELIGIBLE_DIMENSIONS = [
        'occupancy_alignment',
        'furnishing_alignment',
        'timeline_alignment',
        'budget_alignment',
        'lease_term_alignment',
        'hoa_alignment',
    ];

    /**
     * Dimension-to-score-bucket grouping map.
     *
     * Each dimension is assigned to exactly one of three property-scoring buckets:
     * Physical, Financial, or Terms. The Location bucket has no dimensions —
     * location_match_score is always null until Location DNA Phase 2 delivers the
     * required lifestyle/preference signals.
     *
     * This map is the single authoritative source for computeGroupedScores() and must
     * not be reordered or expanded without a corresponding SCORING_FRAMEWORK_VERSION bump.
     *
     * Bucket assignment rationale:
     *   Physical  — dimensions that reflect tangible property characteristics
     *               (type, parking, amenities, occupancy, furnishing, HOA governance)
     *   Financial — dimensions that reflect monetary or financing terms
     *               (financing type, budget ceiling)
     *   Terms     — dimensions that reflect agreement / policy terms
     *               (lease structure, pet policy, smoking restrictions, timeline,
     *                lease length, commercial use designation)
     *   Location  — no dimensions in Phase H; always null
     */
    protected const DIMENSION_BUCKET_MAP = [
        'property_type_alignment'   => 'physical',
        'parking_alignment'         => 'physical',
        'amenity_alignment'         => 'physical',
        'occupancy_alignment'       => 'physical',
        'furnishing_alignment'      => 'physical',
        'hoa_alignment'             => 'physical',

        'financing_alignment'       => 'financial',
        'budget_alignment'          => 'financial',

        'lease_structure_alignment' => 'terms',
        'pet_policy_alignment'      => 'terms',
        'smoking_policy_alignment'  => 'terms',
        'timeline_alignment'        => 'terms',
        'lease_term_alignment'      => 'terms',
        'commercial_alignment'      => 'terms',
    ];

    /**
     * Human-readable positive highlight labels for aligned dimensions.
     * Used by computeHighlights() to produce transparency strings for persisted highlights.
     */
    private const HIGHLIGHT_LABELS = [
        'property_type_alignment'   => 'Property Type Compatible',
        'financing_alignment'       => 'Financing Terms Aligned',
        'lease_structure_alignment' => 'Lease Structure Aligned',
        'pet_policy_alignment'      => 'Pet Policy Compatible',
        'smoking_policy_alignment'  => 'Restrictions Policy Aligned',
        'parking_alignment'         => 'Parking Requirements Met',
        'commercial_alignment'      => 'Commercial Use Compatible',
        'amenity_alignment'         => 'Amenity Requirements Met',
        'occupancy_alignment'       => 'Occupancy Status Compatible',
        'furnishing_alignment'      => 'Furnishing Terms Compatible',
        'timeline_alignment'        => 'Timeline Aligned',
        'hoa_alignment'             => 'HOA Preference Compatible',
        'budget_alignment'          => 'Budget Alignment Confirmed',
        'lease_term_alignment'      => 'Lease Term Length Compatible',
    ];

    /**
     * Transparency strings for unresolved/ineligible dimensions.
     * Used by computeWarnings() to surface what could not be compared.
     */
    private const WARNING_LABELS = [
        'property_type_alignment'   => 'Property Type Comparison Unavailable',
        'financing_alignment'       => 'Financing Comparison Unavailable',
        'lease_structure_alignment' => 'Lease Structure Comparison Unavailable',
        'pet_policy_alignment'      => 'Pet Policy Comparison Unavailable',
        'smoking_policy_alignment'  => 'Restrictions Policy Comparison Unavailable',
        'parking_alignment'         => 'Parking Comparison Unavailable',
        'commercial_alignment'      => 'Commercial Use Comparison Unavailable',
        'amenity_alignment'         => 'Amenity Comparison Unavailable',
        'occupancy_alignment'       => 'Occupancy Comparison Unavailable',
        'furnishing_alignment'      => 'Furnishing Comparison Unavailable',
        'timeline_alignment'        => 'Timeline Comparison Unavailable',
        'hoa_alignment'             => 'HOA Preference Unavailable',
        'budget_alignment'          => 'Budget Alignment Unavailable',
        'lease_term_alignment'      => 'Lease Term Comparison Unavailable',
    ];

    /**
     * Hard fanout dispatch cap (also enforced in observers).
     * Defined here for documentation completeness.
     */
    public const FANOUT_CAP = 500;

    /**
     * Compute compatibility dimensions between a supply (property/landlord) DNA profile
     * and a demand (buyer/tenant) DNA profile.
     *
     * Returns:
     *   aligned_dimensions          — array of dimension names where supply and demand agree
     *   conflicting_dimensions      — array of dimension names where a deterministic conflict exists
     *   unresolved_dimensions       — array of dimension names where one or both sides have no signal
     *   dimension_match_map         — keyed map of dimension name → result ('aligned'|'conflicting'|'unresolved')
     *   eligible_dimension_count    — count of structurally eligible dimensions (the denominator);
     *                                  equals count(STRUCTURALLY_ELIGIBLE_DIMENSIONS) for audit traceability
     *   compatibility_coverage_metric — (resolved eligible dimensions / eligible_dimension_count) × 100;
     *                                    a coverage/completeness metric ONLY — the denominator excludes
     *                                    structurally ineligible dimensions (those whose generator
     *                                    architecture does not yet emit the required source signals);
     *                                    NEVER a ranking, quality, recommendation, or desirability score
     *
     * @param PropertyDnaProfile    $propertyProfile    Supply-side DNA profile (seller or landlord listing)
     * @param BuyerTenantDnaProfile $buyerTenantProfile Demand-side DNA profile (buyer or tenant listing)
     * @return array
     */
    public function compute(PropertyDnaProfile $propertyProfile, BuyerTenantDnaProfile $buyerTenantProfile): array
    {
        $supplyTags    = (array) ($propertyProfile->ai_buyer_archetype_tags ?? []);
        $supplyHooks   = (array) ($propertyProfile->ai_marketing_hooks ?? []);
        $demandTags    = (array) ($buyerTenantProfile->lifestyle_tags ?? []);
        $demandFlags   = (array) ($buyerTenantProfile->deal_breaker_flags ?? []);

        $dimensionMatchMap = [];

        $dimensionMatchMap['property_type_alignment']  = $this->computePropertyTypeAlignment($supplyTags, $demandTags);
        $dimensionMatchMap['financing_alignment']      = $this->computeFinancingAlignment($supplyTags, $demandTags);
        $dimensionMatchMap['lease_structure_alignment']= $this->computeLeaseStructureAlignment($supplyTags, $demandTags);
        $dimensionMatchMap['occupancy_alignment']      = $this->computeOccupancyAlignment($supplyHooks, $demandFlags);
        $dimensionMatchMap['pet_policy_alignment']     = $this->computePetPolicyAlignment($supplyTags, $demandTags, $demandFlags);
        $dimensionMatchMap['smoking_policy_alignment'] = $this->computeSmokingPolicyAlignment($supplyTags, $demandTags);
        $dimensionMatchMap['parking_alignment']        = $this->computeParkingAlignment($supplyTags, $demandTags, $demandFlags);
        $dimensionMatchMap['furnishing_alignment']     = $this->computeFurnishingAlignment($supplyTags, $demandTags);
        $dimensionMatchMap['timeline_alignment']       = $this->computeTimelineAlignment($supplyTags, $demandTags);
        $dimensionMatchMap['hoa_alignment']            = $this->computeHoaAlignment($supplyTags, $demandTags);
        $dimensionMatchMap['commercial_alignment']     = $this->computeCommercialAlignment($supplyTags, $demandTags);
        $dimensionMatchMap['amenity_alignment']        = $this->computeAmenityAlignment($supplyTags, $demandTags, $demandFlags);
        $dimensionMatchMap['budget_alignment']         = $this->computeBudgetAlignment($supplyHooks, $demandFlags);
        $dimensionMatchMap['lease_term_alignment']     = $this->computeLeaseTermAlignment($supplyHooks, $demandFlags);

        $aligned     = [];
        $conflicting = [];
        $unresolved  = [];

        foreach ($dimensionMatchMap as $name => $result) {
            if ($result === 'aligned') {
                $aligned[] = $name;
            } elseif ($result === 'conflicting') {
                $conflicting[] = $name;
            } else {
                $unresolved[] = $name;
            }
        }

        // Count resolved results only among structurally eligible dimensions.
        // Structurally ineligible dimensions always return 'unresolved' due to missing
        // generator signals — they must not penalise the metric denominator.
        $eligibleDimensions = self::STRUCTURALLY_ELIGIBLE_DIMENSIONS;
        $eligibleCount      = count($eligibleDimensions);

        $resolvedEligibleCount = 0;
        foreach ($eligibleDimensions as $dim) {
            $result = $dimensionMatchMap[$dim] ?? 'unresolved';
            if ($result === 'aligned' || $result === 'conflicting') {
                $resolvedEligibleCount++;
            }
        }

        // compatibility_coverage_metric: (resolved eligible dimensions / eligible_dimension_count) × 100.
        //
        // Denominator = count of structurally eligible dimensions only (currently 8 of 14).
        // Structurally ineligible dimensions are excluded from the denominator because their
        // 'unresolved' result is caused by missing generator architecture, not by absent listing
        // data — including them would systematically deflate the metric for all pairs regardless
        // of how complete a specific pair's data actually is.
        //
        // This is a deterministic coverage/completeness metric ONLY — it measures what fraction
        // of encodable dimensions were resolved for this listing pair.
        // It must NEVER be interpreted as ranking quality, recommendation strength,
        // user desirability, approval likelihood, tenant quality, buyer quality,
        // investment quality, or transactional probability.
        $coverageMetric = $eligibleCount > 0
            ? round(($resolvedEligibleCount / $eligibleCount) * 100, 2)
            : 0.0;

        return [
            'aligned_dimensions'            => $aligned,
            'conflicting_dimensions'        => $conflicting,
            'unresolved_dimensions'         => $unresolved,
            'dimension_match_map'           => $dimensionMatchMap,
            'eligible_dimension_count'      => $eligibleCount,
            'compatibility_coverage_metric' => $coverageMetric,
        ];
    }

    // -------------------------------------------------------------------------
    // Grouped score computation — Step 3
    // -------------------------------------------------------------------------

    /**
     * Compute per-bucket sub-scores from the dimension match map.
     *
     * For each bucket (physical, financial, terms), counts aligned against
     * (aligned + conflicting) resolved dimensions in that group and returns a
     * float 0–100 or null if no dimensions in the group were resolved.
     *
     * Location bucket always returns null — no location dimensions exist in Phase H.
     * Unresolved / ineligible dimensions do not enter the denominator for any bucket.
     *
     * @param  array  $matchMap  dimension_match_map from compute()
     * @return array{physical: float|null, financial: float|null, terms: float|null, location: null}
     */
    public function computeGroupedScores(array $matchMap): array
    {
        $buckets = ['physical' => [], 'financial' => [], 'terms' => []];

        foreach ($matchMap as $dimension => $result) {
            $bucket = self::DIMENSION_BUCKET_MAP[$dimension] ?? null;
            if ($bucket === null || !isset($buckets[$bucket])) {
                continue;
            }
            if ($result === 'aligned' || $result === 'conflicting') {
                $buckets[$bucket][] = $result;
            }
            // unresolved dimensions are excluded from each bucket's denominator
        }

        $scores = [];
        foreach ($buckets as $bucket => $results) {
            if (empty($results)) {
                $scores[$bucket] = null;
            } else {
                $alignedCount = count(array_filter($results, fn($r) => $r === 'aligned'));
                $scores[$bucket] = round(($alignedCount / count($results)) * 100, 2);
            }
        }

        // Location bucket: always null — no location dimensions exist in Phase H.
        // PREREQUISITE: Location DNA Phase 2 must emit lifestyle/preference signals
        // and supply-side location geometry before this bucket can be computed.
        $scores['location'] = null;

        return $scores;
    }

    // -------------------------------------------------------------------------
    // Highlights and warnings extractors — Step 5
    // -------------------------------------------------------------------------

    /**
     * Compute a JSON-array-ready list of positive highlight strings for aligned dimensions.
     *
     * Uses a static label map — no dynamic or AI generation.
     * Aligned dimensions only; conflicting and unresolved dimensions are excluded.
     *
     * @param  array  $matchMap  dimension_match_map from compute()
     * @return string[]
     */
    public function computeHighlights(array $matchMap): array
    {
        $highlights = [];
        foreach ($matchMap as $dimension => $result) {
            if ($result === 'aligned') {
                $label        = self::HIGHLIGHT_LABELS[$dimension] ?? null;
                if ($label !== null) {
                    $highlights[] = $label;
                }
            }
        }
        return $highlights;
    }

    /**
     * Compute a JSON-array-ready list of transparency warning strings for
     * unresolved (including ineligible) dimensions.
     *
     * Uses a static label map — no dynamic or AI generation.
     * Only unresolved dimensions appear here; aligned and conflicting dimensions are excluded.
     *
     * @param  array  $matchMap  dimension_match_map from compute()
     * @return string[]
     */
    public function computeWarnings(array $matchMap): array
    {
        $warnings = [];
        foreach ($matchMap as $dimension => $result) {
            if ($result === 'unresolved') {
                $label = self::WARNING_LABELS[$dimension] ?? null;
                if ($label !== null) {
                    $warnings[] = $label;
                }
            }
        }
        return $warnings;
    }

    // -------------------------------------------------------------------------
    // Readiness score — Step 6
    // -------------------------------------------------------------------------

    /**
     * Compute the compatibility readiness score.
     *
     * Returns (resolved_dimensions / eligible_dimensions) × 100 as a float,
     * where resolved = aligned + conflicting across STRUCTURALLY_ELIGIBLE_DIMENSIONS
     * and eligible = count(STRUCTURALLY_ELIGIBLE_DIMENSIONS).
     *
     * This mirrors the existing compatibility_coverage_metric logic but is stored
     * as its own dedicated column (compatibility_readiness_score) to allow independent
     * evolution of the readiness concept in future phases without altering the
     * coverage metric column.
     *
     * Ineligible dimensions are excluded from both numerator and denominator.
     *
     * @param  array  $matchMap  dimension_match_map from compute()
     * @return float             0.0–100.0
     */
    public function computeReadinessScore(array $matchMap): float
    {
        $eligible = self::STRUCTURALLY_ELIGIBLE_DIMENSIONS;
        $total    = count($eligible);

        if ($total === 0) {
            return 0.0;
        }

        $resolved = 0;
        foreach ($eligible as $dim) {
            $result = $matchMap[$dim] ?? 'unresolved';
            if ($result === 'aligned' || $result === 'conflicting') {
                $resolved++;
            }
        }

        return round(($resolved / $total) * 100, 2);
    }

    // -------------------------------------------------------------------------
    // Dimension computation methods — each wrapped in try/catch by the caller
    // and independently isolated so a single failed slot never aborts the full run.
    // -------------------------------------------------------------------------

    /**
     * property_type_alignment: Does the property's stated type match the buyer/tenant preference?
     * Supply source: archetype tag `type:{value}`
     * Demand source: lifestyle tag `prefers-type:{value}`
     * Rule: both present + same value → aligned; both present + different → conflicting; either absent → unresolved
     */
    private function computePropertyTypeAlignment(array $supplyTags, array $demandTags): string
    {
        try {
            $supplyType = $this->extractTagValue($supplyTags, 'type:');
            $demandType = $this->extractTagValue($demandTags, 'prefers-type:');

            if ($supplyType === null || $demandType === null) {
                return 'unresolved';
            }
            return strtolower(trim($supplyType)) === strtolower(trim($demandType)) ? 'aligned' : 'conflicting';
        } catch (\Throwable $e) {
            return 'unresolved';
        }
    }

    /**
     * financing_alignment: Does the supply's seller-financing/assumable availability match demand interest?
     * Supply source: archetype tags `financing:seller-financed`, `financing:assumable`
     * Demand source: lifestyle tags `open-to:seller-financing`, `open-to:assumable-loan`
     * Rule: demand wants seller financing but supply doesn't offer it → conflicting;
     *        demand wants assumable but supply has none → conflicting;
     *        supply offers and demand is open to it → aligned;
     *        neither side signals financing preference → unresolved
     */
    private function computeFinancingAlignment(array $supplyTags, array $demandTags): string
    {
        try {
            $supplySellerFinancing = $this->hasTag($supplyTags, 'financing:seller-financed');
            $supplyAssumable       = $this->hasTag($supplyTags, 'financing:assumable');
            $demandSellerFinancing = $this->hasTag($demandTags, 'open-to:seller-financing');
            $demandAssumable       = $this->hasTag($demandTags, 'open-to:assumable-loan');

            $supplyHasSignal  = $supplySellerFinancing || $supplyAssumable;
            $demandHasSignal  = $demandSellerFinancing || $demandAssumable;

            if (!$supplyHasSignal && !$demandHasSignal) {
                return 'unresolved';
            }

            // Demand wants seller financing but supply doesn't offer → conflicting
            if ($demandSellerFinancing && !$supplySellerFinancing) {
                return 'conflicting';
            }
            // Demand wants assumable but supply has none → conflicting
            if ($demandAssumable && !$supplyAssumable) {
                return 'conflicting';
            }
            // Supply offers financing and demand is open to at least one matching type → aligned
            if (($supplySellerFinancing && $demandSellerFinancing) || ($supplyAssumable && $demandAssumable)) {
                return 'aligned';
            }
            // Supply offers but demand has no expressed interest — not a conflict
            return 'unresolved';
        } catch (\Throwable $e) {
            return 'unresolved';
        }
    }

    /**
     * lease_structure_alignment: Do lease-option/lease-purchase availability and interest align?
     * Supply source: archetype tags `structure:lease-option`, `structure:lease-purchase`
     * Demand source: lifestyle tags `open-to:lease-option`, `open-to:lease-purchase`
     * Rule: demand interested in lease structure that supply provides → aligned;
     *        demand interested but supply has no such structure → conflicting;
     *        neither side expresses interest → unresolved
     */
    private function computeLeaseStructureAlignment(array $supplyTags, array $demandTags): string
    {
        try {
            $supplyLeaseOption    = $this->hasTag($supplyTags, 'structure:lease-option');
            $supplyLeasePurchase  = $this->hasTag($supplyTags, 'structure:lease-purchase');
            $demandLeaseOption    = $this->hasTag($demandTags, 'open-to:lease-option');
            $demandLeasePurchase  = $this->hasTag($demandTags, 'open-to:lease-purchase');

            $demandHasSignal = $demandLeaseOption || $demandLeasePurchase;

            if (!$demandHasSignal) {
                return 'unresolved';
            }
            // Demand wants lease-option but supply has none → conflicting
            if ($demandLeaseOption && !$supplyLeaseOption) {
                return 'conflicting';
            }
            // Demand wants lease-purchase but supply has none → conflicting
            if ($demandLeasePurchase && !$supplyLeasePurchase) {
                return 'conflicting';
            }
            // Demand interest matches supply offering
            return 'aligned';
        } catch (\Throwable $e) {
            return 'unresolved';
        }
    }

    /**
     * occupancy_alignment: Does the supply occupant_status match the demand preference?
     * Supply source: marketing hook with trait `occupant_status`
     * Demand source: deal_breaker_flags (no dedicated lifestyle tag for occupant preference)
     * Rule: if supply specifies occupant status and no demand signal exists → unresolved;
     *        both sides specify and match → aligned; both specify and differ → conflicting
     *
     * PREREQUISITE: BuyerTenantDnaGenerator must emit an occupant_preference lifestyle tag
     * before this dimension can produce aligned/conflicting results. Currently ineligible.
     */
    private function computeOccupancyAlignment(array $supplyHooks, array $demandFlags): string
    {
        try {
            $supplyOccupancy = $this->extractHookValue($supplyHooks, 'occupant_status');

            // No structured demand-side occupant_status preference is encoded in current
            // Phase H BuyerTenant DNA outputs — dimension is unresolved until demand
            // encoding is introduced in a future phase.
            if ($supplyOccupancy === null) {
                return 'unresolved';
            }
            return 'unresolved';
        } catch (\Throwable $e) {
            return 'unresolved';
        }
    }

    /**
     * pet_policy_alignment: Does the property's pet policy match the buyer/tenant's pet status?
     * Supply source: archetype tag `policy:pets-allowed`
     * Demand source: lifestyle tag `has-pets` or deal_breaker_flag `pet_required`
     * Rule: demand has pets + supply allows → aligned;
     *        demand has pets + supply does NOT allow → conflicting;
     *        demand has no pets or no signal → unresolved (no conflict regardless)
     */
    private function computePetPolicyAlignment(array $supplyTags, array $demandTags, array $demandFlags): string
    {
        try {
            $supplyAllowsPets = $this->hasTag($supplyTags, 'policy:pets-allowed');
            // Presence of `policy:pets-allowed` tag means yes; absence means either no or no signal.
            // To detect "supply explicitly disallows", we check if the supply archetype has type signal
            // but NOT pets-allowed (we can only detect the affirmative case reliably).
            $demandHasPets = $this->hasTag($demandTags, 'has-pets');

            if (!$demandHasPets) {
                return 'unresolved';
            }
            // Demand has pets
            if ($supplyAllowsPets) {
                return 'aligned';
            }
            // Supply doesn't have a pets-allowed tag — could be no signal or explicitly no.
            // Since we cannot distinguish "no pets" from "no signal" purely from tag presence,
            // we treat absence of pets-allowed as conflicting when demand has pets.
            return 'conflicting';
        } catch (\Throwable $e) {
            return 'unresolved';
        }
    }

    /**
     * smoking_policy_alignment: Do both sides have smoking/restrictions policy signals?
     * Supply source: archetype tag `policy:restrictions-specified`
     * Demand source: lifestyle tag `preference:restrictions-specified`
     * Rule: both signal restrictions specified → aligned (policy awareness mutual);
     *        only one side specifies → unresolved (not a conflict — just incomplete)
     */
    private function computeSmokingPolicyAlignment(array $supplyTags, array $demandTags): string
    {
        try {
            $supplySpecified = $this->hasTag($supplyTags, 'policy:restrictions-specified');
            $demandSpecified = $this->hasTag($demandTags, 'preference:restrictions-specified');

            if ($supplySpecified && $demandSpecified) {
                return 'aligned';
            }
            return 'unresolved';
        } catch (\Throwable $e) {
            return 'unresolved';
        }
    }

    /**
     * parking_alignment: Does the supply's parking availability satisfy demand's requirements?
     * Supply source: archetype tags `parking:garage`, `parking:carport`
     * Demand source: lifestyle tags `requires:garage`, `requires:carport`
     *                and deal_breaker_flags `garage_required`, `carport_required`
     * Rule: demand requires garage/carport but supply lacks → conflicting;
     *        demand requires and supply provides → aligned;
     *        no demand requirements expressed → unresolved
     */
    private function computeParkingAlignment(array $supplyTags, array $demandTags, array $demandFlags): string
    {
        try {
            $supplyGarage  = $this->hasTag($supplyTags, 'parking:garage');
            $supplyCarport = $this->hasTag($supplyTags, 'parking:carport');

            $demandGarage  = $this->hasTag($demandTags, 'requires:garage')
                          || $this->hasDealBreakerFlag($demandFlags, 'garage_required');
            $demandCarport = $this->hasTag($demandTags, 'requires:carport')
                          || $this->hasDealBreakerFlag($demandFlags, 'carport_required');

            if (!$demandGarage && !$demandCarport) {
                return 'unresolved';
            }
            if ($demandGarage && !$supplyGarage) {
                return 'conflicting';
            }
            if ($demandCarport && !$supplyCarport) {
                return 'conflicting';
            }
            return 'aligned';
        } catch (\Throwable $e) {
            return 'unresolved';
        }
    }

    /**
     * furnishing_alignment: Does the supply specify furnishing terms?
     * Supply source: archetype tag `feature:furnishing-terms-specified`
     * Demand source: no dedicated furnishing preference tag in current Phase H BuyerTenant DNA
     * Rule: supply specifies furnishing → unresolved (demand signal not available in current schema);
     *        neither specifies → unresolved
     * Note: Once a demand-side furnishing dimension is added in a future phase, this
     *       dimension will produce aligned/conflicting results.
     *
     * PREREQUISITE: BuyerTenantDnaGenerator must emit a furnishing_preference lifestyle tag
     * before this dimension can produce aligned/conflicting results. Currently ineligible.
     */
    private function computeFurnishingAlignment(array $supplyTags, array $demandTags): string
    {
        try {
            // No demand-side furnishing preference tag exists in Phase H BuyerTenant DNA output.
            // Dimension is unresolved until demand-side furnishing preference is encoded.
            return 'unresolved';
        } catch (\Throwable $e) {
            return 'unresolved';
        }
    }

    /**
     * timeline_alignment: Do both sides have move-in/availability timing signals?
     * Supply source: archetype tag `timing:move-in-specified`
     * Demand source: no dedicated timeline lifestyle tag in current Phase H BuyerTenant DNA
     * Rule: both specify timing → aligned; only supply specifies → unresolved
     * Note: BuyerTenant DNA stores `timeline_flexibility` internally but does not emit
     *       a corresponding lifestyle tag in Phase H. Unresolved until demand tag is added.
     *
     * PREREQUISITE: BuyerTenantDnaGenerator must emit a timeline_flexibility lifestyle tag
     * before this dimension can produce aligned/conflicting results. Currently ineligible.
     */
    private function computeTimelineAlignment(array $supplyTags, array $demandTags): string
    {
        try {
            $supplySpecified = $this->hasTag($supplyTags, 'timing:move-in-specified');
            // No timeline lifestyle tag emitted by BuyerTenantDnaGenerator in Phase H.
            if (!$supplySpecified) {
                return 'unresolved';
            }
            return 'unresolved';
        } catch (\Throwable $e) {
            return 'unresolved';
        }
    }

    /**
     * hoa_alignment: Does the supply HOA status match the demand HOA awareness?
     * Supply source: archetype tag `governance:hoa-exists`
     * Demand source: no dedicated HOA preference field in BuyerTenantDnaGenerator
     * Rule: supply specifies HOA and demand has no HOA preference signal → unresolved;
     *        both specify and match → aligned; both specify and differ → conflicting
     *
     * PREREQUISITE: BuyerTenantDnaGenerator must emit a dedicated HOA preference field
     * (buyer/tenant listing form HOA preference) before this dimension can produce
     * aligned/conflicting results. Currently ineligible — confirmed Phase J / R-03.
     */
    private function computeHoaAlignment(array $supplyTags, array $demandTags): string
    {
        try {
            // No demand-side HOA preference field is emitted by BuyerTenantDnaGenerator in Phase H.
            // Dimension is unresolved until a dedicated HOA preference field is added to
            // buyer/tenant listing forms and BuyerTenantDnaGenerator emits the corresponding signal.
            return 'unresolved';
        } catch (\Throwable $e) {
            return 'unresolved';
        }
    }

    /**
     * commercial_alignment: Does the supply's commercial use match the demand's property type interest?
     * Supply source: archetype tags `use:commercial` or type tags indicating commercial designation
     * Demand source: lifestyle tag `prefers-type:{value}` where value includes commercial keyword
     * Rule: supply is commercial + demand wants commercial → aligned;
     *        supply is commercial + demand wants residential → conflicting;
     *        supply is not commercial → unresolved (supply non-commercial is the default case)
     */
    private function computeCommercialAlignment(array $supplyTags, array $demandTags): string
    {
        try {
            $supplyIsCommercial = $this->hasTag($supplyTags, 'use:commercial');

            if (!$supplyIsCommercial) {
                return 'unresolved';
            }

            $demandType = $this->extractTagValue($demandTags, 'prefers-type:');

            if ($demandType === null) {
                return 'unresolved';
            }

            $commercialKeywords = ['commercial', 'office', 'retail', 'industrial', 'warehouse', 'mixed'];
            $demandTypeLower    = strtolower(trim($demandType));
            $demandWantsCommercial = false;
            foreach ($commercialKeywords as $kw) {
                if (strpos($demandTypeLower, $kw) !== false) {
                    $demandWantsCommercial = true;
                    break;
                }
            }

            return $demandWantsCommercial ? 'aligned' : 'conflicting';
        } catch (\Throwable $e) {
            return 'unresolved';
        }
    }

    /**
     * amenity_alignment: Does the supply's pool availability satisfy the demand's pool requirement?
     * Supply source: archetype tag `amenity:pool`
     * Demand source: lifestyle tag `requires:pool` and deal_breaker_flag `pool_required`
     * Rule: demand requires pool + supply has pool → aligned;
     *        demand requires pool + supply has no pool → conflicting;
     *        demand does not require pool → unresolved
     */
    private function computeAmenityAlignment(array $supplyTags, array $demandTags, array $demandFlags): string
    {
        try {
            $supplyHasPool  = $this->hasTag($supplyTags, 'amenity:pool');
            $demandNeedsPool = $this->hasTag($demandTags, 'requires:pool')
                            || $this->hasDealBreakerFlag($demandFlags, 'pool_required');

            if (!$demandNeedsPool) {
                return 'unresolved';
            }
            return $supplyHasPool ? 'aligned' : 'conflicting';
        } catch (\Throwable $e) {
            return 'unresolved';
        }
    }

    /**
     * budget_alignment: Does the supply listing fall within the demand's stated budget ceiling?
     * Supply source: no price/value dimension is stored in PropertyDnaProfile in current Phase H schema
     * Demand source: deal_breaker_flag `budget_ceiling_specified`
     * Rule: unresolved — supply-side price signal is not encoded in PropertyDnaProfile in Phase H.
     *        This dimension will produce aligned/conflicting results once a supply-side price
     *        dimension is added to PropertyDnaProfile in a future phase.
     *
     * PREREQUISITE: PropertyDnaGenerator must emit a price/asking-value dimension in
     * PropertyDnaProfile before this dimension can produce aligned/conflicting results.
     * Currently ineligible.
     */
    private function computeBudgetAlignment(array $supplyHooks, array $demandFlags): string
    {
        try {
            // Supply-side listing price is not encoded in PropertyDnaProfile Phase H output.
            // Dimension is unresolved until a supply-side price dimension is available.
            return 'unresolved';
        } catch (\Throwable $e) {
            return 'unresolved';
        }
    }

    /**
     * lease_term_alignment: Do the supply's offered lease lengths align with demand's preferred length?
     * Supply source: marketing hook with trait `lease_length`
     * Demand source: deal_breaker_flag `minimum_lease_length_specified` (not currently emitted in Phase H)
     * Rule: unresolved — no structured lease length deal_breaker_flag is emitted by
     *        BuyerTenantDnaGenerator in Phase H. Dimension becomes active once demand-side
     *        lease term encoding is added in a future phase.
     *
     * PREREQUISITE: BuyerTenantDnaGenerator must emit a desired_lease_length deal_breaker_flag
     * before this dimension can produce aligned/conflicting results. Currently ineligible.
     */
    private function computeLeaseTermAlignment(array $supplyHooks, array $demandFlags): string
    {
        try {
            // No structured demand-side lease term signal in Phase H BuyerTenant DNA output.
            return 'unresolved';
        } catch (\Throwable $e) {
            return 'unresolved';
        }
    }

    // -------------------------------------------------------------------------
    // Tag/hook extraction helpers
    // -------------------------------------------------------------------------

    /**
     * Check whether an exact tag string exists in the tag array.
     */
    private function hasTag(array $tags, string $tag): bool
    {
        return in_array($tag, $tags, true);
    }

    /**
     * Extract the value portion of a prefixed tag (e.g. 'type:SingleFamily' → 'SingleFamily').
     * Returns null if no tag with the given prefix is found.
     */
    private function extractTagValue(array $tags, string $prefix): ?string
    {
        $prefixLen = strlen($prefix);
        foreach ($tags as $tag) {
            if (is_string($tag) && strncmp($tag, $prefix, $prefixLen) === 0) {
                return substr($tag, $prefixLen);
            }
        }
        return null;
    }

    /**
     * Extract the value of a marketing hook by trait name.
     * Returns null if the trait is not present.
     */
    private function extractHookValue(array $hooks, string $traitName): ?string
    {
        foreach ($hooks as $hook) {
            if (is_array($hook) && ($hook['trait'] ?? null) === $traitName) {
                $val = $hook['value'] ?? null;
                return ($val !== null && $val !== '') ? (string) $val : null;
            }
        }
        return null;
    }

    /**
     * Check whether a named flag exists in the deal_breaker_flags array.
     */
    private function hasDealBreakerFlag(array $flags, string $flagName): bool
    {
        foreach ($flags as $flag) {
            if (is_array($flag) && ($flag['flag'] ?? null) === $flagName) {
                return true;
            }
        }
        return false;
    }
}
