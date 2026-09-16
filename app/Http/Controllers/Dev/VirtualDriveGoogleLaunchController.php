<?php

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use App\Support\VirtualDrive\VirtualDriveGoogleAuthBlock;
use App\Support\VirtualDrive\VirtualDriveGoogleGate;
use App\Support\VirtualDrive\VirtualDriveGoogleLaunchLedger;
use Illuminate\Http\JsonResponse;

/**
 * The Google launch claim — the one place a Virtual Drive page is given
 * permission, and the means, to start a billable Street View session.
 *
 * Development-only: the route sits behind `virtual-drive-proof`, so it 404s
 * everywhere VirtualDriveProofGate refuses, production first.
 *
 * THE GRANT CARRIES THE BROWSER KEY, AND THAT IS THE ENFORCEMENT
 * -------------------------------------------------------------
 * The Google page is rendered with no credential in its markup. The Maps
 * JavaScript API key exists in exactly one response: a granted claim. So the
 * kill switch and the daily ceiling are not requests the browser may ignore —
 * refused, the page holds nothing Google would authenticate, and
 * `google-streetview-provider.js` was not even included when the switch is off.
 *
 * IT IS A WRITE, AND IT IS SHAPED LIKE ONE
 * ----------------------------------------
 * POST, CSRF-protected by the `web` group, throttled, and never reachable by
 * following a link or a prefetch — a GET that spends a day's allowance is a
 * allowance spent by a browser guessing which pages to warm up.
 *
 * EVERY ANSWER SAYS WHY, IN WORDS
 * -------------------------------
 * The shell prints `message` verbatim into the launch panel, so the refusal a
 * reviewer reads is written here, once, next to the rule that produced it —
 * rather than assembled in JavaScript from a status code.
 */
class VirtualDriveGoogleLaunchController extends Controller
{
    public function __construct(
        private readonly VirtualDriveGoogleLaunchLedger $ledger,
        private readonly VirtualDriveGoogleAuthBlock $authBlock,
    ) {}

    /** POST /dev/virtual-drive/api/google-launch */
    public function claim(): JsonResponse
    {
        $decision = $this->ledger->claim();

        if ($decision['granted'] === true) {
            return response()->json($decision + [
                'message'    => $this->grantedMessage($decision),
                // The only place this key is ever emitted. See the class docblock.
                'credential' => VirtualDriveGoogleGate::browserKey(),
            ]);
        }

        if ($decision['reason'] === VirtualDriveGoogleLaunchLedger::REASON_AUTH_FAILURE_BLOCKED) {
            // The recorded cause travels with the refusal, so a page opened after
            // the failure shows the same error code and origin as the page that hit it.
            $block = $this->blockOrUnreadable();

            return response()->json($decision + [
                'message'      => $this->authBlock->refusalMessage($block),
                'auth_failure' => $this->authBlock->publicView($block),
            ], $this->status($decision));
        }

        return response()->json($decision + ['message' => $this->refusedMessage($decision)], $this->status($decision));
    }

    /** @return array<string,mixed> */
    private function blockOrUnreadable(): array
    {
        try {
            return $this->authBlock->current() ?? [];
        } catch (\Throwable $e) {
            return ['message' => 'The recorded auth-failure block could not be read; launches stay refused.'];
        }
    }

    /** @param array{limit:int,used:int,remaining:int,day:string} $decision */
    private function grantedMessage(array $decision): string
    {
        return 'Launch ' . $decision['used'] . ' of ' . $decision['limit'] . ' for ' . $decision['day']
            . '. ' . $decision['remaining'] . ' left today across this proof environment.';
    }

    /** @param array{reason:?string,limit:int,used:int,day:string} $decision */
    private function refusedMessage(array $decision): string
    {
        switch ($decision['reason']) {
            case VirtualDriveGoogleLaunchLedger::REASON_LIMIT_REACHED:
                return 'Daily Google Street View limit reached: all ' . $decision['limit'] . ' launches for '
                    . $decision['day'] . ' have been used across this proof environment. No Maps JavaScript '
                    . 'library was loaded and nothing was requested from Google. The limit resets at midnight ('
                    . config('app.timezone') . '); raise ' . VirtualDriveGoogleGate::LIMIT_FLAG
                    . ' only deliberately. Apple Look Around and every listing on this page still work.';

            case VirtualDriveGoogleLaunchLedger::REASON_LEDGER_UNAVAILABLE:
                return 'The daily launch tally could not be read or written, so this launch is refused rather '
                    . 'than started uncounted. Nothing was requested from Google.';

            default:
                // The standing reasons — switched off, no ceiling configured, no
                // key — are the gate's own words, so the page and this endpoint
                // cannot describe the same state two different ways.
                return VirtualDriveGoogleGate::refusalReason()
                    ?? 'Google Street View cannot be launched in this proof environment.';
        }
    }

    /** @param array{reason:?string} $decision */
    private function status(array $decision): int
    {
        if ($decision['reason'] === VirtualDriveGoogleLaunchLedger::REASON_LIMIT_REACHED) {
            return 429;
        }

        if ($decision['reason'] === VirtualDriveGoogleLaunchLedger::REASON_LEDGER_UNAVAILABLE) {
            return 503;
        }

        if ($decision['reason'] === VirtualDriveGoogleLaunchLedger::REASON_AUTH_FAILURE_BLOCKED) {
            return 423;
        }

        return 403;
    }
}
