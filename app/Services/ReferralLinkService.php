<?php

namespace App\Services;

use App\Models\AcceptedBidSummary;
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
 * Phase 7 — Accepted hire persistence hook.
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
                'id'            => $existing->id,
                'agent_id'      => $existing->agent_id,
                'code'          => $existing->code,
                'is_active'     => $existing->is_active,
                'url'           => static::buildUrl($existing->code),
                'click_count'   => (int) ($existing->click_count   ?? 0),
                'signup_count'  => (int) ($existing->signup_count  ?? 0),
                'listing_count' => (int) ($existing->listing_count ?? 0),
                'hire_count'    => (int) ($existing->hire_count    ?? 0),
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
            'id'            => $id,
            'agent_id'      => $agentId,
            'code'          => $code,
            'is_active'     => true,
            'url'           => static::buildUrl($code),
            'click_count'   => 0,
            'signup_count'  => 0,
            'listing_count' => 0,
            'hire_count'    => 0,
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
     * Attribution priority (first non-empty source wins):
     *   A. Active PHP session  (referral.agent_id)
     *   B. referral_code cookie fallback  ← Gap-1 addition
     *   C. None — method returns with no side effects
     *
     * Session keys consumed (read-only here):
     *   referral.agent_id, referral.code, referral.captured_at, referral.visit_id
     *
     * Cookie key consumed (read-only):
     *   referral_code  (set by ReferralController::capture on a fresh attribution)
     *
     * Rules:
     *  • First-write-wins: never overwrites an existing referred_by_agent_id.
     *  • Session is NOT cleared — Phase 6 still needs it.
     *  • Cookie fallback does NOT fabricate a referral_visits row.
     *  • signup_count is incremented only when a fresh attribution is written.
     *  • Fully wrapped in try/catch — never crashes registration.
     */
    public static function persistSignup(int $userId): void
    {
        try {
            // ── Path A: active session referral ───────────────────────────────
            $agentId    = session('referral.agent_id');
            $code       = session('referral.code');
            $capturedAt = session('referral.captured_at');
            $fromCookie = false;

            // ── Path B: cookie fallback (session was cleared / expired) ───────
            if (empty($agentId)) {
                $cookieCode = request()->cookie('referral_code');

                if (!empty($cookieCode)) {
                    $link = DB::table('agent_referral_links')
                        ->where('code', $cookieCode)
                        ->where('is_active', true)
                        ->first();

                    if ($link) {
                        $agentId    = $link->agent_id;
                        $code       = $link->code;
                        $capturedAt = now()->toIso8601String();
                        $fromCookie = true;
                    }
                    // Stale/invalid cookie: $agentId remains empty, handled below.
                }
            }

            // ── Path C: no attribution source — nothing to do ─────────────────
            if (empty($agentId)) {
                return;
            }

            // ── First-write-wins: never overwrite an existing referral ─────────
            $alreadyAttributed = DB::table('users')
                ->where('id', $userId)
                ->whereNotNull('referred_by_agent_id')
                ->exists();

            if ($alreadyAttributed) {
                return;
            }

            // ── Write referral attribution to the new user ────────────────────
            DB::table('users')->where('id', $userId)->update([
                'referred_by_agent_id' => $agentId,
                'referral_source_code' => $code,
                'referral_captured_at' => $capturedAt,
                'updated_at'           => now(),
            ]);

            // ── Update referral_visits row (session path only) ────────────────
            // Cookie-fallback path: no visit row exists to back-fill; skip silently.
            if (!$fromCookie) {
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
            }

            // ── Increment signup_count (only on fresh attribution write) ───────
            DB::table('agent_referral_links')
                ->where('agent_id', $agentId)
                ->where('code', $code)
                ->where('is_active', true)
                ->increment('signup_count');

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
     * Attribution priority: A. Session referral  B. User fallback  C. None.
     *
     * Also updates referral_visits and increments listing_count atomically.
     * Session is preserved — Phase 7 still needs it.
     * Fully wrapped in try/catch — never crashes listing creation.
     */
    public static function persistListingReferral(Model $auction): void
    {
        try {
            if (!$auction->wasRecentlyCreated) {
                return;
            }

            $already = DB::table($auction->getTable())
                ->where('id', $auction->id)
                ->whereNotNull('referring_agent_id')
                ->exists();

            if ($already) {
                return;
            }

            $agentId    = null;
            $code       = null;
            $capturedAt = null;

            $sessionAgentId = session('referral.agent_id');
            if (!empty($sessionAgentId)) {
                $agentId    = $sessionAgentId;
                $code       = session('referral.code');
                $capturedAt = session('referral.captured_at');
            }

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

            if (empty($agentId)) {
                return;
            }

            DB::table($auction->getTable())
                ->where('id', $auction->id)
                ->update([
                    'referring_agent_id'   => $agentId,
                    'referral_source_code' => $code,
                    'referral_captured_at' => $capturedAt,
                    'referral_locked'      => true,
                    'updated_at'           => now(),
                ]);

            $visitId = session('referral.visit_id');
            if ($visitId) {
                DB::table('referral_visits')
                    ->where('id', $visitId)
                    ->update([
                        'listing_id'           => $auction->id,
                        'converted_to_listing' => true,
                        'updated_at'           => now(),
                    ]);
            }

            DB::table('agent_referral_links')
                ->where('agent_id', $agentId)
                ->where('code', $code)
                ->where('is_active', true)
                ->increment('listing_count');

        } catch (\Throwable $e) {
            Log::error('ReferralLinkService::persistListingReferral failed', [
                'listing_table' => $auction->getTable(),
                'listing_id'    => $auction->id ?? null,
                'error'         => $e->getMessage(),
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Phase 7 — Accepted hire persistence
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Called immediately after AcceptedBidSummary::create() in each
     * generateSummary() service method.
     *
     * Copies referral attribution from the parent listing onto the accepted
     * bid summary row, sets referral_status = 'pending', optionally marks
     * converted_to_hire on the referral_visits row, and atomically increments
     * hire_count on agent_referral_links.
     *
     * Rules:
     *  • Never overwrites an accepted record that already has referring_agent_id.
     *  • Source is listing.referring_agent_id only (not session — the hire
     *    flow happens after listing creation; the listing is the authoritative
     *    attribution record at this point).
     *  • If listing has no referring_agent_id, does nothing.
     *  • If session referral.visit_id is missing or stale, logs gracefully.
     *  • Session is NOT cleared — preserved for any later phases.
     *  • Fully wrapped in try/catch — never crashes the acceptance flow.
     *
     * @param  AcceptedBidSummary  $summary  The newly created summary record.
     * @param  Model               $listing  The parent listing Eloquent model.
     */
    public static function persistAcceptedHireReferral(
        AcceptedBidSummary $summary,
        Model $listing
    ): void {
        try {
            // ── Guard: never overwrite an already-attributed summary ──────────
            if (!empty($summary->referring_agent_id)) {
                return;
            }

            // ── Source: listing-level referral only ───────────────────────────
            $agentId = $listing->referring_agent_id ?? null;
            $code    = $listing->referral_source_code ?? null;

            if (empty($agentId)) {
                return;
            }

            // ── 1. Write referral fields onto the accepted_bid_summaries row ──
            DB::table('accepted_bid_summaries')
                ->where('id', $summary->id)
                ->update([
                    'referring_agent_id'   => $agentId,
                    'referral_source_code' => $code,
                    'referral_status'      => 'pending',
                    'updated_at'           => now(),
                ]);

            // ── 2. Mark converted_to_hire on referral_visits if visit exists ──
            $visitId = session('referral.visit_id');
            if ($visitId) {
                DB::table('referral_visits')
                    ->where('id', $visitId)
                    ->update([
                        'converted_to_hire' => true,
                        'updated_at'        => now(),
                    ]);
            }

            // ── 3. Increment hire_count on agent_referral_links (atomic) ──────
            DB::table('agent_referral_links')
                ->where('agent_id', $agentId)
                ->where('code', $code)
                ->where('is_active', true)
                ->increment('hire_count');

        } catch (\Throwable $e) {
            Log::error('ReferralLinkService::persistAcceptedHireReferral failed', [
                'summary_id' => $summary->id ?? null,
                'listing_id' => $listing->id ?? null,
                'error'      => $e->getMessage(),
            ]);
            // Never propagate — acceptance flow must succeed regardless.
        }
    }
}
