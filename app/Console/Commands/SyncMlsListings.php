<?php

namespace App\Console\Commands;

use App\Models\LandlordAgentAuction;
use App\Models\SellerAgentAuction;
use App\Services\ListingImport\QuickImport\MlsQuickImportDraftWriter as Meta;
use App\Services\ListingImport\Sync\MlsListingSyncService;
use App\Services\ListingImport\Sync\MlsSyncDemandQueue;
use App\Services\ListingImport\Sync\MlsSyncOutcome;
use Illuminate\Console\Command;

/**
 * The reconciliation backstop: bring MLS-linked listings back into line with
 * their source records.
 *
 * THIS IS THE PRIMARY MECHANISM, NOT A FALLBACK
 * ---------------------------------------------
 * Stale-on-access only reaches listings somebody opens, and only the owner
 * branch of it sends anything at all. A listing nobody has viewed for a month is
 * exactly the one most likely to be advertising a price that moved. So the sweep
 * is what actually keeps the estate current; access handling adjusts its
 * priority and serves the owner sooner.
 *
 * TWO SCHEDULED SHAPES, ONE COMMAND
 * ---------------------------------
 * Registered in `app/Console/Kernel.php` (see `config/mls_sync.php` → `schedule`
 * for the cadence and the evidence behind it):
 *
 *   · the SWEEP, every `sweep_minutes` (default 15) — ordinary ceiling, honours
 *     demand hints, `withoutOverlapping` so a slow run cannot double up;
 *   · the RECONCILE pass, once a day at `reconcile_at` — raised ceiling, no
 *     demand priority, there to guarantee complete coverage even if every sweep
 *     that day hit its ceiling. This is the daily minimum the owner's contract
 *     names; the sweep is what exceeds it.
 *
 * Both are gated by `mls_sync.schedule.enabled` and by the master
 * `mls_sync.enabled`, and neither is registered when the gate is off — a
 * disabled sweep should not appear in `schedule:list` as something that runs.
 *
 * SAFETY
 * ------
 *   · `--dry-run` reports what would be synced and sends nothing;
 *   · a per-run ceiling caps how many listings one invocation may reconcile,
 *     and that ceiling — not the cadence — is what bounds worst-case traffic;
 *   · the freshness window still applies to every listing, so a re-run minutes
 *     later does almost nothing;
 *   · one listing's failure never stops the run — the whole point is that the
 *     other listings still get reconciled;
 *   · a terminal-status listing falls to the daily window on its own, so
 *     historical records stop consuming the frequent budget without ever being
 *     dropped from the sweep.
 */
class SyncMlsListings extends Command
{
    protected $signature = 'mls:sync-listings
                            {--role= : Restrict to one role (seller|landlord)}
                            {--listing= : Sync exactly one listing id (requires --role)}
                            {--force : Ignore the freshness window and the unchanged-source short-circuit}
                            {--limit= : Override the per-run ceiling}
                            {--reconcile : The daily completeness pass — raised ceiling, no demand priority}
                            {--dry-run : Report what would be synced; send nothing}';

    protected $description = 'Reconcile MLS-linked listings with their current Stellar source records.';

