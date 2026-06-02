<?php

namespace Tests\Unit\Repositories;

use App\Models\Offer;
use App\Models\OfferAuction;
use App\Models\OfferEventLog;
use App\Models\OfferMeta;
use App\Repositories\OfferRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfferRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private OfferRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new OfferRepository();
    }

    public function test_find_by_id_returns_correct_offer(): void
    {
        $offer = Offer::factory()->create();

        $found = $this->repo->findById($offer->id);

        $this->assertNotNull($found);
        $this->assertEquals($offer->id, $found->id);
    }

    public function test_find_by_id_returns_null_for_missing_offer(): void
    {
        $found = $this->repo->findById(99999);

        $this->assertNull($found);
    }

    public function test_find_with_relations_loads_all_six_relationships(): void
    {
        $auction = OfferAuction::factory()->create();
        $parent  = Offer::factory()->create(['offer_auction_id' => $auction->id]);
        $offer   = Offer::factory()->create([
            'offer_auction_id' => $auction->id,
            'parent_offer_id'  => $parent->id,
        ]);
        Offer::factory()->create([
            'offer_auction_id' => $auction->id,
            'parent_offer_id'  => $offer->id,
        ]);
        OfferMeta::factory()->create(['offer_id' => $offer->id, 'meta_key' => 'test_key']);
        OfferEventLog::factory()->create(['offer_id' => $offer->id]);

        $found = $this->repo->findWithRelations($offer->id);

        $this->assertNotNull($found);
        $this->assertTrue($found->relationLoaded('user'));
        $this->assertTrue($found->relationLoaded('offerAuction'));
        $this->assertTrue($found->relationLoaded('parentOffer'));
        $this->assertTrue($found->relationLoaded('childOffers'));
        $this->assertTrue($found->relationLoaded('metas'));
        $this->assertTrue($found->relationLoaded('eventLogs'));

        $this->assertEquals($auction->id, $found->offerAuction->id);
        $this->assertEquals($parent->id, $found->parentOffer->id);
        $this->assertCount(1, $found->childOffers);
        $this->assertCount(1, $found->metas);
        $this->assertCount(1, $found->eventLogs);
    }

    public function test_find_with_relations_returns_null_for_missing_offer(): void
    {
        $found = $this->repo->findWithRelations(99999);

        $this->assertNull($found);
    }

    public function test_find_by_auction_returns_all_offers_for_auction(): void
    {
        $auction      = OfferAuction::factory()->create();
        $otherAuction = OfferAuction::factory()->create();

        Offer::factory()->count(3)->create(['offer_auction_id' => $auction->id]);
        Offer::factory()->count(2)->create(['offer_auction_id' => $otherAuction->id]);

        $results = $this->repo->findByAuction($auction->id);

        $this->assertCount(3, $results);
        $results->each(fn ($o) => $this->assertEquals($auction->id, $o->offer_auction_id));
    }

    public function test_find_active_by_auction_returns_only_submitted_and_countered(): void
    {
        $auction = OfferAuction::factory()->create();

        Offer::factory()->submitted()->create(['offer_auction_id' => $auction->id]);
        Offer::factory()->countered()->create(['offer_auction_id' => $auction->id]);
        Offer::factory()->accepted()->create(['offer_auction_id' => $auction->id]);
        Offer::factory()->create(['offer_auction_id' => $auction->id, 'status' => 'draft']);
        Offer::factory()->create(['offer_auction_id' => $auction->id, 'status' => 'rejected']);

        $results = $this->repo->findActiveByAuction($auction->id);

        $this->assertCount(2, $results);
        $results->each(fn ($o) => $this->assertContains($o->status, ['submitted', 'countered']));
    }

    public function test_find_children_returns_direct_children(): void
    {
        $auction  = OfferAuction::factory()->create();
        $parent   = Offer::factory()->create(['offer_auction_id' => $auction->id]);
        $child1   = Offer::factory()->create(['offer_auction_id' => $auction->id, 'parent_offer_id' => $parent->id]);
        $child2   = Offer::factory()->create(['offer_auction_id' => $auction->id, 'parent_offer_id' => $parent->id]);
        $unrelated = Offer::factory()->create(['offer_auction_id' => $auction->id]);

        $children = $this->repo->findChildren($parent->id);

        $this->assertCount(2, $children);
        $childIds = $children->pluck('id')->all();
        $this->assertContains($child1->id, $childIds);
        $this->assertContains($child2->id, $childIds);
        $this->assertNotContains($unrelated->id, $childIds);
    }

    public function test_find_parent_returns_correct_parent(): void
    {
        $auction = OfferAuction::factory()->create();
        $parent  = Offer::factory()->create(['offer_auction_id' => $auction->id]);
        $child   = Offer::factory()->create([
            'offer_auction_id' => $auction->id,
            'parent_offer_id'  => $parent->id,
        ]);

        $found = $this->repo->findParent($child->id);

        $this->assertNotNull($found);
        $this->assertEquals($parent->id, $found->id);
    }

    public function test_find_parent_returns_null_when_no_parent(): void
    {
        $offer = Offer::factory()->create(['parent_offer_id' => null]);

        $found = $this->repo->findParent($offer->id);

        $this->assertNull($found);
    }

    public function test_find_parent_returns_null_for_missing_offer(): void
    {
        $found = $this->repo->findParent(99999);

        $this->assertNull($found);
    }

    public function test_get_event_history_returns_correct_logs(): void
    {
        $offer = Offer::factory()->create();
        OfferEventLog::factory()->count(3)->create(['offer_id' => $offer->id]);

        $otherOffer = Offer::factory()->create();
        OfferEventLog::factory()->create(['offer_id' => $otherOffer->id]);

        $history = $this->repo->getEventHistory($offer->id);

        $this->assertCount(3, $history);
        $history->each(fn ($log) => $this->assertEquals($offer->id, $log->offer_id));
    }

    public function test_get_event_history_returns_empty_collection_for_missing_offer(): void
    {
        $history = $this->repo->getEventHistory(99999);

        $this->assertTrue($history->isEmpty());
    }

    public function test_get_offer_meta_returns_correct_records(): void
    {
        $offer = Offer::factory()->create();
        OfferMeta::factory()->create(['offer_id' => $offer->id, 'meta_key' => 'key_a']);
        OfferMeta::factory()->create(['offer_id' => $offer->id, 'meta_key' => 'key_b']);

        $otherOffer = Offer::factory()->create();
        OfferMeta::factory()->create(['offer_id' => $otherOffer->id, 'meta_key' => 'key_c']);

        $metas = $this->repo->getOfferMeta($offer->id);

        $this->assertCount(2, $metas);
        $metas->each(fn ($m) => $this->assertEquals($offer->id, $m->offer_id));
    }

    public function test_get_offer_meta_returns_empty_collection_for_missing_offer(): void
    {
        $metas = $this->repo->getOfferMeta(99999);

        $this->assertTrue($metas->isEmpty());
    }

    public function test_get_accepted_offer_for_auction_returns_accepted_offer(): void
    {
        $auction = OfferAuction::factory()->create();

        Offer::factory()->submitted()->create(['offer_auction_id' => $auction->id]);
        $accepted = Offer::factory()->accepted()->create(['offer_auction_id' => $auction->id]);

        $found = $this->repo->getAcceptedOfferForAuction($auction->id);

        $this->assertNotNull($found);
        $this->assertEquals($accepted->id, $found->id);
        $this->assertEquals('accepted', $found->status);
    }

    public function test_get_accepted_offer_for_auction_returns_null_when_none_accepted(): void
    {
        $auction = OfferAuction::factory()->create();
        Offer::factory()->submitted()->create(['offer_auction_id' => $auction->id]);

        $found = $this->repo->getAcceptedOfferForAuction($auction->id);

        $this->assertNull($found);
    }

    public function test_load_relationships_loads_all_six_relationships(): void
    {
        $auction = OfferAuction::factory()->create();
        $parent  = Offer::factory()->create(['offer_auction_id' => $auction->id]);
        $offer   = Offer::factory()->create([
            'offer_auction_id' => $auction->id,
            'parent_offer_id'  => $parent->id,
        ]);
        Offer::factory()->create([
            'offer_auction_id' => $auction->id,
            'parent_offer_id'  => $offer->id,
        ]);
        OfferMeta::factory()->create(['offer_id' => $offer->id, 'meta_key' => 'load_test']);
        OfferEventLog::factory()->create(['offer_id' => $offer->id]);

        $fresh = Offer::find($offer->id);
        $loaded = $this->repo->loadRelationships($fresh);

        $this->assertTrue($loaded->relationLoaded('user'));
        $this->assertTrue($loaded->relationLoaded('offerAuction'));
        $this->assertTrue($loaded->relationLoaded('parentOffer'));
        $this->assertTrue($loaded->relationLoaded('childOffers'));
        $this->assertTrue($loaded->relationLoaded('metas'));
        $this->assertTrue($loaded->relationLoaded('eventLogs'));
    }
}
