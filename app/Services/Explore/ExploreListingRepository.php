<?php

namespace App\Services\Explore;

use App\Models\BridgeProperty;
use Illuminate\Support\Collection;

/**
 * The bounded viewport read. The ONLY place Explore touches MLS storage.
 *
 * NO SECOND SYNC SYSTEM
 * ---------------------
 * This reads `bridge_properties` and nothing else — it issues no provider
 * request, opens no import, writes no row and schedules no job. Making that
 * table CURRENT is somebody else's job, and that somebody is the application's
 * one existing MLS ingestion pipeline: {@see ExploreInventoryService} hands a
 * viewport to {@see \App\Services\Bridge\LazyBridgeImportService}, which is
 * the same lock, fetch cache, pagination, normalizer and Location DNA dispatch
 * the criteria searches use. Explore contributes no client, no importer and no
 * storage.
 *
 * CONFIRMATION, NOT JUST PRESENCE
 * -------------------------------
 * A row existing locally is not evidence that the listing is still on the
 * market. When a discovery pass has COMPLETELY covered this viewport, every
 * currently-eligible listing in it was just upserted — so a row the pass did not
 * touch is one the provider no longer returns: sold, withdrawn, gone from IDX
 * participation, or removed from the feed. Those rows are withheld, via
 * {@see ExploreFreshness} and the `imported_at` stamp every upsert already
 * writes. Nothing is deleted; deleting MLS data on the strength of an absence is
 * a much larger decision than declining to render it.
 *
 * The rule is applied ONLY on a complete pass. After a partial one — a
 * pagination ceiling reached — an absent listing means "we stopped asking", and
 * withholding on that reading would hide real, current inventory. With discovery
 * disabled or the provider unreachable, it is not applied either, and the
 * response says which.
 *
 * OVERFETCH, THEN FILTER, THEN SLICE — AND WHY IT IS NOT A BARE SQL LIMIT
 * ----------------------------------------------------------------------
 * Eligibility depends on `raw_json`: the feed's display permissions and a
 * rental's lease frequency exist only there, and neither is a column. So the
 * final filter runs in PHP, and a bare `LIMIT 150` would hand PHP 150 rows of
 * which an unknown number are ineligible — silently returning 140 markers for a
 * viewport that has 150 eligible ones, with no way to tell that from a
 * neighbourhood that genuinely has 140.
 *
 * So the SQL reads a multiple of the requested page (`explore.viewport.overfetch`),
 * PHP filters, and the result is sliced to the limit. The read is still hard-
 * capped, so a pathological viewport cannot turn into an unbounded scan.
 *
 * WHAT IS FILTERED IN SQL AND WHAT IS NOT
 * ---------------------------------------
 * In SQL, because they are indexed columns and cheap: the bounding box (served
 * by bridge_properties_lat_lng_idx), non-null coordinates, the public status
 * allow-list, and the requested transaction type's PropertyType values. In PHP,
 * because they cannot be expressed as columns: the feed's display permissions.
 *
 * The status and property-type clauses are NOT an optimisation of the policy —
 * {@see ExploreEligibilityPolicy} re-decides both on every row it is handed, so
 * a row that slipped past the query is still excluded. Narrowing here and
 * deciding there means the index is used without the policy being duplicated.
 *
 * DETERMINISTIC ORDER
 * -------------------
 * Ordered by id so two identical requests return the same page. Without it,
 * an unordered LIMIT lets the same viewport return different subsets on
 * consecutive pans, which reads to a consumer as listings flickering in and
 * out of existence.
 */
class ExploreListingRepository
{
    public function __construct(
        private readonly ExploreEligibilityPolicy $policy,
        private readonly ExploreFreshness $freshness,
    ) {}

    /**
     * Eligible listings inside the viewport.
     *
     * @return Collection<int,array{listing:BridgeProperty,raw:array<string,mixed>,type:ExploreTransactionType}>
     */
    public function inViewport(
        ExploreViewport $viewport,
        ExploreAccessTier $tier,
        ?ExploreTransactionType $filter = null,
        ?int $limit = null,
        ?ExploreDiscoveryOutcome $discovery = null,
    ): Collection {
        // Only a pass that saw the whole viewport licenses the inference that an
        // untouched row is no longer current.
        $requireConfirmation = $discovery !== null && $discovery->complete;

        $limit = $this->limit($limit);

        $propertyTypes = $filter !== null
            ? $filter->propertyTypes()
            : ExploreTransactionType::allPropertyTypes();

        if ($propertyTypes === []) {
            return collect();
        }

        $statuses = array_values(array_filter(array_map(
            static fn ($v) => is_string($v) ? trim($v) : '',
            (array) config('explore.public_statuses', ['Active'])
        )));

        if ($statuses === []) {
            // Matches ExploreEligibilityPolicy: an empty allow-list is a config
            // that did not load, and "publish everything" is not a safe reading.
            return collect();
        }

        $rows = BridgeProperty::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereBetween('latitude', [$viewport->south, $viewport->north])
            ->whereBetween('longitude', [$viewport->west, $viewport->east])
            ->whereIn('standard_status', $statuses)
            ->whereIn('property_type', $propertyTypes)
            ->orderBy('id')
            ->limit($this->readCeiling($limit))
            ->get();

        $eligible = collect();

        foreach ($rows as $row) {
            if ($requireConfirmation && ! $this->freshness->isConfirmedCurrent($row)) {
                continue;
            }

            $raw = $this->policy->decodeRaw($row);

            $decision = $this->policy->decide($row, $tier, $viewport, $raw);

            if (! $decision->eligible || $decision->transactionType === null) {
                continue;
            }

            $eligible->push([
                'listing' => $row,
                'raw'     => $raw ?? [],
                'type'    => $decision->transactionType,
            ]);

            if ($eligible->count() >= $limit) {
                break;
            }
        }

        return $eligible;
    }

    /**
     * One eligible listing by MLS ListingKey, for the property panel.
     *
     * Same policy, no viewport. Returns null for anything ineligible, so an
     * ineligible key is indistinguishable from a key that does not exist —
     * which is the correct answer to give somebody probing for withheld
     * listings.
     *
     * @return array{listing:BridgeProperty,raw:array<string,mixed>,type:ExploreTransactionType}|null
     */
    public function findEligible(string $listingKey, ExploreAccessTier $tier): ?array
    {
        $listingKey = trim($listingKey);

        if ($listingKey === '') {
            return null;
        }

        $row = BridgeProperty::query()->where('listing_key', $listingKey)->first();

        if ($row === null) {
            return null;
        }

        $raw      = $this->policy->decodeRaw($row);
        $decision = $this->policy->decide($row, $tier, null, $raw);

        if (! $decision->eligible || $decision->transactionType === null) {
            return null;
        }

        return ['listing' => $row, 'raw' => $raw ?? [], 'type' => $decision->transactionType];
    }

    /** The page size, clamped to the configured ceiling. */
    public function limit(?int $requested): int
    {
        $default = (int) config('explore.viewport.max_results', 150);
        $ceiling = (int) config('explore.viewport.result_ceiling', 250);

        $ceiling = $ceiling > 0 ? $ceiling : 250;
        $default = $default > 0 ? min($default, $ceiling) : min(150, $ceiling);

        if ($requested === null || $requested <= 0) {
            return $default;
        }

        return min($requested, $ceiling);
    }

    /** How many rows the SQL may read before eligibility filtering. */
    private function readCeiling(int $limit): int
    {
        $multiplier = (int) config('explore.viewport.overfetch', 3);
        $multiplier = $multiplier > 0 ? $multiplier : 1;

        return $limit * $multiplier;
    }
}
