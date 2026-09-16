<?php

namespace App\Support\VirtualDrive;

/**
 * Whether the BILLED street-level provider — Google Street View — may run
 * inside the Virtual Drive proof.
 *
 * TWO GATES, TWO QUESTIONS, NEITHER REDUNDANT
 * -------------------------------------------
 * VirtualDriveProofGate answers "does the proof exist here at all?" — the
 * environment allow-list and VIRTUAL_DRIVE_PROOF_ENABLED, covering both
 * providers and every route. This class answers the narrower and more expensive
 * question: "while the proof is open, may Google be started?" Apple Look Around
 * costs Apple quota; Google Street View bills per panorama object instantiated,
 * so it gets its own switch and its own ceiling.
 *
 * The proof gate is checked first, by middleware, and this one never relaxes it:
 * `VIRTUAL_DRIVE_GOOGLE_ENABLED=true` on a production host still reaches a 404.
 *
 * OFF IS NOT A PROMISE THE BROWSER HAS TO KEEP
 * --------------------------------------------
 * When this says no, three independent things are true of the Google page, and
 * each one alone is sufficient:
 *
 *   1. google-streetview-provider.js is not included, so no code capable of
 *      constructing a StreetViewPanorama is on the page;
 *   2. no browser key is emitted — and the key is never in the page's markup
 *      even when Google IS enabled, only in a granted launch claim — so the
 *      Maps JavaScript API has nothing to authenticate with;
 *   3. the launch-claim endpoint refuses, so the shell locks the button and
 *      says why.
 *
 * THE CEILING IS A NUMBER, NOT A BOOLEAN
 * --------------------------------------
 * dailyLaunchLimit() is how many launches the whole proof environment may start
 * in one calendar day. Zero means "refuse", never "unlimited": an operator who
 * switched Google on without choosing a ceiling has not authorised an unbounded
 * day's spend. config/virtual_drive.php does the strict parsing; this class is
 * the one place application code asks.
 */
final class VirtualDriveGoogleGate
{
    public const FLAG = 'VIRTUAL_DRIVE_GOOGLE_ENABLED';

    public const LIMIT_FLAG = 'VIRTUAL_DRIVE_GOOGLE_DAILY_LAUNCH_LIMIT';

    /** The kill switch alone. It says nothing about the day's remaining allowance. */
    public static function enabled(): bool
    {
        return config('virtual_drive.google.enabled') === true;
    }

    /** Launches permitted per calendar day across the proof environment; 0 = none. */
    public static function dailyLaunchLimit(): int
    {
        $limit = config('virtual_drive.google.daily_launch_limit');

        return is_int($limit) && $limit > 0 ? $limit : 0;
    }

    /** A browser key exists to be handed out — not the key itself, and never the key in a page. */
    public static function hasBrowserKey(): bool
    {
        $key = config('virtual_drive.google.browser_key');

        return is_string($key) && trim($key) !== '';
    }

    public static function browserKey(): ?string
    {
        $key = config('virtual_drive.google.browser_key');

        return is_string($key) && trim($key) !== '' ? trim($key) : null;
    }

    /**
     * Why Google cannot be launched, in words a reviewer can act on — or null
     * when the switch and the ceiling both permit it.
     *
     * This covers only the STANDING reasons, which the page can state before
     * anybody presses anything. Whether today's allowance is already spent is
     * the ledger's answer, because reading it is a question about right now.
     */
    public static function refusalReason(): ?string
    {
        if (! self::enabled()) {
            return 'Google Street View is switched off for this proof environment ('
                . self::FLAG . '=false). No Maps JavaScript library is loaded on this page and no '
                . 'panorama can be created. Apple Look Around is unaffected.';
        }

        if (self::dailyLaunchLimit() === 0) {
            return 'Google Street View is switched on but no daily launch ceiling is configured ('
                . self::LIMIT_FLAG . '). Launches are refused until one is set — an unset ceiling is '
                . 'not an unlimited one.';
        }

        if (! self::hasBrowserKey()) {
            return 'Google Street View is switched on but VIRTUAL_DRIVE_GOOGLE_MAPS_BROWSER_KEY is not '
                . 'configured. No request has been sent to Google.';
        }

        return null;
    }
}
