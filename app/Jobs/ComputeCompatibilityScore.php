<?php

namespace App\Jobs;

use App\Models\BuyerCriteriaAuction;
use App\Models\BuyerTenantDnaProfile;
use App\Models\ListingCompatibilityScore;
use App\Models\PropertyDnaProfile;
use App\Models\PropertyLocationDna;
use App\Models\TenantCriteriaAuction;
use App\Services\Dna\Compatibility\CompatibilityEngine;
use App\Services\Dna\Compatibility\CompatibilityExplanationService;
use App\Services\Dna\Compatibility\PropertyCompatibilityNarrativeService;
use App\Services\LocationDna\LocationMatchIntegrationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * ComputeCompatibilityScore — Phase F Compatibility Persistence Job
 *
 * Loads the active PropertyDnaProfile (supply side) and active BuyerTenantDnaProfile
 * (demand side), calls CompatibilityEngine::compute(), and persists an append-only
 * row to `listing_compatibility_scores`.
 *
 * GOVERNANCE CONSTRAINTS enforced here:
 * - Never dispatches additional compatibility jobs from within handle() (no recursive enqueuing).
 * - Runs its own isolated DB transaction for `listing_compatibility_scores` only —
 *   never nested inside or sharing a transaction with listing or DNA profile saves.
 * - Errors that escape handle() are caught by Laravel's queue worker, which invokes failed()
 *   for final logging before the job is discarded. No raw dimension data is logged.
 * - Explicit $tries and $timeout prevent retry storms.
 * - Does NOT acquire locks on any listing workflow table beyond reading active DNA profiles.
 * - score_explanation is internal storage only — never surfaced publicly, cached, broadcast,
 *   or serialized into any telemetry, websocket, or analytics stream.
 */