    public function handle(MlsListingSyncService $sync, MlsSyncDemandQueue $demand): int
    {
        if (! (bool) config('mls_sync.enabled', false)) {
            $this->warn('MLS sync is disabled (mls_sync.enabled). Nothing was done.');

            return self::SUCCESS;
        }

        $roles = $this->resolveRoles();

        if ($roles === []) {
            $this->error('Unknown --role. Valid roles: ' . implode(', ', (array) config('mls_sync.roles', [])));

            return self::FAILURE;
        }

        $listingId = $this->option('listing');

        if ($listingId !== null && count($roles) !== 1) {
            $this->error('--listing requires --role, so the id is resolved against exactly one table.');

            return self::FAILURE;
        }

        $reconcile = (bool) $this->option('reconcile');
        $dryRun    = (bool) $this->option('dry-run');
        $force     = (bool) $this->option('force');

        // The reconcile pass runs once a day and its job is completeness, so it
        // gets the raised ceiling. It deliberately does NOT honour demand
        // priority: "somebody looked at this" is a reason to reorder a
        // fifteen-minute sweep, and no reason at all to reorder the pass whose
        // whole purpose is to reach everything.
        $limit = (int) ($this->option('limit') ?? config(
            $reconcile ? 'mls_sync.reconcile_batch_limit' : 'mls_sync.batch_limit',
            $reconcile ? 500 : 100
        ));

        $tally     = [];
        $processed = 0;

        foreach ($roles as $role) {
            $priority = ($reconcile || $listingId !== null) ? [] : $demand->pending($role);

            foreach ($this->candidates($role, $listingId, $limit - $processed, $priority) as $listing) {
                if ($processed >= $limit) {
                    break 2;
                }

                $processed++;

                if ($dryRun) {
                    $this->line(sprintf(
                        '  [dry-run] %s #%d — key=%s last-synced=%s',
                        $role,
                        $listing->id,
                        (string) ($listing->info(Meta::META_LISTING_KEY) ?: '—'),
                        (string) ($listing->info(Meta::META_SYNCED_AT) ?: 'never'),
                    ));

                    $tally['dry-run'] = ($tally['dry-run'] ?? 0) + 1;

                    continue;
                }

                // One listing's failure must never abort the run. A brokerage
                // whose record has gone strange should not stop every other
                // seller's price being corrected.
                try {
                    $outcome = $sync->sync($listing, $role, $force);
                } catch (\Throwable $e) {
                    $this->error(sprintf('  %s #%d — %s', $role, $listing->id, $e->getMessage()));
                    $tally['exception'] = ($tally['exception'] ?? 0) + 1;

                    continue;
                }

                $tally[$outcome->status] = ($tally[$outcome->status] ?? 0) + 1;

                // The hint has served its purpose the moment the listing has
                // been looked at, whatever the outcome — including FRESH, which
                // means another path got there first. Leaving it set would keep
                // re-promoting a listing that no longer needs it.
                if ($outcome->isConclusive()) {
                    $demand->clear($role, (int) $listing->id);
                }

                $this->reportOne($role, $listing->id, $outcome);
            }
        }

        $this->line('');
        $this->info('Processed ' . $processed . ' listing(s).');

        foreach ($tally as $status => $count) {
            $this->line(sprintf('  %-16s %d', $status, $count));
        }

        // An unrecognised status is reported loudly rather than left in a log:
        // it means the feed is using a word this application has never seen, and
        // somebody has to decide what it means.
        if (($tally[MlsSyncOutcome::SYNCED] ?? 0) > 0) {
            $this->line('');
            $this->line('Statuses are stored exactly as Stellar supplied them. Any flagged as');
            $this->line('unrecognised are listed above and preserved verbatim — nothing was deleted.');
        }

        return self::SUCCESS;
    }

    private function reportOne(string $role, int $id, MlsSyncOutcome $outcome): void
    {
        $line = sprintf('  %s #%d — %s', $role, $id, $outcome->status);

        if ($outcome->isSynced()) {
            $line .= sprintf(
                ' (status=%s, facts=%d, media +%d/~%d/-%d, user photos kept %d)',
                $outcome->sourceStatus ?? '—',
                count($outcome->changedKeys),
                $outcome->mediaAdded,
                $outcome->mediaUpdated,
                $outcome->mediaRemoved,
                $outcome->userPhotosPreserved,
            );
        }

        if ($outcome->statusUnrecognised) {
            $this->warn($line . '  [UNRECOGNISED STATUS — preserved verbatim]');

            return;
        }

        $this->line($line);
    }

