<?php

namespace App\Services\Dna;

use App\Models\PropertyDnaProfile;

/**
 * SellerDnaReportService — Seller DNA Intelligence Report (V1)
 *
 * GOVERNANCE BLOCK:
 * ==================================================================================
 * This service is a READ-ONLY INTERPRETATION LAYER. It translates persisted
 * PropertyDnaProfile data into a structured intelligence report array using
 * deterministic constant maps and threshold comparisons only.
 *
 * This service MUST NEVER:
 *   - Write to, modify, or archive any database row or model.
 *   - Read any table, model, or database connection — all input comes from the
 *     PropertyDnaProfile model instance passed by the caller.
 *   - Call any AI, OpenAI, language model, embedding, ML, or external HTTP service.
 *   - Rank, sort, or order output by any score, metric, or signal.
 *   - Recommend any listing, buyer, seller, landlord, tenant, or agent.
 *   - Determine, infer, or output suitability, qualification, approval, or rejection.
 *   - Predict any outcome, likelihood, or probability of any transaction event.
 *   - Generate narrative persuasion copy, endorsements, or matchmaking language.
 *   - Recalculate, modify, or reinterpret any stored DNA score or completeness value.
 *   - Surface output in any user-facing view, API, PDF, email, or cache layer.
 *
 * All output is deterministic and reproducible from persisted PropertyDnaProfile data.
 * Output order is deterministic: input order from persisted arrays is preserved.
 * No reordering, sorting, or weighting is applied.
 * ==================================================================================
 */
class SellerDnaReportService
{
    /**
     * The five priority score fields tracked in this report.
     */
    private const PRIORITY_SCORE_FIELDS = [
        'flexibility_score',
        'financial_score',
        'marketing_score',
        'compatibility_score',
        'condition_score',
    ];

    /**
     * Deterministic label map for each priority score dimension.
     */
    private const PRIORITY_LABEL_MAP = [
        'flexibility_score'    => 'Flexibility Focus',
        'financial_score'      => 'Financial Outcome Focus',
        'marketing_score'      => 'Marketing Exposure Focus',
        'compatibility_score'  => 'Buyer Compatibility Focus',
        'condition_score'      => 'Property Condition Focus',
    ];

    /**
     * Tag-to-strength label map for ai_buyer_archetype_tags.
     * Only full tag strings listed here produce a strength entry.
     */
    private const TAG_STRENGTH_MAP = [
        'amenity:pool'              => 'Pool',
        'amenity:waterfront'        => 'Waterfront',
        'parking:garage'            => 'Garage',
        'feature:storage'           => 'Storage',
        'marketing:video-tour'      => 'Video Tour Available',
        'financing:seller-financed' => 'Seller Financing',
        'financing:assumable'       => 'Assumable Loan',
        'structure:lease-option'    => 'Lease Option',
        'structure:lease-purchase'  => 'Lease Purchase',
    ];

    /**
     * Signal field names pulled from the profile for the signals block.
     */
    private const SIGNAL_FIELDS = [
        'overall_dna_completeness',
        'walk_score',
        'transit_score',
        'bike_score',
        'school_rating',
        'flood_zone_verified',
        'estimated_monthly_utilities',
    ];

    /**
     * All dimension fields checked for missing_inputs.
     */
    private const ALL_DIMENSION_FIELDS = [
        'flexibility_score',
        'financial_score',
        'marketing_score',
        'compatibility_score',
        'condition_score',
        'physical_score',
        'location_score',
        'legal_score',
        'occupant_qualification_score',
        'commercial_score',
    ];

    /**
     * Generate a structured intelligence report from a persisted PropertyDnaProfile.
     *
     * No database reads or writes occur. No AI or external HTTP calls are made.
     * All derivations are deterministic threshold comparisons and constant-map lookups.
     *
     * @param  PropertyDnaProfile $profile  A cast profile model instance (no DB queries made here).
     * @return array{
     *   success: bool,
     *   status: string,
     *   listing_type: string,
     *   listing_id: int,
     *   seller_priorities: array,
     *   property_strengths: array,
     *   property_considerations: array,
     *   marketing_opportunities: array,
     *   buyer_archetype_alignment: array,
     *   signals: array,
     *   missing_inputs: array,
     *   error: string|null,
     * }
     */
    public function generate(PropertyDnaProfile $profile): array
    {
        try {
            if ($this->isInsufficientData($profile)) {
                return $this->insufficientDataResponse($profile);
            }

            return [
                'success'                   => true,
                'status'                    => 'generated',
                'listing_type'              => 'seller',
                'listing_id'                => (int) $profile->listing_id,
                'seller_priorities'         => $this->deriveSellerPriorities($profile),
                'property_strengths'        => $this->derivePropertyStrengths($profile),
                'property_considerations'   => $this->derivePropertyConsiderations($profile),
                'marketing_opportunities'   => $this->deriveMarketingOpportunities($profile),
                'buyer_archetype_alignment' => $this->deriveBuyerArchetypeAlignment($profile),
                'signals'                   => $this->deriveSignals($profile),
                'missing_inputs'            => $this->deriveMissingInputs($profile),
                'error'                     => null,
            ];
        } catch (\Throwable $e) {
            return [
                'success'                   => false,
                'status'                    => 'failed',
                'listing_type'              => 'seller',
                'listing_id'                => (int) ($profile->listing_id ?? 0),
                'seller_priorities'         => [],
                'property_strengths'        => [],
                'property_considerations'   => [],
                'marketing_opportunities'   => [],
                'buyer_archetype_alignment' => [],
                'signals'                   => [],
                'missing_inputs'            => [],
                'error'                     => $e->getMessage(),
            ];
        }
    }

