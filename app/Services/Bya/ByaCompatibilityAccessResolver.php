<?php

namespace App\Services\Bya;

use App\Models\ByaReviewLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ByaCompatibilityAccessResolver
 *
 * Single source of truth for all consumer-facing BYA compatibility access decisions.
 * Used by the bya-consumer-beta-access Gate callback, ByaConsumerBetaAccessMiddleware,
 * and the dashboard controller to gate link rendering.
 *
 * Admin preview routes must NOT use this resolver — they have their own auth guard.
 *
 * Possible denial reasons (checked in order):
 *   kill_switch_active  — kill switch is on; overrides beta and GA immediately
 *   feature_disabled    — neither consumer_beta_enabled nor ga_enabled is true
 *   agent_denied        — user is an agent (user_type === 'agent')
 *   not_owner           — ownership of the demand-side listing cannot be confirmed
 *   report_not_approved — latest review log is absent or not approved/approved_with_notes
 *   not_in_rollout      — GA path: user not in allowed_user_ids and outside rollout bucket
 *
 * Unauthenticated attempts never reach this resolver — they are blocked at the auth
 * middleware layer before any route guard is invoked.
 */
class ByaCompatibilityAccessResolver
{
    /**
     * Resolve consumer eligibility for a BYA compatibility report.
     *
     * @param  \App\Models\User                          $user
     * @param  \App\Models\ListingCompatibilityScore     $score
     * @return array{allowed: bool, denial_reason: string|null}
     */
    public function resolve($user, $score): array
    {
        // ── 1. Kill switch — short-circuits all consumer access ──────────────
        if (config('bya_compatibility.kill_switch', true)) {
            return ['allowed' => false, 'denial_reason' => 'kill_switch_active'];
        }

        $betaEnabled = config('bya_consumer_beta.consumer_beta_enabled', false);
        $gaEnabled   = config('bya_compatibility.ga_enabled', false);

        // ── 2. At least one feature path must be active ───────────────────────
        if (!$betaEnabled && !$gaEnabled) {
            return ['allowed' => false, 'denial_reason' => 'feature_disabled'];
        }

        // ── 3. Agents are never eligible for consumer reports ─────────────────
        if ($user->user_type === 'agent') {
            return ['allowed' => false, 'denial_reason' => 'agent_denied'];
        }

        // ── 4. Ownership must be proven — never inferred ──────────────────────
        $ownerId = $this->resolveConsumerOwnerUserId($score);
        if ($ownerId === null || $ownerId !== $user->id) {
            return ['allowed' => false, 'denial_reason' => 'not_owner'];
        }

        // ── 5. Report must carry an approved review log ───────────────────────
        $latestLog = ByaReviewLog::where('listing_compatibility_score_id', $score->id)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$latestLog || !in_array($latestLog->status, ['approved', 'approved_with_notes'], true)) {
            return ['allowed' => false, 'denial_reason' => 'report_not_approved'];
        }

        // ── 6. Beta path — open to all eligible consumers ────────────────────
        if ($betaEnabled) {
            return ['allowed' => true, 'denial_reason' => null];
        }

        // ── 7. GA path — allowlist or deterministic rollout bucket ────────────
        $allowedIds = (array) config('bya_compatibility.allowed_user_ids', []);
        if (in_array($user->id, $allowedIds, true)) {
            return ['allowed' => true, 'denial_reason' => null];
        }

        $rolloutPct = (int) config('bya_compatibility.rollout_percentage', 0);
        $bucket     = $this->rolloutBucket($user->id);
        if ($bucket < $rolloutPct) {
            return ['allowed' => true, 'denial_reason' => null];
        }

