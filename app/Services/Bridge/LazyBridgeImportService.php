<?php

namespace App\Services\Bridge;

use App\Jobs\ComputeLocationDna;
use App\Models\BridgeCriteriaFetchCache;
use App\Models\BridgeProperty;
use App\Services\Bridge\OData\BuyerCriteriaODataFilterBuilder;
use App\Services\Bridge\OData\CriteriaODataFilterBuilderInterface;
use App\Services\Bridge\OData\TenantCriteriaODataFilterBuilder;
use App\Services\Stellar\Matching\DTO\BuyerCriteriaPayload;
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

        $cacheRow = BridgeCriteriaFetchCache::where('criteria_hash', $hash)->first();

        if ($cacheRow && $cacheRow->expires_at && $cacheRow->expires_at->isFuture()) {
            Log::info("LazyBridgeImportService: Cache hit for hash {$hash} (role={$role}). Skipping API call.");
            return LazyImportResult::cached((int) $cacheRow->record_count);
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
            return LazyImportResult::failed();
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

        return LazyImportResult::fetched($totalImported, wasPartial: $capReached);
    }

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
