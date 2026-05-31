<?php

namespace Tests\Feature;

use App\Models\BuyerCriteriaAuction;
use App\Models\ByaBetaAccessLog;
use App\Models\ByaReviewLog;
use App\Models\ListingCompatibilityScore;
use App\Models\User;
use App\Services\Bya\ByaCompatibilityAccessResolver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Feature tests for the BYA General Availability access layer (Milestone 15).
 *
 * Covers all scenarios listed in Requirements §10:
 *  §1  — GA flag disabled → 403 (not_in_rollout / feature path)
 *  §2  — Kill switch denies even when beta and GA are enabled
 *  §3  — allowed_user_ids grants GA access
 *  §4  — rollout_percentage=0 denies non-listed user
 *  §5  — rollout_percentage=100 allows eligible user
 *  §6  — Deterministic bucket is stable across resolver calls
 *  §7  — Agent denied under GA
 *  §8  — Unrelated consumer denied under GA
 *  §9  — Unapproved report denied under GA
 *  §10 — approved status allowed under GA
 *  §11 — approved_with_notes allowed under GA
 *  §12 — Dashboard link hidden when ineligible (kill switch on)
 *  §13 — Dashboard link shown when eligible (GA path)
 *  §14 — No internal metadata in rendered output
 *  §15 — No scores/rankings/recommendations in rendered output
 *  §16 — Access log written for allowed and denied attempts
 *  §17 — Beta and GA flags are independent
 *  §18 — Kill switch denial reason is kill_switch_active
 *  §19 — not_in_rollout logged when GA on but no bucket/allowlist match
 */
class ByaGaAccessTest extends TestCase
{
    use DatabaseTransactions;

    private const ROUTE = 'bya.consumer.beta.compatibility-report';

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function makeUser(string $type = 'buyer'): User
    {
        return User::factory()->create(['user_type' => $type]);
    }

