<?php

namespace App\Services\Dna;

use App\Models\PropertyDnaProfile;

/**
 * PropertyPersonalityService — Property Personality Engine V1
 *
 * GOVERNANCE BLOCK:
 * ==================================================================================
 * This service is a READ-ONLY INTERPRETATION LAYER. It classifies a property into
 * structured personality identities using only already-persisted PropertyDnaProfile
 * data and a caller-supplied Location DNA summary array.
 *
 * This service MUST NEVER:
 *   - Write to, modify, or archive any database row or model.
 *   - Read any table, model, or database connection — all input arrives via the
 *     PropertyDnaProfile model instance and the $locationDnaSummary array passed
 *     by the caller.
 *   - Call any AI, OpenAI, language model, embedding, ML, or external HTTP service.
 *   - Rank, sort, or order output by any score, metric, or signal.
 *   - Recommend any listing, buyer, seller, landlord, tenant, or agent.
 *   - Determine, infer, or output suitability, qualification, approval, or rejection.
 *   - Predict any outcome, likelihood, or probability of any transaction event.
 *   - Generate narrative persuasion copy, endorsements, or matchmaking language.
 *   - Recalculate, modify, or reinterpret any stored DNA score or completeness value.
 *   - Surface output in any user-facing view, API, PDF, email, or cache layer.
 *
 * All output is deterministic and reproducible from persisted data.
 * No numeric ranking, scoring, or weighting is applied to personality output.
 * Personality precedence follows the approved deterministic specificity order
 * defined in PERSONALITY_ORDER; the first matching type becomes primary_personality.
 * ==================================================================================
 *
 * V1 Personality Types (12 total, evaluated in specificity order):
 *   Coastal Lifestyle Property | Waterfront Property | Boater-Friendly Property |
 *   Recreation-Oriented Property | Walkable Convenience Property | Amenity-Rich Property |
 *   Luxury Lifestyle Property | Commercial Flexibility Property |
 *   Investment-Oriented Property | Flexible Opportunity Property |
 *   Traditional Residential Property | Unknown Property
 */
class PropertyPersonalityService
{
    // -------------------------------------------------------------------------
    // Location DNA thresholds (miles)
    // -------------------------------------------------------------------------

    /** Beach or beach-access distance that qualifies as Coastal. */
    private const BEACH_MILES = 2.0;

    /** Boat ramp or marina distance that qualifies as Boater-Friendly. */
    private const BOAT_MILES = 5.0;

    /** Park distance that qualifies as Recreation-Oriented. */
    private const PARK_MILES = 1.0;

    /** Dog-park distance that qualifies as Recreation-Oriented. */
    private const DOG_PARK_MILES = 2.0;

    /** Golf-course distance that qualifies as Recreation-Oriented. */
    private const GOLF_MILES = 3.0;

    /** Waterfront-park distance that qualifies as Recreation-Oriented. */
    private const WATERFRONT_PARK_MILES = 2.0;

    /** Grocery distance that qualifies as Walkable Convenience (location path). */
    private const GROCERY_MILES = 0.5;

    // -------------------------------------------------------------------------
    // DNA score thresholds (0–100)
    // -------------------------------------------------------------------------

    /** walk_score minimum for Walkable Convenience. */
    private const WALK_SCORE_MIN = 70;

    /** financial_score minimum for Luxury Lifestyle. */
    private const LUXURY_FINANCIAL_MIN = 80.0;

    /** physical_score minimum for Luxury Lifestyle. */
    private const LUXURY_PHYSICAL_MIN = 75.0;

    /** commercial_score hard minimum for Commercial Flexibility. */
    private const COMMERCIAL_SCORE_MIN = 60.0;

    /** commercial_score soft minimum when combined with flexibility_score. */
    private const COMMERCIAL_SCORE_SOFT = 35.0;

    /** flexibility_score minimum to trigger Commercial Flexibility (soft path). */
    private const COMMERCIAL_FLEX_MIN = 65.0;

    /** financial_score minimum for Investment-Oriented. */
    private const INVESTMENT_FINANCIAL_MIN = 70.0;

    /** flexibility_score minimum to pair with financial for Investment-Oriented. */
    private const INVESTMENT_FLEX_MIN = 55.0;

    /** flexibility_score minimum for Flexible Opportunity. */
    private const FLEX_OPP_MIN = 60.0;

