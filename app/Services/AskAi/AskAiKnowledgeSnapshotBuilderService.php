<?php

namespace App\Services\AskAi;

use App\Models\AskAiKnowledgeSnapshot;
use App\Services\AskAi\Snapshot\BuyerSnapshotBuilder;
use App\Services\AskAi\Snapshot\LandlordSnapshotBuilder;
use App\Services\AskAi\Snapshot\SellerSnapshotBuilder;
use App\Services\AskAi\Snapshot\TenantSnapshotBuilder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * AskAiKnowledgeSnapshotBuilderService — Phase 2 Snapshot Orchestrator
 *
 * GOVERNANCE BLOCK:
 * ==================================================================================
 * ROLE: Orchestrates the creation of versioned knowledge snapshots for Ask AI.
 * Accepts a listing_type and listing_id, resolves the appropriate role builder,
 * and persists a complete snapshot (facts, questions, answers) into the four
 * snapshot tables.
 *
 * This service MUST NEVER:
 *   - Call any external LLM, language model, or external HTTP service.
 *   - Modify existing Ask AI runtime routing or response logic.
 *   - Interrupt or affect any listing save operation (exceptions are caught and
 *     persisted as status=failed; listing saves are never affected).
 * ==================================================================================
 *
 * Guard: Snapshot builds are idempotent — each call creates a new incremented
 * version. Prior snapshot versions are historical records and are not deleted.
 *
 * Concurrency guard: A unique index on (listing_type, listing_id, version) prevents
 * duplicate version rows. On unique constraint violation, build() retries up to
 * MAX_VERSION_RETRIES times, each time re-reading the current max version. This
 * ensures safe sequential versioning under concurrent simultaneous builds.
 */
class AskAiKnowledgeSnapshotBuilderService
{
    private const ROLE_MODELS = [
        'seller'   => \App\Models\SellerAgentAuction::class,
        'buyer'    => \App\Models\BuyerAgentAuction::class,
        'landlord' => \App\Models\LandlordAgentAuction::class,
        'tenant'   => \App\Models\TenantAgentAuction::class,
    ];

    private const TYPE_ALIASES = [
        'seller'                  => 'seller',
        'seller_agent_auction'    => 'seller',
        'property_auction'        => 'seller',
        'buyer'                   => 'buyer',
        'buyer_agent_auction'     => 'buyer',
        'buyer_criteria_auction'  => 'buyer',
        'landlord'                => 'landlord',
        'landlord_agent_auction'  => 'landlord',
        'landlord_auction'        => 'landlord',
        'tenant'                  => 'tenant',
        'tenant_agent_auction'    => 'tenant',
        'tenant_criteria_auction' => 'tenant',
    ];

    private const MAX_VERSION_RETRIES = 5;

    public function __construct(
        private SellerSnapshotBuilder   $sellerBuilder,
        private BuyerSnapshotBuilder    $buyerBuilder,
        private LandlordSnapshotBuilder $landlordBuilder,
        private TenantSnapshotBuilder   $tenantBuilder,
    ) {}

    /**
     * Build and persist a versioned knowledge snapshot for the given listing.
     *
     * Returns the newly created snapshot record on success, or a snapshot record
     * with status=failed on any exception. Never rethrows — listing saves are
     * never interrupted.
     *
     * Idempotent: each call increments the version for the given listing.
     *
     * Concurrency: protected by a unique index on (listing_type, listing_id, version).
     * On unique constraint collision, the build is retried (up to MAX_VERSION_RETRIES)
     * with a fresh max-version read each time, ensuring no duplicate versions are
     * persisted even under simultaneous concurrent calls.
     *
     * @param  string  $listingType  Canonical or aliased listing type string.
     * @param  int     $listingId    Primary key of the listing record.
     * @return AskAiKnowledgeSnapshot
     */
    public function build(string $listingType, int $listingId): AskAiKnowledgeSnapshot
    {
        $canonical    = self::TYPE_ALIASES[strtolower($listingType)] ?? $listingType;
        $lastException = null;

        for ($attempt = 1; $attempt <= self::MAX_VERSION_RETRIES; $attempt++) {
            try {
                $snapshot = DB::transaction(function () use ($canonical, $listingId) {
                    $maxVersion = AskAiKnowledgeSnapshot::where('listing_type', $canonical)
                        ->where('listing_id', $listingId)
                        ->orderByDesc('version')
                        ->value('version') ?? 0;

                    $snapshot = AskAiKnowledgeSnapshot::create([
                        'listing_type'      => $canonical,
                        'listing_id'        => $listingId,
                        'version'           => $maxVersion + 1,
                        'status'            => 'building',
                        'built_at'          => null,
                        'snapshot_uuid'     => (string) Str::uuid(),
                        'source_model'      => self::ROLE_MODELS[$canonical] ?? null,
                        'source_updated_at' => $this->resolveSourceUpdatedAt($canonical, $listingId),
                        'facts_count'       => 0,
                        'questions_count'   => 0,
                        'answers_count'     => 0,
                    ]);

                    $this->resolveBuilder($canonical)->build($snapshot, $listingId);

                    $snapshot->update([
                        'status'          => 'ready',
                        'built_at'        => now(),
                        'facts_count'     => $snapshot->facts()->count(),
                        'questions_count' => $snapshot->questions()->count(),
                        'answers_count'   => $snapshot->answers()->count(),
                    ]);

                    return $snapshot;
                });

                return $snapshot;

            } catch (QueryException $e) {
                if ($this->isUniqueConstraintViolation($e)) {
                    $lastException = $e;
                    continue; // Retry: another concurrent build took this version slot.
                }
                return $this->persistFailure($canonical, $listingId, $e->getMessage());
            } catch (\Throwable $e) {
                return $this->persistFailure($canonical, $listingId, $e->getMessage());
            }
        }

        // All retries exhausted due to persistent unique constraint collisions.
        return $this->persistFailure(
            $canonical,
            $listingId,
            'Version conflict: max retries (' . self::MAX_VERSION_RETRIES . ') exceeded. ' . ($lastException?->getMessage() ?? '')
        );
    }