    private function makeBuyerListing(User $user): int
    {
        return DB::table('buyer_criteria_auctions')->insertGetId([
            'user_id'    => $user->id,
            'buyer_id'   => $user->id,
            'max_price'  => 100000,
            'title'      => 'GA Test Buyer Listing',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeScore(string $demandType = 'buyer', int $demandListingId = 1): ListingCompatibilityScore
    {
        $now = now();
        return ListingCompatibilityScore::create([
            'demand_listing_type'                => $demandType,
            'demand_listing_id'                  => $demandListingId,
            'supply_listing_type'                => 'seller_agent_auction',
            'supply_listing_id'                  => 1,
            'version'                            => 1,
            'scoring_framework_version'          => 'v1',
            'demand_listing_updated_at_snapshot' => $now,
            'supply_listing_updated_at_snapshot' => $now,
            'computed_at'                        => $now,
            'created_at'                         => $now,
            'compatibility_framework_version'    => 'BYA_COMPAT_V1',
            'compatibility_trait_results'        => [],
            'moderation_status'                  => 'pending_review',
        ]);
    }

    private function approveScore(ListingCompatibilityScore $score, string $status = 'approved'): void
    {
        $admin = User::factory()->create(['user_type' => 'admin']);
        ByaReviewLog::create([
            'listing_compatibility_score_id' => $score->id,
            'reviewer_user_id'               => $admin->id,
            'status'                         => $status,
            'notes'                          => null,
            'fair_housing_checklist'         => null,
        ]);
    }

    /** Enable GA-only mode (beta off, kill switch off, rollout 0, no allowlist) */
    private function enableGaOnly(): void
    {
        Config::set('bya_consumer_beta.consumer_beta_enabled', false);
        Config::set('bya_compatibility.ga_enabled', true);
        Config::set('bya_compatibility.kill_switch', false);
        Config::set('bya_compatibility.rollout_percentage', 0);
        Config::set('bya_compatibility.allowed_user_ids', []);
    }

    /** Enable GA with full rollout (0–100 bucket always passes) */
    private function enableGaFullRollout(): void
    {
        $this->enableGaOnly();
        Config::set('bya_compatibility.rollout_percentage', 100);
    }

    /** Activate the kill switch (both beta and GA blocked) */
    private function activateKillSwitch(): void
    {
        Config::set('bya_compatibility.kill_switch', true);
        Config::set('bya_consumer_beta.consumer_beta_enabled', true);
        Config::set('bya_compatibility.ga_enabled', true);
    }

    private function logCountFor(int $scoreId): int
    {
        return ByaBetaAccessLog::where('listing_compatibility_score_id', $scoreId)->count();
    }

    private function latestLog(int $scoreId): ?ByaBetaAccessLog
    {
        return ByaBetaAccessLog::where('listing_compatibility_score_id', $scoreId)
            ->latest('id')
            ->first();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // §1 — GA flag disabled, beta off → feature_disabled
    // ─────────────────────────────────────────────────────────────────────────

    public function test_ga_flag_disabled_and_beta_off_denies_access(): void
    {
        Config::set('bya_consumer_beta.consumer_beta_enabled', false);
        Config::set('bya_compatibility.ga_enabled', false);
        Config::set('bya_compatibility.kill_switch', false);

        $user      = $this->makeUser('buyer');
        $listingId = $this->makeBuyerListing($user);
        $score     = $this->makeScore('buyer', $listingId);
        $this->approveScore($score);

        $response = $this->actingAs($user)->get(route(self::ROUTE, $score->id));

        $response->assertStatus(403);
        $log = $this->latestLog($score->id);
        $this->assertNotNull($log);
        $this->assertFalse((bool) $log->allowed);
        $this->assertSame('feature_disabled', $log->denial_reason);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // §2 — Kill switch denies even when beta AND GA are enabled
    // ─────────────────────────────────────────────────────────────────────────

    public function test_kill_switch_denies_when_both_flags_enabled(): void
    {
        $this->activateKillSwitch();

        $user      = $this->makeUser('buyer');
        $listingId = $this->makeBuyerListing($user);
        $score     = $this->makeScore('buyer', $listingId);
        $this->approveScore($score);

        $response = $this->actingAs($user)->get(route(self::ROUTE, $score->id));

        $response->assertStatus(403);
        $log = $this->latestLog($score->id);
        $this->assertNotNull($log);
        $this->assertFalse((bool) $log->allowed);
        $this->assertSame('kill_switch_active', $log->denial_reason);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // §3 — allowed_user_ids grants GA access regardless of rollout_percentage
    // ─────────────────────────────────────────────────────────────────────────

    public function test_allowlist_grants_ga_access_at_zero_rollout(): void
    {
        $user      = $this->makeUser('buyer');
        $listingId = $this->makeBuyerListing($user);
        $score     = $this->makeScore('buyer', $listingId);
        $this->approveScore($score);

        $this->enableGaOnly();
        Config::set('bya_compatibility.rollout_percentage', 0);
        Config::set('bya_compatibility.allowed_user_ids', [$user->id]);

        $response = $this->actingAs($user)->get(route(self::ROUTE, $score->id));

        $response->assertStatus(200);
        $log = $this->latestLog($score->id);
        $this->assertTrue((bool) $log->allowed);
        $this->assertNull($log->denial_reason);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // §4 — rollout_percentage=0, non-listed user → not_in_rollout
    // ─────────────────────────────────────────────────────────────────────────

    public function test_zero_rollout_and_no_allowlist_denies_with_not_in_rollout(): void
    {
        $user      = $this->makeUser('buyer');
        $listingId = $this->makeBuyerListing($user);
        $score     = $this->makeScore('buyer', $listingId);
        $this->approveScore($score);

        $this->enableGaOnly();
        Config::set('bya_compatibility.rollout_percentage', 0);
        Config::set('bya_compatibility.allowed_user_ids', []);

        $response = $this->actingAs($user)->get(route(self::ROUTE, $score->id));

        $response->assertStatus(403);
        $log = $this->latestLog($score->id);
        $this->assertFalse((bool) $log->allowed);
        $this->assertSame('not_in_rollout', $log->denial_reason);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // §5 — rollout_percentage=100 allows all eligible users
    // ─────────────────────────────────────────────────────────────────────────

    public function test_full_rollout_allows_eligible_user(): void
    {
        $user      = $this->makeUser('buyer');
        $listingId = $this->makeBuyerListing($user);
        $score     = $this->makeScore('buyer', $listingId);
        $this->approveScore($score);

        $this->enableGaFullRollout();

        $response = $this->actingAs($user)->get(route(self::ROUTE, $score->id));

        $response->assertStatus(200);
        $log = $this->latestLog($score->id);
        $this->assertTrue((bool) $log->allowed);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // §6 — Deterministic bucket is stable across resolver calls
    // ─────────────────────────────────────────────────────────────────────────

    public function test_rollout_bucket_is_deterministic(): void
    {
        $resolver = app(ByaCompatibilityAccessResolver::class);

        $userId = 42;
        $bucket1 = $resolver->rolloutBucket($userId);
        $bucket2 = $resolver->rolloutBucket($userId);
        $bucket3 = $resolver->rolloutBucket($userId);

        $this->assertSame($bucket1, $bucket2);
        $this->assertSame($bucket2, $bucket3);
        $this->assertGreaterThanOrEqual(0, $bucket1);
        $this->assertLessThan(100, $bucket1);

        // Different user IDs produce values in 0–99
        foreach ([1, 2, 99, 100, 9999] as $id) {
            $b = $resolver->rolloutBucket($id);
            $this->assertGreaterThanOrEqual(0, $b, "Bucket for user $id must be >= 0");
            $this->assertLessThan(100, $b, "Bucket for user $id must be < 100");
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // §7 — Agent denied under GA
    // ─────────────────────────────────────────────────────────────────────────

    public function test_agent_is_denied_under_ga(): void
    {
        $owner     = $this->makeUser('buyer');
        $agent     = $this->makeUser('agent');
        $listingId = $this->makeBuyerListing($owner);
        $score     = $this->makeScore('buyer', $listingId);
        $this->approveScore($score);

        $this->enableGaFullRollout();

        $response = $this->actingAs($agent)->get(route(self::ROUTE, $score->id));

        $response->assertStatus(403);
        $log = $this->latestLog($score->id);
        $this->assertFalse((bool) $log->allowed);
        $this->assertSame('agent_denied', $log->denial_reason);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // §8 — Unrelated consumer denied under GA
    // ─────────────────────────────────────────────────────────────────────────

    public function test_unrelated_consumer_denied_under_ga(): void
    {
        $owner     = $this->makeUser('buyer');
        $other     = $this->makeUser('buyer');
        $listingId = $this->makeBuyerListing($owner);
        $score     = $this->makeScore('buyer', $listingId);
        $this->approveScore($score);

        $this->enableGaFullRollout();

        $response = $this->actingAs($other)->get(route(self::ROUTE, $score->id));

        $response->assertStatus(403);
        $log = $this->latestLog($score->id);
        $this->assertFalse((bool) $log->allowed);
        $this->assertSame('not_owner', $log->denial_reason);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // §9 — Unapproved report denied under GA
    // ─────────────────────────────────────────────────────────────────────────

    public function test_unapproved_report_denied_under_ga(): void
    {
        $user      = $this->makeUser('buyer');
        $listingId = $this->makeBuyerListing($user);
        $score     = $this->makeScore('buyer', $listingId);
        // No review log — unapproved

        $this->enableGaFullRollout();

        $response = $this->actingAs($user)->get(route(self::ROUTE, $score->id));

        $response->assertStatus(403);
        $log = $this->latestLog($score->id);
        $this->assertFalse((bool) $log->allowed);
        $this->assertSame('report_not_approved', $log->denial_reason);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // §10 — approved status allowed under GA
    // ─────────────────────────────────────────────────────────────────────────

    public function test_approved_report_allowed_under_ga(): void
    {
        $user      = $this->makeUser('buyer');
        $listingId = $this->makeBuyerListing($user);
        $score     = $this->makeScore('buyer', $listingId);
        $this->approveScore($score, 'approved');

        $this->enableGaFullRollout();

        $response = $this->actingAs($user)->get(route(self::ROUTE, $score->id));

        $response->assertStatus(200);
        $log = $this->latestLog($score->id);
        $this->assertTrue((bool) $log->allowed);
        $this->assertNull($log->denial_reason);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // §11 — approved_with_notes allowed under GA
    // ─────────────────────────────────────────────────────────────────────────

    public function test_approved_with_notes_allowed_under_ga(): void
    {
        $user      = $this->makeUser('buyer');
        $listingId = $this->makeBuyerListing($user);
        $score     = $this->makeScore('buyer', $listingId);
        $this->approveScore($score, 'approved_with_notes');

        $this->enableGaFullRollout();

        $response = $this->actingAs($user)->get(route(self::ROUTE, $score->id));

        $response->assertStatus(200);
        $log = $this->latestLog($score->id);
        $this->assertTrue((bool) $log->allowed);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // §12 — Dashboard link hidden when kill switch is active (ineligible)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_dashboard_link_hidden_when_kill_switch_active(): void
    {
        $user      = $this->makeUser('buyer');
        $listingId = $this->makeBuyerListing($user);
        $score     = $this->makeScore('buyer', $listingId);
        $this->approveScore($score, 'approved');

        // Kill switch on — all consumer access blocked
        Config::set('bya_compatibility.kill_switch', true);
        Config::set('bya_consumer_beta.consumer_beta_enabled', true);
        Config::set('bya_compatibility.ga_enabled', true);
        Config::set('bya_compatibility.rollout_percentage', 100);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertStatus(200);
        $reportUrl = route('bya.consumer.beta.compatibility-report', $score->id);
        $response->assertDontSee($reportUrl, false);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // §13 — Dashboard link shown when GA-eligible (kill switch off, rollout 100)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_dashboard_link_shown_when_ga_eligible(): void
    {
        $user      = $this->makeUser('buyer');
        $listingId = $this->makeBuyerListing($user);
        $score     = $this->makeScore('buyer', $listingId);
        $this->approveScore($score, 'approved');

        $this->enableGaFullRollout();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertStatus(200);
        $reportUrl = route('bya.consumer.beta.compatibility-report', $score->id);
        $response->assertSee($reportUrl, false);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // §14 — No internal metadata in rendered output
    // ─────────────────────────────────────────────────────────────────────────

    public function test_no_internal_metadata_in_ga_response(): void
    {
        $user      = $this->makeUser('buyer');
        $listingId = $this->makeBuyerListing($user);
        $score     = $this->makeScore('buyer', $listingId);
        $this->approveScore($score, 'approved');

        $this->enableGaFullRollout();

        $response = $this->actingAs($user)->get(route(self::ROUTE, $score->id));

        $response->assertStatus(200);
        $response->assertDontSee('explanation_key');
        $response->assertDontSee('template_id');
        $response->assertDontSee('trace_keys');
        $response->assertDontSee('reviewer_user_id');
        $response->assertDontSee('overall_score');
        $response->assertDontSee('deal_breaker');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // §15 — No scores/rankings/recommendations in rendered output
    // ─────────────────────────────────────────────────────────────────────────

    public function test_no_scores_rankings_recommendations_in_ga_response(): void
    {
        $user      = $this->makeUser('buyer');
        $listingId = $this->makeBuyerListing($user);
        $score     = $this->makeScore('buyer', $listingId);
        $this->approveScore($score, 'approved');

        $this->enableGaFullRollout();

        $response = $this->actingAs($user)->get(route(self::ROUTE, $score->id));

        $response->assertStatus(200);
        $response->assertDontSee('ranked');
        $response->assertDontSee('recommended');
        $response->assertDontSee('overall_score');
        $response->assertDontSee('match score');
        $response->assertSee('Compatibility Insight');
        $response->assertSee('do not rank, recommend, endorse, approve, or disqualify');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // §16 — Access log written for both allowed and denied GA attempts
    // ─────────────────────────────────────────────────────────────────────────

    public function test_access_log_written_for_allowed_and_denied_ga_attempts(): void
    {
        $user      = $this->makeUser('buyer');
        $listingId = $this->makeBuyerListing($user);
        $score     = $this->makeScore('buyer', $listingId);
        $this->approveScore($score, 'approved');

        // Denied attempt (kill switch on)
        $this->activateKillSwitch();
        $this->actingAs($user)->get(route(self::ROUTE, $score->id));

        // Allowed attempt (GA full rollout)
        $this->enableGaFullRollout();
        $this->actingAs($user)->get(route(self::ROUTE, $score->id));

        $this->assertSame(2, $this->logCountFor($score->id));

        $logs = ByaBetaAccessLog::where('listing_compatibility_score_id', $score->id)
            ->orderBy('id')
            ->get();

        $this->assertFalse((bool) $logs[0]->allowed);
        $this->assertSame('kill_switch_active', $logs[0]->denial_reason);
        $this->assertTrue((bool) $logs[1]->allowed);
        $this->assertNull($logs[1]->denial_reason);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // §17 — Beta and GA flags are independent: beta on keeps GA denial reasons
    // ─────────────────────────────────────────────────────────────────────────

    public function test_beta_and_ga_flags_are_independent(): void
    {
        $user      = $this->makeUser('buyer');
        $listingId = $this->makeBuyerListing($user);
        $score     = $this->makeScore('buyer', $listingId);
        $this->approveScore($score, 'approved');

        // Beta on, GA off — should succeed via beta path (no rollout check)
        Config::set('bya_consumer_beta.consumer_beta_enabled', true);
        Config::set('bya_compatibility.ga_enabled', false);
        Config::set('bya_compatibility.kill_switch', false);
        Config::set('bya_compatibility.rollout_percentage', 0);
        Config::set('bya_compatibility.allowed_user_ids', []);

        $response = $this->actingAs($user)->get(route(self::ROUTE, $score->id));
        $response->assertStatus(200);

        // Now flip: GA on, beta off, rollout 0, no allowlist — should fail with not_in_rollout
        Config::set('bya_consumer_beta.consumer_beta_enabled', false);
        Config::set('bya_compatibility.ga_enabled', true);

        $response2 = $this->actingAs($user)->get(route(self::ROUTE, $score->id));
        $response2->assertStatus(403);

        $log = $this->latestLog($score->id);
        $this->assertSame('not_in_rollout', $log->denial_reason);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // §18 — kill_switch_active is the denial reason when kill switch is on
    // ─────────────────────────────────────────────────────────────────────────

    public function test_kill_switch_denial_reason_is_kill_switch_active(): void
    {
        Config::set('bya_compatibility.kill_switch', true);
        Config::set('bya_consumer_beta.consumer_beta_enabled', true);
        Config::set('bya_compatibility.ga_enabled', true);
        Config::set('bya_compatibility.rollout_percentage', 100);
        Config::set('bya_compatibility.allowed_user_ids', []);

        $user      = $this->makeUser('buyer');
        $listingId = $this->makeBuyerListing($user);
        $score     = $this->makeScore('buyer', $listingId);
        $this->approveScore($score, 'approved');

        $response = $this->actingAs($user)->get(route(self::ROUTE, $score->id));

        $response->assertStatus(403);
        $log = $this->latestLog($score->id);
        $this->assertSame('kill_switch_active', $log->denial_reason);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // §19 — not_in_rollout logged when GA on but no bucket/allowlist match
    // ─────────────────────────────────────────────────────────────────────────

    public function test_not_in_rollout_logged_when_ga_on_but_no_match(): void
    {
        $user      = $this->makeUser('buyer');
        $listingId = $this->makeBuyerListing($user);
        $score     = $this->makeScore('buyer', $listingId);
        $this->approveScore($score, 'approved');

        $this->enableGaOnly();
        Config::set('bya_compatibility.rollout_percentage', 0);
        Config::set('bya_compatibility.allowed_user_ids', []);

        $response = $this->actingAs($user)->get(route(self::ROUTE, $score->id));

        $response->assertStatus(403);
        $log = $this->latestLog($score->id);
        $this->assertNotNull($log);
        $this->assertSame('not_in_rollout', $log->denial_reason);
        $this->assertFalse((bool) $log->allowed);
        $this->assertSame($user->id, $log->user_id);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Resolver unit — resolveConsumerOwnerUserId returns null for unknown type
    // ─────────────────────────────────────────────────────────────────────────

    public function test_resolver_returns_null_for_unknown_demand_listing_type(): void
    {
        $resolver = app(ByaCompatibilityAccessResolver::class);

        $score = $this->makeScore('unknown_type', 1);
        $result = $resolver->resolveConsumerOwnerUserId($score);

        $this->assertNull($result);
    }
}
