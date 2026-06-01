<?php

namespace App\Services\Dna;

use App\Models\PropertyDnaProfile;

/**
 * LandlordDnaReportService — Landlord DNA Intelligence Report V1
 *
 * GOVERNANCE BLOCK:
 * ==================================================================================
 * This service is a DETERMINISTIC INTERPRETATION LAYER ONLY. It converts a
 * persisted PropertyDnaProfile (listing_type = 'landlord') into a structured
 * leasing intelligence report using rule-based signal extraction.
 *
 * This service MUST NEVER:
 *   - Perform AI reasoning, language model inference, embedding lookup, or ML logic.
 *   - Import or reference OpenAI, AI SDK clients, or any machine-learning library.
 *   - Issue additional database queries beyond reading the passed-in profile object.
 *   - Write, update, or persist any data to the database.
 *   - Generate new marketing hooks, narratives, endorsements, or copy of any kind.
 *   - Produce new compatibility scores, rankings, or numeric dimension scores.
 *   - Infer, imply, or output protected-class tenant profile labels (Family, Student,
 *     Senior, Professional, Retiree) or any demographic characteristic.
 *   - Process listing_type values other than 'landlord'.
 *   - Invent or extrapolate signals not supported by persisted tags or score fields.
 *
 * Classification is fully deterministic and reproducible from persisted profile data.
 * All signals must be derived from explicitly persisted ai_buyer_archetype_tags,
 * ai_marketing_hooks, or score fields stored on the PropertyDnaProfile.
 * ==================================================================================
 */
class LandlordDnaReportService
{
    /**
     * Minimum overall_dna_completeness (0–100) considered non-sparse.
     * Profiles below this threshold combined with missing score fields
     * return status = 'insufficient_data'.
     */
    private const MIN_COMPLETENESS = 15.0;

    /**
     * Minimum number of non-null score fields required to proceed past the
     * sparse-profile guard (combined with MIN_COMPLETENESS check).
     */
    private const MIN_POPULATED_SCORES = 2;

    /**
     * Score threshold (0–100) above which a score field maps to a priority label.
     */
    private const PRIORITY_THRESHOLD = 60.0;

    /**
     * The seven score fields and their human-readable landlord priority labels.
     */
    private const SCORE_PRIORITY_MAP = [
        'flexibility_score'            => 'Leasing Flexibility Focus',
        'financial_score'              => 'Rental Income Focus',
        'marketing_score'              => 'Marketing Visibility Focus',
        'compatibility_score'          => 'Tenant Compatibility Focus',
        'occupant_qualification_score' => 'Occupant Qualification Focus',
        'commercial_score'             => 'Commercial Use Focus',
        'condition_score'              => 'Property Condition Focus',
    ];

