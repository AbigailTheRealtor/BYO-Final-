<?php

namespace App\Services\ListingImport\Sync;

use App\Support\Listing\MlsLinkedListingStatus;
use Illuminate\Support\Facades\Log;

/**
 * The stale-on-access entry point: what happens when somebody OPENS an
 * MLS-linked listing whose data has gone stale.
 *
 * TWO VIEWERS, TWO ANSWERS, AND THE DIFFERENCE IS THE WHOLE DESIGN
 * ----------------------------------------------------------------
 * **The owner or their agent**, authenticated, looking at their own listing:
 * one synchronous refresh through the ordinary service. This is a known user
 * taking a deliberate action on their own record, the volume is bounded by how
 * fast a person can load a page, and they are the one person who needs the
 * answer to be current *now* — they may be about to publish, price, or accept
 * against it.
 *
 * **Anybody else**, including every anonymous visitor: NO outbound request.
 * The view is recorded as demand ({@see MlsSyncDemandQueue}) and the scheduled
 * sweep picks the listing up on its next pass, at most one sweep interval
 * later. The visitor waits for nothing and Bridge hears nothing.
 *
 * WHY NOT "QUEUE IT FOR THE PUBLIC PATH"
 * --------------------------------------
 * Because on this deployment that phrase does not mean what it says.
 * `QUEUE_CONNECTION` is `sync`, `deploy/scheduler.sh` runs `schedule:work`, and
 * nothing anywhere runs `queue:work`. A dispatched job executes inline, in the
 * dispatching request. So a "queued public refresh" would be a synchronous
 * Bridge call on an anonymous page render — the exact thing forbidden — hidden
 * behind a word that normally means the opposite. The right architecture is not
 * to dispatch and hope; it is to not have the expensive work on that path.
 *
 * THE REQUEST STORM CANNOT HAPPEN HERE
 * ------------------------------------
 * Twenty visitors on one stale listing produce twenty cache writes and zero
 * Bridge requests. Twenty owners — twenty simultaneous authenticated views of
 * the same listing — produce one request, because the service takes a
 * per-listing-key lock and the losers re-read inside it and find the work done.
 * Neither case is rationed by a counter; both are settled by where the work is.
 *
 * NOTHING HERE DECIDES WHAT MAY BE WRITTEN. Freshness, locking, precedence and
 * the protected-field boundary all belong to {@see MlsListingSyncService} and
 * are not re-implemented, re-checked or relaxed. This class decides only
 * WHETHER TO ASK, and on whose behalf.
 */
final class MlsStaleAccessRefresher
{
    public function __construct(
        private readonly MlsListingSyncService $sync,
        private readonly MlsSyncDemandQueue $demand,
    ) {}

    /**
     * Called when a listing detail page is rendered.
     *
     * Cheap and total: every early return below is a local check — a config
     * read, a meta-array lookup, a timestamp comparison. Nothing on this path
     * touches the network before the owner branch, so a page render costs
     * nothing measurable when the listing is fresh, not MLS-linked, or being
     * viewed by the public.
     *
     * @param  object  $listing        a SellerAgentAuction or LandlordAgentAuction
     * @param  bool    $viewerIsOwner  the viewer owns this listing (or acts for its owner)
     */
    public function onAccess(object $listing, string $role, bool $viewerIsOwner): MlsSyncOutcome
    {
        if (! (bool) config('mls_sync.enabled', false)) {
            return MlsSyncOutcome::disabled();
        }

        if (! (bool) config('mls_sync.lazy_refresh_enabled', false)) {
            return MlsSyncOutcome::disabled();
        }

        if (! in_array($role, MlsSyncFieldPolicy::SYNCABLE_ROLES, true)
            || ! in_array($role, (array) config('mls_sync.roles', []), true)) {
            return MlsSyncOutcome::unsupported();
        }

        try {
            $meta = $listing->get->toArray();
        } catch (\Throwable $e) {
            // A page render must never fail because sync could not read meta.
            Log::warning('[MLS SYNC] access check could not read listing meta', [
                'listing_id' => $listing->id ?? null,
                'role'       => $role,
                'error'      => $e->getMessage(),
            ]);

            return MlsSyncOutcome::unavailable();
        }

        // A manual listing has no source record. Checked here as well as in the
        // service so the public path does not even record demand for one.
        if (! MlsLinkedListingStatus::isLinked($meta)) {
            return MlsSyncOutcome::notMlsLinked();
        }

        if (MlsSyncFreshness::isFresh($meta)) {
            return MlsSyncOutcome::fresh();
        }

        if (! $viewerIsOwner) {
            $this->demand->note($role, (int) $listing->id);

            // FRESH is deliberately not the answer here — the listing is stale
            // and the caller should not be told otherwise. LOCKED is the honest
            // one: the work is someone else's to do, the data on the page is
            // still the last known good, and nothing was written.
            return MlsSyncOutcome::locked();
        }

        // The owner. One refresh, through the ordinary service, with every gate
        // it applies still applying — including the lock, which is what makes
        // concurrent owner views collapse to a single fetch.
        try {
            return $this->sync->sync($listing, $role);
        } catch (\Throwable $e) {
            // Same rule as above, and it matters more here because this branch
            // does reach the network: a provider fault must render the page with
            // last-known-good data, never a stack trace.
            Log::warning('[MLS SYNC] owner-access refresh failed; page served from stored data', [
                'listing_id' => $listing->id ?? null,
                'role'       => $role,
                'error'      => $e->getMessage(),
            ]);

            return MlsSyncOutcome::unavailable();
        }
    }
}
