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
 * Out of scope here:
 *  • Signup persistence (Phase 5)
 *  • Listing / accepted-hire persistence (Phase 6)
 *  • Dashboard / admin UI
 *  • Cookie persistence
 */
class ReferralController extends Controller
{
    /**
     * Session key namespace for referral attribution.
     */
    private const SESSION_NS = 'referral';

    public function capture(Request $request, string $code)
    {
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

        } catch (\Throwable $e) {
            // Never expose DB errors to the visitor.
            Log::error('ReferralController::capture failed', [
                'code'  => $code,
                'error' => $e->getMessage(),
            ]);
        }

        return redirect()->route('home');
    }
}
