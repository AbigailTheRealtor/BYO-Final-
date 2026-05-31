<?php

namespace App\Providers;

use App\Models\ByaReviewLog;
use App\Models\ListingCompatibilityScore;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        /*
         * offer-playoff
         *
         * Single Gate for all Offer Playoff access (routes, Blade, Livewire).
         * Backing store is config('offer.playoff_access.allowed_user_ids').
         * Set that value to '*' for open rollout, or swap the logic here for
         * a DB flag / subscription check — no callers need to change.
         */
        Gate::define('offer-playoff', function ($user) {
            // Admins always have oversight access
            if ($user->user_type === 'admin') {
                return true;
            }

            $allowed = config('offer.playoff_access.allowed_user_ids', []);

            // '*' = open to all authenticated users (future rollout)
            if ($allowed === '*') {
                return true;
            }

            return in_array($user->id, (array) $allowed);
        });

        /*
         * bya-beta-access
         *
         * Single source of truth for the hidden BYA compatibility beta route.
         * Accepts the authenticated $user and the ListingCompatibilityScore being
         * requested. Enforces ALL four conditions required by Milestone 13:
         *
         *   1. Feature flag `bya_beta.hidden_beta_enabled` is true.
         *   2. User ID is in `bya_beta.allowed_user_ids`.
         *   3. The ListingCompatibilityScore's latest ByaReviewLog has status
         *      'approved' or 'approved_with_notes'.
         *   (Condition 4 — authenticated — is guaranteed by the `auth` middleware
         *    running before `bya.beta.access` on the route; the Gate receives a
         *    non-null $user when invoked.)
         *
         * Returns a named Response::deny() so callers can extract the reason
         * for audit logging via Gate::inspect().
         *
         * Admins are NOT automatically granted access; they must be allow-listed.
         * Agents on the allow-list are granted access (explicit list overrides role).
         */
        Gate::define('bya-beta-access', function ($user, ListingCompatibilityScore $score) {
            if (!config('bya_beta.hidden_beta_enabled', false)) {
                return Response::deny('feature_flag_disabled');
            }

            $allowedIds = config('bya_beta.allowed_user_ids', []);
            if (!in_array($user->id, (array) $allowedIds, true)) {
                return Response::deny('not_allow_listed');
            }

            $latestLog = ByaReviewLog::where('listing_compatibility_score_id', $score->id)
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$latestLog || !in_array($latestLog->status, ['approved', 'approved_with_notes'], true)) {
                return Response::deny('report_not_approved');
            }

            return Response::allow();
        });

        /*
         * bya-consumer-beta-access
         *
         * Single source of truth for the Milestone 14 consumer BYA compatibility beta route.
         * Accepts the authenticated $user and the ListingCompatibilityScore being requested.
         * Enforces ALL four eligibility conditions in order:
         *
         *   1. Feature flag `bya_consumer_beta.consumer_beta_enabled` is true.
         *   2. User is not an agent (`user_type !== 'agent'`).
         *   3. Ownership resolver confirms this user owns the demand-side listing.
         *   4. The ListingCompatibilityScore's latest ByaReviewLog has status
         *      'approved' or 'approved_with_notes'.
         *
         * Returns a named Response::deny() so callers can extract the reason
         * for audit logging via Gate::inspect().
         *
         * The ownership resolver switches on demand_listing_type:
         *   'buyer'  → BuyerCriteriaAuction
         *   'tenant' → TenantCriteriaAuction
         * Any unrecognised type, missing record, or missing user_id → null → denied.
         * The resolver never infers or falls back to alternative fields.
         */
        Gate::define('bya-consumer-beta-access', function ($user, ListingCompatibilityScore $score) {
            if (!config('bya_consumer_beta.consumer_beta_enabled', false)) {
                return Response::deny('feature_flag_disabled');
            }

            if ($user->user_type === 'agent') {
                return Response::deny('agent_denied');
            }

            $resolvedUserId = self::resolveConsumerOwnerUserId($score);
            if ($resolvedUserId === null || $resolvedUserId !== $user->id) {
                return Response::deny('not_owner');
            }

            $latestLog = ByaReviewLog::where('listing_compatibility_score_id', $score->id)
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$latestLog || !in_array($latestLog->status, ['approved', 'approved_with_notes'], true)) {
                return Response::deny('report_not_approved');
            }

            return Response::allow();
        });
    }

    /**
     * Resolve the owning user_id for the demand-side listing of a score.
     *
     * Switches on demand_listing_type:
     *   'buyer'  → BuyerCriteriaAuction::find($score->demand_listing_id)?->user_id
     *   'tenant' → TenantCriteriaAuction::find($score->demand_listing_id)?->user_id
     *
     * Returns null immediately for any unrecognised type, missing record, or
     * missing user_id. Never infers, falls back, or uses probabilistic matching.
     */
    private static function resolveConsumerOwnerUserId(ListingCompatibilityScore $score): ?int
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
            // The tenant_criteria_auctions table may not exist in all environments.
            // Check before querying to avoid aborting the active PostgreSQL transaction.
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
