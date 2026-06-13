<?php

namespace Tests\Unit;

use App\Models\BidFunnelTimestamp;
use App\Models\BidScoreSnapshot;
use App\Models\RecommendationInteraction;
use App\Services\BidAnalyticsService;
use App\Services\CompatibilityScoreService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Unit tests for BidAnalyticsService (P7 Matching Analytics)
 *
 * Uses DatabaseTransactions (not RefreshDatabase) because SQLite :memory:
 * has no per-method rollback with RefreshDatabase.
 *
 * Asserts:
 *  - Snapshots are created at all five trigger event types.
 *  - bid_created AND bid_submitted are separately recorded (two distinct events).
 *  - Once-only guard: bid_created/bid_submitted/bid_accepted/agent_hired
 *    deduplicate across time (no minute component in guard key).
 *  - bid_updated allows multiple snapshots per bid (repeatable event).
 *  - Historical snapshots are never mutated.
 *  - Funnel timestamps are recorded only on first stage entry.
 *  - Re-entry does not overwrite existing funnel timestamps.
 *  - Recommendation interactions store attribution correctly.
 *  - surface is cleared when from_recommendation is false.
 *  - SCORING_VERSION constant is present on CompatibilityScoreService.
 */
class BidAnalyticsServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        // Reset per-request token so each test method gets a fresh token.
        // This prevents bid_updated guard keys from bleeding across tests.
        BidAnalyticsService::resetRequestToken();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Scoring version constant
    // ─────────────────────────────────────────────────────────────────────────

    public function test_scoring_version_constant_exists_and_is_non_empty(): void
    {
        $this->assertNotEmpty(CompatibilityScoreService::SCORING_VERSION);
        $this->assertIsString(CompatibilityScoreService::SCORING_VERSION);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Snapshot creation — all five event types
    // ─────────────────────────────────────────────────────────────────────────

    public function test_capture_snapshot_bid_created(): void
    {
        BidAnalyticsService::captureSnapshot(
            'seller_agent', 1001, 'seller', 'single_family',
            BidAnalyticsService::EVENT_BID_CREATED,
            [], []
        );

        $snap = BidScoreSnapshot::where('bid_id', 1001)
            ->where('event_type', BidAnalyticsService::EVENT_BID_CREATED)
            ->first();
        $this->assertNotNull($snap);
        $this->assertSame('seller_agent', $snap->bid_type);
        $this->assertSame(1001, $snap->bid_id);
        $this->assertSame('seller', $snap->role);
        $this->assertSame('single_family', $snap->property_type);
        $this->assertSame(BidAnalyticsService::EVENT_BID_CREATED, $snap->event_type);
        $this->assertSame(CompatibilityScoreService::SCORING_VERSION, $snap->scoring_version);
        $this->assertNotNull($snap->guard_key);
        $this->assertNotNull($snap->captured_at);
    }

    public function test_capture_snapshot_bid_submitted(): void
    {
        BidAnalyticsService::captureSnapshot(
            'buyer_agent', 2002, 'buyer', null,
            BidAnalyticsService::EVENT_BID_SUBMITTED,
            [], []
        );

        $snap = BidScoreSnapshot::where('bid_id', 2002)
            ->where('event_type', BidAnalyticsService::EVENT_BID_SUBMITTED)
            ->first();
        $this->assertNotNull($snap);
        $this->assertSame(BidAnalyticsService::EVENT_BID_SUBMITTED, $snap->event_type);
    }

    public function test_capture_snapshot_bid_updated(): void
    {
        BidAnalyticsService::captureSnapshot(
            'landlord_agent', 3003, 'landlord', 'condo',
            BidAnalyticsService::EVENT_BID_UPDATED,
            [], []
        );

        $snap = BidScoreSnapshot::where('bid_id', 3003)
            ->where('event_type', BidAnalyticsService::EVENT_BID_UPDATED)
            ->first();
        $this->assertNotNull($snap);
        $this->assertSame(BidAnalyticsService::EVENT_BID_UPDATED, $snap->event_type);
        $this->assertSame('condo', $snap->property_type);
    }

    public function test_capture_snapshot_bid_accepted(): void
    {
        BidAnalyticsService::captureSnapshot(
            'tenant_agent', 4004, 'tenant', 'apartment',
            BidAnalyticsService::EVENT_BID_ACCEPTED,
            [], []
        );

        $snap = BidScoreSnapshot::where('bid_id', 4004)
            ->where('event_type', BidAnalyticsService::EVENT_BID_ACCEPTED)
            ->first();
        $this->assertNotNull($snap);
        $this->assertSame(BidAnalyticsService::EVENT_BID_ACCEPTED, $snap->event_type);
    }

    public function test_capture_snapshot_agent_hired(): void
    {
        BidAnalyticsService::captureSnapshot(
            'seller_agent', 5005, 'seller', null,
            BidAnalyticsService::EVENT_AGENT_HIRED,
            [], []
        );

        $snap = BidScoreSnapshot::where('bid_id', 5005)
            ->where('event_type', BidAnalyticsService::EVENT_AGENT_HIRED)
            ->first();
        $this->assertNotNull($snap);
        $this->assertSame(BidAnalyticsService::EVENT_AGENT_HIRED, $snap->event_type);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // bid_created and bid_submitted are separate events for the same bid
    // ─────────────────────────────────────────────────────────────────────────

    public function test_bid_created_and_bid_submitted_produce_separate_snapshots(): void
    {
        BidAnalyticsService::captureSnapshot(
            'seller_agent', 6001, 'seller', null,
            BidAnalyticsService::EVENT_BID_CREATED,
            [], []
        );
        BidAnalyticsService::captureSnapshot(
            'seller_agent', 6001, 'seller', null,
            BidAnalyticsService::EVENT_BID_SUBMITTED,
            [], []
        );

        $this->assertSame(2, BidScoreSnapshot::where('bid_id', 6001)->count());
        $this->assertSame(
            1,
            BidScoreSnapshot::where('bid_id', 6001)
                ->where('event_type', BidAnalyticsService::EVENT_BID_CREATED)->count()
        );
        $this->assertSame(
            1,
            BidScoreSnapshot::where('bid_id', 6001)
                ->where('event_type', BidAnalyticsService::EVENT_BID_SUBMITTED)->count()
        );
    }

    public function test_capture_snapshot_stores_readiness_state_and_score_type(): void
    {
        BidAnalyticsService::captureSnapshot(
            'seller_agent', 6006, 'seller', null,
            BidAnalyticsService::EVENT_BID_SUBMITTED,
            [], []
        );

        $snap = BidScoreSnapshot::where('bid_id', 6006)->first();
        $this->assertNotNull($snap);
        $this->assertContains($snap->readiness_state, ['not_ready', 'quick_match_ready', 'full_match_ready', 'unknown']);
        $this->assertContains($snap->score_type, ['quick_match', 'full_match', 'none']);
    }

    public function test_capture_snapshot_stores_scoring_version(): void
    {
        BidAnalyticsService::captureSnapshot(
            'buyer_agent', 7007, 'buyer', null,
            BidAnalyticsService::EVENT_BID_SUBMITTED,
            [], []
        );

        $snap = BidScoreSnapshot::where('bid_id', 7007)->first();
        $this->assertNotNull($snap);
        $this->assertSame(CompatibilityScoreService::SCORING_VERSION, $snap->scoring_version);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Once-only guard — bid_created / bid_submitted / bid_accepted / agent_hired
    // guard is deterministic (no time component), so second call is a no-op
    // regardless of when it fires.
    // ─────────────────────────────────────────────────────────────────────────

    public function test_bid_created_is_recorded_only_once_per_bid(): void
    {
        BidAnalyticsService::captureSnapshot(
            'seller_agent', 8001, 'seller', null,
            BidAnalyticsService::EVENT_BID_CREATED,
            [], []
        );
        BidAnalyticsService::captureSnapshot(
            'seller_agent', 8001, 'seller', null,
            BidAnalyticsService::EVENT_BID_CREATED,
            [], []
        );

        $this->assertSame(
            1,
            BidScoreSnapshot::where('bid_id', 8001)
                ->where('event_type', BidAnalyticsService::EVENT_BID_CREATED)
                ->count()
        );
    }

    public function test_bid_submitted_is_recorded_only_once_per_bid(): void
    {
        BidAnalyticsService::captureSnapshot(
            'seller_agent', 8002, 'seller', null,
            BidAnalyticsService::EVENT_BID_SUBMITTED,
            [], []
        );
        BidAnalyticsService::captureSnapshot(
            'seller_agent', 8002, 'seller', null,
            BidAnalyticsService::EVENT_BID_SUBMITTED,
            [], []
        );

        $this->assertSame(
            1,
            BidScoreSnapshot::where('bid_id', 8002)
                ->where('event_type', BidAnalyticsService::EVENT_BID_SUBMITTED)
                ->count()
        );
    }

    public function test_bid_accepted_is_recorded_only_once_per_bid(): void
    {
        BidAnalyticsService::captureSnapshot(
            'buyer_agent', 8003, 'buyer', null,
            BidAnalyticsService::EVENT_BID_ACCEPTED,
            [], []
        );
        BidAnalyticsService::captureSnapshot(
            'buyer_agent', 8003, 'buyer', null,
            BidAnalyticsService::EVENT_BID_ACCEPTED,
            [], []
        );

        $this->assertSame(
            1,
            BidScoreSnapshot::where('bid_id', 8003)
                ->where('event_type', BidAnalyticsService::EVENT_BID_ACCEPTED)
                ->count()
        );
    }

    public function test_bid_updated_deduplicates_within_same_request(): void
    {
        // Same request token → same guard_key → unique constraint blocks second insert.
        // This models the Eloquent observer firing twice from a single save() call.
        BidAnalyticsService::captureSnapshot(
            'seller_agent', 8004, 'seller', null,
            BidAnalyticsService::EVENT_BID_UPDATED,
            [], []
        );
        BidAnalyticsService::captureSnapshot(
            'seller_agent', 8004, 'seller', null,
            BidAnalyticsService::EVENT_BID_UPDATED,
            [], []
        );

        $this->assertSame(
            1,
            BidScoreSnapshot::where('bid_id', 8004)
                ->where('event_type', BidAnalyticsService::EVENT_BID_UPDATED)
                ->count(),
            'Two captureSnapshot calls within the same request should produce exactly one row.'
        );
    }

    public function test_bid_updated_allows_multiple_snapshots_across_separate_requests(): void
    {
        // First "request"
        BidAnalyticsService::captureSnapshot(
            'seller_agent', 8040, 'seller', null,
            BidAnalyticsService::EVENT_BID_UPDATED,
            [], []
        );

        // Simulate a new request by resetting the token.
        BidAnalyticsService::resetRequestToken();

        // Second "request"
        BidAnalyticsService::captureSnapshot(
            'seller_agent', 8040, 'seller', null,
            BidAnalyticsService::EVENT_BID_UPDATED,
            [], []
        );

        $this->assertSame(
            2,
            BidScoreSnapshot::where('bid_id', 8040)
                ->where('event_type', BidAnalyticsService::EVENT_BID_UPDATED)
                ->count(),
            'Separate request tokens should produce a distinct row for each real user edit.'
        );
    }

    public function test_different_bids_create_separate_snapshots(): void
    {
        BidAnalyticsService::captureSnapshot(
            'seller_agent', 8005, 'seller', null,
            BidAnalyticsService::EVENT_BID_SUBMITTED,
            [], []
        );
        BidAnalyticsService::captureSnapshot(
            'seller_agent', 8006, 'seller', null,
            BidAnalyticsService::EVENT_BID_SUBMITTED,
            [], []
        );

        $this->assertSame(1, BidScoreSnapshot::where('bid_id', 8005)->count());
        $this->assertSame(1, BidScoreSnapshot::where('bid_id', 8006)->count());
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Immutability — historical snapshots are never mutated
    // ─────────────────────────────────────────────────────────────────────────

    public function test_historical_snapshots_are_not_mutated_on_subsequent_events(): void
    {
        BidAnalyticsService::captureSnapshot(
            'seller_agent', 9001, 'seller', null,
            BidAnalyticsService::EVENT_BID_SUBMITTED,
            [], []
        );

        $originalSnap = BidScoreSnapshot::where('bid_id', 9001)
            ->where('event_type', BidAnalyticsService::EVENT_BID_SUBMITTED)
            ->first();
        $originalId  = $originalSnap->id;
        $originalKey = $originalSnap->guard_key;

        BidAnalyticsService::captureSnapshot(
            'seller_agent', 9001, 'seller', null,
            BidAnalyticsService::EVENT_BID_ACCEPTED,
            [], []
        );

        $reloaded = BidScoreSnapshot::find($originalId);
        $this->assertSame($originalKey, $reloaded->guard_key);
        $this->assertSame(BidAnalyticsService::EVENT_BID_SUBMITTED, $reloaded->event_type);
        $this->assertSame(2, BidScoreSnapshot::where('bid_id', 9001)->count());
    }

    // ─────────────────────────────────────────────────────────────────────────
    // captureAgentHiredSnapshot
    // ─────────────────────────────────────────────────────────────────────────

    public function test_capture_agent_hired_snapshot_without_bid(): void
    {
        BidAnalyticsService::captureAgentHiredSnapshot('seller', 'condo');

        $snap = BidScoreSnapshot::whereNull('bid_id')
            ->where('role', 'seller')
            ->where('event_type', BidAnalyticsService::EVENT_AGENT_HIRED)
            ->orderByDesc('id')->first();

        $this->assertNotNull($snap);
        $this->assertNull($snap->bid_type);
        $this->assertSame('condo', $snap->property_type);
        $this->assertSame('none', $snap->score_type);
        $this->assertNull($snap->score_value);
    }

    public function test_capture_agent_hired_snapshot_multiple_times_creates_multiple_rows(): void
    {
        $before = BidScoreSnapshot::whereNull('bid_id')->where('role', 'buyer')->count();

        BidAnalyticsService::captureAgentHiredSnapshot('buyer', null);
        BidAnalyticsService::captureAgentHiredSnapshot('buyer', null);

        $after = BidScoreSnapshot::whereNull('bid_id')->where('role', 'buyer')->count();
        $this->assertSame($before + 2, $after);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Funnel timestamps — first-entry-only
    // ─────────────────────────────────────────────────────────────────────────

    public function test_advance_funnel_records_timestamp_on_first_entry(): void
    {
        BidAnalyticsService::advanceFunnel('seller_agent', 10001, 'seller', 'not_ready');

        $row = BidFunnelTimestamp::where('bid_id', 10001)->first();
        $this->assertNotNull($row);
        $this->assertSame('seller_agent', $row->bid_type);
        $this->assertSame(10001, $row->bid_id);
        $this->assertSame('seller', $row->role);
        $this->assertNotNull($row->not_ready_at);
        $this->assertNull($row->quick_match_ready_at);
    }

    public function test_advance_funnel_does_not_overwrite_existing_timestamp(): void
    {
        BidAnalyticsService::advanceFunnel('seller_agent', 10002, 'seller', 'not_ready');
        $firstTimestamp = BidFunnelTimestamp::where('bid_id', 10002)->value('not_ready_at');

        BidAnalyticsService::advanceFunnel('seller_agent', 10002, 'seller', 'not_ready');

        $row = BidFunnelTimestamp::where('bid_id', 10002)->first();
        $this->assertEquals($firstTimestamp, $row->not_ready_at);
        $this->assertSame(1, BidFunnelTimestamp::where('bid_id', 10002)->count());
    }

    public function test_advance_funnel_records_multiple_distinct_stages(): void
    {
        BidAnalyticsService::advanceFunnel('buyer_agent', 10003, 'buyer', 'not_ready');
        BidAnalyticsService::advanceFunnel('buyer_agent', 10003, 'buyer', 'quick_match_ready');
        BidAnalyticsService::advanceFunnel('buyer_agent', 10003, 'buyer', 'full_match_ready');

        $row = BidFunnelTimestamp::where('bid_id', 10003)->first();
        $this->assertNotNull($row->not_ready_at);
        $this->assertNotNull($row->quick_match_ready_at);
        $this->assertNotNull($row->full_match_ready_at);
        $this->assertNull($row->bid_submitted_at);
        $this->assertSame(1, BidFunnelTimestamp::where('bid_id', 10003)->count());
    }

    public function test_advance_funnel_bid_submitted_stage(): void
    {
        BidAnalyticsService::advanceFunnel('seller_agent', 10004, 'seller', BidAnalyticsService::EVENT_BID_SUBMITTED);

        $row = BidFunnelTimestamp::where('bid_id', 10004)->first();
        $this->assertNotNull($row->bid_submitted_at);
    }

    public function test_advance_funnel_bid_accepted_stage(): void
    {
        BidAnalyticsService::advanceFunnel('seller_agent', 10005, 'seller', BidAnalyticsService::EVENT_BID_ACCEPTED);

        $row = BidFunnelTimestamp::where('bid_id', 10005)->first();
        $this->assertNotNull($row->bid_accepted_at);
    }

    public function test_advance_funnel_agent_hired_stage(): void
    {
        BidAnalyticsService::advanceFunnel('tenant_agent', 10006, 'tenant', BidAnalyticsService::EVENT_AGENT_HIRED);

        $row = BidFunnelTimestamp::where('bid_id', 10006)->first();
        $this->assertNotNull($row->agent_hired_at);
    }

    public function test_advance_funnel_different_bids_have_separate_rows(): void
    {
        BidAnalyticsService::advanceFunnel('seller_agent', 10007, 'seller', 'not_ready');
        BidAnalyticsService::advanceFunnel('seller_agent', 10008, 'seller', 'not_ready');

        $this->assertSame(1, BidFunnelTimestamp::where('bid_id', 10007)->count());
        $this->assertSame(1, BidFunnelTimestamp::where('bid_id', 10008)->count());
    }

    public function test_advance_funnel_ignores_unknown_stage(): void
    {
        BidAnalyticsService::advanceFunnel('seller_agent', 10009, 'seller', 'invalid_stage');

        $this->assertSame(0, BidFunnelTimestamp::where('bid_id', 10009)->count());
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Recommendation interactions — attribution
    // ─────────────────────────────────────────────────────────────────────────

    public function test_recommendation_interaction_attributed_stores_from_recommendation_true(): void
    {
        BidAnalyticsService::recordRecommendationInteraction(
            'bid_viewed', 'seller', true, 'consumer_fit_card',
            'seller_agent', 20001, 'single_family', null, ['listing_id' => 42]
        );

        $row = RecommendationInteraction::where('bid_id', 20001)->first();
        $this->assertNotNull($row);
        $this->assertTrue((bool) $row->from_recommendation);
        $this->assertSame('consumer_fit_card', $row->recommendation_surface);
        $this->assertSame('bid_viewed', $row->event_type);
        $this->assertSame('seller', $row->role);
        $this->assertSame('seller_agent', $row->bid_type);
    }

    public function test_normal_interaction_not_attributed_to_recommendation(): void
    {
        BidAnalyticsService::recordRecommendationInteraction(
            'bid_viewed', 'buyer', false, null,
            'buyer_agent', 20002, null
        );

        $row = RecommendationInteraction::where('bid_id', 20002)->first();
        $this->assertNotNull($row);
        $this->assertFalse((bool) $row->from_recommendation);
        $this->assertNull($row->recommendation_surface);
    }

    public function test_recommendation_surface_is_null_for_non_attributed_interactions(): void
    {
        // Even if surface is accidentally passed with from_recommendation=false,
        // the service must null it out to prevent inflated attribution stats.
        BidAnalyticsService::recordRecommendationInteraction(
            'bid_accepted', 'landlord', false, 'coaching_panel',
            'landlord_agent', 20003
        );

        $row = RecommendationInteraction::where('bid_id', 20003)->first();
        $this->assertNotNull($row);
        $this->assertNull($row->recommendation_surface);
    }

    public function test_recommendation_interaction_agent_hired_event(): void
    {
        BidAnalyticsService::recordRecommendationInteraction(
            'agent_hired', 'tenant', true, 'preset_completion',
            'tenant_agent', 20004
        );

        $row = RecommendationInteraction::where('bid_id', 20004)->first();
        $this->assertNotNull($row);
        $this->assertSame('agent_hired', $row->event_type);
        $this->assertSame('preset_completion', $row->recommendation_surface);
        $this->assertTrue((bool) $row->from_recommendation);
    }

    public function test_recommendation_interaction_metadata_stored_correctly(): void
    {
        BidAnalyticsService::recordRecommendationInteraction(
            'bid_viewed', 'seller', true, 'consumer_fit_card',
            'seller_agent', 20006, null, null, ['listing_id' => 99, 'session' => 'abc']
        );

        $row = RecommendationInteraction::where('bid_id', 20006)->first();
        $this->assertNotNull($row);
        $this->assertSame(99, $row->metadata['listing_id']);
        $this->assertSame('abc', $row->metadata['session']);
    }

    public function test_empty_metadata_stored_as_null(): void
    {
        BidAnalyticsService::recordRecommendationInteraction(
            'bid_viewed', 'seller', true, 'consumer_fit_card',
            'seller_agent', 20007, null, null, []
        );

        $row = RecommendationInteraction::where('bid_id', 20007)->first();
        $this->assertNotNull($row);
        $this->assertNull($row->metadata);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Recommendation attribution context — session propagation
    // ─────────────────────────────────────────────────────────────────────────

    public function test_store_and_retrieve_rec_context_round_trips_correctly(): void
    {
        BidAnalyticsService::storeRecContext('seller_agent', 30001, true, 'consumer_fit_card');

        $ctx = BidAnalyticsService::getRecContext('seller_agent', 30001);
        $this->assertTrue($ctx['from_recommendation']);
        $this->assertSame('consumer_fit_card', $ctx['surface']);
    }

    public function test_get_rec_context_returns_default_when_not_stored(): void
    {
        $ctx = BidAnalyticsService::getRecContext('buyer_agent', 99999);
        $this->assertFalse($ctx['from_recommendation']);
        $this->assertNull($ctx['surface']);
    }

    public function test_store_rec_context_with_false_clears_surface(): void
    {
        BidAnalyticsService::storeRecContext('tenant_agent', 30002, false, 'some_surface');

        $ctx = BidAnalyticsService::getRecContext('tenant_agent', 30002);
        $this->assertFalse($ctx['from_recommendation']);
        $this->assertNull($ctx['surface']);
    }

    public function test_rec_context_is_per_bid_type_and_bid_id(): void
    {
        BidAnalyticsService::storeRecContext('seller_agent', 30003, true, 'coaching_panel');
        BidAnalyticsService::storeRecContext('buyer_agent',  30003, false, null);

        $sellerCtx = BidAnalyticsService::getRecContext('seller_agent', 30003);
        $buyerCtx  = BidAnalyticsService::getRecContext('buyer_agent',  30003);

        $this->assertTrue($sellerCtx['from_recommendation']);
        $this->assertSame('coaching_panel', $sellerCtx['surface']);
        $this->assertFalse($buyerCtx['from_recommendation']);
        $this->assertNull($buyerCtx['surface']);
    }

    public function test_bid_viewed_from_recommendation_stores_rec_context_in_session(): void
    {
        BidAnalyticsService::recordRecommendationInteraction(
            'bid_viewed', 'seller', true, 'preset_completion',
            'seller_agent', 30004, null, null
        );

        $ctx = BidAnalyticsService::getRecContext('seller_agent', 30004);
        $this->assertTrue($ctx['from_recommendation']);
        $this->assertSame('preset_completion', $ctx['surface']);
    }

    public function test_bid_viewed_without_recommendation_does_not_store_rec_context(): void
    {
        BidAnalyticsService::recordRecommendationInteraction(
            'bid_viewed', 'buyer', false, null,
            'buyer_agent', 30005, null, null
        );

        $ctx = BidAnalyticsService::getRecContext('buyer_agent', 30005);
        $this->assertFalse($ctx['from_recommendation']);
    }

    public function test_agent_hired_rec_attribution_uses_stored_session_context(): void
    {
        // Simulate: user viewed the bid from a recommendation, then hired the agent.
        BidAnalyticsService::storeRecContext('seller_agent', 30006, true, 'consumer_fit_card');

        $recCtx = BidAnalyticsService::getRecContext('seller_agent', 30006);
        BidAnalyticsService::recordRecommendationInteraction(
            'agent_hired', 'seller',
            $recCtx['from_recommendation'], $recCtx['surface'],
            'seller_agent', 30006, null, null
        );

        $row = RecommendationInteraction::where('bid_id', 30006)
            ->where('event_type', 'agent_hired')
            ->first();
        $this->assertNotNull($row);
        $this->assertTrue((bool) $row->from_recommendation);
        $this->assertSame('consumer_fit_card', $row->recommendation_surface);
    }

    public function test_agent_hired_without_rec_context_is_not_attributed(): void
    {
        // No stored rec context → hired interaction should be non-attributed.
        $recCtx = BidAnalyticsService::getRecContext('landlord_agent', 30007);
        BidAnalyticsService::recordRecommendationInteraction(
            'agent_hired', 'landlord',
            $recCtx['from_recommendation'], $recCtx['surface'],
            'landlord_agent', 30007, null, null
        );

        $row = RecommendationInteraction::where('bid_id', 30007)
            ->where('event_type', 'agent_hired')
            ->first();
        $this->assertNotNull($row);
        $this->assertFalse((bool) $row->from_recommendation);
        $this->assertNull($row->recommendation_surface);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Stale rec context clearing — bid_viewed with from_recommendation=false
    // must overwrite any previously stored true context for the same bid.
    // ─────────────────────────────────────────────────────────────────────────

    public function test_bid_viewed_without_recommendation_clears_previously_stored_rec_context(): void
    {
        // Step 1: user views bid from a recommendation surface → context stored as true.
        BidAnalyticsService::recordRecommendationInteraction(
            'bid_viewed', 'seller', true, 'consumer_fit_card',
            'seller_agent', 40001, null, null
        );

        $ctx = BidAnalyticsService::getRecContext('seller_agent', 40001);
        $this->assertTrue($ctx['from_recommendation'], 'Pre-condition: rec view should store true context.');

        // Step 2: user revisits the same bid via a normal link (no ?from_rec param).
        // The normal view MUST clear the stale true context.
        BidAnalyticsService::recordRecommendationInteraction(
            'bid_viewed', 'seller', false, null,
            'seller_agent', 40001, null, null
        );

        $ctx = BidAnalyticsService::getRecContext('seller_agent', 40001);
        $this->assertFalse($ctx['from_recommendation'],
            'A normal bid_viewed must clear previously stored rec attribution for the same bid.'
        );
        $this->assertNull($ctx['surface'],
            'Surface must be null after context is cleared by a normal view.'
        );
    }

    public function test_normal_view_only_clears_context_for_its_own_bid_not_others(): void
    {
        // Two bids — one viewed via rec, one viewed normally.
        // The normal view must NOT clear the other bid's rec context.
        BidAnalyticsService::recordRecommendationInteraction(
            'bid_viewed', 'seller', true, 'coaching_panel',
            'seller_agent', 40002, null, null
        );
        BidAnalyticsService::recordRecommendationInteraction(
            'bid_viewed', 'seller', false, null,
            'seller_agent', 40003, null, null  // different bid_id
        );

        $ctx40002 = BidAnalyticsService::getRecContext('seller_agent', 40002);
        $ctx40003 = BidAnalyticsService::getRecContext('seller_agent', 40003);

        $this->assertTrue($ctx40002['from_recommendation'],
            'Rec context for bid 40002 must remain true; the normal view was for a different bid.'
        );
        $this->assertFalse($ctx40003['from_recommendation'],
            'Normal view for bid 40003 should be stored as false.'
        );
    }

    public function test_view_rec_then_view_normal_then_accept_not_attributed(): void
    {
        // Full flow that previously caused false attribution:
        //   Request A: user views bid from a recommendation surface → context = true
        //   Request B: user revisits the same bid via a normal link → should clear to false
        //   Request C: user accepts the bid → attribution should be false

        // Request A — viewed from recommendation
        BidAnalyticsService::recordRecommendationInteraction(
            'bid_viewed', 'buyer', true, 'match_score_panel',
            'buyer_agent', 40004, null, null
        );

        $ctx = BidAnalyticsService::getRecContext('buyer_agent', 40004);
        $this->assertTrue($ctx['from_recommendation'], 'Pre-condition: rec view stored.');

        // Request B — user navigates back to the same bid via a normal link
        BidAnalyticsService::recordRecommendationInteraction(
            'bid_viewed', 'buyer', false, null,
            'buyer_agent', 40004, null, null
        );

        // Request C — user accepts the bid; controller calls getRecContext then recordRecommendationInteraction
        $recCtx = BidAnalyticsService::getRecContext('buyer_agent', 40004);
        BidAnalyticsService::recordRecommendationInteraction(
            'bid_accepted', 'buyer',
            $recCtx['from_recommendation'], $recCtx['surface'],
            'buyer_agent', 40004, null, null
        );

        $row = RecommendationInteraction::where('bid_id', 40004)
            ->where('event_type', 'bid_accepted')
            ->first();

        $this->assertNotNull($row);
        $this->assertFalse((bool) $row->from_recommendation,
            'bid_accepted must NOT be attributed to a recommendation when the user last viewed the bid via a normal link.'
        );
        $this->assertNull($row->recommendation_surface,
            'recommendation_surface must be null for a non-attributed acceptance.'
        );
    }

    public function test_view_rec_then_accept_immediately_still_attributed(): void
    {
        // Regression guard: if user views via rec and accepts without any normal view in between,
        // the attribution must still be true (the fix must not break the happy path).
        BidAnalyticsService::recordRecommendationInteraction(
            'bid_viewed', 'tenant', true, 'consumer_fit_card',
            'tenant_agent', 40005, null, null
        );

        $recCtx = BidAnalyticsService::getRecContext('tenant_agent', 40005);
        BidAnalyticsService::recordRecommendationInteraction(
            'bid_accepted', 'tenant',
            $recCtx['from_recommendation'], $recCtx['surface'],
            'tenant_agent', 40005, null, null
        );

        $row = RecommendationInteraction::where('bid_id', 40005)
            ->where('event_type', 'bid_accepted')
            ->first();

        $this->assertNotNull($row);
        $this->assertTrue((bool) $row->from_recommendation,
            'bid_accepted must still be attributed when user viewed via rec and accepted without a normal view in between.'
        );
        $this->assertSame('consumer_fit_card', $row->recommendation_surface);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Resilience — no side effects on failure
    // ─────────────────────────────────────────────────────────────────────────

    public function test_capture_snapshot_does_not_throw_on_scoring_error(): void
    {
        $this->expectNotToPerformAssertions();

        BidAnalyticsService::captureSnapshot(
            'unknown_type', 99999, 'unknown_role', null,
            'custom_event',
            ['bad' => ['nested', 'array']],
            ['services' => 'not-an-array']
        );
    }
}