    /** marketing_score minimum for the Amenity-Rich marketing-score path. */
    private const AMENITY_MARKETING_MIN = 70.0;

    /** Number of amenity:* tags that qualifies as Amenity-Rich (count path). */
    private const AMENITY_TAG_COUNT = 3;

    /** overall_dna_completeness minimum for Traditional Residential fallback. */
    private const TRADITIONAL_COMPLETENESS_MIN = 20.0;

    // -------------------------------------------------------------------------
    // Ordered personality type identifiers
    // -------------------------------------------------------------------------

    private const PERSONALITY_ORDER = [
        'coastal',
        'waterfront',
        'boater_friendly',
        'recreation_oriented',
        'walkable_convenience',
        'amenity_rich',
        'luxury_lifestyle',
        'commercial_flexibility',
        'investment_oriented',
        'flexible_opportunity',
        'traditional_residential',
    ];

    /**
     * Approved public labels for each internal personality type identifier.
     * Used when populating primary_personality and secondary_personalities in output.
     * Internal codes are never returned directly to callers.
     */
    private const PERSONALITY_LABELS = [
        'coastal'                 => 'Coastal Lifestyle Property',
        'waterfront'              => 'Waterfront Property',
        'boater_friendly'         => 'Boater-Friendly Property',
        'recreation_oriented'     => 'Recreation-Oriented Property',
        'walkable_convenience'    => 'Walkable Convenience Property',
        'amenity_rich'            => 'Amenity-Rich Property',
        'luxury_lifestyle'        => 'Luxury Lifestyle Property',
        'commercial_flexibility'  => 'Commercial Flexibility Property',
        'investment_oriented'     => 'Investment-Oriented Property',
        'flexible_opportunity'    => 'Flexible Opportunity Property',
        'traditional_residential' => 'Traditional Residential Property',
        'unknown'                 => 'Unknown Property',
    ];

    // -------------------------------------------------------------------------
    // Dimension fields consulted during classification (for missing_inputs)
    // -------------------------------------------------------------------------

    private const CONSULTED_SCORE_FIELDS = [
        'physical_score',
        'financial_score',
        'location_score',
        'condition_score',
        'flexibility_score',
        'marketing_score',
        'commercial_score',
    ];

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Generate a structured personality classification from a PropertyDnaProfile
     * and an optional Location DNA summary array.
     *
     * No database reads or writes occur. No AI or external HTTP calls are made.
     * All derivations are deterministic threshold comparisons and tag-map lookups.
     *
     * @param  PropertyDnaProfile $profile           A cast profile model instance (no DB queries made here).
     * @param  array              $locationDnaSummary Optional Location DNA summary produced by LocationDnaSummaryService.
     *                                               Expected top-level keys: coastal, daily_convenience,
     *                                               outdoor_recreation, transportation.
     * @return array{
     *   success: bool,
     *   status: string,
     *   listing_type: string,
     *   listing_id: int,
     *   primary_personality: string|null,
     *   secondary_personalities: array,
     *   personality_signals: array,
     *   missing_inputs: array,
     *   error: string|null,
     * }
     */
    public function generate(PropertyDnaProfile $profile, array $locationDnaSummary = []): array
    {
        try {
            if ($this->isInsufficientData($profile, $locationDnaSummary)) {
                return $this->insufficientDataResponse($profile);
            }

            $tagSignals      = $this->extractTagSignals($profile);
            $locationSignals = $this->extractLocationSignals($locationDnaSummary);

            [$matchedTypes, $personalitySignals] = $this->classifyAll($profile, $tagSignals, $locationSignals);

            if (empty($matchedTypes)) {
                $primaryCode = 'unknown';
                $secondaryCodes = [];
            } else {
                $primaryCode    = $matchedTypes[0];
                $secondaryCodes = array_slice($matchedTypes, 1);
            }

            return [
                'success'                 => true,
                'status'                  => 'generated',
                'listing_type'            => (string) ($profile->listing_type ?? ''),
                'listing_id'              => (int) ($profile->listing_id ?? 0),
                'primary_personality'     => self::PERSONALITY_LABELS[$primaryCode] ?? $primaryCode,
                'secondary_personalities' => array_map(
                    fn(string $code) => self::PERSONALITY_LABELS[$code] ?? $code,
                    $secondaryCodes
                ),
                'personality_signals'     => $personalitySignals,
                'missing_inputs'          => $this->deriveMissingInputs($profile),
                'error'                   => null,
            ];
        } catch (\Throwable $e) {
            return [
                'success'                 => false,
                'status'                  => 'failed',
                'listing_type'            => (string) ($profile->listing_type ?? ''),
                'listing_id'              => (int) ($profile->listing_id ?? 0),
                'primary_personality'     => null,
                'secondary_personalities' => [],
                'personality_signals'     => [],
                'missing_inputs'          => [],
                'error'                   => $e->getMessage(),
            ];
        }
    }

