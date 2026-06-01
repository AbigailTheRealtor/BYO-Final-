<?php

namespace App\Services\LocationDna;

use App\Models\PropertyLocationDna;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * LocationDnaLifestyleScoreService — Phase 2 Lifestyle Intelligence Layer
 *
 * GOVERNANCE BLOCK:
 * ==================================================================================
 * This service converts raw thematic distance blocks from summary_json into five
 * deterministic lifestyle scores (0–100), a set of lifestyle category labels, and a
 * plain-English narrative. All computation is deterministic — no AI, no external
 * API calls.
 *
 * This service MUST NEVER:
 *   - Make any external API calls of any kind.
 *   - Import or invoke OpenAI, Ask AI, or any AI pipeline class.
 *   - Import or invoke PropertyDnaGenerator, PropertyPersonalityService, or any
 *     Property DNA service.
 *   - Import or invoke LocationDnaMarketingContextService or any marketing
 *     intelligence pipeline class.
 *   - Write to marketing_reports, dna_marketing_outputs, or property_dna_profiles.
 *   - Touch LocationDnaIntelligenceContextService (read-only Phase H layer).
 *   - Touch BuyerTenantDnaProfile or any Buyer/Tenant Avatar system.
 *   - Touch ListingCompatibilityScore or any Compatibility system.
 *   - Introduce routes, controllers, Blade views, Livewire components, or JavaScript.
 * ==================================================================================
 */
class LocationDnaLifestyleScoreService
{
    /**
     * Version tag embedded in every lifestyle_json payload so future scoring
     * algorithm changes can be identified at the record level.
     */
    private const VERSION = 'LDNA_LIFESTYLE_V1';

    /**
     * Distance-to-score tiers (miles → points).
     * Applied by scoreFromDistance() for each thematic field.
     */
    private const DISTANCE_TIERS = [
        [0.5,  100],
        [1.0,  85],
        [2.0,  70],
        [5.0,  50],
        [10.0, 30],
    ];

    /**
     * Score below any distance tier (>= 10 miles, present but far).
     */
    private const SCORE_FAR = 10;

    /**
     * Score for null/absent distance values.
     */
    private const SCORE_ABSENT = 0;

    /**
     * Minimum score to assign each lifestyle category label.
     */
    private const CATEGORY_THRESHOLDS = [
        'coastal_score'     => ['Beach Lovers' => 70, 'Boaters' => 70],
        'family_score'      => ['Families' => 60],
        'commuter_score'    => ['Commuters' => 60],
        'walkability_score' => ['Remote Workers' => 70],
        'convenience_score' => ['Convenience Seekers' => 60],
    ];

    /**
     * Outdoor Enthusiasts: awarded when the outdoor recreation sub-score is ≥ 60.
     */
    private const OUTDOOR_ENTHUSIASTS_THRESHOLD = 60;

    /**
     * Retirees: coastal moderate (≥ 40) AND family moderate (≥ 40).
     */
    private const RETIREES_COASTAL_MIN = 40;
    private const RETIREES_FAMILY_MIN  = 40;

    public function __construct(
        private readonly ?LocationDnaAuditService $auditService = null,
    ) {}

