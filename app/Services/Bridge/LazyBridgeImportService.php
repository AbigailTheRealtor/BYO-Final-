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
    /**
     * Roles this importer will run for.
     *
     * 'buyer' and 'tenant' are the criteria-driven searches this class was built
     * for. 'explore_sale' and 'explore_rent' are BidYourOffer Explore's viewport
     * discovery, added here rather than in a second importer: Explore needs the
     * same pagination, the same advisory lock, the same fetch cache, the same
     * normalizer and the same DNA dispatch rules, and a parallel implementation
     * of all five is exactly the second MLS ingestion system that must not exist.
     * The role string only selects a filter builder and namespaces the cache key.
     */
    private const SUPPORTED_ROLES = ['buyer', 'tenant', 'explore_sale', 'explore_rent'];

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
     * Two optional controls, both inert at their defaults, so every existing
     * caller behaves exactly as before:
     *
     * - $beforeProviderRequest is asked immediately before EACH page is sent,
     *   and returns null to allow it or a reason to refuse it. A refusal stops
     *   pagination before that page goes out: the rows already upserted stay,
     *   no fetch-cache row is written (a truncated pass must not later be served
     *   as a warm, complete tile), and the result is LazyImportResult::refused().
     *   This is how a budgeted caller makes its ceiling hard at the request
     *   boundary rather than checking once and charging afterwards.
     * - $dispatchDna = false upserts without dispatching ComputeLocationDna,
     *   mirroring the option BridgeListingLookupService already has. For a
     *   caller that does not use Location DNA and must not start its provider
     *   work (Google Places, through the POI step) as a side effect.
     *
     * @param  (callable(): ?string)|null  $beforeProviderRequest
     * @throws \InvalidArgumentException  For unsupported role values.
     */
    public function importForCriteria(
        BuyerCriteriaPayload $payload,
        string $role,
        ?int $maxPagesOverride = null,
        ?int $maxRecordsOverride = null,
        ?callable $beforeProviderRequest = null,
        bool $dispatchDna = true,
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
            // The caller may lower — never raise — the pagination ceilings.
            //
            // A criteria search runs from a results page the user is waiting on
            // and can afford the full envelope. An Explore viewport request runs
            // while somebody is moving a camera, so it bounds itself tighter. A
            // caller that asked for MORE than the configured ceiling would be
            // raising a global spend limit from a call site, so the override is
            // clamped downwards and a non-positive value is ignored entirely.
            $maxPages   = (int) config('bridge.lazy_max_pages', 20);
            $maxRecords = (int) config('bridge.lazy_max_records', 500);

            if ($maxPagesOverride !== null && $maxPagesOverride > 0) {
                $maxPages = min($maxPages, $maxPagesOverride);
            }

            if ($maxRecordsOverride !== null && $maxRecordsOverride > 0) {
                $maxRecords = min($maxRecords, $maxRecordsOverride);
            }
            $pageSize   = (int) config('bridge.lazy_page_size', 200);

            $skip          = 0;
            $page          = 0;
            $totalImported = 0;
            $capReached    = false;

            // Provider pages actually dispatched this cycle. Incremented BEFORE
            // each call, so a page that throws is still counted: it reached the
            // provider and consumed its capacity whatever came back. Reported
            // on the result so a budget-aware caller (Explore) charges for real
            // outbound traffic rather than for successful outbound traffic.
            $pagesAttempted = 0;

            // Set when the caller's admission check refuses a page. That page is
            // then NOT sent, and the cycle ends as refused rather than fetched.
            $refusedReason = null;

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

                    // Admission, per request: asked immediately before the page
                    // is sent, so a refused page is never sent — the difference
                    // between a ceiling and a report that one was exceeded.
                    if ($beforeProviderRequest !== null) {
                        $refusal = $beforeProviderRequest();

                        if ($refusal !== null) {
                            $refusedReason = (string) $refusal;
                            break;
                        }
                    }

                    $pagesAttempted++;

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

                        // Dispatch DNA for a new record, an address/coordinate
                        // change, or a row that has never had DNA requested for
                        // its current address (one Explore imported first, say) —
                        // and only for a caller that has not opted out.
                        if ($dispatchDna && BridgeLocationDnaState::shouldDispatch($upsertResult)) {
                            ComputeLocationDna::dispatch('bridge', $upsertResult->model->id);
                            Log::info('LazyBridgeImportService: dispatched ComputeLocationDna', [
                                'bridge_property_id' => $upsertResult->model->id,
                                'listing_key'        => $upsertResult->model->listing_key,
                                'reason'             => $upsertResult->isNew
                                    ? 'new_record'
                                    : ($upsertResult->addressChanged ? 'address_changed' : 'location_dna_missing'),
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
                return LazyImportResult::failed(hash: $hash, pagesAttempted: $pagesAttempted);
            }

            if ($refusedReason !== null) {
                // No fetch-cache row. A pass its caller stopped part-way saw only
                // part of the tile, and a cache row would serve that part as a
                // warm, complete answer until it expired.
                Log::info(
                    "LazyBridgeImportService: provider request refused by caller ({$refusedReason}) after "
                    . "{$pagesAttempted} page(s) for hash {$hash}; {$totalImported} record(s) upserted, no cache written."
                );

                return LazyImportResult::refused(
                    count: $totalImported,
                    hash: $hash,
                    pagesAttempted: $pagesAttempted,
                    reason: $refusedReason,
                );
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

            return LazyImportResult::fetched(
                $totalImported,
                wasPartial: $capReached,
                hash: $hash,
                pagesAttempted: $pagesAttempted,
            );

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

            // BOTH Explore roles use the BUYER builder, and that is deliberate.
            //
            // Its name is historical: it emits `StandardStatus eq 'Active'`, a
            // PropertyType disjunction and a lat/lng bounding box, and knows
            // nothing about purchasing. Explore supplies the rental PropertyType
            // strings for explore_rent, so the same builder produces the rental
            // filter correctly.
            //
            // The tenant builder is NOT used, and not because either would do.
            // Its documented rental PropertyType vocabulary includes
            // 'Residential', which in this dataset is a SALE type — a defect
            // reported separately and owned by the tenant-search work. Explore
            // must not inherit it, and must not fix it here either.
            'explore_sale', 'explore_rent' => new BuyerCriteriaODataFilterBuilder(),
        };
    }
}