    // -------------------------------------------------------------------------
    // Insufficient-data guard
    // -------------------------------------------------------------------------

    /**
     * Returns true when there is genuinely no classifiable data:
     *   - overall_dna_completeness is null or zero
     *   - all consulted score fields are null
     *   - ai_buyer_archetype_tags is null or empty
     *   - walk_score is null
     *   - locationDnaSummary is empty
     */
    private function isInsufficientData(PropertyDnaProfile $profile, array $locationDnaSummary): bool
    {
        $completeness = $profile->overall_dna_completeness;
        if ($completeness !== null && (float) $completeness > 0.0) {
            return false;
        }

        foreach (self::CONSULTED_SCORE_FIELDS as $field) {
            if ($profile->{$field} !== null) {
                return false;
            }
        }

        if (!empty($profile->ai_buyer_archetype_tags)) {
            return false;
        }

        if ($profile->walk_score !== null) {
            return false;
        }

        if (!empty($locationDnaSummary)) {
            return false;
        }

        return true;
    }

    private function insufficientDataResponse(PropertyDnaProfile $profile): array
    {
        return [
            'success'                 => false,
            'status'                  => 'insufficient_data',
            'listing_type'            => (string) ($profile->listing_type ?? ''),
            'listing_id'              => (int) ($profile->listing_id ?? 0),
            'primary_personality'     => null,
            'secondary_personalities' => [],
            'personality_signals'     => [],
            'missing_inputs'          => [],
            'error'                   => 'Insufficient profile data: no scores, tags, walk score, or location DNA are available.',
        ];
    }

    // -------------------------------------------------------------------------
    // Signal extraction
    // -------------------------------------------------------------------------

    /**
     * Hook trait strings that map to personality signal keys.
     * Matches the `trait` field of ai_marketing_hooks entries.
     */
    private const HOOK_TRAIT_SIGNAL_MAP = [
        'waterfront'       => 'has_waterfront',
        'coastal'          => 'has_waterfront',
        'pool'             => 'has_pool',
        'garage'           => 'has_garage',
        'parking'          => 'has_garage',
        'commercial'       => 'has_commercial',
        'lease-option'     => 'has_lease_option',
        'lease-purchase'   => 'has_lease_purchase',
        'seller-financed'  => 'has_seller_finance',
        'assumable'        => 'has_assumable',
        'luxury'           => 'has_luxury_hook',
    ];

    /**
     * Extract a flat boolean/value map from both ai_buyer_archetype_tags
     * and ai_marketing_hooks into a combined signal boolean map.
     *
     * Tag signals are derived from the archetype tag namespace prefix/value.
     * Hook signals are derived from the deterministic `trait` field of each hook entry.
     * No new inferences are made — only explicitly persisted tag/hook values are read.
     *
     * @param  PropertyDnaProfile $profile
     * @return array<string, bool|int>
     */
    private function extractTagSignals(PropertyDnaProfile $profile): array
    {
        $signals = [
            'has_waterfront'      => false,
            'has_pool'            => false,
            'has_garage'          => false,
            'has_commercial'      => false,
            'has_lease_option'    => false,
            'has_lease_purchase'  => false,
            'has_seller_finance'  => false,
            'has_assumable'       => false,
            'has_luxury_hook'     => false,
            'amenity_tag_count'   => 0,
        ];

        // --- ai_buyer_archetype_tags ---
        $tags = (array) ($profile->ai_buyer_archetype_tags ?? []);
        foreach ($tags as $tag) {
            $tag = (string) $tag;

            switch ($tag) {
                case 'amenity:waterfront':
                case 'feature:waterfront':
                    $signals['has_waterfront'] = true;
                    break;
                case 'amenity:pool':
                    $signals['has_pool'] = true;
                    break;
                case 'amenity:garage':
                case 'parking:garage':
                    $signals['has_garage'] = true;
                    break;
                case 'use:commercial':
                    $signals['has_commercial'] = true;
                    break;
                case 'structure:lease-option':
                    $signals['has_lease_option'] = true;
                    break;
                case 'structure:lease-purchase':
                    $signals['has_lease_purchase'] = true;
                    break;
                case 'financing:seller-financed':
                    $signals['has_seller_finance'] = true;
                    break;
                case 'financing:assumable':
                    $signals['has_assumable'] = true;
                    break;
            }

            if (str_starts_with($tag, 'amenity:')) {
                $signals['amenity_tag_count']++;
            }
        }

        // --- ai_marketing_hooks ---
        // Each hook entry is ['trait' => string, 'value' => string].
        // Only the `trait` field is read; no new inferences are made from `value`.
        $hooks = (array) ($profile->ai_marketing_hooks ?? []);
        foreach ($hooks as $hook) {
            if (!is_array($hook)) {
                continue;
            }
            $trait = strtolower((string) ($hook['trait'] ?? ''));
            if (isset(self::HOOK_TRAIT_SIGNAL_MAP[$trait])) {
                $signals[self::HOOK_TRAIT_SIGNAL_MAP[$trait]] = true;
            }
        }

        return $signals;
    }

