<?php

namespace App\Jobs;

use App\Models\BuyerTenantDnaProfile;
use App\Models\ListingCompatibilityScore;
use App\Models\PropertyDnaProfile;
use App\Services\Dna\Compatibility\CompatibilityEngine;
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

        $this->persist($result, $supplyProfile, $demandProfile);
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
    private function persist(array $result, PropertyDnaProfile $supplyProfile, BuyerTenantDnaProfile $demandProfile): void
    {
        DB::transaction(function () use ($result, $supplyProfile, $demandProfile) {
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
                // coverage/completeness metric ONLY (non-unresolved dimensions / 14 × 100).
                // It must NEVER be interpreted as ranking quality, recommendation strength,
                // user desirability, approval likelihood, tenant quality, buyer quality,
                // investment quality, or transactional probability.
                'overall_score'   => $result['compatibility_coverage_metric'],

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
                'score_explanation' => [
                    'aligned_dimensions'       => $result['aligned_dimensions'],
                    'conflicting_dimensions'   => $conflictingDimensions,
                    'unresolved_dimensions'    => $result['unresolved_dimensions'],
                    'dimension_match_map'      => $result['dimension_match_map'],
                    'eligible_dimension_count' => $result['eligible_dimension_count'],
                ],

                'computed_at' => now(),
                'archived_at' => null,
                'created_at'  => now(),
            ]);
        });
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
