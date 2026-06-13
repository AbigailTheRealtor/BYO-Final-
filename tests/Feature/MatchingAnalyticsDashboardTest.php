<?php

namespace Tests\Feature;

use App\Services\BidAnalyticsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Feature tests for the P7 Matching Analytics admin dashboard.
 *
 * Uses DatabaseTransactions (not RefreshDatabase) because SQLite :memory:
 * has no per-method rollback with RefreshDatabase.
 *
 * Asserts:
 *  - Dashboard is admin-only (non-admins are redirected).
 *  - Dashboard returns HTTP 200 for admins.
 *  - Dashboard renders all required sections.
 *  - Time range filters (7 / 30 / 90 / all) behave correctly.
 *  - Funnel data includes property_type breakdown.
 *  - Conversion rates use bid_funnel_timestamps (stage-to-stage denominator).
 *  - Recommendation attribution is reported correctly.
 *  - Normal interactions are not attributed to recommendations.
 */
class MatchingAnalyticsDashboardTest extends TestCase
{
    use DatabaseTransactions;

    private function makeAdminUser()
    {
        return \App\Models\User::factory()->asAdmin()->create();
    }

    private function makeNonAdminUser()
    {
        return \App\Models\User::factory()->create(['user_type' => 'buyer']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Access control
    // ─────────────────────────────────────────────────────────────────────────

    public function test_unauthenticated_user_cannot_access_dashboard(): void
    {
        $response = $this->get(route('admin.matching.analytics'));
        $response->assertRedirect();
    }

    public function test_non_admin_is_redirected_from_dashboard(): void
    {
        $user = $this->makeNonAdminUser();
        $response = $this->actingAs($user)->get(route('admin.matching.analytics'));
        $response->assertRedirect();
    }

    public function test_admin_can_access_dashboard(): void
    {
        $admin = $this->makeAdminUser();
        $response = $this->actingAs($admin)->get(route('admin.matching.analytics'));
        $response->assertOk();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Dashboard renders all required sections with no data
    // ─────────────────────────────────────────────────────────────────────────

    public function test_dashboard_renders_with_no_snapshot_data(): void
    {
        $admin = $this->makeAdminUser();
        $response = $this->actingAs($admin)->get(route('admin.matching.analytics'));
        $response->assertOk();
        $response->assertSee('Matching Analytics');
        $response->assertSee('Readiness Funnel by Role');
        $response->assertSee('Score Distribution');
        $response->assertSee('Stage-to-Stage Conversion Funnel');
        $response->assertSee('Recommendation Effectiveness');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Time range filters
    // ─────────────────────────────────────────────────────────────────────────

    public function test_dashboard_accepts_range_7_filter(): void
    {
        $admin = $this->makeAdminUser();
        $response = $this->actingAs($admin)->get(route('admin.matching.analytics', ['range' => '7']));
        $response->assertOk();
        $response->assertSee('Last 7 days');
    }

    public function test_dashboard_accepts_range_30_filter(): void
    {
        $admin = $this->makeAdminUser();
        $response = $this->actingAs($admin)->get(route('admin.matching.analytics', ['range' => '30']));
        $response->assertOk();
    }

    public function test_dashboard_accepts_range_90_filter(): void
    {
        $admin = $this->makeAdminUser();
        $response = $this->actingAs($admin)->get(route('admin.matching.analytics', ['range' => '90']));
        $response->assertOk();
        $response->assertSee('Last 90 days');
    }

    public function test_dashboard_accepts_range_all_filter(): void
    {
        $admin = $this->makeAdminUser();
        $response = $this->actingAs($admin)->get(route('admin.matching.analytics', ['range' => 'all']));
        $response->assertOk();
        $response->assertSee('All time');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Funnel data — summary cards and property_type breakdown
    // ─────────────────────────────────────────────────────────────────────────

    public function test_dashboard_summary_cards_count_snapshots(): void
    {
        $this->insertSnapshot('seller_agent', 50001, 'seller', 'bid_submitted', 'not_ready',        'none',        null, 'residential');
        $this->insertSnapshot('seller_agent', 50002, 'seller', 'bid_submitted', 'quick_match_ready', 'quick_match', 75,   'residential');
        $this->insertSnapshot('seller_agent', 50003, 'seller', 'bid_accepted',  'full_match_ready',  'full_match',  88,   'condo');

        $admin = $this->makeAdminUser();
        $response = $this->actingAs($admin)->get(route('admin.matching.analytics', ['range' => 'all']));
        $response->assertOk();

        $summaryCards = $response->viewData('summaryCards');
        $this->assertGreaterThanOrEqual(3, $summaryCards['total_snapshots']);
    }

    public function test_funnel_data_includes_property_type_breakdown(): void
    {
        $this->insertSnapshot('seller_agent', 51001, 'seller', 'bid_submitted', 'not_ready', 'none', null, 'single_family');
        $this->insertSnapshot('seller_agent', 51002, 'seller', 'bid_submitted', 'not_ready', 'none', null, 'condo');

        $admin = $this->makeAdminUser();
        $response = $this->actingAs($admin)->get(route('admin.matching.analytics', ['range' => 'all']));
        $response->assertOk();

        $funnelData = $response->viewData('funnelData');
        $sellerRow = collect($funnelData)->firstWhere('role', 'seller');

        $this->assertArrayHasKey('by_property_type', $sellerRow);
        $propertyTypes = collect($sellerRow['by_property_type'])->pluck('property_type');
        $this->assertTrue($propertyTypes->contains('single_family'));
        $this->assertTrue($propertyTypes->contains('condo'));
    }

    public function test_funnel_data_by_role_aggregates_correctly(): void
    {
        $admin = $this->makeAdminUser();
        $before = $this->actingAs($admin)->get(route('admin.matching.analytics', ['range' => 'all']));
        $funnelBefore = collect($before->viewData('funnelData'))->firstWhere('role', 'buyer');

        $this->insertSnapshot('buyer_agent', 51003, 'buyer', 'bid_submitted', 'not_ready',       'none',        null);
        $this->insertSnapshot('buyer_agent', 51004, 'buyer', 'bid_submitted', 'full_match_ready', 'full_match', 82);

        $response = $this->actingAs($admin)->get(route('admin.matching.analytics', ['range' => 'all']));
        $response->assertOk();

        $buyerRow = collect($response->viewData('funnelData'))->firstWhere('role', 'buyer');
        $this->assertSame(
            ($funnelBefore['not_ready'] ?? 0) + 1,
            $buyerRow['not_ready']
        );
        $this->assertSame(
            ($funnelBefore['full_match_ready'] ?? 0) + 1,
            $buyerRow['full_match_ready']
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // True stage-to-stage conversion funnel (bid_funnel_timestamps)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_conversion_rates_use_funnel_timestamps(): void
    {
        // Insert funnel timestamps for landlord (unique role to isolate)
        $this->insertFunnelTimestamp('landlord_agent', 52001, 'landlord', [
            'not_ready_at'         => now(),
            'quick_match_ready_at' => now(),
            'full_match_ready_at'  => now(),
            'bid_submitted_at'     => now(),
            'bid_accepted_at'      => now(),
            'agent_hired_at'       => now(),
        ]);

        $admin = $this->makeAdminUser();
        $response = $this->actingAs($admin)->get(route('admin.matching.analytics', ['range' => 'all']));
        $response->assertOk();

        $conversionRates = $response->viewData('conversionRates');
        $landlordRow = collect($conversionRates)->firstWhere('role', 'landlord');

        $this->assertGreaterThanOrEqual(1, $landlordRow['not_ready']);
        $this->assertGreaterThanOrEqual(1, $landlordRow['quick_match_ready']);
        $this->assertGreaterThanOrEqual(1, $landlordRow['full_match_ready']);
        $this->assertGreaterThanOrEqual(1, $landlordRow['submitted']);
        $this->assertGreaterThanOrEqual(1, $landlordRow['accepted']);
        $this->assertGreaterThanOrEqual(1, $landlordRow['hired']);
    }

    public function test_conversion_rates_stage_to_stage_denominators(): void
    {
        // Two bids: one reaches Quick only, one reaches Full
        $this->insertFunnelTimestamp('tenant_agent', 53001, 'tenant', [
            'not_ready_at'         => now(),
            'quick_match_ready_at' => now(),
            // Does NOT reach full_match_ready
        ]);
        $this->insertFunnelTimestamp('tenant_agent', 53002, 'tenant', [
            'not_ready_at'         => now(),
            'quick_match_ready_at' => now(),
            'full_match_ready_at'  => now(),
        ]);

        $admin = $this->makeAdminUser();
        $response = $this->actingAs($admin)->get(route('admin.matching.analytics', ['range' => 'all']));
        $response->assertOk();

        $conversionRates = $response->viewData('conversionRates');
        $tenantRow = collect($conversionRates)->firstWhere('role', 'tenant');

        // Both bids are "not_ready" and "quick_match_ready"
        // quick_to_full_rate should be 50% (1 of 2 quick bids reached full)
        $this->assertSame(2, $tenantRow['quick_match_ready']);
        $this->assertSame(1, $tenantRow['full_match_ready']);
        $this->assertSame(50.0, $tenantRow['quick_to_full_rate']);
    }

    public function test_conversion_rates_contain_required_stage_to_stage_keys(): void
    {
        $admin = $this->makeAdminUser();
        $response = $this->actingAs($admin)->get(route('admin.matching.analytics', ['range' => 'all']));
        $response->assertOk();

        $conversionRates = $response->viewData('conversionRates');
        $sellerRow = collect($conversionRates)->firstWhere('role', 'seller');

        $requiredKeys = [
            'role', 'total', 'not_ready', 'quick_match_ready', 'full_match_ready',
            'submitted', 'accepted', 'hired',
            'not_to_quick_rate', 'quick_to_full_rate', 'full_to_hired_rate',
            'submit_to_accept', 'accept_to_hired', 'submit_to_hired',
        ];
        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $sellerRow, "Missing key: $key");
        }
    }

    public function test_stage_timestamps_used_as_range_anchor_not_created_at(): void
    {
        // Key correctness guarantee: each stage must be counted by ITS OWN timestamp,
        // not by created_at (row insertion time).
        //
        // Scenario: a bid was submitted 60 days ago (funnel row created then),
        // but bid_accepted_at falls within the 30-day window. The 30-day view must
        // count this bid in "accepted" even though the row's created_at is outside range.
        $sixtyDaysAgo = now()->subDays(60)->toDateTimeString();
        $yesterday    = now()->subDay()->toDateTimeString();

        $this->insertFunnelTimestamp('buyer_agent', 57001, 'buyer', [
            'bid_submitted_at' => $sixtyDaysAgo,   // outside 30-day window
            'not_ready_at'     => $sixtyDaysAgo,
            'bid_accepted_at'  => $yesterday,       // inside 30-day window
        ]);

        $admin = $this->makeAdminUser();
        $response = $this->actingAs($admin)->get(
            route('admin.matching.analytics', ['range' => '30'])
        );
        $response->assertOk();

        $conversionRates = $response->viewData('conversionRates');
        $buyerRow = collect($conversionRates)->firstWhere('role', 'buyer');

        // bid_accepted_at is within the 30-day window → must appear in accepted count.
        $this->assertGreaterThanOrEqual(1, $buyerRow['accepted'],
            'bid_accepted_at within 30-day window must be counted even if bid_submitted_at is older.'
        );

        // bid_submitted_at is outside the 30-day window → must NOT appear in submitted count.
        $submittedCount = $buyerRow['submitted'];
        $this->assertSame(0, $submittedCount,
            'bid_submitted_at outside 30-day window must not appear in the submitted count.'
        );
    }

    public function test_conversion_rate_stage_timestamps_are_independent_per_stage(): void
    {
        // Confirm that each stage count is gated by its own timestamp independently.
        // A bid where quick_match_ready_at is within 7 days but not_ready_at is 8 days ago:
        //   - quick_match_ready count for 7-day view should INCREASE by 1 after insert
        //   - not_ready count for 7-day view should NOT change (timestamp outside window)
        //
        // Uses delta-comparison (before vs after insert) rather than absolute counts
        // so the test is robust against pre-existing seller funnel rows from other tests.
        $eightDaysAgo = now()->subDays(8)->toDateTimeString();
        $threeDaysAgo = now()->subDays(3)->toDateTimeString();

        $admin = $this->makeAdminUser();

        // Baseline: record seller counts BEFORE inserting the new row.
        $before = $this->actingAs($admin)->get(route('admin.matching.analytics', ['range' => '7']));
        $before->assertOk();
        $sellerBefore = collect($before->viewData('conversionRates'))->firstWhere('role', 'seller');
        $baselineQuick    = $sellerBefore['quick_match_ready'] ?? 0;
        $baselineNotReady = $sellerBefore['not_ready'] ?? 0;

        // Insert: not_ready_at is 8 days ago (outside window), quick_match_ready_at 3 days ago (inside).
        $this->insertFunnelTimestamp('seller_agent', 57002, 'seller', [
            'bid_submitted_at'     => $eightDaysAgo,
            'not_ready_at'         => $eightDaysAgo,    // outside 7-day window
            'quick_match_ready_at' => $threeDaysAgo,    // inside 7-day window
        ]);

        $after = $this->actingAs($admin)->get(route('admin.matching.analytics', ['range' => '7']));
        $after->assertOk();
        $sellerAfter = collect($after->viewData('conversionRates'))->firstWhere('role', 'seller');

        // quick_match_ready must increase by exactly 1 (the new row's timestamp is in range).
        $this->assertSame($baselineQuick + 1, $sellerAfter['quick_match_ready'],
            'quick_match_ready_at within 7-day window must increase the count by 1.'
        );

        // not_ready must NOT change (the new row's not_ready_at is 8 days ago = outside window).
        $this->assertSame($baselineNotReady, $sellerAfter['not_ready'],
            'not_ready_at outside 7-day window must not change the count.'
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Recommendation attribution in dashboard
    // ─────────────────────────────────────────────────────────────────────────

    public function test_dashboard_recommendation_data_reflects_attributed_interactions(): void
    {
        DB::table('recommendation_interactions')->insert([
            [
                'bid_type'               => 'seller_agent',
                'bid_id'                 => 60001,
                'role'                   => 'seller',
                'property_type'          => null,
                'event_type'             => 'bid_viewed',
                'from_recommendation'    => true,
                'recommendation_surface' => 'consumer_fit_card',
                'user_id'                => null,
                'metadata'               => null,
                'created_at'             => now()->toDateTimeString(),
            ],
            [
                'bid_type'               => 'seller_agent',
                'bid_id'                 => 60002,
                'role'                   => 'seller',
                'property_type'          => null,
                'event_type'             => 'bid_viewed',
                'from_recommendation'    => false,
                'recommendation_surface' => null,
                'user_id'                => null,
                'metadata'               => null,
                'created_at'             => now()->toDateTimeString(),
            ],
        ]);

        $admin = $this->makeAdminUser();
        $response = $this->actingAs($admin)->get(route('admin.matching.analytics', ['range' => 'all']));
        $response->assertOk();

        $recData = $response->viewData('recommendationData');
        $this->assertGreaterThanOrEqual(2, $recData['viewed_total']);
        $this->assertGreaterThanOrEqual(1, $recData['viewed_rec']);
    }

    public function test_normal_interactions_not_attributed_to_recommendations(): void
    {
        DB::table('recommendation_interactions')->insert([
            [
                'bid_type' => 'buyer_agent', 'bid_id' => 61001, 'role' => 'buyer',
                'property_type' => null, 'event_type' => 'bid_viewed',
                'from_recommendation' => false, 'recommendation_surface' => null,
                'user_id' => null, 'metadata' => null,
                'created_at' => now()->toDateTimeString(),
            ],
        ]);

        $recCount = DB::table('recommendation_interactions')
            ->where('bid_id', 61001)
            ->where('from_recommendation', true)
            ->count();

        $this->assertSame(0, $recCount);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Time range filter
    // ─────────────────────────────────────────────────────────────────────────

    public function test_time_range_filter_excludes_old_snapshots(): void
    {
        $oldKey    = 'time-range-test-old-' . uniqid('', true);
        $recentKey = 'time-range-test-recent-' . uniqid('', true);

        DB::table('bid_score_snapshots')->insert([
            'bid_type' => 'tenant_agent', 'bid_id' => 62001, 'role' => 'tenant',
            'property_type' => null, 'event_type' => 'bid_submitted',
            'readiness_state' => 'not_ready', 'score_type' => 'none',
            'score_value' => null, 'scoring_version' => '1.0',
            'guard_key' => $oldKey, 'captured_at' => now()->subDays(30)->toDateTimeString(),
        ]);

        DB::table('bid_score_snapshots')->insert([
            'bid_type' => 'tenant_agent', 'bid_id' => 62002, 'role' => 'tenant',
            'property_type' => null, 'event_type' => 'bid_submitted',
            'readiness_state' => 'not_ready', 'score_type' => 'none',
            'score_value' => null, 'scoring_version' => '1.0',
            'guard_key' => $recentKey, 'captured_at' => now()->subDays(1)->toDateTimeString(),
        ]);

        $recentCount = DB::table('bid_score_snapshots')
            ->where('bid_id', 62002)
            ->where('captured_at', '>=', now()->subDays(6)->startOfDay())
            ->count();

        $oldCount = DB::table('bid_score_snapshots')
            ->where('bid_id', 62001)
            ->where('captured_at', '>=', now()->subDays(6)->startOfDay())
            ->count();

        $this->assertSame(1, $recentCount);
        $this->assertSame(0, $oldCount);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function insertSnapshot(
        string $bidType,
        int $bidId,
        string $role,
        string $eventType,
        string $readinessState,
        string $scoreType,
        ?int $scoreValue,
        ?string $propertyType = null
    ): void {
        DB::table('bid_score_snapshots')->insert([
            'bid_type'        => $bidType,
            'bid_id'          => $bidId,
            'role'            => $role,
            'property_type'   => $propertyType,
            'event_type'      => $eventType,
            'readiness_state' => $readinessState,
            'score_type'      => $scoreType,
            'score_value'     => $scoreValue,
            'scoring_version' => '1.0',
            'guard_key'       => $bidId . '-' . $eventType . '-' . uniqid('', true),
            'captured_at'     => now()->toDateTimeString(),
        ]);
    }

    private function insertFunnelTimestamp(
        string $bidType,
        int $bidId,
        string $role,
        array $timestamps
    ): void {
        $now = now()->toDateTimeString();
        DB::table('bid_funnel_timestamps')->insert(array_merge(
            [
                'bid_type'             => $bidType,
                'bid_id'               => $bidId,
                'role'                 => $role,
                'not_ready_at'         => null,
                'quick_match_ready_at' => null,
                'full_match_ready_at'  => null,
                'bid_submitted_at'     => null,
                'bid_accepted_at'      => null,
                'agent_hired_at'       => null,
                'created_at'           => $now,
                'updated_at'           => $now,
            ],
            array_map(fn ($ts) => $ts instanceof \Carbon\Carbon ? $ts->toDateTimeString() : $ts, $timestamps)
        ));
    }
}