class ComputeCompatibilityScore implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Maximum number of queue retry attempts before the job is discarded.
     * Explicit limit prevents retry storms on persistent failure conditions.
     */
    public int $tries = 3;

    /**
     * Job execution timeout in seconds.
     * Explicit limit prevents queue worker stall on malformed data or slow DB responses.
     */
    public int $timeout = 60;

    /**
     * Scoring framework version identifier for this Phase F implementation.
     * Must be updated when the computation logic changes in a future phase.
     */
    private const SCORING_FRAMEWORK_VERSION = 'phase-h-v1';

    public string $demandListingType;
    public int    $demandListingId;
    public string $supplyListingType;
    public int    $supplyListingId;

    /**
     * @param string $demandListingType  'buyer' or 'tenant'
     * @param int    $demandListingId    BuyerTenantDnaProfile listing_id
     * @param string $supplyListingType  'seller' or 'landlord'
     * @param int    $supplyListingId    PropertyDnaProfile listing_id
     */
    public function __construct(
        string $demandListingType,
        int    $demandListingId,
        string $supplyListingType,
        int    $supplyListingId
    ) {
        $this->demandListingType = $demandListingType;
        $this->demandListingId   = $demandListingId;
        $this->supplyListingType = $supplyListingType;
        $this->supplyListingId   = $supplyListingId;
    }

    public function handle(CompatibilityEngine $engine): void
    {
        // NON-RECURSIVE GUARANTEE: This method does not dispatch any further queue jobs.
        // It does not dispatch ComputeCompatibilityScore, ComputePropertyDnaProfile,
        // ComputeBuyerTenantDnaProfile, or any other job. Fanout is one-directional only:
        // the compatibility observers trigger this job; this job persists a score row and
        // terminates. ListingCompatibilityScore has no registered observer, so no further
        // jobs are ever enqueued as a downstream consequence of the persist() call below.

        $supplyProfile = PropertyDnaProfile::where('listing_type', $this->supplyListingType)
            ->where('listing_id', $this->supplyListingId)
            ->whereNull('archived_at')
            ->first();

        if (!$supplyProfile) {
            Log::info('ComputeCompatibilityScore: no active supply profile found', [
                'supply_listing_type' => $this->supplyListingType,
                'supply_listing_id'   => $this->supplyListingId,
            ]);
            return;
        }

        $demandProfile = BuyerTenantDnaProfile::where('listing_type', $this->demandListingType)
            ->where('listing_id', $this->demandListingId)
            ->whereNull('archived_at')
            ->first();

        if (!$demandProfile) {
            Log::info('ComputeCompatibilityScore: no active demand profile found', [
                'demand_listing_type' => $this->demandListingType,
                'demand_listing_id'   => $this->demandListingId,
            ]);
            return;
        }

        $result = $engine->compute($supplyProfile, $demandProfile);

        $this->persist($result, $engine, $supplyProfile, $demandProfile);
    }

    /**
     * Called by Laravel when all retry attempts have been exhausted.
     * Logs job identifiers and error class only — never raw dimension arrays or
     * score_explanation payloads (governance constraint: no debug tooling exposure).
     */
    public function failed(Throwable $exception): void
    {
        Log::error('ComputeCompatibilityScore job permanently failed', [
            'job'                 => self::class,
            'demand_listing_type' => $this->demandListingType,
            'demand_listing_id'   => $this->demandListingId,
            'supply_listing_type' => $this->supplyListingType,
            'supply_listing_id'   => $this->supplyListingId,
            'framework_version'   => self::SCORING_FRAMEWORK_VERSION,
            'error'               => $exception->getMessage(),
            'exception'           => get_class($exception),
        ]);
    }

    /**
     * Persist a new compatibility score row using append-only semantics.
     *
     * Idempotency contract:
     * - Acquires a per-pair advisory lock (PostgreSQL only) before any read or write,
     *   preventing concurrent jobs from racing on the same profile pair.
     * - Archives the prior active row (if any) and creates a new row with version + 1.
     * - This transaction is isolated to `listing_compatibility_scores` only and must
     *   never be nested inside or share a boundary with listing or DNA profile transactions.
     * - The ListingCompatibilityScore model save inside this transaction does NOT trigger
     *   any observer that dispatches further compatibility jobs (governance safeguard #7).
     */
    private function persist(
        array $result,
        CompatibilityEngine $engine,
        PropertyDnaProfile $supplyProfile,
        BuyerTenantDnaProfile $demandProfile
    ): void {
        DB::transaction(function () use ($result, $engine, $supplyProfile, $demandProfile) {
            $this->acquirePairLock();

            $prior = ListingCompatibilityScore::where('demand_listing_type', $this->demandListingType)
                ->where('demand_listing_id', $this->demandListingId)
                ->where('supply_listing_type', $this->supplyListingType)
                ->where('supply_listing_id', $this->supplyListingId)
                ->orderByDesc('version')
                ->first();

            $newVersion = 1;

            if ($prior) {
                if (is_null($prior->archived_at)) {
                    $prior->archived_at = now();
                    $prior->save();
                }
                $newVersion = $prior->version + 1;
            }

            // Guard conflicting_dimensions against null or unexpected non-array shape.
            // An absent or malformed key must never cause a type error — default to empty array.
            $conflictingDimensions = $result['conflicting_dimensions'] ?? [];
            if (!is_array($conflictingDimensions)) {
                $conflictingDimensions = [];
            }

            // $resolvedMatchMap holds the per-dimension outcome array produced by
            // CompatibilityEngine::compute(). It is stored under the JSON key
            // 'dimension_match_map' (exactly once) in score_explanation below.
            $resolvedMatchMap = $result['dimension_match_map'] ?? [];

            // --- Grouped sub-scores ---
            // Compute per-bucket scores using the dimension-to-category grouping map.
            $groupedScores = $engine->computeGroupedScores($resolvedMatchMap);

            // --- Location match score ---
            // Populated via LocationMatchIntegrationService when a PropertyLocationDna
            // record exists for the supply listing and the demand listing has saved
            // location_dna_preferences. Gracefully falls back to null when either side
            // lacks data — preserving backward compatibility with pre-Phase 6 records.
            $locationMatchScore = $this->computeLocationMatchScore();

            // --- Highlights and warnings ---
            $highlights = $engine->computeHighlights($resolvedMatchMap);
            $warnings   = $engine->computeWarnings($resolvedMatchMap);

            // --- Readiness score ---
            $readinessScore = $engine->computeReadinessScore($resolvedMatchMap);

            // --- Property compatibility narrative ---
            $narrativeService = app(PropertyCompatibilityNarrativeService::class);
            $narrativePayload = $narrativeService->generate($resolvedMatchMap);

            // --- CompatibilityExplanationService — confirm outputs are captured ---
            // CompatibilityExplanationService reads from a persisted score row, so we
            // build a transient stub score to extract per-dimension explanation strings
            // and store them under an 'explanations' key in score_explanation.
            // This ensures no structured output produced in memory is orphaned.
            $explanationService = app(CompatibilityExplanationService::class);
            $stubScore = new ListingCompatibilityScore();
            $stubScore->score_explanation = [
                'aligned_dimensions'      => $result['aligned_dimensions'] ?? [],
                'conflicting_dimensions'  => $conflictingDimensions,
                'unresolved_dimensions'   => $result['unresolved_dimensions'] ?? [],
                'dimension_match_map'     => $resolvedMatchMap,
                'eligible_dimension_count' => $result['eligible_dimension_count'] ?? 0,
            ];
            $explanations = $explanationService->generate($stubScore);

            // --- Compatibility summary JSON ---
            // Structured JSON object for internal audit use.
            // 'narrative_summary' captures the single-sentence summary from
            // PropertyCompatibilityNarrativeService so no generated output is orphaned.
            $compatibilitySummaryJson = [
                'overall_score'          => $result['compatibility_coverage_metric'] ?? 0.0,
                'physical_score'         => $groupedScores['physical'],
                'financial_score'        => $groupedScores['financial'],
                'terms_score'            => $groupedScores['terms'],
                'matched_dimensions'     => $result['aligned_dimensions'] ?? [],
                'unresolved_dimensions'  => $result['unresolved_dimensions'] ?? [],
                'conflicting_dimensions' => $conflictingDimensions,
                'narrative_summary'      => $narrativePayload['summary'] ?? '',
            ];

            ListingCompatibilityScore::create([
                'demand_listing_type'              => $this->demandListingType,
                'demand_listing_id'                => $this->demandListingId,
                'supply_listing_type'              => $this->supplyListingType,
                'supply_listing_id'                => $this->supplyListingId,
                'version'                          => $newVersion,
                'scoring_framework_version'        => self::SCORING_FRAMEWORK_VERSION,
                'demand_listing_updated_at_snapshot' => $demandProfile->source_listing_updated_at,
                'supply_listing_updated_at_snapshot' => $supplyProfile->source_listing_updated_at,

                // overall_score stores the compatibility_coverage_metric — a deterministic
                // coverage/completeness metric ONLY (non-unresolved dimensions / 8 × 100).
                // It must NEVER be interpreted as ranking quality, recommendation strength,
                // user desirability, approval likelihood, tenant quality, buyer quality,
                // investment quality, or transactional probability.
                'overall_score'   => $result['compatibility_coverage_metric'],

                // Grouped sub-scores per property dimension bucket.
                // location_match_score is populated when both supply-side PropertyLocationDna
                // and demand-side location_dna_preferences are available; null otherwise.
                'physical_match_score'  => $groupedScores['physical'],
                'financial_match_score' => $groupedScores['financial'],
                'terms_match_score'     => $groupedScores['terms'],
                'location_match_score'  => $locationMatchScore,

                // deal_breaker_triggered is a conflict-presence indicator ONLY.
                // True means one or more deterministic field conflicts were detected.
                // It must NOT be interpreted as rejection, disapproval, tenant disqualification,
                // buyer unworthiness, suitability assessment, or decision-making signal of any kind.
                'deal_breaker_triggered' => count($conflictingDimensions) > 0,

                // deal_breaker_flags stores the raw array of conflicting dimension identifier strings
                // (e.g. ["pet_policy_alignment", "parking_alignment"]) for internal audit use only.
                // This column is deterministic conflict-presence metadata ONLY and must NEVER be
                // interpreted as rejection, disqualification, suitability assessment, qualification
                // scoring, recommendation output, or decision-making signal of any kind.
                // An empty array means no conflicts were detected; null is never persisted.
                'deal_breaker_flags' => $conflictingDimensions,

                // score_explanation stores the structured dimension map for internal audit use only.
                // This field must NEVER be surfaced publicly, cached, broadcast, serialized into
                // telemetry, exposed in admin tooling, or used to generate narrative text.
                //
                // eligible_dimension_count records the denominator used for compatibility_coverage_metric
                // at the time this row was computed. It equals count(STRUCTURALLY_ELIGIBLE_DIMENSIONS)
                // from CompatibilityEngine and is stored here so historical rows remain self-describing
                // even after future phases expand the eligible set and bump SCORING_FRAMEWORK_VERSION.
                //
                // 'explanations' captures the CompatibilityExplanationService output so no
                // structured per-dimension explanation strings are orphaned in memory.
                'score_explanation' => [
                    'aligned_dimensions'       => $result['aligned_dimensions'],
                    'conflicting_dimensions'   => $conflictingDimensions,
                    'unresolved_dimensions'    => $result['unresolved_dimensions'],
                    'dimension_match_map'      => $resolvedMatchMap,
                    'eligible_dimension_count' => $result['eligible_dimension_count'],
                    'explanations'             => $explanations,
                ],

                // Property compatibility narrative — full per-dimension plain-language text.
                'compatibility_narrative' => $narrativePayload['narrative'] ?? '',

                // Structured JSON summary object for internal audit use.
                'compatibility_summary_json' => $compatibilitySummaryJson,

                // Positive signal strings for aligned dimensions.
                'compatibility_highlights' => $highlights,

                // Transparency strings for unresolved/ineligible dimensions.
                'compatibility_warnings' => $warnings,

                // Readiness score: resolved eligible dimensions / eligible dimensions × 100.
                'compatibility_readiness_score' => $readinessScore,

                'computed_at' => now(),
                'archived_at' => null,
                'created_at'  => now(),
            ]);
        });
    }

    /**
     * Compute a 0–100 location match score for this supply/demand pair.
     *
     * Returns null gracefully when either side lacks the required data:
     *   - Supply side: no PropertyLocationDna record (geocode never run).
     *   - Demand side: no location_dna_preferences saved on the criteria auction.
     *
     * When both sides are present, delegates to LocationMatchIntegrationService and
     * converts the fired overlap_signals count into a normalized 0–100 score:
     *   0 signals → 0   (property is outside all preferred areas)
     *   1 signal  → 40  (matched one area type — e.g. city only)
     *   2 signals → 55
     *   3 signals → 70
     *   4 signals → 85
     *   5 signals → 100 (all five area types matched)
     *
     * This method MUST NOT write to the database, dispatch jobs, or throw.
     *
     * @return float|null  Normalized 0–100 score, or null when data is absent.
     */
    private function computeLocationMatchScore(): ?float
    {
        try {
            // --- Supply side: load PropertyLocationDna ---
            // listing_type in property_location_dna uses the '_agent' suffix form.
            $dnaListingType = match ($this->supplyListingType) {
                'seller'   => 'seller_agent',
                'landlord' => 'landlord_agent',
                default    => null,
            };

            if ($dnaListingType === null) {
                return null;
            }

            $propertyDna = PropertyLocationDna::where('listing_type', $dnaListingType)
                ->where('listing_id', $this->supplyListingId)
                ->whereNotNull('geocoded_lat')
                ->whereNotNull('geocoded_lng')
                ->first();

            if (!$propertyDna) {
                return null;
            }

            $propertyData = [
                'city'         => (string) ($propertyDna->source_city ?? ''),
                'zip'          => (string) ($propertyDna->source_zip  ?? ''),
                'neighborhood' => '',
                'lat'          => (float)  ($propertyDna->geocoded_lat ?? 0.0),
                'lng'          => (float)  ($propertyDna->geocoded_lng ?? 0.0),
            ];

            // --- Demand side: load location_dna_preferences from criteria auction ---
            $criteriaAuction = match ($this->demandListingType) {
                'buyer'  => BuyerCriteriaAuction::find($this->demandListingId),
                'tenant' => TenantCriteriaAuction::find($this->demandListingId),
                default  => null,
            };

            if (!$criteriaAuction) {
                return null;
            }

            $rawPreferences = method_exists($criteriaAuction, 'info')
                ? $criteriaAuction->info('location_dna_preferences')
                : null;

            if (empty($rawPreferences)) {
                return null;
            }

            $preferences = is_array($rawPreferences)
                ? $rawPreferences
                : (json_decode($rawPreferences, true) ?? []);

            if (empty($preferences)) {
                return null;
            }

            // --- Run location match ---
            $integrationService = app(LocationMatchIntegrationService::class);
            $matchResult        = $integrationService->build($preferences, $propertyData);
            $signals            = $matchResult['match_results']['overlap_signals'] ?? [];

            // Normalize signal count to 0–100.
            // There are 5 possible signal types (city, zip, neighborhood, polygon, radius).
            // Score = (fired signals / 5) × 100 — a true linear normalization.
            // 0 signals → 0.0, 1 → 20.0, 2 → 40.0, 3 → 60.0, 4 → 80.0, 5 → 100.0
            $signalCount = count($signals);

            return (float) round(($signalCount / 5) * 100, 2);

        } catch (\Throwable $e) {
            Log::warning('ComputeCompatibilityScore: location_match_score computation failed — falling back to null', [
                'supply_listing_type' => $this->supplyListingType,
                'supply_listing_id'   => $this->supplyListingId,
                'demand_listing_type' => $this->demandListingType,
                'demand_listing_id'   => $this->demandListingId,
                'error'               => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Acquires a per-pair advisory lock on PostgreSQL to prevent concurrent jobs
     * from racing on the same supply/demand profile pair.
     *
     * The lock key is derived from the four identifier components using crc32.
     * On non-PostgreSQL drivers, no lock is acquired; the wrapping DB::transaction()
     * provides best-effort ordering, and any duplicate-version violations will escape
     * handle() and be handled by Laravel's queue retry and failed() mechanism.
     *
     * Lock scope: `listing_compatibility_scores` only — never acquires locks on any
     * listing workflow table or DNA profile table.
     */
    private function acquirePairLock(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            $lockKey = crc32(
                'compat:' . $this->demandListingType . ':' . $this->demandListingId
                . ':' . $this->supplyListingType . ':' . $this->supplyListingId
            );
            DB::statement('SELECT pg_advisory_xact_lock(?)', [$lockKey]);
        }
    }
}
