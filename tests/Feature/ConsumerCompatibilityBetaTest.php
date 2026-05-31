<?php

namespace Tests\Feature;

use App\Models\BuyerCriteriaAuction;
use App\Models\ByaBetaAccessLog;
use App\Models\ByaReviewLog;
use App\Models\ListingCompatibilityScore;
use App\Models\TenantCriteriaAuction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Feature tests for the BYA Consumer Beta access layer (Milestone 14).
 *
 * Covers:
 *  §1  — Feature flag disabled → 403 + log row with denial_reason = 'feature_flag_disabled'
 *  §2  — Unauthenticated → redirect to login (no log row, blocked at auth layer)
 *  §3  — Agent user → 403 + log row with denial_reason = 'agent_denied'
 *  §4  — Unrelated consumer (does not own the listing) → 403 + log row 'not_owner'
 *  §5  — Owning consumer, report not approved → 403 + log row 'report_not_approved'
 *  §6  — Owning consumer, report 'approved' → 200 + log row allowed
 *  §7  — Owning consumer, report 'approved_with_notes' → 200 + log row allowed
 *  §8  — Only consumer-safe fields rendered (assertSee / assertDontSee)
 *  §9  — Internal fields not in response (explanation_key, template_id, trace_keys, etc.)
 *  §10 — Log row written for both allowed and denied attempts
 *  §11 — No scores/rankings/recommendations in rendered output
 *  §12 — Tenant listing type ownership works correctly
 *  §13 — Unrecognised demand_listing_type → 403 + not_owner
 *  §14 — Cache-Control: no-store header on 200 response
 */
class ConsumerCompatibilityBetaTest extends TestCase
{
    use DatabaseTransactions;

    private const ROUTE = 'bya.consumer.beta.compatibility-report';

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

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
            'title'      => 'Test Buyer Listing',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function tenantTableExists(): bool
    {
        return DB::getSchemaBuilder()->hasTable('tenant_criteria_auctions');
    }

