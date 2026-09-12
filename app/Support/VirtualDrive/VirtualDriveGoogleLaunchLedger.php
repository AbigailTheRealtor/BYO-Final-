<?php

namespace App\Support\VirtualDrive;

use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * The daily ceiling on intentional Virtual Drive launches.
 *
 * WHAT ONE LAUNCH IS
 * ------------------
 * One page that has been granted permission to construct its one billable
 * StreetViewPanorama. Google bills Dynamic Street View per panorama OBJECT
 * instantiated, the Google provider constructs at most one for the life of a
 * page, and moving that panorama between homes with setPano() costs nothing
 * more. So the unit counted here is deliberately the unit that is billed:
 *
 *   • opening the page, browsing homes, cards, photos or the nearby list —
 *     nothing is claimed, because nothing is loaded;
 *   • the first "Drive with Google" on a page — ONE claim;
 *   • pressing "Try again" after a home with no imagery, changing homes,
 *     clicking signs, choosing a unit in a building — no further claim, because
 *     the page already holds its one permitted panorama;
 *   • reloading and pressing again — a new page, a new panorama, a new claim.
 *
 * The claim is taken BEFORE the Maps JavaScript API is requested, so a refusal
 * means the library was never fetched and no panorama could exist. That is
 * conservative in the one direction that matters: a launch whose home turns out
 * to have no coverage has still spent its claim.
 *
 * WHY THE TALLY IS SERVER-SIDE
 * ----------------------------
 * The ceiling is "across the proof environment", which rules out anything the
 * browser keeps: localStorage is per-browser, per-profile, and cleared by the
 * devtools button nobody thinks twice about pressing. A shared server-side
 * tally is the only thing that makes two tabs, two browsers and two people
 * draw from one day's allowance.
 *
 * And the grant is what DELIVERS THE BROWSER KEY. The Google page is rendered
 * with no credential in its markup; `VirtualDriveGoogleLaunchController` returns
 * the key only in the response to a granted claim. So the ceiling is not advice
 * the page may decline to take — past it, the browser has nothing to
 * authenticate a Maps JavaScript API load with.
 *
 * WHERE IT IS KEPT, AND HOW IT FAILS
 * ----------------------------------
 * One counter per calendar day in the application's configured cache, under the
 * app timezone (`config/app.timezone`, UTC here) so "today" is one definition
 * rather than each visitor's own. Read and write happen inside a cache lock
 * where the store offers one, so two simultaneous presses cannot both take the
 * last launch of the day.
 *
 * Every write is read back. A store that cannot hold the tally — `CACHE_DRIVER=null`
 * most obviously — would otherwise make every launch look like the first one of
 * the day, forever, with no error anywhere: the ceiling would read as enforced
 * and enforce nothing. A failed readback, a lock we could not take and any
 * exception from the cache are all refusals. The same posture as the rest of
 * this proof: a gate that cannot be evaluated is closed.
 *
 * Nothing here is a substitute for Google Cloud quotas. It is an application
 * ceiling over a development proof, which is the layer this repository owns.
 */
final class VirtualDriveGoogleLaunchLedger
{
    public const KEY_PREFIX = 'virtual_drive:google:daily_launches:';

    /** Refusal reasons. Carried in the API response so a message can be written once. */
    public const REASON_DISABLED = 'google_disabled';

    public const REASON_NOT_CONFIGURED = 'ceiling_not_configured';

    public const REASON_NO_CREDENTIAL = 'credential_missing';

    public const REASON_LIMIT_REACHED = 'daily_limit_reached';

    public const REASON_LEDGER_UNAVAILABLE = 'ledger_unavailable';

    private const LOCK_SECONDS = 5;

    private const LOCK_WAIT_SECONDS = 3;

    /** The calendar day the ceiling applies to, in the application timezone. */
    public function day(): string
    {
        return now()->toDateString();
    }