    /**
     * Silently build a snapshot — catches all exceptions so the calling
     * listing-save method is never interrupted.
     */
    public function buildSilently(string $listingType, int $listingId): void
    {
        try {
            $this->build($listingType, $listingId);
        } catch (\Throwable) {
        }
    }

    private function resolveBuilder(string $canonical): SellerSnapshotBuilder|BuyerSnapshotBuilder|LandlordSnapshotBuilder|TenantSnapshotBuilder
    {
        return match ($canonical) {
            'seller'   => $this->sellerBuilder,
            'buyer'    => $this->buyerBuilder,
            'landlord' => $this->landlordBuilder,
            'tenant'   => $this->tenantBuilder,
            default    => throw new \InvalidArgumentException("Unknown canonical listing type: {$canonical}"),
        };
    }

    /**
     * Returns true when a QueryException is a unique constraint violation
     * (PostgreSQL SQLSTATE 23505, MySQL 1062, or SQLite UNIQUE constraint failed).
     */
    private function isUniqueConstraintViolation(QueryException $e): bool
    {
        $msg = $e->getMessage();
        return str_contains($msg, '23505')                  // PostgreSQL
            || str_contains($msg, 'unique constraint')       // PostgreSQL (text form)
            || str_contains($msg, 'Duplicate entry')         // MySQL
            || str_contains($msg, 'UNIQUE constraint failed'); // SQLite
    }

    private function persistFailure(string $canonical, int $listingId, string $errorMessage): AskAiKnowledgeSnapshot
    {
        try {
            $maxVersion = AskAiKnowledgeSnapshot::where('listing_type', $canonical)
                ->where('listing_id', $listingId)
                ->orderByDesc('version')
                ->value('version') ?? 0;

            return AskAiKnowledgeSnapshot::create([
                'listing_type'      => $canonical,
                'listing_id'        => $listingId,
                'version'           => $maxVersion + 1,
                'status'            => 'failed',
                'error_message'     => $errorMessage,
                'built_at'          => null,
                'snapshot_uuid'     => (string) Str::uuid(),
                'source_model'      => self::ROLE_MODELS[$canonical] ?? null,
                'source_updated_at' => null,
                'facts_count'       => 0,
                'questions_count'   => 0,
                'answers_count'     => 0,
            ]);
        } catch (\Throwable) {
            return new AskAiKnowledgeSnapshot([
                'listing_type'   => $canonical,
                'listing_id'     => $listingId,
                'version'        => 0,
                'status'         => 'failed',
                'error_message'  => $errorMessage,
                'snapshot_uuid'  => (string) Str::uuid(),
                'facts_count'    => 0,
                'questions_count' => 0,
                'answers_count'  => 0,
            ]);
        }
    }

    /**
     * Looks up the listing record's updated_at timestamp so it can be stored
     * as source_updated_at on the snapshot, enabling stale-detection queries.
     * Returns null when the listing cannot be loaded (e.g. unknown canonical type,
     * missing table, or non-existent listing ID — all treated as non-fatal).
     */
    private function resolveSourceUpdatedAt(string $canonical, int $listingId): ?Carbon
    {
        $modelClass = self::ROLE_MODELS[$canonical] ?? null;
        if ($modelClass === null) {
            return null;
        }
        try {
            $val = $modelClass::where('id', $listingId)->value('updated_at');
            return $val ? Carbon::parse($val) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
