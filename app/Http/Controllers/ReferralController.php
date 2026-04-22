<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ReferralController
 *
 * Phase 4 — Referral capture entry point only.
 *
 * GET /invite/{code}  →  named: referral.capture
 *
 * Responsibilities:
 *  • Validate the referral code against agent_referral_links.
 *  • Enforce first-click-wins: never overwrite an existing session attribution.
 *  • Write one referral_visits row per new attribution.
 *  • Increment click_count on the referral link.
 *  • Always redirect safely to the homepage — no crashes exposed to the user.
 *
 * Gap-1 addition:
 *  • Set a 30-day referral_code cookie on every fresh (new) attribution so
 *    attribution survives session expiry.  The cookie is set ONLY when the
 *    session is also being set (i.e. on a genuine new attribution event) to
 *    keep the cookie and session in sync.  No extra visit rows and no extra
 *    click_count increments are created on revisits.
 */
class ReferralController extends Controller
{
    /**
     * Session key namespace for referral attribution.
     */
    private const SESSION_NS = 'referral';

    /** Cookie name used for cross-session fallback. */
    private const COOKIE_NAME = 'referral_code';

    /** Cookie lifetime in minutes (30 days). */
    private const COOKIE_MINUTES = 60 * 24 * 30;

    public function capture(Request $request, string $code)
    {
        // Holds the code to bake into the cookie; set only on a fresh attribution.
        $cookieCode = null;

        try {
            // ── 1. Look up the referral link ────────────────────────────
            $link = DB::table('agent_referral_links')
                ->where('code', $code)
                ->where('is_active', true)
                ->first();

            if (!$link) {
                // Invalid or inactive code — redirect quietly, no error flash.
                return redirect()->route('home');
            }

            // ── 2. First-click-wins: never overwrite an existing attribution ──
            if (session(self::SESSION_NS . '.agent_id') !== null) {
                // Attribution already locked in session — still redirect safely.
                // Cookie is NOT refreshed here; it was already set on the original
                // capture event and must not be overwritten with a potentially
                // different code from a second link click.
                return redirect()->route('home');
            }

            // ── 3. Store attribution in session ─────────────────────────
            $capturedAt = now();
            session([
                self::SESSION_NS . '.agent_id'    => $link->agent_id,
                self::SESSION_NS . '.code'         => $link->code,
                self::SESSION_NS . '.captured_at'  => $capturedAt->toIso8601String(),
            ]);

            // ── 4. Write referral_visits row ─────────────────────────────
            $visitId = DB::table('referral_visits')->insertGetId([
                'agent_id'                => $link->agent_id,
                'referral_code'           => $link->code,
                'session_id'              => session()->getId(),
                'ip_address'              => $request->ip(),
                'user_agent'              => $request->userAgent(),
                'landing_url'             => $request->fullUrl(),
                'converted_to_signup'     => false,
                'converted_to_listing'    => false,
                'converted_to_hire'       => false,
                'created_at'              => $capturedAt,
                'updated_at'              => $capturedAt,
            ]);

            // Store visit_id so downstream phases can update the row.
            session([self::SESSION_NS . '.visit_id' => $visitId]);

            // ── 5. Increment click_count ─────────────────────────────────
            DB::table('agent_referral_links')
                ->where('id', $link->id)
                ->increment('click_count');

            // ── 6. Mark code for cookie (set below, outside try/catch) ───
            $cookieCode = $link->code;

        } catch (\Throwable $e) {
            // Never expose DB errors to the visitor.
            Log::error('ReferralController::capture failed', [
                'code'  => $code,
                'error' => $e->getMessage(),
            ]);
        }

        // ── 7. Attach referral cookie to the redirect ────────────────────────
        // Only set when a fresh attribution was successfully recorded above.
        // HttpOnly=true, SameSite=Lax, Secure=true on HTTPS environments.
        $response = redirect()->route('home');

        if ($cookieCode !== null) {
            $response = $response->withCookie(
                cookie(
                    self::COOKIE_NAME,
                    $cookieCode,
                    self::COOKIE_MINUTES,
                    '/',
                    null,
                    $request->secure(), // Secure flag follows the current scheme
                    true,               // HttpOnly
                    false,              // raw
                    'Lax'               // SameSite
                )
            );
        }

        return $response;
    }
}
