<?php

namespace Tests\Unit\Services\Offers;

use App\Models\Offer;
use App\Models\OfferEventLog;
use App\Services\Offers\OfferHistoryService;
use App\Services\Offers\OfferNegotiationChainService;
use App\Services\Offers\OfferTimelineBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class OfferTimelineBuilderTest extends TestCase
{
    use DatabaseTransactions;

    private OfferTimelineBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->builder = new OfferTimelineBuilder(
            new OfferNegotiationChainService(),
            new OfferHistoryService(),
        );
    }

    // ── Case 1: buildForOffer returns exactly one item for a root-only offer ──

    public function test_build_for_offer_returns_one_item_for_root_only_offer_with_no_logs(): void
    {
        $root = Offer::factory()->create(['parent_offer_id' => null]);

        $timeline = $this->builder->buildForOffer($root);

        $this->assertCount(1, $timeline);
        $this->assertSame($root->id, $timeline[0]['offer_id']);
    }

    // ── Case 2: buildForOffer returns one item per offer in the full chain ────

    public function test_build_for_offer_returns_one_item_per_offer_in_chain(): void
    {
        $root  = Offer::factory()->create(['parent_offer_id' => null]);
        $child = Offer::factory()->create(['parent_offer_id' => $root->id]);
        $grand = Offer::factory()->create(['parent_offer_id' => $child->id]);

        $timeline = $this->builder->buildForOffer($root);

        $this->assertCount(3, $timeline);
    }

    // ── Case 3: Timeline items preserve chain order (root first) ──────────────

    public function test_timeline_items_preserve_chain_order_root_first(): void
    {
        $root  = Offer::factory()->create(['parent_offer_id' => null]);
        $child = Offer::factory()->create(['parent_offer_id' => $root->id]);
        $grand = Offer::factory()->create(['parent_offer_id' => $child->id]);

        $timeline = $this->builder->buildForOffer($root);

        $this->assertSame($root->id,  $timeline[0]['offer_id']);
        $this->assertSame($child->id, $timeline[1]['offer_id']);
        $this->assertSame($grand->id, $timeline[2]['offer_id']);
    }

    // ── Case 4: Each item contains all required keys ──────────────────────────

    public function test_each_timeline_item_contains_all_required_keys(): void
    {
        $root = Offer::factory()->create(['parent_offer_id' => null]);

        $timeline = $this->builder->buildForOffer($root);

        $requiredKeys = [
            'offer_id',
            'parent_offer_id',
            'status',
            'created_at',
            'submitted_at',
            'event_count',
            'latest_event_type',
            'latest_event_at',
        ];

        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $timeline[0], "Missing key: {$key}");
        }
    }

    // ── Case 5: event_count correctly reflects the number of logs per offer ───

    public function test_event_count_correctly_reflects_log_count_per_offer(): void
    {
        $root  = Offer::factory()->create(['parent_offer_id' => null]);
        $child = Offer::factory()->create(['parent_offer_id' => $root->id]);

        OfferEventLog::factory()->count(3)->create(['offer_id' => $root->id]);
        OfferEventLog::factory()->count(1)->create(['offer_id' => $child->id]);

        $timeline = $this->builder->buildForOffer($root);

        $rootItem  = collect($timeline)->firstWhere('offer_id', $root->id);
        $childItem = collect($timeline)->firstWhere('offer_id', $child->id);

        $this->assertSame(3, $rootItem['event_count']);
        $this->assertSame(1, $childItem['event_count']);
    }

    // ── Case 6: latest_event_type reflects the newest log's event_type ────────

    public function test_latest_event_type_reflects_newest_log_event_type(): void
    {
        $root = Offer::factory()->create(['parent_offer_id' => null]);

        OfferEventLog::factory()->create([
            'offer_id'   => $root->id,
            'event_type' => 'submitted',
            'created_at' => now()->subMinutes(10),
        ]);
        OfferEventLog::factory()->create([
            'offer_id'   => $root->id,
            'event_type' => 'accepted',
            'created_at' => now(),
        ]);

        $timeline = $this->builder->buildForOffer($root);

        $this->assertSame('accepted', $timeline[0]['latest_event_type']);
    }

    // ── Case 7: latest_event_at is null when an offer has no event logs ───────

    public function test_latest_event_at_is_null_when_no_logs(): void
    {
        $root = Offer::factory()->create(['parent_offer_id' => null]);

        $timeline = $this->builder->buildForOffer($root);

        $this->assertNull($timeline[0]['latest_event_type']);
        $this->assertNull($timeline[0]['latest_event_at']);
    }

    // ── Case 8: parent_offer_id is null for root, set for children ───────────

    public function test_parent_offer_id_is_null_for_root_and_correct_for_children(): void
    {
        $root  = Offer::factory()->create(['parent_offer_id' => null]);
        $child = Offer::factory()->create(['parent_offer_id' => $root->id]);

        $timeline = $this->builder->buildForOffer($root);

        $rootItem  = collect($timeline)->firstWhere('offer_id', $root->id);
        $childItem = collect($timeline)->firstWhere('offer_id', $child->id);

        $this->assertNull($rootItem['parent_offer_id']);
        $this->assertSame($root->id, $childItem['parent_offer_id']);
    }

    // ── Case 9: buildForChain works when passed a Collection directly ─────────

    public function test_build_for_chain_works_correctly_with_collection_directly(): void
    {
        $root  = Offer::factory()->create(['parent_offer_id' => null]);
        $child = Offer::factory()->create(['parent_offer_id' => $root->id]);

        OfferEventLog::factory()->count(2)->create(['offer_id' => $root->id]);

        $chain    = new Collection([$root, $child]);
        $timeline = $this->builder->buildForChain($chain);

        $this->assertCount(2, $timeline);
        $this->assertSame($root->id,  $timeline[0]['offer_id']);
        $this->assertSame($child->id, $timeline[1]['offer_id']);
        $this->assertSame(2, $timeline[0]['event_count']);
        $this->assertSame(0, $timeline[1]['event_count']);
    }

    // ── Case 10: Static scan — service file contains no write operations ──────

    public function test_service_file_contains_no_write_operations(): void
    {
        $source = file_get_contents(app_path('Services/Offers/OfferTimelineBuilder.php'));

        $this->assertStringNotContainsString('::create',  $source);
        $this->assertStringNotContainsString('->save(',   $source);
        $this->assertStringNotContainsString('->update(', $source);
        $this->assertStringNotContainsString('->delete(', $source);
        $this->assertStringNotContainsString('->insert(', $source);
    }
}
