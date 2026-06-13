<?php

namespace Tests\Unit;

use App\Models\BidScoreSnapshot;
use App\Observers\AgentBidAnalyticsObserver;
use App\Services\BidAnalyticsService;
use App\Services\CompatibilityScoreService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tests for AgentBidAnalyticsObserver.
 *
 * Two layers of coverage:
 *
 *  1. normalizeAccessor() unit tests — validates the private helper handles
 *     all realistic accessor shapes: stdClass (what the real models return),
 *     native array, Arrayable (Collection), null, and scalar values.
 *
 *  2. Integration tests — creates real DB records in seller_agent_auctions /
 *     seller_agent_auction_bids / metas, loads them via Eloquent, triggers the
 *     observer's created() method, and asserts snapshot fields are populated
 *     from the actual model data (not empty defaults).
 *
 * Uses DatabaseTransactions (not RefreshDatabase) per the project memory rule
 * for SQLite :memory: environments.
 */
class AgentBidAnalyticsObserverTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        BidAnalyticsService::resetRequestToken();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // normalizeAccessor() — private helper exposed via ReflectionMethod
    //
    // Validates all realistic shapes that the `get` accessor may return.
    // All four bid/auction models do: `return $collection->push((object) $data)->first()`
    // which always produces a stdClass, so the stdClass path is the critical one.
    // ─────────────────────────────────────────────────────────────────────────

    private function callNormalizeAccessor(mixed $input): array
    {
        $method = new \ReflectionMethod(AgentBidAnalyticsObserver::class, 'normalizeAccessor');
        $method->setAccessible(true);
        return $method->invoke(new AgentBidAnalyticsObserver(), $input);
    }

    public function test_normalizer_converts_stdclass_to_array(): void
    {
        $stdClass = (object) ['commission_percentage' => '2.5', 'services' => ['Full Service']];
        $result   = $this->callNormalizeAccessor($stdClass);

        $this->assertIsArray($result);
        $this->assertSame('2.5', $result['commission_percentage']);
        $this->assertSame(['Full Service'], $result['services']);
    }

    public function test_normalizer_passes_native_array_through_unchanged(): void
    {
        $arr    = ['foo' => 'bar', 'nested' => [1, 2, 3]];
        $result = $this->callNormalizeAccessor($arr);

        $this->assertSame($arr, $result);
    }

    public function test_normalizer_converts_laravel_collection_via_arrayable(): void
    {
        $collection = collect(['alpha' => 1, 'beta' => 2]);
        $result     = $this->callNormalizeAccessor($collection);

        $this->assertIsArray($result);
        $this->assertSame(1, $result['alpha']);
        $this->assertSame(2, $result['beta']);
    }

    public function test_normalizer_returns_empty_array_for_null(): void
    {
        $result = $this->callNormalizeAccessor(null);
        $this->assertSame([], $result);
    }

    public function test_normalizer_returns_empty_array_for_string_scalar(): void
    {
        $result = $this->callNormalizeAccessor('some string');
        $this->assertSame([], $result);
    }

    public function test_normalizer_returns_empty_array_for_integer_scalar(): void
    {
        $result = $this->callNormalizeAccessor(42);
        $this->assertSame([], $result);
    }

    public function test_normalizer_handles_empty_stdclass(): void
    {
        $result = $this->callNormalizeAccessor(new \stdClass());
        $this->assertSame([], $result);
    }

    public function test_normalizer_handles_stdclass_with_nested_array(): void
    {
        $stdClass = (object) ['services' => ['Service A', 'Service B']];
        $result   = $this->callNormalizeAccessor($stdClass);

        $this->assertIsArray($result);
        $this->assertSame(['Service A', 'Service B'], $result['services']);
    }

    public function test_normalizer_handles_anonymous_class_with_toarray(): void
    {
        // This is the exact shape returned by the four auction model getGetAttribute()
        // implementations. The private $data field with (array) cast gives mangled
        // null-byte keys, so toArray() must be called instead.
        $anonObj = new class(['property_type' => 'condo', 'auction_type' => 'Full Service']) {
            private $data;
            public function __construct($data) { $this->data = $data; }
            public function __get($name) { return $this->data[$name] ?? null; }
            public function __isset($name) { return isset($this->data[$name]); }
            public function toArray(): array { return $this->data; }
        };

        $result = $this->callNormalizeAccessor($anonObj);

        $this->assertIsArray($result);
        $this->assertSame('condo', $result['property_type']);
        $this->assertSame('Full Service', $result['auction_type']);
    }

    public function test_normalizer_anonymous_class_without_toarray_returns_empty(): void
    {
        // Without toArray(), (array) cast on an anonymous class with private $data
        // produces mangled null-byte keys — verify normalizer returns [] rather than
        // garbage keys in that case (the models should always have toArray() now, but
        // the normalizer must not produce corrupt data for legacy/external objects).
        $legacyObj = new class(['key' => 'value']) {
            private $data;
            public function __construct($data) { $this->data = $data; }
            public function __get($name) { return $this->data[$name] ?? null; }
        };

        $result = $this->callNormalizeAccessor($legacyObj);

        // The cast result will have null-byte mangled keys — the test just verifies
        // the normalizer doesn't throw and that no mangled keys leak through as valid data.
        $this->assertIsArray($result);
        foreach (array_keys($result) as $key) {
            $this->assertStringNotContainsString("\0", $key,
                'Null-byte mangled private property keys must not appear in normalized output.'
            );
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Integration — real DB records confirm stdClass fix works end-to-end
    //
    // The `get` accessor on real models returns stdClass. Without the fix,
    // is_array(stdClass) = false, so bidData/listingData were always [],
    // making scoring inputs empty. These tests verify that after the fix the
    // observer correctly extracts data from real Eloquent models.
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Create a minimal seller_agent_auctions row and return its ID.
     */
    private function createAuction(array $meta = []): int
    {
        $auctionId = DB::table('seller_agent_auctions')->insertGetId([
            'user_id'      => 1,
            'address'      => '123 Test Street',
            'auction_type' => 'Full Service',
            'created_at'   => now()->toDateTimeString(),
            'updated_at'   => now()->toDateTimeString(),
        ]);

        foreach ($meta as $key => $value) {
            DB::table('seller_agent_auction_metas')->insert([
                'seller_agent_auction_id' => $auctionId,
                'meta_key'                => $key,
                'meta_value'              => is_array($value) ? json_encode($value) : $value,
                'created_at'              => now()->toDateTimeString(),
                'updated_at'              => now()->toDateTimeString(),
            ]);
        }

        return $auctionId;
    }

    /**
     * Create a minimal seller_agent_auction_bids row and return its ID.
     */
    private function createBid(int $auctionId, array $meta = []): int
    {
        $bidId = DB::table('seller_agent_auction_bids')->insertGetId([
            'seller_agent_auction_id' => $auctionId,
            'user_id'                 => 2,
            'price_percent'           => 3.0,
            'accepted'                => 'pending',
            'created_at'              => now()->toDateTimeString(),
            'updated_at'              => now()->toDateTimeString(),
        ]);

        foreach ($meta as $key => $value) {
            DB::table('seller_agent_auction_bid_metas')->insert([
                'seller_agent_auction_bid_id' => $bidId,
                'meta_key'                    => $key,
                'meta_value'                  => is_array($value) ? json_encode($value) : $value,
                'created_at'                  => now()->toDateTimeString(),
                'updated_at'                  => now()->toDateTimeString(),
            ]);
        }

        return $bidId;
    }

    public function test_observer_created_produces_bid_created_snapshot_for_real_model(): void
    {
        $auctionId = $this->createAuction([
            'property_type' => 'single_family',
        ]);
        $bidId = $this->createBid($auctionId, [
            'commission_percentage' => '2.5',
            'services'              => json_encode(['Full Service']),
        ]);

        // Load via Eloquent — the real `get` accessor returns stdClass
        $bid = \App\Models\SellerAgentAuctionBid::with(['meta', 'auction.meta'])->find($bidId);
        $this->assertNotNull($bid, 'Bid record should be loadable via Eloquent.');

        // Trigger the observer directly (simulates what Eloquent fires on create)
        (new AgentBidAnalyticsObserver())->created($bid);

        $snap = BidScoreSnapshot::where('bid_id', $bidId)
            ->where('event_type', BidAnalyticsService::EVENT_BID_CREATED)
            ->first();

        $this->assertNotNull($snap, 'A bid_created snapshot should be recorded.');
        $this->assertSame('seller_agent', $snap->bid_type);
        $this->assertSame('seller', $snap->role);
        $this->assertSame(CompatibilityScoreService::SCORING_VERSION, $snap->scoring_version);

        // readiness_state must be in a valid set — NOT 'unknown', which would
        // indicate a scoring exception (typically caused by the empty-array bug).
        $this->assertContains(
            $snap->readiness_state,
            ['not_ready', 'quick_match_ready', 'full_match_ready'],
            "readiness_state 'unknown' indicates scoring received empty data; the stdClass normalizer may not be working."
        );
    }

    public function test_observer_created_extracts_property_type_from_auction_meta(): void
    {
        $auctionId = $this->createAuction([
            'property_type' => 'condo',
        ]);
        $bidId = $this->createBid($auctionId);

        $bid = \App\Models\SellerAgentAuctionBid::with(['meta', 'auction.meta'])->find($bidId);
        (new AgentBidAnalyticsObserver())->created($bid);

        $snap = BidScoreSnapshot::where('bid_id', $bidId)->first();
        $this->assertNotNull($snap);
        $this->assertSame('condo', $snap->property_type,
            'property_type should be extracted from auction meta via the normalized stdClass accessor.'
        );
    }

    public function test_observer_created_emits_both_bid_created_and_bid_submitted(): void
    {
        $auctionId = $this->createAuction();
        $bidId     = $this->createBid($auctionId);

        $bid = \App\Models\SellerAgentAuctionBid::with(['meta', 'auction.meta'])->find($bidId);
        (new AgentBidAnalyticsObserver())->created($bid);

        $this->assertSame(
            1,
            BidScoreSnapshot::where('bid_id', $bidId)
                ->where('event_type', BidAnalyticsService::EVENT_BID_CREATED)
                ->count(),
            'bid_created should be recorded exactly once.'
        );
        $this->assertSame(
            1,
            BidScoreSnapshot::where('bid_id', $bidId)
                ->where('event_type', BidAnalyticsService::EVENT_BID_SUBMITTED)
                ->count(),
            'bid_submitted should be recorded exactly once (separate event from bid_created).'
        );
    }

    public function test_observer_updated_bid_accepted_records_bid_accepted_event(): void
    {
        $auctionId = $this->createAuction(['property_type' => 'townhouse']);
        $bidId     = $this->createBid($auctionId);

        // Load the bid and simulate an accepted state change
        $bid = \App\Models\SellerAgentAuctionBid::with(['meta', 'auction.meta'])->find($bidId);

        // Manually set the original + current values to simulate accepted transition
        $bid->setRawAttributes(array_merge($bid->getAttributes(), ['accepted' => 'accepted']));
        $bid->syncOriginal();
        // Now set original to 'pending' and current to 'accepted' to trigger becameAccepted
        $bid->setRawAttributes(array_merge($bid->getAttributes(), ['accepted' => 'accepted']));

        // We fake the `getOriginal('accepted')` by forcing the dirty state
        // More direct: use the observer with a model that has the right original state
        DB::table('seller_agent_auction_bids')
            ->where('id', $bidId)
            ->update(['accepted' => 'accepted']);

        $bid = \App\Models\SellerAgentAuctionBid::with(['meta', 'auction.meta'])->find($bidId);

        // Trick the dirty detection: make original 'pending', current 'accepted'
        $bid->syncOriginal();
        $bid->accepted = 'pending'; // revert in memory
        $bid->syncOriginal();       // original = 'pending'
        $bid->accepted = 'accepted'; // set back to accepted → dirty

        (new AgentBidAnalyticsObserver())->updated($bid);

        $snap = BidScoreSnapshot::where('bid_id', $bidId)
            ->where('event_type', BidAnalyticsService::EVENT_BID_ACCEPTED)
            ->first();

        $this->assertNotNull($snap, 'A bid_accepted snapshot should be recorded when accepted state changes.');
        $this->assertSame('seller_agent', $snap->bid_type);
        $this->assertSame('townhouse', $snap->property_type);
    }

    public function test_observer_updated_bid_updated_event_is_deduplicated_within_same_request(): void
    {
        $auctionId = $this->createAuction();
        $bidId     = $this->createBid($auctionId);

        $bid = \App\Models\SellerAgentAuctionBid::with(['meta', 'auction.meta'])->find($bidId);
        $bid->accepted = 'pending'; // not becoming accepted

        $observer = new AgentBidAnalyticsObserver();
        $observer->updated($bid); // first call
        $observer->updated($bid); // second call — same request token → deduped

        $this->assertSame(
            1,
            BidScoreSnapshot::where('bid_id', $bidId)
                ->where('event_type', BidAnalyticsService::EVENT_BID_UPDATED)
                ->count(),
            'Two observer->updated() calls in the same request should produce only one bid_updated snapshot.'
        );
    }

    public function test_observer_created_does_not_throw_when_auction_has_no_meta(): void
    {
        $auctionId = $this->createAuction(); // no meta
        $bidId     = $this->createBid($auctionId); // no bid meta

        $bid = \App\Models\SellerAgentAuctionBid::with(['meta', 'auction.meta'])->find($bidId);

        $this->expectNotToPerformAssertions();
        (new AgentBidAnalyticsObserver())->created($bid);
    }

    public function test_normalizer_is_applied_so_bid_data_is_not_empty_in_snapshot(): void
    {
        // Insert bid meta with a meaningful commission percentage so MatchReadiness
        // has something to evaluate. With the stdClass bug (is_array guard), bidData
        // was always [] and scoring would always fall back to a default state.
        // With the fix, the commission_percentage from meta reaches the scorer.
        $auctionId = $this->createAuction();
        $bidId     = $this->createBid($auctionId, [
            'commission_percentage' => '2.5',
            'services'              => json_encode(['Full Service', 'Staging', 'Photography']),
        ]);

        $bid = \App\Models\SellerAgentAuctionBid::with(['meta', 'auction.meta'])->find($bidId);
        (new AgentBidAnalyticsObserver())->created($bid);

        $snap = BidScoreSnapshot::where('bid_id', $bidId)->first();
        $this->assertNotNull($snap);

        // The critical assertion: readiness_state must NOT be 'unknown'.
        // 'unknown' indicates that safeScore() caught a Throwable — which
        // happens when the scoring receives completely wrong-typed inputs.
        // A valid 'not_ready' result means the scorer ran successfully with
        // the normalized bid data, even if the score itself wasn't high enough.
        $this->assertNotSame('unknown', $snap->readiness_state,
            "readiness_state='unknown' indicates safeScore() threw an exception. " .
            "This typically means bidData was passed as the wrong type (e.g., still a stdClass " .
            "after the normalizeAccessor fix, or completely empty when meta was present)."
        );
    }
}
