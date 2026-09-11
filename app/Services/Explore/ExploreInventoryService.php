<?php

namespace App\Services\Explore;

use App\Models\BridgeProperty;
use App\Services\Bridge\BridgeListingLookupService;
use App\Services\Bridge\LazyBridgeImportService;
use App\Services\Explore\Guards\ExploreProviderBudget;
use App\Services\Explore\Guards\ExploreProviderTelemetry;
use App\Services\Stellar\Matching\DTO\BuyerCriteriaPayload;
use Illuminate\Support\Facades\Log;

/**
 * Explore's viewport discovery — a TRANSLATOR, not an importer.
 *
 * Everything below this class is the application's one existing MLS ingestion
 * architecture: {@see LazyBridgeImportService} holds the advisory lock,
 * consults the fetch cache, paginates through
 * {@see \App\Services\Bridge\BridgeApiService} and upserts through
 * {@see \App\Services\Bridge\BridgePropertyNormalizer}. This class contributes
 * the payload that pipeline already understands — and two restrictions on how
 * Explore may use it.
 *
 * EVERY PROVIDER REQUEST IS ADMITTED BEFORE IT IS SENT
 * ----------------------------------------------------
 * Each page of a pass, and the panel's single lookup, is admitted by
 * {@see ExploreProviderBudget::acquire()} immediately before it goes out, so
 * the configured ceilings are hard maxima. A pass that runs out of budget
 * part-way stops there and reports itself degraded; it never sends the page it
 * was refused.
 *
 * NO LOCATION DNA FROM EXPLORE
 * ----------------------------
 * The importer dispatches ComputeLocationDna for every new or re-addressed row,
 * and that job's POI step can call Google Places. Explore renders no Location
 * DNA, so a discovery pass reaching that path would spend a paid provider on
 * work nobody on this surface sees — up to 500 rows a pass, inline, because the
 * queue runs `sync`. Both Explore entry points therefore opt out
 * (`dispatchDna: false`), through options the importer and the lookup service
 * expose for exactly this. Every other caller keeps the dispatch it had.
 *
 * WHY A BuyerCriteriaPayload AND NOT A NEW FILTER BUILDER
 * ------------------------------------------------------
 * `BuyerCriteriaODataFilterBuilder` already emits exactly the filter Explore
 * needs — `StandardStatus eq 'Active'`, a PropertyType disjunction, and a
 * lat/lng bounding box from `PolygonBoundingBox::fromPayload()`. A payload
 * carrying only `property_types` and one rectangular polygon produces precisely
 * that and nothing else, because every other clause is skipped when its field is
 * null. So Explore writes no OData at all. A bespoke Explore filter builder
 * would have been a second place where "which Stellar records are current"
 * is expressed in a query string.
 *
 * THE TILE GRID IS LOAD-BEARING
 * -----------------------------
 * The fetch cache is keyed on a hash of the payload, so an unsnapped viewport
 * would mint a new cache key on every pixel of pan and the "reuse the existing
 * cache" story would collapse into a provider request per camera nudge. The
 * discovery box is therefore snapped OUTWARDS to a coarse grid: neighbouring
 * viewports share one key, a pan within a tile is free, and the box always
 * contains the viewport it was derived from — so every row the caller may return
 * was covered by the pass.
 *
 * Snapping outwards also pre-warms the area just beyond the screen edge, which
 * is where the user is about to move.
 *
 * ONE PASS PER REQUEST, BOUNDED
 * -----------------------------
 * At most one discovery pass runs per viewport request — never one per marker.
 * The pass is bounded by per-call ceilings lower than the global BRIDGE_LAZY_*
 * envelope, because this runs while somebody is moving a camera rather than on a
 * results page they are waiting for. The importer clamps an override downwards
 * only, so a call site cannot raise a global spend limit.
 *
 * IT SHIPS OFF. `explore.discovery.enabled` defaults false, like every other
 * outbound gate in this repository: deploying this code must not by itself start
 * unattended traffic to a third-party provider.
 */