    /**
     * Extract distance values from all four Location DNA thematic blocks:
     * coastal, daily_convenience, outdoor_recreation, and transportation.
     *
     * All four blocks are accessed via the approved Phase D output key names.
     * Values are cast to float where present, or null when absent/not-found.
     *
     * @param  array $locationDnaSummary
     * @return array<string, float|null>
     */
    private function extractLocationSignals(array $locationDnaSummary): array
    {
        $coastal        = $locationDnaSummary['coastal'] ?? [];
        $daily          = $locationDnaSummary['daily_convenience'] ?? [];
        $outdoor        = $locationDnaSummary['outdoor_recreation'] ?? [];
        $transportation = $locationDnaSummary['transportation'] ?? [];

        return [
            // coastal block
            'nearest_beach_miles'          => $this->floatOrNull($coastal['nearest_beach_miles'] ?? null),
            'nearest_beach_access_miles'   => $this->floatOrNull($coastal['nearest_beach_access_miles'] ?? null),
            'nearest_boat_ramp_miles'      => $this->floatOrNull($coastal['nearest_boat_ramp_miles'] ?? null),
            'nearest_marina_miles'         => $this->floatOrNull($coastal['nearest_marina_miles'] ?? null),
            // daily_convenience block
            'nearest_grocery_miles'        => $this->floatOrNull($daily['nearest_grocery_miles'] ?? null),
            // outdoor_recreation block
            'nearest_park_miles'           => $this->floatOrNull($outdoor['nearest_park_miles'] ?? null),
            'nearest_dog_park_miles'       => $this->floatOrNull($outdoor['nearest_dog_park_miles'] ?? null),
            'nearest_golf_course_miles'    => $this->floatOrNull($outdoor['nearest_golf_course_miles'] ?? null),
            'nearest_waterfront_park_miles'=> $this->floatOrNull($outdoor['nearest_waterfront_park_miles'] ?? null),
            // transportation block
            'nearest_transit_miles'        => $this->floatOrNull($transportation['nearest_transit_miles'] ?? null),
            'nearest_gas_station_miles'    => $this->floatOrNull($transportation['nearest_gas_station_miles'] ?? null),
        ];
    }

    private function floatOrNull(mixed $value): ?float
    {
        return $value !== null ? (float) $value : null;
    }

    // -------------------------------------------------------------------------
    // Classification engine
    // -------------------------------------------------------------------------

    /**
     * Evaluate all 12 personality types in specificity order.
     *
     * Returns [matchedTypes[], personalitySignals[]] where matchedTypes is ordered
     * by the PERSONALITY_ORDER specificity list. The first entry is primary.
     *
     * @param  PropertyDnaProfile    $profile
     * @param  array<string, mixed>  $tagSignals
     * @param  array<string, mixed>  $locationSignals
     * @return array{0: string[], 1: array}
     */
    private function classifyAll(
        PropertyDnaProfile $profile,
        array $tagSignals,
        array $locationSignals
    ): array {
        $matched = [];
        $signals = [];

        foreach (self::PERSONALITY_ORDER as $type) {
            [$triggered, $typeSignals] = $this->evaluateType($type, $profile, $tagSignals, $locationSignals);
            if ($triggered) {
                $matched[] = $type;
                foreach ($typeSignals as $s) {
                    $signals[] = $s;
                }
            }
        }

        if (empty($matched)) {
            $matched[] = 'unknown';
        }

        return [$matched, $signals];
    }