    /** @return list<string> */
    private function resolveRoles(): array
    {
        $configured = (array) config('mls_sync.roles', []);
        $requested  = $this->option('role');

        if ($requested === null) {
            return $configured;
        }

        return in_array($requested, $configured, true) ? [$requested] : [];
    }

    /**
     * MLS-linked listings for a role, most-in-need first.
     *
     * DISCOVERY IS BY PROVENANCE, NOT BY VINTAGE, AND THAT IS THE REQUIREMENT.
     * -----------------------------------------------------------------------
     * The only test applied is "does this listing carry an MLS identifier" — a
     * meta EXISTS on ListingKey or MLS number, the same pair
     * {@see \App\Support\Listing\MlsLinkedListingStatus::isLinked()} uses. There
     * is deliberately no filter on when the listing was created, no requirement
     * that it carry sync metadata, and no flag an import has to have set.
     *
     * So a listing imported long before any of this existed is a candidate on
     * the first run, because the identifier it has always carried is the whole
     * qualification. Anything narrower would have quietly excluded exactly the
     * listings that have been stale the longest — the ones this feature is for.
     *
     * ListingKey is preferred over MLS number wherever present (the service
     * resolves by key), but either is enough to be DISCOVERED here: a listing
     * imported before ListingKey was persisted must not be invisible to the
     * sweep on account of a column that did not exist yet.
     *
     * ORDERING, IN TWO TIERS
     * ----------------------
     * Demand hints first: a listing somebody is looking at right now, whose data
     * is stale, is where visible staleness costs most. Then oldest-first, so a
     * ceiling that cuts the run short cuts off the freshest rather than the
     * stalest, and every listing eventually reaches the front.
     *
     * Ordering by `updated_at` rather than by the last-synced meta value is
     * deliberate: the sync stamp lives in an EAV row, so ordering on it would
     * need a join per role and would silently place never-synced listings
     * wherever the join's nulls happened to sort. `updated_at` is a native
     * column on both tables, and a listing that has never synced has an old one.
     *
     * Freshness is NOT filtered here. The service re-checks it inside the lock
     * — the only place it can be checked correctly — and a listing that turns
     * out to be fresh costs a local timestamp comparison and no request.
     *
     * @param  list<int>  $priority  listing ids to place at the front
     * @return iterable<object>
     */
    private function candidates(string $role, ?string $listingId, int $remaining, array $priority = []): iterable
    {
        if ($remaining <= 0) {
            return [];
        }

        $modelClass = match ($role) {
            'seller'   => SellerAgentAuction::class,
            'landlord' => LandlordAgentAuction::class,
            default    => null,
        };

        if ($modelClass === null) {
            return [];
        }

        $mlsLinked = fn ($query) => $query->whereHas('meta', function ($q) {
            $q->whereIn('meta_key', [Meta::META_LISTING_KEY, Meta::META_MLS_NUMBER])
              ->whereNotNull('meta_value')
              ->where('meta_value', '<>', '');
        });

        if ($listingId !== null) {
            return $mlsLinked($modelClass::query())->whereKey((int) $listingId)->limit($remaining)->get();
        }

        $selected = collect();

        // ── Tier 1: viewed while stale ──────────────────────────────────────
        //
        // Still required to be MLS-linked. A hint is a hint about ORDER; it can
        // never make a manual listing eligible, which is why the same clause is
        // applied rather than trusting the id.
        if ($priority !== []) {
            $selected = $mlsLinked($modelClass::query())
                ->whereIn((new $modelClass)->getKeyName(), array_slice($priority, 0, $remaining))
                ->get();
        }

        $remaining -= $selected->count();

        if ($remaining <= 0) {
            return $selected;
        }

        // ── Tier 2: everything else, longest-untouched first ────────────────
        $rest = $mlsLinked($modelClass::query())
            ->when($selected->isNotEmpty(), fn ($q) => $q->whereKeyNot($selected->modelKeys()))
            ->orderBy('updated_at')
            ->limit($remaining)
            ->get();

        return $selected->concat($rest);
    }
}
