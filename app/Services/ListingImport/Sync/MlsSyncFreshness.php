<?php

namespace App\Services\ListingImport\Sync;

use App\Services\ListingImport\QuickImport\MlsQuickImportDraftWriter as Meta;
use App\Support\Listing\MlsSourceStatus;
use Carbon\CarbonImmutable;

/**
 * When is a listing's stored MLS data too old to be trusted?
 *
 * ONE DEFINITION, TWO READERS, AND THAT IS THE POINT.
 * ---------------------------------------------------
 * {@see MlsListingSyncService} asks this before deciding whether to spend a
 * Bridge request, and {@see \App\Console\Commands\SyncMlsListings} asks it when
 * ordering candidates so the sweep works on the listings that are actually due.
 *
 * Those two answers must agree. If the sweep's idea of "due" were even slightly
 * looser than the service's idea of "fresh", every run would select a batch of
 * listings, call the service on each, and be told FRESH — a sweep that costs a
 * query per listing, sends nothing, and reports success. That failure is silent
 * and looks exactly like a healthy system with nothing to do.
 *
 * THREE WINDOWS, NOT ONE, BECAUSE THEY ANSWER DIFFERENT QUESTIONS
 * --------------------------------------------------------------
 *   · ERROR — the last attempt failed. Shortest window. A transport blip must
 *     not freeze a price for an hour, but an unreachable provider must not be
 *     hammered either.
 *
 *   · TERMINAL — the source says Closed / Expired / Withdrawn / Canceled /
 *     Temporarily Off Market. Longest window. A property that has left the
 *     market is not about to change its price, and polling it every hour
 *     forever spends a request an hour, for years, to re-learn a fact that was
 *     settled the first time. The record is never deleted and never stops being
 *     checked — it is checked far less often, which is the distinction the
 *     owner's lifecycle clause draws.
 *
 *   · LIVE — everything else: Active, Pending, Active Under Contract, Coming
 *     Soon, and any status we do not recognise. The ordinary window.
 *
 * AN UNRECOGNISED STATUS GETS THE **LIVE** WINDOW, DELIBERATELY.
 * -------------------------------------------------------------
 * {@see MlsSourceStatus::isOffMarket()} answers false for a word it does not
 * know, so an unfamiliar status falls here rather than into TERMINAL. That is
 * the safe direction: guessing "off market" from an unrecognised string would
 * quietly drop a live listing to daily polling, and the listing would go stale
 * for exactly the reason nobody would think to look for.
 */
final class MlsSyncFreshness
{
    public const REASON_ERROR    = 'error_backoff';
    public const REASON_TERMINAL = 'terminal_status';
    public const REASON_LIVE     = 'live_status';
    public const REASON_NEVER    = 'never_synced';

    /**
     * The window that applies to this listing right now, in minutes.
     *
     * @param  array<string,mixed>  $meta
     */
    public static function windowMinutes(array $meta): int
    {
        return max(0, (int) config(
            match (self::reason($meta)) {
                self::REASON_ERROR    => 'mls_sync.retry_after_minutes',
                self::REASON_TERMINAL => 'mls_sync.terminal_freshness_minutes',
                default               => 'mls_sync.freshness_minutes',
            },
            0
        ));
    }

    /**
     * Which rule applies, as a word — for the window above, and for the console
     * report, so an operator can see WHY a listing is not being polled rather
     * than inferring it from a timestamp.
     *
     * @param  array<string,mixed>  $meta
     */
    public static function reason(array $meta): string
    {
        if (self::stamp($meta, Meta::META_SYNC_ERROR) !== null) {
            return self::REASON_ERROR;
        }

        if (self::stamp($meta, Meta::META_SYNCED_AT) === null) {
            return self::REASON_NEVER;
        }

        // StandardStatus first, then MlsStatus — the same precedence
        // MlsLinkedListingStatus applies, for the same reason: the two genuinely
        // disagree on real records and StandardStatus is the RESO-normalised one.
        $status = MlsSourceStatus::normalize($meta[Meta::META_STANDARD_STATUS] ?? null)
            ?? MlsSourceStatus::normalize($meta[Meta::META_SOURCE_STATUS] ?? null);

        return MlsSourceStatus::isOffMarket($status)
            ? self::REASON_TERMINAL
            : self::REASON_LIVE;
    }

    /**
     * Is this listing's stored data still inside its window?
     *
     * A listing that has never synced successfully is never fresh — it has no
     * data to trust. An unparseable stamp also reads as stale: refusing to sync
     * because a timestamp is corrupt would let one bad row freeze a listing
     * permanently, which is the worse of the two failures.
     *
     * @param  array<string,mixed>  $meta
     */
    public static function isFresh(array $meta): bool
    {
        $due = self::dueAt($meta);

        return $due !== null && $due->isFuture();
    }

    /**
     * The instant this listing next becomes eligible, or null when it is
     * eligible now (including "has never synced").
     *
     * @param  array<string,mixed>  $meta
     */
    public static function dueAt(array $meta): ?CarbonImmutable
    {
        $reason = self::reason($meta);

        if ($reason === self::REASON_NEVER) {
            return null;
        }

        // On the error path the clock runs from the ATTEMPT, not from the last
        // success: a listing failing repeatedly would otherwise measure its
        // backoff from a success that gets further away every time, and retry
        // in a tight loop forever.
        $stamp = self::stamp(
            $meta,
            $reason === self::REASON_ERROR ? Meta::META_SYNC_ATTEMPTED_AT : Meta::META_SYNCED_AT
        );

        if ($stamp === null) {
            return null;
        }

        try {
            return CarbonImmutable::parse($stamp)->addMinutes(self::windowMinutes($meta));
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * A non-empty stored value, or null. Used for both timestamps and the error
     * code, so a key cleared to the literal string 'null' — which a careless
     * write could produce — does not read as present.
     *
     * @param  array<string,mixed>  $meta
     */
    private static function stamp(array $meta, string $key): ?string
    {
        $value = $meta[$key] ?? null;

        if (! is_scalar($value)) {
            return null;
        }

        $trimmed = trim((string) $value);

        return ($trimmed === '' || $trimmed === 'null') ? null : $trimmed;
    }
}