class ExploreInventoryService
{
    public function __construct(
        private readonly LazyBridgeImportService $importer,
        private readonly BridgeListingLookupService $lookup,
        private readonly ExploreFreshness $freshness,
        private readonly ExploreProviderBudget $budget,
    ) {}

    public function isEnabled(): bool
    {
        return (bool) config('explore.discovery.enabled', false);
    }

    /**
     * Ensure the shared MLS cache holds the CURRENT eligible Stellar listings
     * for this viewport.
     *
     * A null $filter means "both markets", which runs one pass per transaction
     * type — sale and rent are different PropertyType sets and therefore
     * different filters. Two passes, not two hundred.
     */
    public function ensureCurrentFor(
        ExploreViewport $viewport,
        ?ExploreTransactionType $filter = null,
        ?string $actorKey = null,
    ): ExploreDiscoveryOutcome {
        if (! $this->isEnabled()) {
            ExploreProviderTelemetry::record(ExploreProviderTelemetry::OUTCOME_DISABLED);

            return ExploreDiscoveryOutcome::disabled();
        }

        // Fast path: a request whose ceiling is already spent skips the
        // importer entirely — no lock, no cache lookup, no pass.
        //
        // This reads and charges nothing, and it is NOT the ceiling. Every page
        // a pass actually sends is admitted on its own, just before it is sent
        // (see discover()); that is what makes the configured number a hard
        // maximum. A pass can therefore still be stopped part-way — when the
        // last unit goes to somebody else — and combine() reports that as
        // degraded rather than as half a map presented whole.
        $blocked = $this->budget->blockedReason($actorKey);

        if ($blocked !== null) {
            ExploreProviderTelemetry::record(ExploreProviderTelemetry::OUTCOME_BUDGET_BLOCKED, [
                'reason' => $blocked,
                'stage'  => 'before_request',
                'actor'  => $actorKey,
                'spent'  => $this->budget->spent($actorKey),
            ]);

            return ExploreDiscoveryOutcome::budgetLimited($blocked);
        }

        $types = $filter !== null ? [$filter] : ExploreTransactionType::cases();

        $outcomes = [];

        foreach ($types as $type) {
            $outcomes[] = $this->discover($viewport, $type, $actorKey);
        }

        return $this->combine($outcomes);
    }

    /**
     * Bring ONE record up to date before it is published in the property panel.
     *
     * REUSES THE EXISTING SINGLE-RECORD REFRESH.
     * {@see BridgeListingLookupService::refreshByListingKey()} exists precisely
     * for this question: every other lookup on that class is local-first, which
     * is right for prefill and Match Check and exactly wrong here, because the
     * row we would compare against is the row we would be handed back. It sends
     * one `ListingKey eq …` request and upserts through the shared normalizer.
     *
     * WHY THE PANEL AND NOT JUST THE MARKER
     * -------------------------------------
     * A marker carries a price and a status a consumer glances at. The panel is
     * where they read the rent, decide the property is available, and click
     * through. The cost of being one refresh out of date is different in the two
     * places, so the panel pays for a request and the marker does not.
     *
     * Bounded by construction: one request, for one record, on an explicit user
     * action — never on camera movement, and never once per marker. It is
     * skipped entirely when the row was already confirmed inside the shared
     * freshness window, so repeatedly opening the same panel costs nothing.
     *
     * Returns true when a refresh was actually attempted, so the caller knows to
     * re-read the row and re-run eligibility on it.
     */
    public function refreshRecord(BridgeProperty $listing, ?string $actorKey = null): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $listingKey = trim((string) ($listing->listing_key ?? ''));

        if ($listingKey === '') {
            return false;
        }

        if ($this->freshness->isConfirmedCurrent($listing)) {
            return false;
        }

