<?php

namespace App\Services\Explore;

use App\Models\BridgeProperty;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * How long a stored Stellar record may be treated as current — BORROWED, not
 * invented.
 *
 * ONE FRESHNESS ANSWER, THREE READERS
 * -----------------------------------
 * `config/bridge.php` has shipped `lazy_ttl_minutes = 60` since the criteria
 * importer existed: this repository's already-made judgment about how long
 * Bridge data may be treated as current. `config/mls_sync.php` borrowed exactly
 * that number on purpose, and says so — two Bridge-facing caches answering the
 * same question differently would not be two decisions, it would be one decision
 * and one oversight. Explore borrows it for the third time rather than adding an
 * EXPLORE_FRESHNESS_MINUTES nobody would remember to keep aligned.
 *
 * It is also the same value by construction rather than by coincidence: the
 * viewport discovery pass writes a fetch-cache row with this TTL, so "the cache
 * is still warm" and "the rows are still confirmed" are the same window. A
 * separate Explore dial could put those two out of step, which would produce
 * either rows withheld while the cache says no refresh is needed, or rows served
 * that the last pass never confirmed.
 *
 * WHAT `imported_at` MEANS
 * ------------------------
 * `BridgePropertyNormalizer::upsert()` writes `imported_at => now()` on every
 * upsert — insert and update alike — so it is already a "last confirmed against
 * the source" timestamp for every row, filled by every ingestion path this
 * application has. Explore needed no new column and added none.
 */
class ExploreFreshness
{
    /** The window, in minutes. */
    public function minutes(): int
    {
        $minutes = (int) config('bridge.lazy_ttl_minutes', 60);

        // A zero or negative window would withhold every row the instant it was
        // written, which reads as an empty market rather than as a misconfigured
        // number. Fall back to the shipped default.
        return $minutes > 0 ? $minutes : 60;
    }

    /** The cutoff: a row confirmed before this is not current. */
    public function cutoff(?CarbonInterface $now = null): Carbon
    {
        $now = $now ? Carbon::instance($now->toDateTime()) : Carbon::now();

        return $now->copy()->subMinutes($this->minutes());
    }

    /**
     * Has this row been confirmed against the source inside the window?
     *
     * A row with no `imported_at` at all answers false. Every ingestion path
     * this application has sets it, so its absence means a row written by
     * something that predates them — not something to treat as freshly
     * confirmed.
     */
    public function isConfirmedCurrent(BridgeProperty $listing, ?CarbonInterface $now = null): bool
    {
        $importedAt = $listing->imported_at;

        if ($importedAt === null) {
            return false;
        }

        return Carbon::instance($importedAt->toDateTime())->greaterThanOrEqualTo($this->cutoff($now));
    }
}
