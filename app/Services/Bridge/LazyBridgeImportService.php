<?php

namespace App\Services\Bridge;

use App\Jobs\ComputeLocationDna;
use App\Models\BridgeCriteriaFetchCache;
use App\Models\BridgeProperty;
use App\Services\Bridge\OData\BuyerCriteriaODataFilterBuilder;
use App\Services\Bridge\OData\CriteriaODataFilterBuilderInterface;
use App\Services\Bridge\OData\TenantCriteriaODataFilterBuilder;
use App\Services\Stellar\Matching\DTO\BuyerCriteriaPayload;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LazyBridgeImportService
{
    private const SUPPORTED_ROLES = ['buyer', 'tenant'];

    /**
     * @param  array<string, CriteriaODataFilterBuilderInterface>  $builders  Role → builder map.
     *         Defaults to the production pair; injectable for testing.
     */
    public function __construct(
        private readonly CriteriaHashService $hasher,
        private readonly BridgeApiService $api,
        private readonly BridgePropertyNormalizer $normalizer,
        private readonly array $builders = [],
    ) {}

    /**
     * Import Bridge properties for the given criteria, respecting the fetch cache.
     *
     * The correct OData filter builder is resolved internally from the role string —
     * 'buyer' uses BuyerCriteriaODataFilterBuilder, 'tenant' uses
     * TenantCriteriaODataFilterBuilder. Passing an unsupported role throws immediately.
     *
     * Concurrency: a PostgreSQL session-level advisory lock keyed by the criteria hash
     * serialises concurrent imports for the same criteria set. Only the lock-winner calls
     * the Bridge API; other processes wait and then benefit from the cache row the winner
     * wrote (double-checked locking pattern). Lock acquisition failure is fail-open —
     * if the lock cannot be acquired (e.g., DB connectivity issue), the import proceeds
     * without synchronisation, preserving the original behaviour.
     *
     * - Cache hit  (expires_at in the future) → returns LazyImportResult::cached()  with no API call.
     * - Cache miss → paginates the Bridge API, upserts records, writes cache row.
     * - API error  → logs a warning and returns LazyImportResult::failed().
     *
     * Pagination stops when BRIDGE_LAZY_MAX_PAGES pages or BRIDGE_LAZY_MAX_RECORDS
     * records have been processed, whichever comes first.
     *
     * ComputeLocationDna is dispatched only for new records or records whose
     * unparsed_address or postal_code changed since the last import.
     *
     * @throws \InvalidArgumentException  For unsupported role values.
     */
    public function importForCriteria(
        BuyerCriteriaPayload $payload,
        string $role,
    ): LazyImportResult {
        $role = strtolower(trim($role));

        $filterBuilder = $this->resolveBuilder($role);

        $hash = $this->hasher->hash($payload, $role);

        // -----------------------------------------------------------------------
        // Fast-path: check cache BEFORE acquiring the advisory lock.
        // On a warm cache (the common case after the first import for a criteria set)
        // this exits immediately with zero lock overhead.
        // -----------------------------------------------------------------------
        $cacheRow = BridgeCriteriaFetchCache::where('criteria_hash', $hash)->first();

        if ($cacheRow && $cacheRow->expires_at && $cacheRow->expires_at->isFuture()) {
            Log::info("LazyBridgeImportService: Cache hit for hash {$hash} (role={$role}). Skipping API call.");
            return LazyImportResult::cached(count: (int) $cacheRow->record_count, hash: $hash);
        }

        // -----------------------------------------------------------------------
        // Acquire a PostgreSQL session-level advisory lock keyed by criteria hash.
        // Serialises concurrent imports so only one process calls Bridge per criteria
        // set. Fail-open: if lock acquisition fails, we proceed without locking
        // (preserves original single-process behaviour and handles non-PG drivers).
        // -----------------------------------------------------------------------
        $lockKey      = $this->hashToLockKey($hash);
        $lockAcquired = $this->acquireAdvisoryLock($lockKey, $hash, $role);

        try {
            // -------------------------------------------------------------------
            // Double-check: another process may have completed the import while
            // we were waiting for the lock. If the cache is now warm, return
            // immediately without calling the Bridge API.
            // -------------------------------------------------------------------
            if ($lockAcquired) {
                $cacheRow = BridgeCriteriaFetchCache::where('criteria_hash', $hash)->first();
                if ($cacheRow && $cacheRow->expires_at && $cacheRow->expires_at->isFuture()) {
                    Log::info(
                        "LazyBridgeImportService: Cache warm after lock wait for hash {$hash} (role={$role}). "
                        . 'Skipping API call.'
                    );
                    return LazyImportResult::cached(count: (int) $cacheRow->record_count, hash: $hash);
                }
            }

            $filter     = $filterBuilder->build($payload);
            $maxPages   = (int) config('bridge.lazy_max_pages', 20);
            $maxRecords = (int) config('bridge.lazy_max_records', 500);
            $pageSize   = (int) config('bridge.lazy_page_size', 200);

            $skip          = 0;
            $page          = 0;
            $totalImported = 0;
            $capReached    = false;

            try {
                while (true) {
                    $page++;

                    if ($page > $maxPages) {
                        Log::warning(
                            "LazyBridgeImportService: BRIDGE_LAZY_MAX_PAGES ({$maxPages}) reached for hash {$hash}. "
                            . 'Stopping pagination and upserting partial results.'
                        );
                        $capReached = true;
                        break;
                    }

                    $records = $this->api->fetchPropertiesPaginated($pageSize, $skip, $filter);

                    if (empty($records)) {
                        break;
                    }

                    foreach ($records as $record) {
                        if ($totalImported >= $maxRecords) {
                            Log::warning(
                                "LazyBridgeImportService: BRIDGE_LAZY_MAX_RECORDS ({$maxRecords}) reached for hash {$hash}. "
                                . 'Stopping pagination and upserting partial results.'
                            );
                            $capReached = true;
                            break 2;
                        }

                        $upsertResult = $this->normalizer->upsert($record);
                        if ($upsertResult === null) {
                            continue;
                        }

                        // Dispatch DNA only for new records or address/coordinate changes.
                        if ($upsertResult->shouldDispatchDna()) {
                            ComputeLocationDna::dispatch('bridge', $upsertResult->model->id);
                            Log::info('LazyBridgeImportService: dispatched ComputeLocationDna', [
                                'bridge_property_id' => $upsertResult->model->id,
                                'listing_key'        => $upsertResult->model->listing_key,
                                'reason'             => $upsertResult->isNew ? 'new_record' : 'address_changed',
                                'hash'               => $hash,
                                'role'               => $role,
                            ]);
                        }

                        $totalImported++;
                    }

                    if (count($records) < $pageSize) {
                        break;
                    }

                    $skip += $pageSize;
                }
            } catch (\Throwable $e) {
                Log::warning(
                    'LazyBridgeImportService: API call failed — ' . $e->getMessage(),
                    ['hash' => $hash, 'role' => $role, 'page' => $page]
                );
                return LazyImportResult::failed(hash: $hash);
            }

            if ($capReached) {
                $partialTtl = (int) config('bridge.lazy_partial_ttl_minutes', 5);
                if ($partialTtl > 0) {
                    $expiresAt = now()->addMinutes($partialTtl);
                    BridgeCriteriaFetchCache::updateOrCreate(
                        ['criteria_hash' => $hash],
                        [
                            'role'            => $role,
                            'last_fetched_at' => now(),
                            'record_count'    => $totalImported,
                            'expires_at'      => $expiresAt,
                        ]
                    );
                    Log::info(
                        "LazyBridgeImportService: Partial import — {$totalImported} record(s) upserted "
                        . "(cap reached). Cache written with shortened TTL of {$partialTtl} min for hash {$hash}."
                    );
                } else {
                    Log::info(
                        "LazyBridgeImportService: Partial import — {$totalImported} record(s) upserted "
                        . "(cap reached). Partial caching disabled (BRIDGE_LAZY_PARTIAL_TTL_MINUTES=0); "
                        . "no cache written for hash {$hash}."
                    );
                }
            } else {
                $ttl       = (int) config('bridge.lazy_ttl_minutes', 60);
                $expiresAt = now()->addMinutes($ttl);
                BridgeCriteriaFetchCache::updateOrCreate(
                    ['criteria_hash' => $hash],
                    [
                        'role'            => $role,
                        'last_fetched_at' => now(),
                        'record_count'    => $totalImported,
                        'expires_at'      => $expiresAt,
                    ]
                );
                Log::info(
                    "LazyBridgeImportService: Import complete — {$totalImported} record(s) upserted. "
                    . "Cache written for hash {$hash}."
                );
            }

            return LazyImportResult::fetched($totalImported, wasPartial: $capReached, hash: $hash);

        } finally {
            if ($lockAcquired) {
                $this->releaseAdvisoryLock($lockKey);
            }
        }
    }

    // =========================================================================
    // Advisory locking — protected so subclasses can override in tests
    // =========================================================================

    /**
     * Acquire a PostgreSQL session-level advisory lock keyed by $lockKey.
     *
     * Blocks until the lock is available (i.e., all other processes holding the
     * same key have released it). Returns true on success.
     *
     * Returns false and logs a warning on failure (e.g., the DB driver is not
     * PostgreSQL, or a connectivity issue occurs). The caller MUST NOT call
     * releaseAdvisoryLock() when this returns false.
     */
    protected function acquireAdvisoryLock(int $lockKey, string $hash, string $role): bool
    {
        try {
            DB::select('SELECT pg_advisory_lock(?)', [$lockKey]);
            return true;
        } catch (\Throwable $e) {
            Log::warning(
                'LazyBridgeImportService: Advisory lock acquisition failed; proceeding without lock.',
                ['hash' => $hash, 'role' => $role, 'error' => $e->getMessage()]
            );
            return false;
        }
    }

    /**
     * Release the session-level advisory lock acquired by acquireAdvisoryLock().
     *
     * Safe to call even if the lock is no longer held — pg_advisory_unlock() returns
     * false (not an error) in that case. Any exception is swallowed since the lock
     * auto-releases when the DB connection is returned to the pool.
     */
    protected function releaseAdvisoryLock(int $lockKey): void
    {
        try {
            DB::select('SELECT pg_advisory_unlock(?)', [$lockKey]);
        } catch (\Throwable) {
            // Swallow — the lock releases automatically when the connection closes.
        }
    }

    /**
     * Derive a signed int64 lock key from the first 8 bytes of a SHA-256 hex string.
     *
     * Compatible with PostgreSQL's pg_advisory_lock(bigint) parameter type.
     * The mapping is deterministic and collision-resistant given SHA-256's distribution.
     *
     * On 64-bit PHP, unpack('J') returns the unsigned value as a signed int (wrapping
     * past PHP_INT_MAX to a negative integer), which is exactly the bigint range
     * PostgreSQL expects — the same bit-pattern, different interpretation.
     */
    protected function hashToLockKey(string $hash): int
    {
        return unpack('J', hex2bin(substr($hash, 0, 16)))[1];
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Resolve the OData filter builder for the given role.
     *
     * Uses the injected $builders map when provided (for testing), otherwise
     * instantiates the canonical production builders.
     *
     * @throws \InvalidArgumentException  For unsupported role values.
     */
    private function resolveBuilder(string $role): CriteriaODataFilterBuilderInterface
    {
        if (!in_array($role, self::SUPPORTED_ROLES, true)) {
            throw new \InvalidArgumentException(
                "LazyBridgeImportService: unsupported role '{$role}'. Supported: "
                . implode(', ', self::SUPPORTED_ROLES) . '.'
            );
        }

        if (isset($this->builders[$role])) {
            return $this->builders[$role];
        }

        return match ($role) {
            'buyer'  => new BuyerCriteriaODataFilterBuilder(),
            'tenant' => new TenantCriteriaODataFilterBuilder(),
        };
    }
}