    /**
     * Evaluate a single personality type. Returns [bool $triggered, array $signals].
     *
     * @param  string                $type
     * @param  PropertyDnaProfile    $profile
     * @param  array<string, mixed>  $tagSignals
     * @param  array<string, mixed>  $locationSignals
     * @return array{0: bool, 1: array}
     */
    private function evaluateType(
        string $type,
        PropertyDnaProfile $profile,
        array $tagSignals,
        array $locationSignals
    ): array {
        return match ($type) {
            'coastal'                => $this->evalCoastal($locationSignals),
            'waterfront'             => $this->evalWaterfront($tagSignals),
            'boater_friendly'        => $this->evalBoaterFriendly($locationSignals),
            'recreation_oriented'    => $this->evalRecreationOriented($locationSignals),
            'walkable_convenience'   => $this->evalWalkableConvenience($profile, $locationSignals),
            'amenity_rich'           => $this->evalAmenityRich($profile, $tagSignals),
            'luxury_lifestyle'       => $this->evalLuxuryLifestyle($profile),
            'commercial_flexibility' => $this->evalCommercialFlexibility($profile, $tagSignals),
            'investment_oriented'    => $this->evalInvestmentOriented($profile, $tagSignals),
            'flexible_opportunity'   => $this->evalFlexibleOpportunity($profile, $tagSignals),
            'traditional_residential'=> $this->evalTraditionalResidential($profile),
            default                  => [false, []],
        };
    }

    // -------------------------------------------------------------------------
    // Individual personality evaluators
    // -------------------------------------------------------------------------

    /** Coastal: beach or beach-access within BEACH_MILES. */
    private function evalCoastal(array $loc): array
    {
        $signals = [];

        $beach = $loc['nearest_beach_miles'];
        if ($beach !== null && $beach <= self::BEACH_MILES) {
            $signals[] = ['signal' => 'nearest_beach_miles', 'value' => $beach];
        }

        $access = $loc['nearest_beach_access_miles'];
        if ($access !== null && $access <= self::BEACH_MILES) {
            $signals[] = ['signal' => 'nearest_beach_access_miles', 'value' => $access];
        }

        return [!empty($signals), $signals];
    }

    /** Waterfront: tag amenity:waterfront or feature:waterfront present. */
    private function evalWaterfront(array $tagSignals): array
    {
        if ($tagSignals['has_waterfront']) {
            return [true, [['signal' => 'tag_waterfront', 'value' => true]]];
        }
        return [false, []];
    }

    /** Boater-Friendly: boat ramp or marina within BOAT_MILES. */
    private function evalBoaterFriendly(array $loc): array
    {
        $signals = [];

        $ramp = $loc['nearest_boat_ramp_miles'];
        if ($ramp !== null && $ramp <= self::BOAT_MILES) {
            $signals[] = ['signal' => 'nearest_boat_ramp_miles', 'value' => $ramp];
        }

        $marina = $loc['nearest_marina_miles'];
        if ($marina !== null && $marina <= self::BOAT_MILES) {
            $signals[] = ['signal' => 'nearest_marina_miles', 'value' => $marina];
        }

        return [!empty($signals), $signals];
    }

    /** Recreation-Oriented: park, dog park, golf course, or waterfront park within thresholds. */
    private function evalRecreationOriented(array $loc): array
    {
        $signals = [];

        $park = $loc['nearest_park_miles'];
        if ($park !== null && $park <= self::PARK_MILES) {
            $signals[] = ['signal' => 'nearest_park_miles', 'value' => $park];
        }

        $dogPark = $loc['nearest_dog_park_miles'];
        if ($dogPark !== null && $dogPark <= self::DOG_PARK_MILES) {
            $signals[] = ['signal' => 'nearest_dog_park_miles', 'value' => $dogPark];
        }

        $golf = $loc['nearest_golf_course_miles'];
        if ($golf !== null && $golf <= self::GOLF_MILES) {
            $signals[] = ['signal' => 'nearest_golf_course_miles', 'value' => $golf];
        }

        $wfPark = $loc['nearest_waterfront_park_miles'];
        if ($wfPark !== null && $wfPark <= self::WATERFRONT_PARK_MILES) {
            $signals[] = ['signal' => 'nearest_waterfront_park_miles', 'value' => $wfPark];
        }

        return [!empty($signals), $signals];
    }