        // The panel spends from the SAME ceilings as discovery, deliberately.
        //
        // It is one request rather than a paginated pass, but it is reachable
        // by opening properties one after another — which is exactly the shape
        // of traversal the actor ceiling exists to bound. A separate allowance
        // for "cheap" calls would be a second budget with its own idea of what
        // a request costs, and the sum of two ceilings is not a ceiling.
        //
        // Admitted and charged BEFORE the call, as exactly one unit.
        // `refreshByListingKey()` returns null both for "the provider had
        // nothing" and for "the provider could not be reached", and swallows
        // the difference, so charging afterwards would mean either guessing
        // whether a request went out or not charging for one that did.
        //
        // Refused means the panel serves the stored record and the surface says
        // nothing is wrong with the property — because nothing is. The listing
        // is simply not being re-confirmed right now.
        $blocked = $this->budget->acquire($actorKey);

        if ($blocked !== null) {
            ExploreProviderTelemetry::record(ExploreProviderTelemetry::OUTCOME_BUDGET_BLOCKED, [
                'reason'      => $blocked,
                'scope'       => 'panel_record_refresh',
                'listing_key' => $listingKey,
                'actor'       => $actorKey,
            ]);

            return false;
        }

        try {
            // The return value is deliberately ignored. Null means "the provider
            // had nothing" OR "the provider could not be reached", and neither
            // is something Explore acts on: a listing is withheld because the
            // POLICY says so after a re-read, never because a fetch came back
            // empty. Treating an unreachable provider as a delisting would make
            // our connectivity look like a change in the market.
            //
            // `dispatchDna: false` — see the class note. Refreshing a record for
            // the panel must not start Location DNA work, and through it Google
            // Places, as a side effect.
            $this->lookup->refreshByListingKey($listingKey, dispatchDna: false);
            ExploreProviderTelemetry::record(ExploreProviderTelemetry::OUTCOME_FETCHED, [
                'scope'       => 'panel_record_refresh',
                'pages'       => 1,
                'listing_key' => $listingKey,
                'actor'       => $actorKey,
            ]);
        } catch (\Throwable $e) {
            Log::warning('ExploreInventoryService: record refresh failed', [
                'listing_key' => $listingKey,
                'error'       => $e->getMessage(),
            ]);

            ExploreProviderTelemetry::record(ExploreProviderTelemetry::OUTCOME_PROVIDER_FAILURE, [
                'scope'       => 'panel_record_refresh',
                'pages'       => 1,
                'listing_key' => $listingKey,
                'actor'       => $actorKey,
            ]);
        }

