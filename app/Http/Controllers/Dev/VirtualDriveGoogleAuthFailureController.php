<?php

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use App\Support\VirtualDrive\VirtualDriveGoogleAuthBlock;
use App\Support\VirtualDrive\VirtualDriveGoogleLaunchLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Where the Google proof page reports that Google rejected the browser key.
 *
 * Development-only: the route sits behind `virtual-drive-proof`, so it 404s
 * everywhere VirtualDriveProofGate refuses, production first.
 *
 * ONE DIRECTION ONLY
 * ------------------
 * A report sets VirtualDriveGoogleAuthBlock, which stops every later launch
 * claim. Nothing this endpoint accepts can grant a launch, refund one, or clear
 * a block — clearing is the reset command's alone. So the report being
 * untrusted browser input is acceptable: at worst it stops a development proof.
 *
 * THE SERVER ADDS WHAT ONLY IT CAN SEE
 * ------------------------------------
 * The browser describes its own origin, but the `Origin` header on this POST is
 * what the browser actually sends cross-site — scheme, host AND port — and is
 * the value to compare with the key's website restrictions. It is recorded next
 * to the page's own claim, with the day's tally at the moment of failure.
 */
class VirtualDriveGoogleAuthFailureController extends Controller
{
    public function __construct(
        private readonly VirtualDriveGoogleAuthBlock $authBlock,
        private readonly VirtualDriveGoogleLaunchLedger $ledger,
    ) {}

    /** POST /dev/virtual-drive/api/google-auth-failure */
    public function report(Request $request): JsonResponse
    {
        $tally = $this->ledger->peek();

        try {
            $block = $this->authBlock->record((array) $request->json()->all(), [
                'request_origin'  => $request->headers->get('Origin'),
                'request_referer' => $request->headers->get('Referer'),
                'ledger_used'     => $tally['readable'] ? $tally['used'] : null,
                'ledger_limit'    => $tally['limit'],
                'day'             => $tally['day'],
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'blocked' => false,
                'message' => 'Google rejected the browser key, but the proof environment could not record the block. '
                    . 'This page stays stopped; do not launch again from another page until the key is fixed.',
            ], 503);
        }

        return response()->json([
            'blocked'      => true,
            'message'      => $this->authBlock->refusalMessage($block),
            'auth_failure' => $this->authBlock->publicView($block),
        ]);
    }
}