    /**
     * Generate a structured leasing intelligence report from a PropertyDnaProfile.
     *
     * Reads only listing_type, listing_id, overall_dna_completeness, score fields,
     * ai_buyer_archetype_tags, and ai_marketing_hooks from the passed-in profile.
     * No additional database queries are issued.
     *
     * Output contract — always returns exactly these keys:
     *   success                    bool
     *   status                     'generated' | 'insufficient_data' | 'failed'
     *   listing_type               'landlord'
     *   listing_id                 int
     *   landlord_priorities        array<string>
     *   property_strengths         array<string>
     *   leasing_considerations     array<string>
     *   tenant_fit_signals         array<string, string|null>
     *   marketing_opportunities    array   (verbatim ai_marketing_hooks records)
     *   lease_compatibility_signals array<string, string|null>
     *   signals                    array<string, mixed>
     *   missing_inputs             array<string>
     *   error                      string|null
     *
     * @param  PropertyDnaProfile $profile  A cast, in-memory profile instance.
     * @return array
     */
    public function generate(PropertyDnaProfile $profile): array
    {
        $stub = [
            'success'                    => false,
            'status'                     => 'insufficient_data',
            'listing_type'               => 'landlord',
            'listing_id'                 => (int) ($profile->listing_id ?? 0),
            'landlord_priorities'        => [],
            'property_strengths'         => [],
            'leasing_considerations'     => [],
            'tenant_fit_signals'         => [],
            'marketing_opportunities'    => [],
            'lease_compatibility_signals'=> [],
            'signals'                    => [],
            'missing_inputs'             => [],
            'error'                      => null,
        ];

        // Guard — wrong listing_type.
        if (($profile->listing_type ?? '') !== 'landlord') {
            $stub['missing_inputs'] = ['listing_type must be landlord'];
            return $stub;
        }

        // Guard — sparse profile: low completeness AND too few populated score fields.
        $completeness   = (float) ($profile->overall_dna_completeness ?? 0.0);
        $populatedScores = $this->countPopulatedScores($profile);

        if ($completeness < self::MIN_COMPLETENESS && $populatedScores < self::MIN_POPULATED_SCORES) {
            $stub['missing_inputs'] = $this->buildMissingInputsFromSparseProfile($profile);
            return $stub;
        }

        try {
            $tags   = (array) ($profile->ai_buyer_archetype_tags ?? []);
            $hooks  = (array) ($profile->ai_marketing_hooks ?? []);

            $signals = $this->extractSignals($tags, $profile);

            return [
                'success'                     => true,
                'status'                      => 'generated',
                'listing_type'                => 'landlord',
                'listing_id'                  => (int) $profile->listing_id,
                'landlord_priorities'         => $this->buildLandlordPriorities($profile),
                'property_strengths'          => $this->buildPropertyStrengths($tags, $profile),
                'leasing_considerations'      => $this->buildLeasingConsiderations($tags, $profile),
                'tenant_fit_signals'          => $this->buildTenantFitSignals($tags, $profile),
                'marketing_opportunities'     => $hooks,
                'lease_compatibility_signals' => $this->buildLeaseCompatibilitySignals($tags),
                'signals'                     => $signals,
                'missing_inputs'              => $this->buildMissingInputs($tags, $profile),
                'error'                       => null,
            ];
        } catch (\Throwable $e) {
            return array_merge($stub, [
                'status' => 'failed',
                'error'  => $e->getMessage(),
            ]);
        }
    }

    // -------------------------------------------------------------------------
    // Guard helpers
    // -------------------------------------------------------------------------