    /** Walkable Convenience: walk_score >= 70 or grocery store within GROCERY_MILES. */
    private function evalWalkableConvenience(PropertyDnaProfile $profile, array $loc): array
    {
        $signals = [];

        $walkScore = $profile->walk_score !== null ? (float) $profile->walk_score : null;
        if ($walkScore !== null && $walkScore >= self::WALK_SCORE_MIN) {
            $signals[] = ['signal' => 'walk_score', 'value' => $walkScore];
        }

        $grocery = $loc['nearest_grocery_miles'];
        if ($grocery !== null && $grocery <= self::GROCERY_MILES) {
            $signals[] = ['signal' => 'nearest_grocery_miles', 'value' => $grocery];
        }

        return [!empty($signals), $signals];
    }

    /**
     * Amenity-Rich: 3+ amenity:* tags, OR (marketing_score >= 70 AND has any amenity tag),
     *               OR (pool tag AND garage tag).
     */
    private function evalAmenityRich(PropertyDnaProfile $profile, array $tagSignals): array
    {
        $signals = [];

        $amenityCount = (int) $tagSignals['amenity_tag_count'];
        if ($amenityCount >= self::AMENITY_TAG_COUNT) {
            $signals[] = ['signal' => 'amenity_tag_count', 'value' => $amenityCount];
        }

        $marketingScore = $profile->marketing_score !== null ? (float) $profile->marketing_score : null;
        if ($marketingScore !== null && $marketingScore >= self::AMENITY_MARKETING_MIN && $amenityCount >= 1) {
            $signals[] = ['signal' => 'marketing_score_with_amenity_tags', 'value' => $marketingScore];
        }

        if ($tagSignals['has_pool'] && $tagSignals['has_garage']) {
            $signals[] = ['signal' => 'pool_and_garage_tags', 'value' => true];
        }

        return [!empty($signals), $signals];
    }

    /** Luxury Lifestyle: financial_score >= 80 AND physical_score >= 75. */
    private function evalLuxuryLifestyle(PropertyDnaProfile $profile): array
    {
        $financial = $profile->financial_score !== null ? (float) $profile->financial_score : null;
        $physical  = $profile->physical_score !== null ? (float) $profile->physical_score : null;

        if ($financial !== null && $physical !== null
            && $financial >= self::LUXURY_FINANCIAL_MIN
            && $physical >= self::LUXURY_PHYSICAL_MIN
        ) {
            return [true, [
                ['signal' => 'financial_score', 'value' => $financial],
                ['signal' => 'physical_score', 'value' => $physical],
            ]];
        }

        return [false, []];
    }

    /**
     * Commercial Flexibility:
     *   - commercial_score >= 60, OR
     *   - tag use:commercial, OR
     *   - flexibility_score >= 65 AND commercial_score >= 35
     */
    private function evalCommercialFlexibility(PropertyDnaProfile $profile, array $tagSignals): array
    {
        $signals = [];

        $commercial = $profile->commercial_score !== null ? (float) $profile->commercial_score : null;
        $flex       = $profile->flexibility_score !== null ? (float) $profile->flexibility_score : null;

        if ($commercial !== null && $commercial >= self::COMMERCIAL_SCORE_MIN) {
            $signals[] = ['signal' => 'commercial_score', 'value' => $commercial];
        }

        if ($tagSignals['has_commercial']) {
            $signals[] = ['signal' => 'tag_use_commercial', 'value' => true];
        }

        if ($flex !== null && $flex >= self::COMMERCIAL_FLEX_MIN
            && $commercial !== null && $commercial >= self::COMMERCIAL_SCORE_SOFT
        ) {
            $signals[] = ['signal' => 'flexibility_score_with_commercial', 'value' => $flex];
        }

        return [!empty($signals), $signals];
    }