    /**
     * Generate lifestyle scores, categories, and narrative for the given listing.
     *
     * Returns the approved seven-key output contract in all cases:
     * [
     *     'success'             => bool,         // true only when status === 'completed'
     *     'status'              => string,        // 'completed' | 'skipped' | 'failed'
     *     'listing_type'        => string,        // echoed from $listingType
     *     'listing_id'          => int,           // echoed from $listingId
     *     'lifestyle_scores'    => array|null,    // populated on success, null otherwise
     *     'lifestyle_categories' => array|null,   // populated on success, null otherwise
     *     'error'               => string|null,   // skip/failure reason, null on success
     * ]
     *
     * Guard conditions (return 'skipped'):
     *   (a) No PropertyLocationDna record exists for the listing.
     *   (b) PropertyLocationDna.geocode_status is not 'geocoded'.
     *   (c) summary_json is null or empty.
     *
     * Uses DB::table() (not Eloquent) for the initial guard read per the
     * postgres-gate-resolver memory note. Eloquent is used only for the final
     * ->save() on the already-loaded model.
     *
     * On any unexpected Throwable, returns 'failed' without re-throwing.
     *
     * @param  string $listingType  The listing model type (e.g. 'seller_agent_auction').
     * @param  int    $listingId    The primary key of the listing record.
     * @return array                Approved seven-key output contract.
     */
    public function generateForListing(string $listingType, int $listingId): array
    {
        try {
            // (a) Guard: DNA record must exist — use DB::table() per postgres-gate-resolver note
            $raw = DB::table('property_location_dna')
                ->where('listing_type', $listingType)
                ->where('listing_id', $listingId)
                ->select(['id', 'geocode_status', 'summary_json'])
                ->first();

            if ($raw === null) {
                $output = $this->skippedOutput(
                    $listingType,
                    $listingId,
                    'No PropertyLocationDna record found for this listing',
                );
                $this->audit($listingType, $listingId, $output);
                return $output;
            }

            // (b) Guard: DNA record must be geocoded
            if ($raw->geocode_status !== 'geocoded') {
                $output = $this->skippedOutput(
                    $listingType,
                    $listingId,
                    "PropertyLocationDna geocode_status is '{$raw->geocode_status}', expected 'geocoded'",
                );
                $this->audit($listingType, $listingId, $output);
                return $output;
            }

            // (c) Guard: summary_json must be populated
            $summaryRaw = $raw->summary_json;
            if ($summaryRaw === null || $summaryRaw === '' || $summaryRaw === '[]') {
                $output = $this->skippedOutput(
                    $listingType,
                    $listingId,
                    'PropertyLocationDna summary_json is null or empty — run LocationDnaSummaryService first',
                );
                $this->audit($listingType, $listingId, $output);
                return $output;
            }

            $summary = is_string($summaryRaw) ? json_decode($summaryRaw, true) : (array) $summaryRaw;

            if (empty($summary)) {
                $output = $this->skippedOutput(
                    $listingType,
                    $listingId,
                    'PropertyLocationDna summary_json decoded to an empty array',
                );
                $this->audit($listingType, $listingId, $output);
                return $output;
            }

            // Compute the five scores
            $scores = $this->computeScores($summary);

            // Derive lifestyle categories from scores
            $categories = $this->deriveCategories($scores, $summary);

            // Build deterministic narrative
            $narrative = $this->buildNarrative($scores, $categories);

            // Assemble the full lifestyle payload
            $lifestylePayload = array_merge(
                ['version' => self::VERSION],
                $scores,
                [
                    'lifestyle_categories' => $categories,
                    'location_narrative'   => $narrative,
                ],
            );

            // Persist using Eloquent on the already-identified record (safe: guard read already done)
            $dnaRecord = PropertyLocationDna::find($raw->id);
            $dnaRecord->lifestyle_json = $lifestylePayload;
            $dnaRecord->save();

            $output = $this->completedOutput($listingType, $listingId, $scores, $categories);
            $this->audit($listingType, $listingId, $output);
            return $output;

        } catch (Throwable $e) {
            $output = $this->failedOutput($listingType, $listingId, $e->getMessage());
            $this->audit($listingType, $listingId, $output);
            return $output;
        }
    }

    // =========================================================================
    // Score computation
    // =========================================================================

    /**
     * Compute the five integer lifestyle scores (0–100) from thematic blocks.
     *
     * Each score is a weighted average of one or more thematic distance fields.
     * Null/absent fields score 0. All scores are cast to int.
     *
     * @param  array $summary  Decoded summary_json array.
     * @return array           ['coastal_score', 'walkability_score', 'convenience_score',
     *                          'commuter_score', 'family_score']
     */
    private function computeScores(array $summary): array
    {
        $coastal  = $summary['coastal']            ?? [];
        $conv     = $summary['daily_convenience']  ?? [];
        $outdoor  = $summary['outdoor_recreation'] ?? [];
        $transit  = $summary['transportation']     ?? [];

        // coastal_score: average of beach and marina distances
        $coastalScore = $this->weightedAvg([
            [$this->scoreFromDistance($coastal['nearest_beach_miles']  ?? null), 1.0],
            [$this->scoreFromDistance($coastal['nearest_marina_miles'] ?? null), 0.5],
        ]);

        // walkability_score: convenience POIs within walking range
        $walkabilityScore = $this->weightedAvg([
            [$this->scoreFromDistance($conv['nearest_grocery_miles']    ?? null), 1.0],
            [$this->scoreFromDistance($conv['nearest_restaurant_miles'] ?? null), 0.8],
            [$this->scoreFromDistance($conv['nearest_coffee_miles']     ?? null), 0.7],
            [$this->scoreFromDistance($conv['nearest_pharmacy_miles']   ?? null), 0.5],
        ]);

        // convenience_score: daily essentials proximity
        $convenienceScore = $this->weightedAvg([
            [$this->scoreFromDistance($conv['nearest_grocery_miles']  ?? null), 1.0],
            [$this->scoreFromDistance($conv['nearest_pharmacy_miles'] ?? null), 0.8],
            [$this->scoreFromDistance($conv['nearest_coffee_miles']   ?? null), 0.5],
        ]);

        // commuter_score: transit and gas station access
        $commuterScore = $this->weightedAvg([
            [$this->scoreFromDistance($transit['nearest_transit_miles']     ?? null), 1.0],
            [$this->scoreFromDistance($transit['nearest_gas_station_miles'] ?? null), 0.6],
        ]);

        // family_score: parks, outdoor recreation, and conveniences
        $familyScore = $this->weightedAvg([
            [$this->scoreFromDistance($outdoor['nearest_park_miles']            ?? null), 1.0],
            [$this->scoreFromDistance($outdoor['nearest_dog_park_miles']        ?? null), 0.6],
            [$this->scoreFromDistance($outdoor['nearest_waterfront_park_miles'] ?? null), 0.5],
            [$this->scoreFromDistance($conv['nearest_grocery_miles']            ?? null), 0.7],
        ]);

        return [
            'coastal_score'      => (int) round($coastalScore),
            'walkability_score'  => (int) round($walkabilityScore),
            'convenience_score'  => (int) round($convenienceScore),
            'commuter_score'     => (int) round($commuterScore),
            'family_score'       => (int) round($familyScore),
        ];
    }