    /**
     * Count the number of non-null score fields on the profile.
     *
     * @param  PropertyDnaProfile $profile
     * @return int
     */
    private function countPopulatedScores(PropertyDnaProfile $profile): int
    {
        $count = 0;
        foreach (array_keys(self::SCORE_PRIORITY_MAP) as $field) {
            if ($profile->$field !== null) {
                $count++;
            }
        }
        // Also check physical, location, legal scores.
        foreach (['physical_score', 'location_score', 'legal_score'] as $field) {
            if ($profile->$field !== null) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Build a missing_inputs list for sparse profiles below the completeness threshold.
     *
     * @param  PropertyDnaProfile $profile
     * @return string[]
     */
    private function buildMissingInputsFromSparseProfile(PropertyDnaProfile $profile): array
    {
        $tags = (array) ($profile->ai_buyer_archetype_tags ?? []);

        $missing = $this->buildMissingInputs($tags, $profile);
        $missing[] = 'Additional DNA dimensions (profile completeness below minimum threshold)';

        return $missing;
    }

    // -------------------------------------------------------------------------
    // Signal extraction
    // -------------------------------------------------------------------------

    /**
     * Extract a named signal map from archetype tags and score fields.
     *
     * All signals originate from explicitly persisted tag strings or score values.
     * No signal is inferred from overall_dna_completeness alone.
     *
     * @param  string[]           $tags
     * @param  PropertyDnaProfile $profile
     * @return array<string, mixed>
     */
    private function extractSignals(array $tags, PropertyDnaProfile $profile): array
    {
        $signals = [
            'pets_allowed'        => false,
            'no_pets'             => false,
            'smoking_allowed'     => false,
            'no_smoking'          => false,
            'furnished'           => false,
            'has_pool'            => false,
            'has_garage'          => false,
            'has_waterfront'      => false,
            'has_view'            => false,
            'commercial_use'      => false,
            'lease_option'        => false,
            'lease_purchase'      => false,
            'has_parking'         => false,
            'has_available_date'  => false,
            'has_amenities'       => false,
            'condition_strong'    => false,
            'commercial_score_strong' => false,
        ];

        foreach ($tags as $tag) {
            $tag = (string) $tag;

            switch ($tag) {
                case 'policy:pets-allowed':
                    $signals['pets_allowed'] = true;
                    break;
                case 'policy:no-pets':
                    $signals['no_pets'] = true;
                    break;
                case 'policy:smoking-allowed':
                    $signals['smoking_allowed'] = true;
                    break;
                case 'policy:no-smoking':
                    $signals['no_smoking'] = true;
                    break;
                case 'amenity:furnished':
                case 'feature:furnished':
                    $signals['furnished'] = true;
                    break;
                case 'amenity:pool':
                    $signals['has_pool'] = true;
                    $signals['has_amenities'] = true;
                    break;
                case 'amenity:garage':
                case 'parking:garage':
                    $signals['has_garage'] = true;
                    break;
                case 'feature:waterfront':
                    $signals['has_waterfront'] = true;
                    break;
                case 'feature:view':
                    $signals['has_view'] = true;
                    break;
                case 'use:commercial':
                    $signals['commercial_use'] = true;
                    break;
                case 'structure:lease-option':
                    $signals['lease_option'] = true;
                    break;
                case 'structure:lease-purchase':
                    $signals['lease_purchase'] = true;
                    break;
            }

            if (str_starts_with($tag, 'parking:')) {
                $signals['has_parking'] = true;
            }
            if (str_starts_with($tag, 'timing:')) {
                $signals['has_available_date'] = true;
            }
            if (str_starts_with($tag, 'amenity:')) {
                $signals['has_amenities'] = true;
            }
        }

        // Score-derived signals — only from persisted score fields, not computed here.
        $conditionScore = $profile->condition_score !== null ? (float) $profile->condition_score : null;
        if ($conditionScore !== null && $conditionScore > self::PRIORITY_THRESHOLD) {
            $signals['condition_strong'] = true;
        }

        $commercialScore = $profile->commercial_score !== null ? (float) $profile->commercial_score : null;
        if ($commercialScore !== null && $commercialScore > self::PRIORITY_THRESHOLD) {
            $signals['commercial_score_strong'] = true;
        }

        return $signals;
    }

    // -------------------------------------------------------------------------
    // Output section builders
    // -------------------------------------------------------------------------

    /**
     * Build landlord priority labels from the seven stored score fields.
     * Only scores above PRIORITY_THRESHOLD are included.
     *
     * @param  PropertyDnaProfile $profile
     * @return string[]
     */
    private function buildLandlordPriorities(PropertyDnaProfile $profile): array
    {
        $priorities = [];

        foreach (self::SCORE_PRIORITY_MAP as $field => $label) {
            $value = $profile->$field;
            if ($value !== null && (float) $value > self::PRIORITY_THRESHOLD) {
                $priorities[] = $label;
            }
        }

        return $priorities;
    }

    /**
     * Build property strength labels from archetype tags and score fields.
     * Only includes a strength when a supporting tag, hook, or score is present.
     *
     * @param  string[]           $tags
     * @param  PropertyDnaProfile $profile
     * @return string[]
     */
    private function buildPropertyStrengths(array $tags, PropertyDnaProfile $profile): array
    {
        $signals   = $this->extractSignals($tags, $profile);
        $strengths = [];

        if ($signals['pets_allowed']) {
            $strengths[] = 'Pet-Friendly Policy';
        }
        if ($signals['has_pool']) {
            $strengths[] = 'Pool On-Site';
        }
        if ($signals['has_garage'] || $signals['has_parking']) {
            $strengths[] = 'Parking Available';
        }
        if ($signals['furnished']) {
            $strengths[] = 'Furnished Unit';
        }
        if ($signals['has_waterfront']) {
            $strengths[] = 'Waterfront Property';
        }
        if ($signals['has_view']) {
            $strengths[] = 'View Specified';
        }
        if ($signals['commercial_use'] || $signals['commercial_score_strong']) {
            $strengths[] = 'Commercial Use Eligible';
        }
        if ($signals['condition_strong']) {
            $strengths[] = 'Strong Condition Score';
        }
        if ($signals['lease_option']) {
            $strengths[] = 'Lease-Option Available';
        }
        if ($signals['lease_purchase']) {
            $strengths[] = 'Lease-Purchase Available';
        }

        return $strengths;
    }

    /**
     * Identify unspecified policy dimensions from absent tags or null score fields.
     * Returns factual "Not Specified" labels only — no subjective warnings.
     *
     * @param  string[]           $tags
     * @param  PropertyDnaProfile $profile
     * @return string[]
     */
    private function buildLeasingConsiderations(array $tags, PropertyDnaProfile $profile): array
    {
        $signals        = $this->extractSignals($tags, $profile);
        $considerations = [];

        if (!$signals['pets_allowed'] && !$signals['no_pets']) {
            $considerations[] = 'Pet Policy: Not Specified';
        }
        if (!$signals['smoking_allowed'] && !$signals['no_smoking']) {
            $considerations[] = 'Smoking Policy: Not Specified';
        }
        if (!$signals['furnished']) {
            $considerations[] = 'Furnishing Terms: Not Specified';
        }
        if (!$signals['has_parking']) {
            $considerations[] = 'Parking Details: Not Specified';
        }
        if (!$signals['has_available_date']) {
            $considerations[] = 'Available Date: Not Specified';
        }
        if (!$signals['commercial_use'] && $profile->commercial_score === null) {
            $considerations[] = 'Commercial Use: Not Specified';
        }
        if (!$signals['lease_option'] && !$signals['lease_purchase']) {
            $considerations[] = 'Lease Structure Options: Not Specified';
        }

        return $considerations;
    }

    /**
     * Surface factual fit indicators present in the profile.
     * Derived from archetype tags and score fields — no demographic tenant labels.
     *
     * @param  string[]           $tags
     * @param  PropertyDnaProfile $profile
     * @return array<string, string|null>
     */
    private function buildTenantFitSignals(array $tags, PropertyDnaProfile $profile): array
    {
        $signals = $this->extractSignals($tags, $profile);

        return [
            'pet_policy'      => $signals['pets_allowed']
                ? 'Pets Allowed'
                : ($signals['no_pets'] ? 'No Pets' : null),
            'smoking_policy'  => $signals['smoking_allowed']
                ? 'Smoking Allowed'
                : ($signals['no_smoking'] ? 'No Smoking' : null),
            'furnishing'      => $signals['furnished'] ? 'Furnished' : null,
            'parking'         => $signals['has_garage']
                ? 'Garage Parking'
                : ($signals['has_parking'] ? 'Parking Available' : null),
            'lease_option'    => $signals['lease_option'] ? 'Lease-Option Available' : null,
            'lease_purchase'  => $signals['lease_purchase'] ? 'Lease-Purchase Available' : null,
            'commercial_use'  => ($signals['commercial_use'] || $signals['commercial_score_strong'])
                ? 'Commercial Use Eligible' : null,
            'amenities'       => $signals['has_amenities']
                ? 'Amenities Present'
                : ($signals['has_pool'] ? 'Pool On-Site' : null),
            'move_in_date'    => $signals['has_available_date'] ? 'Availability Date Specified' : null,
        ];
    }

    /**
     * Extract lease-compatibility-relevant signals from archetype tags.
     * No new compatibility calculations — reads persisted tag data only.
     *
     * @param  string[] $tags
     * @return array<string, string|null>
     */
    private function buildLeaseCompatibilitySignals(array $tags): array
    {
        $result = [
            'pet_policy'      => null,
            'smoking_policy'  => null,
            'furnishing_terms'=> null,
            'parking'         => null,
            'lease_option'    => null,
            'lease_purchase'  => null,
            'commercial_use'  => null,
            'amenities'       => null,
            'move_in_date'    => null,
        ];

        foreach ($tags as $tag) {
            $tag = (string) $tag;

            if ($tag === 'policy:pets-allowed') {
                $result['pet_policy'] = 'pets-allowed';
            } elseif ($tag === 'policy:no-pets') {
                $result['pet_policy'] = 'no-pets';
            }

            if ($tag === 'policy:smoking-allowed') {
                $result['smoking_policy'] = 'smoking-allowed';
            } elseif ($tag === 'policy:no-smoking') {
                $result['smoking_policy'] = 'no-smoking';
            }

            if ($tag === 'amenity:furnished' || $tag === 'feature:furnished') {
                $result['furnishing_terms'] = 'furnished';
            }

            if (str_starts_with($tag, 'parking:')) {
                $result['parking'] = substr($tag, strlen('parking:'));
            }

            if ($tag === 'structure:lease-option') {
                $result['lease_option'] = 'available';
            }

            if ($tag === 'structure:lease-purchase') {
                $result['lease_purchase'] = 'available';
            }

            if ($tag === 'use:commercial') {
                $result['commercial_use'] = 'eligible';
            }

            if (str_starts_with($tag, 'amenity:')) {
                $amenityName = substr($tag, strlen('amenity:'));
                $result['amenities'] = $result['amenities']
                    ? $result['amenities'] . ',' . $amenityName
                    : $amenityName;
            }

            if (str_starts_with($tag, 'timing:')) {
                $result['move_in_date'] = substr($tag, strlen('timing:'));
            }
        }

        return $result;
    }

    /**
     * Build the missing_inputs list identifying absent landlord DNA dimensions.
     *
     * Reports dimensions that are absent from the profile so callers know what
     * additional data would improve the report.
     *
     * @param  string[]           $tags
     * @param  PropertyDnaProfile $profile
     * @return string[]
     */
    private function buildMissingInputs(array $tags, PropertyDnaProfile $profile): array
    {
        $signals = $this->extractSignals($tags, $profile);
        $missing = [];

        if (!$signals['pets_allowed'] && !$signals['no_pets']) {
            $missing[] = 'Pet policy';
        }
        if (!$signals['smoking_allowed'] && !$signals['no_smoking']) {
            $missing[] = 'Smoking policy';
        }
        if (!$signals['furnished']) {
            $missing[] = 'Furnishing terms';
        }
        if (!$signals['has_parking']) {
            $missing[] = 'Parking details';
        }
        if (!$signals['has_available_date']) {
            $missing[] = 'Available date';
        }
        if (!$signals['lease_option'] && !$signals['lease_purchase']) {
            $missing[] = 'Lease option terms';
        }
        if (!$signals['commercial_use'] && $profile->commercial_score === null) {
            $missing[] = 'Commercial use';
        }
        if (!$signals['has_amenities']) {
            $missing[] = 'Amenities';
        }
        if (!$signals['has_waterfront']) {
            $missing[] = 'Waterfront detail';
        }
        if (!$signals['has_view']) {
            $missing[] = 'View detail';
        }
        if ($profile->condition_score === null) {
            $missing[] = 'Condition score';
        }
        if ($profile->commercial_score === null) {
            $missing[] = 'Zoning / commercial score';
        }

        return $missing;
    }
}
