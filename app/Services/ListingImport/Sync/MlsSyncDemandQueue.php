<?php

namespace App\Services\ListingImport\Sync;

use Illuminate\Support\Facades\Cache;

/**
 * "Somebody looked at this listing while it was stale."
 *
 * A hint, not a job queue. Recording demand costs one cache write and sends
 * nothing; the scheduled sweep reads it and reconciles those listings FIRST.
 * So a visitor's page view influences the ORDER of work that was going to
 * happen anyway, and never causes an outbound request on the visitor's own
 * request cycle.
 *
 * WHY A HINT RATHER THAN A DISPATCH
 * ---------------------------------
 * `QUEUE_CONNECTION` is `sync` on this deployment and there is no queue worker
 * process — `deploy/scheduler.sh` runs `schedule:work` and nothing runs
 * `queue:work`. So `SomeJob::dispatch()` does not defer anything here: it
 * executes inline, inside the request that dispatched it. A "queued
 * asynchronous public stale refresh" would therefore be a synchronous Bridge
 * request on an anonymous page render wearing the word `dispatch`, which is the
 * one thing the stale-on-access rule forbids.
 *
 * A cache write has no such trapdoor. It cannot become a network call because
 * somebody changed a driver in an unrelated file.
 *
 * THE STORM PROPERTY IS STRUCTURAL, NOT RATIONED
 * ----------------------------------------------
 * Twenty visitors opening the same stale listing write the same cache key
 * twenty times and produce ZERO Bridge requests between them. There is no
 * counter to tune and no lock to contend on, because there is nothing to
 * de-duplicate: the expensive thing was never on this path.
 *
 * BOUNDED BY CONSTRUCTION
 * -----------------------
 * The index is one cache entry holding at most `access_hint_limit` ids, and
 * every entry expires. A listing that stops being viewed stops being
 * prioritised on its own, with no cleanup pass.
 */
final class MlsSyncDemandQueue
{
    private const INDEX_KEY = 'mls-sync:demand-index';

    /**
     * Note that this listing was viewed while stale.
     *
     * Ids are namespaced by role because a seller #12 and a landlord #12 are
     * different listings in different tables.
     */
    public function note(string $role, int $listingId): void
    {
        $ttl   = max(1, (int) config('mls_sync.access_hint_ttl_minutes', 60));
        $limit = max(1, (int) config('mls_sync.access_hint_limit', 200));

        try {
            $index = $this->index();
            $token = $this->token($role, $listingId);

            // Move-to-front on a repeat view: the listing somebody is looking at
            // right now is the one whose staleness is most visible.
            $index = array_values(array_filter($index, fn ($t) => $t !== $token));
            array_unshift($index, $token);

            Cache::put(self::INDEX_KEY, array_slice($index, 0, $limit), now()->addMinutes($ttl));
        } catch (\Throwable) {
            // A cache that cannot be written is not a reason to fail a page
            // render. The sweep still reaches this listing on its ordinary
            // oldest-first pass; it simply is not promoted.
        }
    }

    /**
     * Listing ids waiting for priority treatment in this role, most recently
     * viewed first.
     *
     * @return list<int>
     */
    public function pending(string $role): array
    {
        $prefix = $role . ':';

        $ids = [];

        foreach ($this->index() as $token) {
            if (is_string($token) && str_starts_with($token, $prefix)) {
                $id = (int) substr($token, strlen($prefix));

                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }

        return $ids;
    }

    /** Drop a listing's hint once the sweep has dealt with it. */
    public function clear(string $role, int $listingId): void
    {
        try {
            $token = $this->token($role, $listingId);
            $index = array_values(array_filter($this->index(), fn ($t) => $t !== $token));

            Cache::put(
                self::INDEX_KEY,
                $index,
                now()->addMinutes(max(1, (int) config('mls_sync.access_hint_ttl_minutes', 60)))
            );
        } catch (\Throwable) {
            // Same posture as note(): a hint that cannot be cleared costs one
            // wasted priority slot, which expires on its own.
        }
    }

    public function flush(): void
    {
        try {
            Cache::forget(self::INDEX_KEY);
        } catch (\Throwable) {
            // Nothing to do; the entry expires.
        }
    }

    /** @return list<string> */
    private function index(): array
    {
        try {
            $index = Cache::get(self::INDEX_KEY, []);
        } catch (\Throwable) {
            return [];
        }

        return is_array($index) ? array_values(array_filter($index, 'is_string')) : [];
    }

    private function token(string $role, int $listingId): string
    {
        return $role . ':' . $listingId;
    }
}