    /**
     * Returns true when overall_dna_completeness is null or zero AND
     * all five priority score fields are null.
     */
    private function isInsufficientData(PropertyDnaProfile $profile): bool
    {
        $completeness = $profile->overall_dna_completeness;
        $completenessEmpty = ($completeness === null || (float) $completeness === 0.0);

        if (!$completenessEmpty) {
            return false;
        }

        foreach (self::PRIORITY_SCORE_FIELDS as $field) {
            if ($profile->{$field} !== null) {
                return false;
            }
        }

        return true;
    }

    /**
     * Build the insufficient_data response envelope.
     */
    private function insufficientDataResponse(PropertyDnaProfile $profile): array
    {
        return [
            'success'                   => false,
            'status'                    => 'insufficient_data',
            'listing_type'              => 'seller',
            'listing_id'                => (int) ($profile->listing_id ?? 0),
            'seller_priorities'         => [],
            'property_strengths'        => [],
            'property_considerations'   => [],
            'marketing_opportunities'   => [],
            'buyer_archetype_alignment' => [],
            'signals'                   => [],
            'missing_inputs'            => [],
            'error'                     => 'Insufficient profile data: overall_dna_completeness is empty and no priority scores are available.',
        ];
    }

    /**
     * Derive seller_priorities from non-null priority score fields.
     *
     * Each entry: ['dimension' => string, 'label' => string, 'coverage' => float]
     * Input order from PRIORITY_SCORE_FIELDS is preserved.
     */
    private function deriveSellerPriorities(PropertyDnaProfile $profile): array
    {
        $priorities = [];

        foreach (self::PRIORITY_SCORE_FIELDS as $field) {
            $value = $profile->{$field};
            if ($value === null) {
                continue;
            }
            $priorities[] = [
                'dimension' => $field,
                'label'     => self::PRIORITY_LABEL_MAP[$field],
                'coverage'  => (float) $value,
            ];
        }

        return $priorities;
    }

    /**
     * Derive property_strengths from ai_buyer_archetype_tags.
     *
     * Only tags present in TAG_STRENGTH_MAP produce a strength entry.
     * Each entry: ['tag' => string, 'label' => string]
     * Original tag is passed through verbatim.
     */
    private function derivePropertyStrengths(PropertyDnaProfile $profile): array
    {
        $tags = (array) ($profile->ai_buyer_archetype_tags ?? []);
        $strengths = [];

        foreach ($tags as $tag) {
            $tag = (string) $tag;
            if (isset(self::TAG_STRENGTH_MAP[$tag])) {
                $strengths[] = [
                    'tag'   => $tag,
                    'label' => self::TAG_STRENGTH_MAP[$tag],
                ];
            }
        }

        return $strengths;
    }

    /**
     * Derive property_considerations from low-coverage signals already in the profile.
     *
     * No new calculations — threshold comparisons only against already-stored values.
     * Each entry: ['signal' => string]
     */
    private function derivePropertyConsiderations(PropertyDnaProfile $profile): array
    {
        $considerations = [];

        $completeness = $profile->overall_dna_completeness;
        if ($completeness !== null && (float) $completeness < 50) {
            $considerations[] = ['signal' => 'Low overall data completeness'];
        }

        $conditionScore = $profile->condition_score;
        if ($conditionScore !== null && (float) $conditionScore < 34) {
            $considerations[] = ['signal' => 'Property condition data is sparse'];
        }

        $marketingScore = $profile->marketing_score;
        if ($marketingScore !== null && (float) $marketingScore < 34) {
            $considerations[] = ['signal' => 'Marketing dimension data is sparse'];
        }

        return $considerations;
    }

    /**
     * Derive marketing_opportunities by passing through ai_marketing_hooks verbatim.
     *
     * Each hook is ['trait' => string, 'value' => string]. No new hooks generated.
     */
    private function deriveMarketingOpportunities(PropertyDnaProfile $profile): array
    {
        return (array) ($profile->ai_marketing_hooks ?? []);
    }

    /**
     * Derive buyer_archetype_alignment by passing through ai_buyer_archetype_tags verbatim.
     *
     * Each entry: ['tag' => string]. No new tags generated.
     */
    private function deriveBuyerArchetypeAlignment(PropertyDnaProfile $profile): array
    {
        $tags = (array) ($profile->ai_buyer_archetype_tags ?? []);
        $result = [];

        foreach ($tags as $tag) {
            $result[] = ['tag' => (string) $tag];
        }

        return $result;
    }

    /**
     * Derive signals from key observable profile fields.
     *
     * Only includes entries where the profile field is non-null.
     * Each entry: ['key' => string, 'value' => mixed]
     */
    private function deriveSignals(PropertyDnaProfile $profile): array
    {
        $signals = [];

        foreach (self::SIGNAL_FIELDS as $field) {
            $value = $profile->{$field};
            if ($value !== null) {
                $signals[] = [
                    'key'   => $field,
                    'value' => $value,
                ];
            }
        }

        return $signals;
    }

    /**
     * Derive missing_inputs: list any ALL_DIMENSION_FIELDS that are null on the profile.
     *
     * Each entry: ['dimension' => string]
     */
    private function deriveMissingInputs(PropertyDnaProfile $profile): array
    {
        $missing = [];

        foreach (self::ALL_DIMENSION_FIELDS as $field) {
            if ($profile->{$field} === null) {
                $missing[] = ['dimension' => $field];
            }
        }

        return $missing;
    }
}
