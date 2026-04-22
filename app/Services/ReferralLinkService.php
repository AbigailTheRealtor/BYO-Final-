<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * ReferralLinkService
 *
 * Phase 3 — Referral link generation and retrieval.
 * Phase 5 — Signup persistence hook.
 */
class ReferralLinkService
{
    // ─────────────────────────────────────────────────────────────────────────
    // Phase 3 — Link generation
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Return an existing active referral link for the agent, or create one.
     * Returns: id, agent_id, code, is_active, url.
     */
    public static function getOrCreateForAgent(int $agentId): array
    {
        $existing = DB::table('agent_referral_links')
            ->where('agent_id', $agentId)
            ->where('is_active', true)
            ->first();

        if ($existing) {
            return [
                'id'        => $existing->id,
                'agent_id'  => $existing->agent_id,
                'code'      => $existing->code,
                'is_active' => $existing->is_active,
                'url'       => static::buildUrl($existing->code),
            ];
        }

        $code = static::generateUniqueCode($agentId);
        $now  = now();

        $id = DB::table('agent_referral_links')->insertGetId([
            'agent_id'      => $agentId,
            'code'          => $code,
            'slug'          => $code,
            'is_active'     => true,
            'click_count'   => 0,
            'signup_count'  => 0,
            'listing_count' => 0,
            'hire_count'    => 0,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);

        return [
            'id'        => $id,
            'agent_id'  => $agentId,
            'code'      => $code,
            'is_active' => true,
            'url'       => static::buildUrl($code),
        ];
    }

    /**
     * Generate a unique, short, human-shareable referral code.
     * Format: agt{agentId}-{6 random alphanumeric chars} e.g. agt11-k7x2qm
     */
    public static function generateUniqueCode(int $agentId): string
    {
        do {
            $code = 'agt' . $agentId . '-' . Str::lower(Str::random(6));
        } while (
            DB::table('agent_referral_links')->where('code', $code)->exists()
        );

        return $code;
    }

    /**
     * Build the full absolute shareable URL for a given code (/invite/{code}).
     */
    public static function buildUrl(string $code): string
    {
        return route('referral.capture', ['code' => $code]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Phase 5 — Signup persistence
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Called immediately after a new user account is successfully created.
     *
     * Reads the referral session set by Phase 4 (ReferralController::capture)
     * and persists attribution onto the user record, referral_visits row, and
     * the agent_referral_links signup_count.
     *
     * Rules:
     *  • First-click-wins: never overwrites an existing referred_by_agent_id.
     *  • If visit_id is missing, still attaches referral to the user gracefully.
     *  • If visit row is missing, fails gracefully — signup is never affected.
     *  • Does NOT clear session — Phase 6 (listing attribution) still needs it.
     *  • Fully wrapped in try/catch — never crashes the registration flow.
     *
     * Session keys consumed (read-only here):
     *   referral.agent_id, referral.code, referral.captured_at, referral.visit_id
     */
    public static function persistSignup(int $userId): void
    {
        try {
            $agentId = session('referral.agent_id');

            // Nothing to do if no referral was captured in this session.
            if (empty($agentId)) {
                return;
            }

            // Guard: never overwrite an existing attribution on this user.
            $existing = DB::table('users')
                ->where('id', $userId)
                ->whereNotNull('referred_by_agent_id')
                ->exists();

            if ($existing) {
                return;
            }

            $code        = session('referral.code');
            $capturedAt  = session('referral.captured_at');

            // ── 1. Persist referral fields on the user record ────────────
            DB::table('users')->where('id', $userId)->update([
                'referred_by_agent_id' => $agentId,
                'referral_source_code' => $code,
                'referral_captured_at' => $capturedAt,
                'updated_at'           => now(),
            ]);

            // ── 2. Update referral_visits row if visit_id is available ───
            $visitId = session('referral.visit_id');
            if ($visitId) {
                DB::table('referral_visits')
                    ->where('id', $visitId)
                    ->update([
                        'visitor_user_id'     => $userId,
                        'converted_to_signup' => true,
                        'updated_at'          => now(),
                    ]);
            }

            // ── 3. Increment signup_count on the referral link (atomic) ──
            DB::table('agent_referral_links')
                ->where('agent_id', $agentId)
                ->where('code', $code)
                ->where('is_active', true)
                ->increment('signup_count');

            // Session preserved — Phase 6 (listing attribution) reads it.

        } catch (\Throwable $e) {
            Log::error('ReferralLinkService::persistSignup failed', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);
            // Never propagate — signup must succeed regardless.
        }
    }
}
