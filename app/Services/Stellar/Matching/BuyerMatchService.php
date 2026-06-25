<?php

namespace App\Services\Stellar\Matching;

use App\Models\BridgeProperty;
use App\Services\Bridge\LazyBridgeImportService;
use App\Services\Bridge\LazyImportResult;
use App\Services\Stellar\Matching\DTO\BuyerCriteriaPayload;
use App\Services\Stellar\Matching\DTO\BuyerMatchResult;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class BuyerMatchService
{
    private ?LazyImportResult $lastImportResult = null;

    public function __construct(
        private BuyerMatchQueryBuilder  $queryBuilder,
        private BuyerMatchScorer        $scorer,
        private BuyerMatchResultBuilder $resultBuilder,
        private LazyBridgeImportService $lazyImport,
    ) {}

    /**
     * Run the full buyer matching pipeline.
     *
     * Before querying the local bridge_properties cache, triggers a lazy Bridge API
     * import so the local data is fresh for the given criteria. If the import fails
     * (API down, token missing, etc.) a warning is logged and the match proceeds
     * against existing local data — search results are never blocked by an API outage.
     *
     * @param  BuyerCriteriaPayload $criteria
     * @param  int                  $candidateCap Target maximum records passed to the scorer.
     *                                            The query builder over-fetches by IDX_OVERFETCH_MULTIPLIER
     *                                            so that after IDX filtering we still have ~candidateCap records.
     * @param  string               $role         'buyer' or 'tenant' — passed to LazyBridgeImportService
     *                                            to select the correct OData filter builder.
     * @return Collection<BuyerMatchResult>       Sorted by total_score DESC.
     */
    public function match(BuyerCriteriaPayload $criteria, int $candidateCap = 200, string $role = 'buyer'): Collection
    {
        // Reset last import result at the start of each call.
        $this->lastImportResult = null;

        // Pre-match lazy import: ensure local bridge_properties cache is fresh.
        $importResult = $this->lazyImport->importForCriteria($criteria, $role);
        $this->lastImportResult = $importResult;

        if ($importResult->isFailed()) {
            Log::warning('LazyBridgeImport failed; proceeding with local cache', [
                'role'           => $role,
                'criteria_hash'  => $importResult->criteriaHash,
                'status'         => $importResult->status,
                'property_types' => $criteria->propertyTypes,
            ]);
        }

        // Layer 1: SQL query using native columns only.
        // The query builder applies a ×1.25 fetch buffer so IDX removals
        // don't shrink the scoring pool below candidateCap.
        $query      = $this->queryBuilder->build($criteria, $candidateCap);
        $candidates = $query->get();

        // IDX gate: PHP post-filter — exclude listings where IDXParticipationYN = false.
        // Applied before the scorer so scoring overhead is only paid for eligible records.
        // Uses filter_var to safely handle boolean strings ("true"/"false") that the
        // Bridge API may return alongside native PHP booleans.
        $candidates = $candidates->filter(function (BridgeProperty $listing) {
            $data  = $listing->raw_json ? json_decode($listing->raw_json, true) : [];
            if (!is_array($data)) {
                return true; // malformed json → default to IDX-eligible (fail open)
            }

            if (!array_key_exists('IDXParticipationYN', $data)) {
                return true; // absent key → default to eligible per spec
            }

            $raw = $data['IDXParticipationYN'];

            // Normalize booleans, integer 0/1, and string "true"/"false" safely.
            if (is_bool($raw)) {
                return $raw;
            }

            $normalized = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            // filter_var returns null when it cannot parse the value; default to eligible.
            return $normalized !== false;
        });

        // Trim to candidateCap after IDX filtering so the scorer never receives
        // more records than requested, while still benefiting from the over-fetch buffer.
        $candidates = $candidates->take($candidateCap);

        // Layer 2: Score each candidate
        $results = $this->scorer->scoreAll($candidates, $criteria);

        // Layer 3: Assemble explanation blocks
        $results = $this->resultBuilder->buildAll($results, $criteria);

        // Sort by total_score descending
        usort($results, fn(BuyerMatchResult $a, BuyerMatchResult $b) => $b->totalScore <=> $a->totalScore);

        return collect($results);
    }

    /**
     * Return the LazyImportResult from the most recent match() call.
     * Returns null if match() has not yet been called on this instance.
     */
    public function getLastImportResult(): ?LazyImportResult
    {
        return $this->lastImportResult;
    }
}
