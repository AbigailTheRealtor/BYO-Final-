<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * ReferralLinkService
 *
 * Phase 3 — Referral link generation and retrieval.
 * Phase 5 — Signup persistence hook.
 * Phase 6 — Listing persistence hook.
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
     * Session keys consumed (read-only here):
     *   referral.agent_id, referral.code, referral.captured_at, referral.visit_id
     *
     * Rules:
     *  • First-click-wins: never overwrites an existing referred_by_agent_id.
     *  • Session is NOT cleared — Phase 6 still needs it.
     *  • Fully wrapped in try/catch — never crashes registration.
     */
    public static function persistSignup(int $userId): void
    {
        try {
            $agentId = session('referral.agent_id');
            if (empty($agentId)) {
                return;
            }

            // Guard: never overwrite an existing attribution on this user.
            $alreadyAttributed = DB::table('users')
                ->where('id', $userId)
                ->whereNotNull('referred_by_agent_id')
                ->exists();

            if ($alreadyAttributed) {
                return;
            }

            $code       = session('referral.code');
            $capturedAt = session('referral.captured_at');

            // 1. Persist referral fields on the user record.
            DB::table('users')->where('id', $userId)->update([
                'referred_by_agent_id' => $agentId,
                'referral_source_code' => $code,
                'referral_captured_at' => $capturedAt,
                'updated_at'           => now(),
            ]);

            // 2. Update referral_visits row if visit_id is in session.
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

            // 3. Increment signup_count on the referral link (atomic).
            DB::table('agent_referral_links')
                ->where('agent_id', $agentId)
                ->where('code', $code)
                ->where('is_active', true)
                ->increment('signup_count');

            // Session is preserved — Phase 6 (listing attribution) reads it.

        } catch (\Throwable $e) {
            Log::error('ReferralLinkService::persistSignup failed', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Phase 6 — Listing persistence
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Called immediately after a listing record is saved.
     *
     * Only acts when $auction->wasRecentlyCreated (brand-new DB row).
     * The caller is responsible for an additional guard on append-only draft
     * versioning — e.g. only calling this on the first draft version.
     *
     * Attribution priority:
     *   A. Session referral  (referral.agent_id / code / captured_at)
     *   B. User-level fallback (users.referred_by_agent_id / …)
     *   C. None — do nothing
     *
     * Also:
     *   • Updates referral_visits if session('referral.visit_id') exists.
     *   • Increments listing_count on agent_referral_links (atomic).
     *   • Does NOT clear session — Phase 7 still needs it.
     *   • Fully wrapped in try/catch — never crashes listing creation.
     */
    public static function persistListingReferral(Model $auction): void
    {
        try {
            // Only act on brand-new rows.
            if (!$auction->wasRecentlyCreated) {
                return;
            }

            // Belt-and-suspenders: skip if already attributed.
            $already = DB::table($auction->getTable())
                ->where('id', $auction->id)
                ->whereNotNull('referring_agent_id')
                ->exists();

            if ($already) {
                return;
            }

            // ── Determine attribution source ─────────────────────────────────
            $agentId    = null;
            $code       = null;
            $capturedAt = null;

            // Priority A: session referral.
            $sessionAgentId = session('referral.agent_id');
            if (!empty($sessionAgentId)) {
                $agentId    = $sessionAgentId;
                $code       = session('referral.code');
                $capturedAt = session('referral.captured_at');
            }

            // Priority B: user-level fallback.
            if (empty($agentId)) {
                $userId = Auth::id();
                if ($userId) {
                    $userRow = DB::table('users')
                        ->where('id', $userId)
                        ->whereNotNull('referred_by_agent_id')
                        ->first();

                    if ($userRow) {
                        $agentId    = $userRow->referred_by_agent_id;
                        $code       = $userRow->referral_source_code;
                        $capturedAt = $userRow->referral_captured_at;
                    }
                }
            }

            // No attribution available.
            if (empty($agentId)) {
                return;
            }

            // ── 1. Write referral fields onto the listing row ─────────────────
            DB::table($auction->getTable())
                ->where('id', $auction->id)
                ->update([
                    'referring_agent_id'  => $agentId,
                    'referral_source_code' => $code,
                    'referral_captured_at' => $capturedAt,
                    'referral_locked'      => true,
                    'updated_at'           => now(),
                ]);

            // ── 2. Update referral_visits row if visit_id is in session ────────
            $visitId = session('referral.visit_id');
            if ($visitId) {
                DB::table('referral_visits')
                    ->where('id', $visitId)
                    ->update([
                        'listing_id'            => $auction->id,
                        'converted_to_listing'  => true,
                        'updated_at'            => now(),
                    ]);
            }

            // ── 3. Increment listing_count on the referral link (atomic) ──────
            DB::table('agent_referral_links')
                ->where('agent_id', $agentId)
                ->where('code', $code)
                ->where('is_active', true)
                ->increment('listing_count');

            // Session is preserved — Phase 7 (hire attribution) reads it.

        } catch (\Throwable $e) {
            Log::error('ReferralLinkService::persistListingReferral failed', [
                'listing_table' => $auction->getTable(),
                'listing_id'    => $auction->id ?? null,
                'error'         => $e->getMessage(),
            ]);
        }
    }
}