        return true;
    }

    /** One pass, for one transaction type. */
    private function discover(
        ExploreViewport $viewport,
        ExploreTransactionType $type,
        ?string $actorKey = null,
    ): ExploreDiscoveryOutcome {
        $propertyTypes = $type->propertyTypes();

        if ($propertyTypes === []) {
            // No classified PropertyType for this direction means there is
            // nothing coherent to ask the provider for. Asking with an empty
            // disjunction would return the whole market.
            return ExploreDiscoveryOutcome::disabled();
        }

        try {
            $result = $this->importer->importForCriteria(
                $this->payloadFor($viewport, $propertyTypes),
                $this->roleFor($type),
                $this->maxPages(),
                $this->maxRecords(),
                // Admission per request: each page is admitted and charged just
                // before it is sent, so a refused page is never sent.
                beforeProviderRequest: fn (): ?string => $this->budget->acquire($actorKey),
                // No Location DNA from Explore — see the class note.
                dispatchDna: false,
            );
        } catch (\Throwable $e) {
            // Discovery must never take the surface down with it. A provider
            // fault degrades Explore to last-known rows; it does not 500 a map.
            //
            // Nothing further to charge: every page that was sent was admitted,
            // and so charged, before it went out.
            Log::warning('ExploreInventoryService: discovery failed', [
                'transaction_type' => $type->value,
                'error'            => $e->getMessage(),
            ]);

            ExploreProviderTelemetry::record(ExploreProviderTelemetry::OUTCOME_PROVIDER_FAILURE, [
                'transaction_type' => $type->value,
                'pages'            => 0,
                'actor'            => $actorKey,
            ]);

            return ExploreDiscoveryOutcome::unavailable();
        }

        // NOTHING IS CHARGED HERE. Every page this pass sent was admitted and
        // charged by acquire() BEFORE it went out — failures included, since a
        // page that failed consumed the provider's capacity whatever came back.
        // A retry cannot bypass that: each attempt re-enters admission page by
        // page. A cache hit sent nothing and was charged nothing, which is the
        // entire reason the tile cache is worth having.

        if ($result->isRefused()) {
            $reason = (string) $result->refusalReason;

            ExploreProviderTelemetry::record(ExploreProviderTelemetry::OUTCOME_BUDGET_BLOCKED, [
                'reason'           => $reason,
                'stage'            => $result->pagesAttempted > 0 ? 'mid_pass' : 'before_first_page',
                'transaction_type' => $type->value,
                'pages'            => $result->pagesAttempted,
                'records'          => $result->recordCount,
                'actor'            => $actorKey,
                'spent'            => $this->budget->spent($actorKey),
            ]);

            // Degraded and incomplete: the rows this pass did upsert are kept
            // and shown, nothing is withheld on the strength of the part it did
            // not reach, and the surface says live data is limited rather than
            // that the area is empty.
            return ExploreDiscoveryOutcome::budgetLimited(
                $reason,
                $result->recordCount,
                $result->pagesAttempted > 0 ? 1 : 0,
            );
        }

        if ($result->isFailed()) {
            ExploreProviderTelemetry::record(ExploreProviderTelemetry::OUTCOME_PROVIDER_FAILURE, [
                'transaction_type' => $type->value,
                'pages'            => $result->pagesAttempted,
                'actor'            => $actorKey,
            ]);

            return ExploreDiscoveryOutcome::unavailable();
        }

        if ($result->isCached()) {
            ExploreProviderTelemetry::record(ExploreProviderTelemetry::OUTCOME_CACHE_HIT, [
                'transaction_type' => $type->value,
                'pages'            => 0,
                'records'          => $result->recordCount,
                'actor'            => $actorKey,
            ]);

            return ExploreDiscoveryOutcome::cached($result->recordCount);
        }

        ExploreProviderTelemetry::record(
            $result->isPartial()
                ? ExploreProviderTelemetry::OUTCOME_PARTIAL
                : ExploreProviderTelemetry::OUTCOME_FETCHED,
            [
                'transaction_type' => $type->value,
                'pages'            => $result->pagesAttempted,
                'records'          => $result->recordCount,
                'actor'            => $actorKey,
                'spent'            => $this->budget->spent($actorKey),
            ]
        );

        return $result->isPartial()
            ? ExploreDiscoveryOutcome::partial($result->recordCount)
            : ExploreDiscoveryOutcome::fetched($result->recordCount);
    }

    /**
     * The payload the existing buyer filter builder turns into
     *   StandardStatus eq 'Active' and (PropertyType eq …) and (bbox)
     *
     * Only the two fields that produce those clauses are populated. Every other
     * criterion is left null so the builder omits it — a max price or a bedroom
     * floor here would silently narrow the market Explore can show.
     *
     * @param list<string> $propertyTypes
     */
    public function payloadFor(ExploreViewport $viewport, array $propertyTypes): BuyerCriteriaPayload
    {
        $box = $this->discoveryBox($viewport);

        return new BuyerCriteriaPayload([
            'property_types' => $propertyTypes,

            // Required by the payload's own constructor as an explicit boolean.
            // False is the non-narrowing value: it expresses no 55+ criterion
            // and the filter builder emits no clause for it either way.
            'is_55_plus_eligible' => false,

            // A rectangle, expressed as the polygon shape PolygonBoundingBox
            // already understands. Its envelope IS the rectangle, so the derived
            // bounding box is exact rather than an over-approximation.
            'polygons' => [[
                'path' => [
                    ['lat' => $box['south'], 'lng' => $box['west']],
                    ['lat' => $box['south'], 'lng' => $box['east']],
                    ['lat' => $box['north'], 'lng' => $box['east']],
                    ['lat' => $box['north'], 'lng' => $box['west']],
                ],
            ]],
        ]);
    }

    /**
     * The viewport snapped OUTWARDS to the tile grid.
     *
     * Outwards, never nearest: the box must always contain the viewport, or a
     * property near the screen edge would be inside what Explore renders and
     * outside what discovery asked about — present on the map only if some
     * earlier workflow happened to have imported it, which is the whole defect
     * being fixed.
     *
     * @return array{south:float,west:float,north:float,east:float}
     */
    public function discoveryBox(ExploreViewport $viewport): array
    {
        $grid = (float) config('explore.discovery.tile_degrees', 0.05);

        if (! is_finite($grid) || $grid <= 0.0) {
            $grid = 0.05;
        }

        $snap = static fn (float $value, bool $down): float => $down
            ? floor($value / $grid) * $grid
            : ceil($value / $grid) * $grid;

        return [
            // Clamped to the coordinate range: a tile at the edge of the world
            // must not produce a latitude of 90.05.
            'south' => max(-90.0,  round($snap($viewport->south, true), 6)),
            'west'  => max(-180.0, round($snap($viewport->west,  true), 6)),
            'north' => min(90.0,   round($snap($viewport->north, false), 6)),
            'east'  => min(180.0,  round($snap($viewport->east,  false), 6)),
        ];
    }

    /**
     * The fetch-cache role, which namespaces this tile's cache entry.
     *
     * Sale and rent are separate entries on purpose: they are different provider
     * queries with different result sets, and one warming the other's cache
     * would report a rental tile as fetched when only sales had been asked for.
     */
    public function roleFor(ExploreTransactionType $type): string
    {
        return 'explore_' . $type->value;
    }

    private function maxPages(): int
    {
        $pages = (int) config('explore.discovery.max_pages', 5);

        return $pages > 0 ? $pages : 5;
    }

    private function maxRecords(): int
    {
        $records = (int) config('explore.discovery.max_records', 500);

        return $records > 0 ? $records : 500;
    }

    /**
     * Fold the per-type outcomes into one.
     *
     * The pessimistic reading wins on both axes, and for different reasons:
     * degraded is true if ANY pass failed, because half an answer is still a
     * partial view of the market; complete is true only if EVERY pass was
     * complete, because the withholding rule is applied across the whole
     * response and one unverified direction makes it unsound for all of it.
     *
     * @param list<ExploreDiscoveryOutcome> $outcomes
     */
    private function combine(array $outcomes): ExploreDiscoveryOutcome
    {
        if ($outcomes === []) {
            return ExploreDiscoveryOutcome::disabled();
        }

        if (count($outcomes) === 1) {
            return $outcomes[0];
        }

        $records = array_sum(array_map(static fn ($o) => $o->recordCount, $outcomes));
        $sent    = array_sum(array_map(static fn ($o) => $o->providerRequests, $outcomes));

        foreach ($outcomes as $outcome) {
            if ($outcome->degraded) {
                // A pass stopped by the budget is reported as exactly that —
                // with the rows the passes did return — rather than as a
                // provider outage. "We chose to stop" and "they did not answer"
                // are different operational problems.
                return $outcome->status === ExploreDiscoveryOutcome::STATUS_BUDGET_LIMITED
                    ? ExploreDiscoveryOutcome::budgetLimited((string) $outcome->reason, $records, $sent)
                    : ExploreDiscoveryOutcome::unavailable();
            }
        }

        foreach ($outcomes as $outcome) {
            if (! $outcome->complete) {
                return $outcome->status === ExploreDiscoveryOutcome::STATUS_DISABLED
                    ? ExploreDiscoveryOutcome::disabled()
                    : ExploreDiscoveryOutcome::partial($records);
            }
        }

        // Every pass complete. Report a request only if one was actually sent.
        return $sent > 0
            ? ExploreDiscoveryOutcome::fetched($records)
            : ExploreDiscoveryOutcome::cached($records);
    }
}