    private function makeTenantListing(User $user): int
    {
        return DB::table('tenant_criteria_auctions')->insertGetId([
            'user_id'    => $user->id,
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

    private function enableBeta(): void
    {
        Config::set('bya_consumer_beta.consumer_beta_enabled', true);
        Config::set('bya_compatibility.kill_switch', false);
        Config::set('bya_compatibility.ga_enabled', false);
    }

    private function disableBeta(): void
    {
        Config::set('bya_consumer_beta.consumer_beta_enabled', false);
        Config::set('bya_compatibility.ga_enabled', false);
        Config::set('bya_compatibility.kill_switch', false);
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

    // -------------------------------------------------------------------------
    // §1 — Feature flag disabled → 403 + log row 'feature_flag_disabled'
    // -------------------------------------------------------------------------

    public function test_flag_disabled_returns_403_and_writes_log(): void
    {
        $this->disableBeta();
        $user          = $this->makeUser();
        $listingId     = $this->makeBuyerListing($user);
        $score         = $this->makeScore('buyer', $listingId);
        $this->approveScore($score);
        $before = $this->logCountFor($score->id);

        $response = $this->actingAs($user)->get(route(self::ROUTE, $score->id));

        $response->assertStatus(403);
        $this->assertSame($before + 1, $this->logCountFor($score->id));

        $log = $this->latestLog($score->id);
        $this->assertNotNull($log);
        $this->assertFalse((bool) $log->allowed);
        $this->assertSame('feature_disabled', $log->denial_reason);
        $this->assertSame($user->id, $log->user_id);
    }

    // -------------------------------------------------------------------------
    // §2 — Unauthenticated → redirect to login (no log row written)
    // -------------------------------------------------------------------------

    public function test_unauthenticated_redirects_to_login_with_no_log(): void
    {
        $this->enableBeta();
        $user      = $this->makeUser();
        $listingId = $this->makeBuyerListing($user);
        $score     = $this->makeScore('buyer', $listingId);
        $this->approveScore($score);
        $before = $this->logCountFor($score->id);

        $response = $this->get(route(self::ROUTE, $score->id));

        $response->assertRedirect(route('login'));
        $this->assertSame($before, $this->logCountFor($score->id));
    }

    // -------------------------------------------------------------------------
    // §3 — Agent user → 403 + log row 'agent_denied'
    // -------------------------------------------------------------------------

    public function test_agent_is_denied_403_with_log(): void
    {
        $this->enableBeta();
        $owner     = $this->makeUser('buyer');
        $agent     = $this->makeUser('agent');
        $listingId = $this->makeBuyerListing($owner);
        $score     = $this->makeScore('buyer', $listingId);
        $this->approveScore($score);
        $before = $this->logCountFor($score->id);

        $response = $this->actingAs($agent)->get(route(self::ROUTE, $score->id));

        $response->assertStatus(403);
        $this->assertSame($before + 1, $this->logCountFor($score->id));

        $log = $this->latestLog($score->id);
        $this->assertFalse((bool) $log->allowed);
        $this->assertSame('agent_denied', $log->denial_reason);
        $this->assertSame($agent->id, $log->user_id);
    }

    // -------------------------------------------------------------------------
    // §4 — Unrelated consumer → 403 + log row 'not_owner'
    // -------------------------------------------------------------------------

    public function test_unrelated_consumer_is_denied_403_with_log(): void
    {
        $this->enableBeta();
        $owner     = $this->makeUser('buyer');
        $other     = $this->makeUser('buyer');
        $listingId = $this->makeBuyerListing($owner);
        $score     = $this->makeScore('buyer', $listingId);
        $this->approveScore($score);
        $before = $this->logCountFor($score->id);

        $response = $this->actingAs($other)->get(route(self::ROUTE, $score->id));

        $response->assertStatus(403);
        $this->assertSame($before + 1, $this->logCountFor($score->id));

        $log = $this->latestLog($score->id);
        $this->assertFalse((bool) $log->allowed);
        $this->assertSame('not_owner', $log->denial_reason);
    }

    // -------------------------------------------------------------------------
    // §5 — Owning consumer, report not approved → 403 + log row 'report_not_approved'
    // -------------------------------------------------------------------------

    public function test_owner_with_unapproved_report_gets_403(): void
    {
        $this->enableBeta();
        $user      = $this->makeUser('buyer');
        $listingId = $this->makeBuyerListing($user);
        $score     = $this->makeScore('buyer', $listingId);
        // No review log — report is unapproved
        $before = $this->logCountFor($score->id);

        $response = $this->actingAs($user)->get(route(self::ROUTE, $score->id));

        $response->assertStatus(403);
        $this->assertSame($before + 1, $this->logCountFor($score->id));

        $log = $this->latestLog($score->id);
        $this->assertFalse((bool) $log->allowed);
        $this->assertSame('report_not_approved', $log->denial_reason);
    }

    // -------------------------------------------------------------------------
    // §5b — Owning consumer, latest review log is pending_review → 403
    // -------------------------------------------------------------------------

    public function test_owner_with_pending_report_gets_403(): void
    {
        $this->enableBeta();
        $user      = $this->makeUser('buyer');
        $listingId = $this->makeBuyerListing($user);
        $score     = $this->makeScore('buyer', $listingId);

        $admin = User::factory()->create(['user_type' => 'admin']);
        ByaReviewLog::create([
            'listing_compatibility_score_id' => $score->id,
            'reviewer_user_id'               => $admin->id,
            'status'                         => 'pending_review',
        ]);

        $response = $this->actingAs($user)->get(route(self::ROUTE, $score->id));

        $response->assertStatus(403);
        $log = $this->latestLog($score->id);
        $this->assertSame('report_not_approved', $log->denial_reason);
    }

    // -------------------------------------------------------------------------
    // §6 — Owning consumer, report 'approved' → 200 + log row allowed
    // -------------------------------------------------------------------------

    public function test_owner_with_approved_report_gets_200(): void
    {
        $this->enableBeta();
        $user      = $this->makeUser('buyer');
        $listingId = $this->makeBuyerListing($user);
        $score     = $this->makeScore('buyer', $listingId);
        $this->approveScore($score, 'approved');
        $before = $this->logCountFor($score->id);

        $response = $this->actingAs($user)->get(route(self::ROUTE, $score->id));

        $response->assertStatus(200);
        $this->assertSame($before + 1, $this->logCountFor($score->id));

        $log = $this->latestLog($score->id);
        $this->assertTrue((bool) $log->allowed);
        $this->assertNull($log->denial_reason);
        $this->assertSame($user->id, $log->user_id);
    }

    // -------------------------------------------------------------------------
    // §7 — Owning consumer, report 'approved_with_notes' → 200 + log row allowed
    // -------------------------------------------------------------------------

    public function test_owner_with_approved_with_notes_report_gets_200(): void
    {
        $this->enableBeta();
        $user      = $this->makeUser('buyer');
        $listingId = $this->makeBuyerListing($user);
        $score     = $this->makeScore('buyer', $listingId);
        $this->approveScore($score, 'approved_with_notes');
        $before = $this->logCountFor($score->id);

        $response = $this->actingAs($user)->get(route(self::ROUTE, $score->id));

        $response->assertStatus(200);
        $this->assertSame($before + 1, $this->logCountFor($score->id));

        $log = $this->latestLog($score->id);
        $this->assertTrue((bool) $log->allowed);
    }

    // -------------------------------------------------------------------------
    // §8 — Only consumer-safe fields rendered
    // -------------------------------------------------------------------------

    public function test_only_consumer_safe_fields_are_in_response(): void
    {
        $this->enableBeta();
        $user      = $this->makeUser('buyer');
        $listingId = $this->makeBuyerListing($user);
        $score     = $this->makeScore('buyer', $listingId);
        $this->approveScore($score, 'approved');

        $response = $this->actingAs($user)->get(route(self::ROUTE, $score->id));

        $response->assertStatus(200);
        // Required consumer-safe content
        $response->assertSee('Compatibility Insight');
        $response->assertSee('do not rank, recommend, endorse, approve, or disqualify');
    }

    // -------------------------------------------------------------------------
    // §9 — Internal fields not in response
    // -------------------------------------------------------------------------

    public function test_internal_fields_are_not_in_response(): void
    {
        $this->enableBeta();
        $user      = $this->makeUser('buyer');
        $listingId = $this->makeBuyerListing($user);
        $score     = $this->makeScore('buyer', $listingId);
        $this->approveScore($score, 'approved');

        $response = $this->actingAs($user)->get(route(self::ROUTE, $score->id));

        $response->assertStatus(200);
        $response->assertDontSee('explanation_key');
        $response->assertDontSee('template_id');
        $response->assertDontSee('trace_keys');
        $response->assertDontSee('reviewer notes');
        $response->assertDontSee('reviewer_user_id');
        $response->assertDontSee('overall_score');
        $response->assertDontSee('deal_breaker');
    }

    // -------------------------------------------------------------------------
    // §10 — Log row written for both allowed and denied attempts
    // -------------------------------------------------------------------------

    public function test_log_row_written_for_every_attempt(): void
    {
        $user      = $this->makeUser('buyer');
        $listingId = $this->makeBuyerListing($user);
        $score     = $this->makeScore('buyer', $listingId);
        $this->approveScore($score, 'approved');

        // Denied attempt (flag off)
        $this->disableBeta();
        $this->actingAs($user)->get(route(self::ROUTE, $score->id));

        // Allowed attempt (flag on)
        $this->enableBeta();
        $this->actingAs($user)->get(route(self::ROUTE, $score->id));

        $this->assertSame(2, $this->logCountFor($score->id));

        $logs = ByaBetaAccessLog::where('listing_compatibility_score_id', $score->id)
            ->orderBy('id')
            ->get();
        $this->assertFalse((bool) $logs[0]->allowed);
        $this->assertTrue((bool) $logs[1]->allowed);
    }

    // -------------------------------------------------------------------------
    // §11 — No numeric scores/rankings/recommendations in rendered output
    // -------------------------------------------------------------------------

    public function test_no_scores_or_rankings_in_rendered_output(): void
    {
        $this->enableBeta();
        $user      = $this->makeUser('buyer');
        $listingId = $this->makeBuyerListing($user);
        $score     = $this->makeScore('buyer', $listingId);
        $this->approveScore($score, 'approved');

        $response = $this->actingAs($user)->get(route(self::ROUTE, $score->id));

        $response->assertStatus(200);
        $response->assertDontSee('ranked');
        $response->assertDontSee('recommended');
        $response->assertDontSee('overall_score');
        $response->assertDontSee('match score');
    }

    // -------------------------------------------------------------------------
    // §12 — Tenant listing type ownership works correctly
    // -------------------------------------------------------------------------

    public function test_tenant_listing_ownership_works(): void
    {
        if (!$this->tenantTableExists()) {
            $this->markTestSkipped('tenant_criteria_auctions table does not exist in this environment.');
        }

        $this->enableBeta();
        $user      = $this->makeUser('tenant');
        $listingId = $this->makeTenantListing($user);
        $score     = $this->makeScore('tenant', $listingId);
        $this->approveScore($score, 'approved');

        $response = $this->actingAs($user)->get(route(self::ROUTE, $score->id));

        $response->assertStatus(200);

        $log = $this->latestLog($score->id);
        $this->assertTrue((bool) $log->allowed);
    }

    // -------------------------------------------------------------------------
    // §12b — Tenant listing: unrelated consumer is denied
    // -------------------------------------------------------------------------

    public function test_tenant_listing_unrelated_consumer_denied(): void
    {
        if (!$this->tenantTableExists()) {
            $this->markTestSkipped('tenant_criteria_auctions table does not exist in this environment.');
        }

        $this->enableBeta();
        $owner     = $this->makeUser('tenant');
        $other     = $this->makeUser('tenant');
        $listingId = $this->makeTenantListing($owner);
        $score     = $this->makeScore('tenant', $listingId);
        $this->approveScore($score, 'approved');

        $response = $this->actingAs($other)->get(route(self::ROUTE, $score->id));

        $response->assertStatus(403);
        $log = $this->latestLog($score->id);
        $this->assertSame('not_owner', $log->denial_reason);
    }

    // -------------------------------------------------------------------------
    // §13 — Unrecognised demand_listing_type → 403 + not_owner
    // -------------------------------------------------------------------------

    public function test_unrecognised_demand_listing_type_is_denied(): void
    {
        $this->enableBeta();
        $user  = $this->makeUser('seller');
        $score = $this->makeScore('seller_agent_auction', 1);
        $this->approveScore($score, 'approved');

        $response = $this->actingAs($user)->get(route(self::ROUTE, $score->id));

        $response->assertStatus(403);
        $log = $this->latestLog($score->id);
        $this->assertSame('not_owner', $log->denial_reason);
    }

    // -------------------------------------------------------------------------
    // §14 — Cache-Control: no-store header on 200 response
    // -------------------------------------------------------------------------

    public function test_cache_control_no_store_on_successful_response(): void
    {
        $this->enableBeta();
        $user      = $this->makeUser('buyer');
        $listingId = $this->makeBuyerListing($user);
        $score     = $this->makeScore('buyer', $listingId);
        $this->approveScore($score, 'approved');

        $response = $this->actingAs($user)->get(route(self::ROUTE, $score->id));

        $response->assertStatus(200);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control', ''));
    }
}