    /**
     * Convert a distance in miles to a 0–100 score using tiered thresholds.
     * Null/absent distance → SCORE_ABSENT (0).
     */
    private function scoreFromDistance(?float $miles): int
    {
        if ($miles === null) {
            return self::SCORE_ABSENT;
        }

        foreach (self::DISTANCE_TIERS as [$threshold, $points]) {
            if ($miles < $threshold) {
                return $points;
            }
        }

        return self::SCORE_FAR;
    }

    /**
     * Compute a weighted average of [score, weight] pairs.
     * Returns 0.0 when total weight is zero.
     *
     * @param  array $pairs  Array of [score (int), weight (float)] pairs.
     * @return float
     */
    private function weightedAvg(array $pairs): float
    {
        $totalWeight    = 0.0;
        $weightedTotal  = 0.0;

        foreach ($pairs as [$score, $weight]) {
            $totalWeight   += $weight;
            $weightedTotal += $score * $weight;
        }

        if ($totalWeight === 0.0) {
            return 0.0;
        }

        return $weightedTotal / $totalWeight;
    }

    // =========================================================================
    // Category derivation
    // =========================================================================

    /**
     * Derive the lifestyle category labels from the five computed scores.
     *
     * Possible values: 'Beach Lovers', 'Boaters', 'Families', 'Retirees',
     *                  'Remote Workers', 'Commuters', 'Outdoor Enthusiasts',
     *                  'Convenience Seekers'
     *
     * @param  array $scores   The five-score array from computeScores().
     * @param  array $summary  Decoded summary_json (for outdoor sub-score).
     * @return array           Unique, sorted list of category label strings.
     */
    private function deriveCategories(array $scores, array $summary): array
    {
        $categories = [];

        // Score-threshold-driven categories
        foreach (self::CATEGORY_THRESHOLDS as $scoreKey => $labelThresholds) {
            foreach ($labelThresholds as $label => $threshold) {
                if (($scores[$scoreKey] ?? 0) >= $threshold) {
                    $categories[] = $label;
                }
            }
        }

        // Outdoor Enthusiasts: outdoor recreation sub-score ≥ threshold
        $outdoor = $summary['outdoor_recreation'] ?? [];
        $outdoorSubScore = $this->weightedAvg([
            [$this->scoreFromDistance($outdoor['nearest_park_miles']            ?? null), 1.0],
            [$this->scoreFromDistance($outdoor['nearest_dog_park_miles']        ?? null), 0.8],
            [$this->scoreFromDistance($outdoor['nearest_waterfront_park_miles'] ?? null), 0.6],
            [$this->scoreFromDistance($outdoor['nearest_golf_course_miles']     ?? null), 0.4],
        ]);
        if ((int) round($outdoorSubScore) >= self::OUTDOOR_ENTHUSIASTS_THRESHOLD) {
            $categories[] = 'Outdoor Enthusiasts';
        }

        // Retirees: coastal moderate AND family moderate
        if (
            ($scores['coastal_score'] ?? 0) >= self::RETIREES_COASTAL_MIN &&
            ($scores['family_score']  ?? 0) >= self::RETIREES_FAMILY_MIN
        ) {
            $categories[] = 'Retirees';
        }

        $categories = array_values(array_unique($categories));
        sort($categories);
        return $categories;
    }