    /**
     * Investment-Oriented:
     *   - financial_score >= 70 AND flexibility_score >= 55, OR
     *   - tag financing:seller-financed, OR
     *   - tag financing:assumable
     */
    private function evalInvestmentOriented(PropertyDnaProfile $profile, array $tagSignals): array
    {
        $signals = [];

        $financial = $profile->financial_score !== null ? (float) $profile->financial_score : null;
        $flex      = $profile->flexibility_score !== null ? (float) $profile->flexibility_score : null;

        if ($financial !== null && $flex !== null
            && $financial >= self::INVESTMENT_FINANCIAL_MIN
            && $flex >= self::INVESTMENT_FLEX_MIN
        ) {
            $signals[] = ['signal' => 'financial_score', 'value' => $financial];
            $signals[] = ['signal' => 'flexibility_score', 'value' => $flex];
        }

        if ($tagSignals['has_seller_finance']) {
            $signals[] = ['signal' => 'tag_seller_financed', 'value' => true];
        }

        if ($tagSignals['has_assumable']) {
            $signals[] = ['signal' => 'tag_assumable', 'value' => true];
        }

        return [!empty($signals), $signals];
    }

    /**
     * Flexible Opportunity:
     *   - flexibility_score >= 60, OR
     *   - tag structure:lease-option, OR
     *   - tag structure:lease-purchase
     */
    private function evalFlexibleOpportunity(PropertyDnaProfile $profile, array $tagSignals): array
    {
        $signals = [];

        $flex = $profile->flexibility_score !== null ? (float) $profile->flexibility_score : null;
        if ($flex !== null && $flex >= self::FLEX_OPP_MIN) {
            $signals[] = ['signal' => 'flexibility_score', 'value' => $flex];
        }

        if ($tagSignals['has_lease_option']) {
            $signals[] = ['signal' => 'tag_lease_option', 'value' => true];
        }

        if ($tagSignals['has_lease_purchase']) {
            $signals[] = ['signal' => 'tag_lease_purchase', 'value' => true];
        }

        return [!empty($signals), $signals];
    }

    /**
     * Traditional Residential: fallback when some classifiable data is present.
     *   - overall_dna_completeness >= 20, OR
     *   - physical_score is non-null, OR
     *   - location_score is non-null
     */
    private function evalTraditionalResidential(PropertyDnaProfile $profile): array
    {
        $completeness = $profile->overall_dna_completeness !== null
            ? (float) $profile->overall_dna_completeness
            : null;

        if ($completeness !== null && $completeness >= self::TRADITIONAL_COMPLETENESS_MIN) {
            return [true, [['signal' => 'overall_dna_completeness', 'value' => $completeness]]];
        }

        if ($profile->physical_score !== null) {
            return [true, [['signal' => 'physical_score', 'value' => (float) $profile->physical_score]]];
        }

        if ($profile->location_score !== null) {
            return [true, [['signal' => 'location_score', 'value' => (float) $profile->location_score]]];
        }

        return [false, []];
    }

    // -------------------------------------------------------------------------
    // missing_inputs derivation
    // -------------------------------------------------------------------------

    /**
     * Report consulted DNA dimensions that are null, zero, or effectively empty.
     *
     * Score fields are treated as missing when they are null OR exactly zero
     * (zero indicates the dimension was never populated, not a meaningful score).
     * ai_buyer_archetype_tags is treated as missing when null or an empty array.
     * ai_marketing_hooks is treated as missing when null or an empty array.
     * walk_score is treated as missing when null.
     *
     * Each entry: ['dimension' => string]
     */
    private function deriveMissingInputs(PropertyDnaProfile $profile): array
    {
        $missing = [];

        foreach (self::CONSULTED_SCORE_FIELDS as $field) {
            $value = $profile->{$field};
            if ($value === null || (float) $value === 0.0) {
                $missing[] = ['dimension' => $field];
            }
        }

        $tags = $profile->ai_buyer_archetype_tags;
        if ($tags === null || (is_array($tags) && count($tags) === 0)) {
            $missing[] = ['dimension' => 'ai_buyer_archetype_tags'];
        }

        $hooks = $profile->ai_marketing_hooks;
        if ($hooks === null || (is_array($hooks) && count($hooks) === 0)) {
            $missing[] = ['dimension' => 'ai_marketing_hooks'];
        }

        if ($profile->walk_score === null) {
            $missing[] = ['dimension' => 'walk_score'];
        }

        return $missing;
    }
}
