<?php

namespace Tests\Feature;

use App\Models\ByaBetaAccessLog;
use App\Models\ByaReviewLog;
use App\Models\ListingCompatibilityScore;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Feature tests for the BYA Hidden Beta access layer (Milestone 13).
 *
 * Covers:
 *  §1 — Feature flag disabled → 403 + log row with denial_reason = 'feature_flag_disabled'
 *  §2 — Flag enabled, user not on allow-list → 403 + log row 'not_allow_listed'
 *  §3 — Flag enabled, allow-listed user, report not approved → 403 + log row 'report_not_approved'
 *  §4 — Flag enabled, allow-listed user, report approved → 200 + only permitted fields in view
 *  §5 — Agent user not on allow-list → 403 + log row 'not_allow_listed'
 *  §6 — Allow-listed agent user → 200 (explicit allow-list overrides agent block)
 *  All cases assert that exactly one log row is written.
 */
class ByaBetaAccessTest extends TestCase
{
    use DatabaseTransactions;

    private const ROUTE = 'bya.beta.compatibility-report';

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeUser(string $type = 'seller'): User
    {
        return User::factory()->create(['user_type' => $type]);
    }

    private function makeScore(): ListingCompatibilityScore
    {
        $now = now();
        return ListingCompatibilityScore::create([
            'demand_listing_type'                => 'buyer_agent_auction',
            'demand_listing_id'                  => 1,
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

    private function enableBeta(array $allowedUserIds = []): void
    {
        Config::set('bya_beta.hidden_beta_enabled', true);
        Config::set('bya_beta.allowed_user_ids', $allowedUserIds);
    }

    private function disableBeta(): void
    {
        Config::set('bya_beta.hidden_beta_enabled', false);
        Config::set('bya_beta.allowed_user_ids', []);
    }

    private function logCountBefore(int $scoreId): int
    {
        return ByaBetaAccessLog::where('listing_compatibility_score_id', $scoreId)->count();
    }

    // -------------------------------------------------------------------------
    // §1 — Feature flag disabled
    // -------------------------------------------------------------------------

    public function test_flag_disabled_returns_403_and_writes_log(): void
    {
        $this->disableBeta();
        $user  = $this->makeUser();
        $score = $this->makeScore();
        $before = $this->logCountBefore($score->id);

        $response = $this->actingAs($user)->get(route(self::ROUTE, $score->id));

        $response->assertStatus(403);
        $this->assertSame($before + 1, $this->logCountBefore($score->id));

        $log = ByaBetaAccessLog::where('listing_compatibility_score_id', $score->id)
            ->latest('id')->first();
        $this->assertFalse((bool) $log->allowed);
        $this->assertSame('feature_flag_disabled', $log->denial_reason);
        $this->assertSame($user->id, $log->user_id);
    }

    // -------------------------------------------------------------------------
    // §2 — Flag enabled, user not on allow-list
    // -------------------------------------------------------------------------

    public function test_flag_enabled_not_allow_listed_returns_403_and_writes_log(): void
    {
        $user  = $this->makeUser();
        $score = $this->makeScore();
        $this->enableBeta([]);  // empty allow-list
        $before = $this->logCountBefore($score->id);

        $response = $this->actingAs($user)->get(route(self::ROUTE, $score->id));

        $response->assertStatus(403);
        $this->assertSame($before + 1, $this->logCountBefore($score->id));

        $log = ByaBetaAccessLog::where('listing_compatibility_score_id', $score->id)
            ->latest('id')->first();
        $this->assertFalse((bool) $log->allowed);
        $this->assertSame('not_allow_listed', $log->denial_reason);
    }

    // -------------------------------------------------------------------------
    // §3 — Allow-listed user, report not approved
    // -------------------------------------------------------------------------

    public function test_allow_listed_user_unapproved_report_returns_403_and_writes_log(): void
    {
        $user  = $this->makeUser();
        $score = $this->makeScore();
        $this->enableBeta([$user->id]);
        // No review log created — report is not approved
        $before = $this->logCountBefore($score->id);

        $response = $this->actingAs($user)->get(route(self::ROUTE, $score->id));

        $response->assertStatus(403);
        $this->assertSame($before + 1, $this->logCountBefore($score->id));

        $log = ByaBetaAccessLog::where('listing_compatibility_score_id', $score->id)
            ->latest('id')->first();
        $this->assertFalse((bool) $log->allowed);
        $this->assertSame('report_not_approved', $log->denial_reason);
    }

    // -------------------------------------------------------------------------
    // §3b — Allow-listed user, report has non-approved status (e.g. 'pending_review')
    // -------------------------------------------------------------------------

    public function test_allow_listed_user_pending_report_returns_403(): void
    {
        $user  = $this->makeUser();
        $score = $this->makeScore();
        $this->enableBeta([$user->id]);

        $admin = User::factory()->create(['user_type' => 'admin']);
        ByaReviewLog::create([
            'listing_compatibility_score_id' => $score->id,
            'reviewer_user_id'               => $admin->id,
            'status'                         => 'pending_review',
        ]);

        $response = $this->actingAs($user)->get(route(self::ROUTE, $score->id));
        $response->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // §4 — Allow-listed user, approved report → 200 with only permitted fields
    // -------------------------------------------------------------------------

    public function test_allow_listed_user_approved_report_returns_200(): void
    {
        $user  = $this->makeUser();
        $score = $this->makeScore();
        $this->enableBeta([$user->id]);
        $this->approveScore($score, 'approved');
        $before = $this->logCountBefore($score->id);

        $response = $this->actingAs($user)->get(route(self::ROUTE, $score->id));

        $response->assertStatus(200);
        $this->assertSame($before + 1, $this->logCountBefore($score->id));

        $log = ByaBetaAccessLog::where('listing_compatibility_score_id', $score->id)
            ->latest('id')->first();
        $this->assertTrue((bool) $log->allowed);
        $this->assertNull($log->denial_reason);

        // Verify the view contains the disclaimer banner text
        $response->assertSee('Beta Compatibility Insight');
        $response->assertSee('do not rank, recommend, endorse, approve, or disqualify');

        // Internal fields must NOT appear in the view
        $response->assertDontSee('explanation_key');
        $response->assertDontSee('template_id');
        $response->assertDontSee('reviewer notes');
        $response->assertDontSee('trace_keys');
    }

    // -------------------------------------------------------------------------
    // §4b — Allow-listed user, approved_with_notes report → 200
    // -------------------------------------------------------------------------

    public function test_allow_listed_user_approved_with_notes_returns_200(): void
    {
        $user  = $this->makeUser();
        $score = $this->makeScore();
        $this->enableBeta([$user->id]);
        $this->approveScore($score, 'approved_with_notes');

        $response = $this->actingAs($user)->get(route(self::ROUTE, $score->id));
        $response->assertStatus(200);
    }

    // -------------------------------------------------------------------------
    // §5 — Agent not on allow-list → 403
    // -------------------------------------------------------------------------

    public function test_agent_not_allow_listed_returns_403_and_writes_log(): void
    {
        $agent = $this->makeUser('agent');
        $score = $this->makeScore();
        $this->enableBeta([]);  // agent not in allow-list
        $this->approveScore($score);
        $before = $this->logCountBefore($score->id);

        $response = $this->actingAs($agent)->get(route(self::ROUTE, $score->id));

        $response->assertStatus(403);
        $this->assertSame($before + 1, $this->logCountBefore($score->id));

        $log = ByaBetaAccessLog::where('listing_compatibility_score_id', $score->id)
            ->latest('id')->first();
        $this->assertFalse((bool) $log->allowed);
        $this->assertSame('not_allow_listed', $log->denial_reason);
    }

    // -------------------------------------------------------------------------
    // §6 — Allow-listed agent → 200 (explicit allow-list overrides agent block)
    // -------------------------------------------------------------------------

    public function test_allow_listed_agent_returns_200(): void
    {
        $agent = $this->makeUser('agent');
        $score = $this->makeScore();
        $this->enableBeta([$agent->id]);
        $this->approveScore($score, 'approved');
        $before = $this->logCountBefore($score->id);

        $response = $this->actingAs($agent)->get(route(self::ROUTE, $score->id));

        $response->assertStatus(200);
        $this->assertSame($before + 1, $this->logCountBefore($score->id));

        $log = ByaBetaAccessLog::where('listing_compatibility_score_id', $score->id)
            ->latest('id')->first();
        $this->assertTrue((bool) $log->allowed);
        $this->assertNull($log->denial_reason);
    }

    // -------------------------------------------------------------------------
    // Every case writes exactly one log row
    // -------------------------------------------------------------------------

    public function test_every_access_attempt_writes_exactly_one_log_row(): void
    {
        $user  = $this->makeUser();
        $score = $this->makeScore();

        // Three separate attempts under different conditions
        $this->disableBeta();
        $this->actingAs($user)->get(route(self::ROUTE, $score->id));

        $this->enableBeta([]);
        $this->actingAs($user)->get(route(self::ROUTE, $score->id));

        $this->enableBeta([$user->id]);
        $this->actingAs($user)->get(route(self::ROUTE, $score->id));

        $this->assertSame(
            3,
            ByaBetaAccessLog::where('listing_compatibility_score_id', $score->id)->count()
        );
    }
}