    // =========================================================================
    // Narrative builder
    // =========================================================================

    /**
     * Build a deterministic plain-English narrative from the score profile.
     * No AI, no external calls — pure string composition from score data.
     *
     * @param  array $scores      The five-score array from computeScores().
     * @param  array $categories  The derived lifestyle category labels.
     * @return string             A non-empty narrative string.
     */
    private function buildNarrative(array $scores, array $categories): string
    {
        $phrases = [];

        if (($scores['coastal_score'] ?? 0) >= 70) {
            $phrases[] = 'exceptional coastal access with beaches and waterways nearby';
        } elseif (($scores['coastal_score'] ?? 0) >= 40) {
            $phrases[] = 'reasonable proximity to coastal amenities';
        }

        if (($scores['convenience_score'] ?? 0) >= 70) {
            $phrases[] = 'excellent daily conveniences including groceries and pharmacies within easy reach';
        } elseif (($scores['convenience_score'] ?? 0) >= 40) {
            $phrases[] = 'good access to everyday conveniences';
        }

        if (($scores['walkability_score'] ?? 0) >= 70) {
            $phrases[] = 'a highly walkable environment with dining, shopping, and services close by';
        } elseif (($scores['walkability_score'] ?? 0) >= 40) {
            $phrases[] = 'moderate walkability with key services accessible on foot';
        }

        if (($scores['commuter_score'] ?? 0) >= 60) {
            $phrases[] = 'strong commuter infrastructure including transit and fuel access';
        }

        if (($scores['family_score'] ?? 0) >= 60) {
            $phrases[] = 'family-friendly surroundings with parks and recreational options nearby';
        } elseif (($scores['family_score'] ?? 0) >= 40) {
            $phrases[] = 'outdoor spaces suitable for families and active lifestyles';
        }

        if (in_array('Outdoor Enthusiasts', $categories, true)) {
            $phrases[] = 'proximity to recreational amenities appealing to outdoor enthusiasts';
        }

        if (empty($phrases)) {
            return 'This location offers a variety of nearby amenities to suit diverse lifestyle needs.';
        }

        if (count($phrases) === 1) {
            return 'This location offers ' . $phrases[0] . '.';
        }

        $last    = array_pop($phrases);
        $joined  = implode(', ', $phrases);
        return 'This location offers ' . $joined . ', and ' . $last . '.';
    }

    // =========================================================================
    // Audit integration
    // =========================================================================

    /**
     * Write an audit row. Wrapped in its own try/catch so an audit failure
     * cannot prevent the caller's return value from being delivered.
     */
    private function audit(string $listingType, int $listingId, array $output): void
    {
        try {
            $auditService = $this->auditService ?? new LocationDnaAuditService();
            $auditService->record(
                listingType:    $listingType,
                listingId:      $listingId,
                eventType:      'lifestyle_scores_generated',
                status:         $output['status'],
                source:         null,
                inputSnapshot:  ['listing_type' => $listingType, 'listing_id' => $listingId],
                outputSnapshot: $output,
                error:          $output['error'] ?? null,
            );
        } catch (Throwable) {
            // Audit failure must never alter the service's return value.
        }
    }

    // =========================================================================
    // Output shape helpers — approved seven-key contract in all cases
    // =========================================================================

    private function completedOutput(
        string $listingType,
        int    $listingId,
        array  $scores,
        array  $categories,
    ): array {
        return [
            'success'              => true,
            'status'               => 'completed',
            'listing_type'         => $listingType,
            'listing_id'           => $listingId,
            'lifestyle_scores'     => $scores,
            'lifestyle_categories' => $categories,
            'error'                => null,
        ];
    }

    private function skippedOutput(string $listingType, int $listingId, string $error): array
    {
        return [
            'success'              => false,
            'status'               => 'skipped',
            'listing_type'         => $listingType,
            'listing_id'           => $listingId,
            'lifestyle_scores'     => null,
            'lifestyle_categories' => null,
            'error'                => $error,
        ];
    }

    private function failedOutput(string $listingType, int $listingId, ?string $error): array
    {
        return [
            'success'              => false,
            'status'               => 'failed',
            'listing_type'         => $listingType,
            'listing_id'           => $listingId,
            'lifestyle_scores'     => null,
            'lifestyle_categories' => null,
            'error'                => $error,
        ];
    }
}