        return ['allowed' => false, 'denial_reason' => 'not_in_rollout'];
    }

    /**
     * Resolve GA-only eligibility for a BYA compatibility report.
     *
     * Identical to resolve() except it only evaluates the GA path — it never
     * falls through to the beta path. Use this for diagnostics where you need
     * to know whether the user qualifies specifically under GA rules (independent
     * of whether the consumer beta is enabled).
     *
     * @param  \App\Models\User                          $user
     * @param  \App\Models\ListingCompatibilityScore     $score
     * @return array{allowed: bool, denial_reason: string|null}
     */
    public function resolveGaOnly($user, $score): array
    {
        // ── 1. Kill switch ────────────────────────────────────────────────────
        if (config('bya_compatibility.kill_switch', true)) {
            return ['allowed' => false, 'denial_reason' => 'kill_switch_active'];
        }

        // ── 2. GA flag specifically ───────────────────────────────────────────
        if (!config('bya_compatibility.ga_enabled', false)) {
            return ['allowed' => false, 'denial_reason' => 'feature_disabled'];
        }

        // ── 3. Agents are never eligible ──────────────────────────────────────
        if ($user->user_type === 'agent') {
            return ['allowed' => false, 'denial_reason' => 'agent_denied'];
        }

        // ── 4. Ownership must be proven ───────────────────────────────────────
        $ownerId = $this->resolveConsumerOwnerUserId($score);
        if ($ownerId === null || $ownerId !== $user->id) {
            return ['allowed' => false, 'denial_reason' => 'not_owner'];
        }

        // ── 5. Report must carry an approved review log ───────────────────────
        $latestLog = ByaReviewLog::where('listing_compatibility_score_id', $score->id)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$latestLog || !in_array($latestLog->status, ['approved', 'approved_with_notes'], true)) {
            return ['allowed' => false, 'denial_reason' => 'report_not_approved'];
        }

        // ── 6. GA allowlist or deterministic rollout bucket ───────────────────
        $allowedIds = (array) config('bya_compatibility.allowed_user_ids', []);
        if (in_array($user->id, $allowedIds, true)) {
            return ['allowed' => true, 'denial_reason' => null];
        }

        $rolloutPct = (int) config('bya_compatibility.rollout_percentage', 0);
        $bucket     = $this->rolloutBucket($user->id);
        if ($bucket < $rolloutPct) {
            return ['allowed' => true, 'denial_reason' => null];
        }

        return ['allowed' => false, 'denial_reason' => 'not_in_rollout'];
    }

    /**
     * Compute the deterministic 0–99 rollout bucket for a given user ID.
     * Stable across calls: crc32 is deterministic for fixed input.
     *
     * We interpret crc32 as an unsigned 32-bit value to avoid negative modulo
     * issues: if the signed result is negative, add 2^32 to get the unsigned form.
     */
    public function rolloutBucket(int $userId): int
    {
        $crc = crc32((string) $userId);
        if ($crc < 0) {
            $crc += 4294967296; // treat as unsigned 32-bit
        }
        return $crc % 100;
    }

    /**
     * Resolve the owning user_id for the demand-side listing of a score.
     *
     * Switches on demand_listing_type:
     *   'buyer'  → buyer_criteria_auctions.user_id
     *   'tenant' → tenant_criteria_auctions.user_id (table may not exist)
     *
     * Returns null immediately for any unrecognised type, missing record, or
     * missing user_id. Never infers, falls back, or uses probabilistic matching.
     */
    public function resolveConsumerOwnerUserId($score): ?int
    {
        $type = $score->demand_listing_type;
        $id   = $score->demand_listing_id;

        if (!$id) {
            return null;
        }

        if ($type === 'buyer') {
            $row = DB::table('buyer_criteria_auctions')
                ->select('user_id')
                ->where('id', $id)
                ->first();
            return ($row && $row->user_id) ? (int) $row->user_id : null;
        }

        if ($type === 'tenant') {
            if (!Schema::hasTable('tenant_criteria_auctions')) {
                return null;
            }
            $row = DB::table('tenant_criteria_auctions')
                ->select('user_id')
                ->where('id', $id)
                ->first();
            return ($row && $row->user_id) ? (int) $row->user_id : null;
        }

        return null;
    }
}