    /**
     * Today's state without changing it. This is what the page can say before
     * anybody presses anything, so it must not consume an allowance.
     *
     * @return array{day:string,limit:int,used:int,remaining:int,readable:bool}
     */
    public function peek(): array
    {
        $limit = VirtualDriveGoogleGate::dailyLaunchLimit();

        try {
            $used = $this->storedCount();
        } catch (Throwable $e) {
            return ['day' => $this->day(), 'limit' => $limit, 'used' => 0, 'remaining' => 0, 'readable' => false];
        }

        return [
            'day'       => $this->day(),
            'limit'     => $limit,
            'used'      => $used,
            'remaining' => max(0, $limit - $used),
            'readable'  => true,
        ];
    }

    /**
     * Take one launch out of today's allowance, or refuse and say why.
     *
     * @return array{granted:bool,reason:?string,day:string,limit:int,used:int,remaining:int}
     */
    public function claim(): array
    {
        $limit = VirtualDriveGoogleGate::dailyLaunchLimit();

        if (! VirtualDriveGoogleGate::enabled()) {
            return $this->refuse(self::REASON_DISABLED, $limit, 0);
        }

        if ($limit === 0) {
            return $this->refuse(self::REASON_NOT_CONFIGURED, 0, 0);
        }

        if (! VirtualDriveGoogleGate::hasBrowserKey()) {
            return $this->refuse(self::REASON_NO_CREDENTIAL, $limit, 0);
        }

        $lock = $this->lock();

        if ($lock === false) {
            // A store that offers locks but would not give us one. Deciding
            // without it would let two presses share the day's last launch.
            return $this->refuse(self::REASON_LEDGER_UNAVAILABLE, $limit, 0);
        }

        try {
            return $this->claimUnderLock($limit);
        } catch (Throwable $e) {
            return $this->refuse(self::REASON_LEDGER_UNAVAILABLE, $limit, 0);
        } finally {
            if ($lock !== null) {
                $lock->release();
            }
        }
    }

    /** @return array{granted:bool,reason:?string,day:string,limit:int,used:int,remaining:int} */
    private function claimUnderLock(int $limit): array
    {
        $used = $this->storedCount();

        if ($used >= $limit) {
            return $this->refuse(self::REASON_LIMIT_REACHED, $limit, $used);
        }

        $next = $used + 1;

        Cache::put($this->key(), $next, $this->secondsLeftToday());

        // THE READBACK. A store that silently discards this turns the ceiling
        // into decoration, so the tally is only believed once it has been read
        // out of the store it was written to.
        if ($this->storedCount() !== $next) {
            return $this->refuse(self::REASON_LEDGER_UNAVAILABLE, $limit, $used);
        }

        return [
            'granted'   => true,
            'reason'    => null,
            'day'       => $this->day(),
            'limit'     => $limit,
            'used'      => $next,
            'remaining' => max(0, $limit - $next),
        ];
    }

    /** @return array{granted:false,reason:string,day:string,limit:int,used:int,remaining:int} */
    private function refuse(string $reason, int $limit, int $used): array
    {
        return [
            'granted'   => false,
            'reason'    => $reason,
            'day'       => $this->day(),
            'limit'     => $limit,
            'used'      => $used,
            'remaining' => max(0, $limit - $used),
        ];
    }

    public function key(): string
    {
        return self::KEY_PREFIX . $this->day();
    }

    private function storedCount(): int
    {
        $value = Cache::get($this->key());

        return is_numeric($value) && $value > 0 ? (int) $value : 0;
    }

    /**
     * The day's counter expires with the day, so nothing accumulates and a key
     * left behind by a clock change cannot ration tomorrow.
     */
    private function secondsLeftToday(): int
    {
        return max(60, now()->endOfDay()->diffInSeconds(now()) + 60);
    }

    /**
     * A held lock, `null` when the store offers none (nothing to release), or
     * `false` when one was offered and could not be taken.
     *
     * @return \Illuminate\Contracts\Cache\Lock|null|false
     */
    private function lock()
    {
        try {
            $store = Cache::getStore();

            if (! $store instanceof LockProvider) {
                return null;
            }

            $lock = $store->lock(self::KEY_PREFIX . 'lock', self::LOCK_SECONDS);

            return $lock->block(self::LOCK_WAIT_SECONDS) ? $lock : false;
        } catch (Throwable $e) {
            return false;
        }
    }
}
